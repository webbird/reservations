<?php

declare(strict_types=1);

namespace Reservations\Core\Storage\Pdo;

use UnexpectedValueException;

/**
 * Checked access to database rows. Drivers return ints as int or numeric
 * string depending on platform and settings; anything else is a corrupt row
 * and must not be cast silently to 0 or ''.
 */
final class Row
{
    /**
     * @param array<mixed> $row
     */
    public static function int(array $row, string $column): int
    {
        return self::toInt(self::value($row, $column), $column);
    }

    /**
     * For single values such as COUNT(*) results.
     */
    public static function toInt(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        throw self::invalid($label, 'integer', $value);
    }

    /**
     * @param array<mixed> $row
     */
    public static function nullableInt(array $row, string $column): ?int
    {
        return self::value($row, $column) === null ? null : self::int($row, $column);
    }

    /**
     * @param array<mixed> $row
     */
    public static function string(array $row, string $column): string
    {
        $value = self::value($row, $column);
        if (is_string($value)) {
            return $value;
        }
        throw self::invalid($column, 'string', $value);
    }

    /**
     * @param array<mixed> $row
     */
    public static function nullableString(array $row, string $column): ?string
    {
        return self::value($row, $column) === null ? null : self::string($row, $column);
    }

    /**
     * @param array<mixed> $row
     */
    public static function bool(array $row, string $column): bool
    {
        return self::int($row, $column) !== 0;
    }

    /**
     * @param array<mixed> $row
     */
    private static function value(array $row, string $column): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new UnexpectedValueException(sprintf('Column "%s" missing in result row.', $column));
        }
        return $row[$column];
    }

    private static function invalid(string $column, string $expected, mixed $value): UnexpectedValueException
    {
        return new UnexpectedValueException(sprintf('Column "%s": expected %s, got %s.', $column, $expected, get_debug_type($value)));
    }
}
