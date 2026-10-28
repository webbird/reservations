<?php

declare(strict_types=1);

namespace Reservations\Core\Storage\Pdo;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Thin layer over the PDO connection handed in by the CMS wrapper.
 * Knows the table prefix and the platform, binds parameters, converts dates
 * and runs locked transactions. Does not rely on the caller's PDO settings
 * (fetch mode, emulation), except that errors must raise exceptions.
 */
final class Connection
{
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const TIME_FORMAT = 'H:i:s';

    private readonly string $platform;
    private readonly DateTimeZone $timezone;
    private readonly ?Closure $logger;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tablePrefix,
        ?DateTimeZone $timezone = null,
        ?Closure $logger = null,
    ) {
        if (preg_match('/^[A-Za-z0-9_]*$/', $tablePrefix) !== 1) {
            throw new InvalidArgumentException('Invalid table prefix.');
        }
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->platform = match ($driver) {
            'mysql' => 'mysql',
            'sqlite' => 'sqlite',
            default => throw new RuntimeException(sprintf('Unsupported database driver "%s".', is_string($driver) ? $driver : get_debug_type($driver))),
        };
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new LogicException('The PDO connection must use PDO::ERRMODE_EXCEPTION.');
        }
        $this->timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
        $this->logger = $logger ?? (getenv('RESERVATIONS_DEBUG') === '1'
            ? static function (string $sql, array $params): void {
                error_log('[reservations] ' . preg_replace('/\s+/', ' ', $sql) . ' ' . json_encode($params));
            }
            : null);
    }

    public function platform(): string
    {
        return $this->platform;
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function table(string $name): string
    {
        return $this->tablePrefix . $name;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(string $sql, array $params = []): PDOStatement
    {
        if ($this->logger !== null) {
            ($this->logger)($sql, $params);
        }
        $stmt = $this->pdo->prepare($sql);
        foreach (array_values($params) as $i => $value) {
            $stmt->bindValue($i + 1, ...$this->bindable($value));
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $rows = [];
        foreach ($this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function fetchInt(string $sql, array $params = []): int
    {
        return Row::toInt($this->fetchValue($sql, $params), $sql);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * @param array<string, mixed> $data column => value; column names come from the core only
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table($table),
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?')),
        );
        $this->run($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where column => value, combined with AND
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
        $cond = implode(' AND ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($where)));
        $stmt = $this->run(
            sprintf('UPDATE %s SET %s WHERE %s', $this->table($table), $set, $cond),
            [...array_values($data), ...array_values($where)],
        );
        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $where
     */
    public function delete(string $table, array $where): int
    {
        $cond = implode(' AND ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($where)));
        return $this->run(sprintf('DELETE FROM %s WHERE %s', $this->table($table), $cond), array_values($where))->rowCount();
    }

    /**
     * Runs $work in a transaction that holds a write lock on one row.
     * MySQL: row lock via SELECT … FOR UPDATE (joins a transaction the caller
     * already started). SQLite: BEGIN IMMEDIATE, which serialises writers;
     * nesting into a caller's transaction is refused there.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function lockedTransaction(string $table, string $keyColumn, int $key, callable $work): mixed
    {
        if ($this->platform === 'sqlite') {
            if ($this->pdo->inTransaction()) {
                throw new LogicException('Nested transactions are not supported on SQLite.');
            }
            $this->pdo->exec('BEGIN IMMEDIATE');
            try {
                $result = $work();
                $this->pdo->exec('COMMIT');
                return $result;
            } catch (Throwable $e) {
                $this->pdo->exec('ROLLBACK');
                throw $e;
            }
        }

        $own = !$this->pdo->inTransaction();
        if ($own) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->run(sprintf('SELECT %1$s FROM %2$s WHERE %1$s = ? FOR UPDATE', $keyColumn, $this->table($table)), [$key]);
            $result = $work();
            if ($own) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($own) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function toDb(DateTimeImmutable $date): string
    {
        return $date->setTimezone($this->timezone)->format(self::DATETIME_FORMAT);
    }

    public function fromDb(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!' . self::DATETIME_FORMAT, $value, $this->timezone);
        if ($date === false) {
            throw new RuntimeException(sprintf('Invalid date/time value "%s" in database.', $value));
        }
        return $date;
    }

    public function tableExists(string $name): bool
    {
        try {
            $this->pdo->query(sprintf('SELECT 1 FROM %s LIMIT 1', $this->table($name)));
            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public function exec(string $sql): void
    {
        if ($this->logger !== null) {
            ($this->logger)($sql, []);
        }
        $this->pdo->exec($sql);
    }

    /**
     * @return array{0: mixed, 1: int}
     */
    private function bindable(mixed $value): array
    {
        return match (true) {
            $value === null => [null, PDO::PARAM_NULL],
            is_bool($value) => [$value ? 1 : 0, PDO::PARAM_INT],
            is_int($value) => [$value, PDO::PARAM_INT],
            $value instanceof DateTimeImmutable => [$this->toDb($value), PDO::PARAM_STR],
            $value instanceof \BackedEnum => [$value->value, is_int($value->value) ? PDO::PARAM_INT : PDO::PARAM_STR],
            is_string($value), is_float($value), $value instanceof \Stringable => [(string) $value, PDO::PARAM_STR],
            default => throw new InvalidArgumentException('Unsupported parameter type ' . get_debug_type($value)),
        };
    }
}
