<?php

declare(strict_types=1);

namespace Reservations\Core\Storage;

use Reservations\Core\Storage\Pdo\Connection;

/**
 * Table definitions for version 1.0, rendered per platform (MySQL/MariaDB, SQLite).
 * Columns are described abstractly so both platforms share one definition.
 */
final class Schema
{
    /**
     * Column specs: pk | int | int? | small | small? | bool | str:N | str?:N | text | datetime | datetime? | time?
     * dropped: columns of older versions that install() removes.
     *
     * @return array<string, array{columns: array<string, string>, dropped?: list<string>, primary?: list<string>, indexes?: array<string, list<string>>, unique?: array<string, list<string>>}>
     */
    public static function tables(): array
    {
        return [
            'settings' => [
                'columns' => [
                    'section_id' => 'int',
                    'page_id' => 'int',
                    'display_mode' => "str:20='multi'",
                    'default_view' => "str:20='plan'",
                    'view' => "str:50='default'",
                    'plan_range' => 'small=1',
                    'show_labels' => 'bool=1',
                    'requests_enabled' => 'bool=0',
                    'notify_email' => "str:255=''",
                    'retention_days' => 'small=90',
                    'past_months' => 'small=12',
                    'future_months' => 'small=24',
                    'resource_id' => 'int?',
                    'booking_mode' => "str:20='day'",
                    'presentation_source' => 'str?:100',
                    'captcha' => 'bool=0',
                ],
                // the visibility of the status with role request_new decides that since 0.5.0
                'dropped' => ['show_requests'],
                'primary' => ['section_id'],
            ],
            'types' => [
                'columns' => [
                    'type_id' => 'pk',
                    'section_id' => 'int',
                    'title' => 'str:100',
                    'defaults' => 'text',
                    'position' => 'int=0',
                ],
                // the booking mode moved to settings in 0.3.0
                'dropped' => ['booking_mode'],
                'indexes' => ['section' => ['section_id']],
            ],
            'statuses' => [
                'columns' => [
                    'status_id' => 'pk',
                    'section_id' => 'int',
                    'title' => 'str:100',
                    'blocks' => 'bool=1',
                    'visibility' => "str:20='label'",
                    'color' => "str:7='#185fa5'",
                    'pattern' => "str:20='solid'",
                    'role' => 'str?:20',
                    'active' => 'bool=1',
                    'position' => 'int=0',
                ],
                'indexes' => ['section' => ['section_id']],
                'unique' => ['role' => ['section_id', 'role']],
            ],
            'resources' => [
                'columns' => [
                    'resource_id' => 'pk',
                    'section_id' => 'int',
                    'type_id' => 'int',
                    'title' => 'str:255',
                    'color' => 'str?:7',
                    'active' => 'bool=1',
                    'position' => 'int=0',
                    'presentation_ref' => 'str?:100',
                    'capacity' => 'small?',
                    'min_persons' => 'small?',
                    'auto_confirm' => 'bool=0',
                    'slot_minutes' => 'small=30',
                    'min_duration' => 'int?',
                    'max_duration' => 'int?',
                    'buffer_before' => 'small=0',
                    'buffer_after' => 'small=0',
                    'lead_min_minutes' => 'int=0',
                    'lead_max_days' => 'int?',
                    'checkin_time' => 'time?',
                    'checkout_time' => 'time?',
                ],
                'indexes' => ['section' => ['section_id'], 'type' => ['type_id']],
            ],
            'hours' => [
                'columns' => [
                    'hour_id' => 'pk',
                    'resource_id' => 'int',
                    'weekday' => 'small',
                    'start_time' => 'time',
                    'end_time' => 'time',
                ],
                'indexes' => ['resource' => ['resource_id']],
            ],
            'bookings' => [
                'columns' => [
                    'booking_id' => 'pk',
                    'section_id' => 'int',
                    'resource_id' => 'int',
                    'start_at' => 'datetime',
                    'end_at' => 'datetime',
                    'status_id' => 'int',
                    'label' => "str:255=''",
                    'note' => 'text',
                    'service_id' => 'int?',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                    'created_by' => 'int?',
                    'updated_by' => 'int?',
                ],
                'indexes' => [
                    'range' => ['resource_id', 'start_at', 'end_at'],
                    'status' => ['section_id', 'status_id'],
                ],
            ],
            'requests' => [
                'columns' => [
                    'request_id' => 'pk',
                    'booking_id' => 'int',
                    'name' => "str:255=''",
                    'email' => "str:255=''",
                    'phone' => "str:50=''",
                    'persons' => 'small?',
                    'message' => 'text',
                    'privacy_accepted_at' => 'datetime?',
                    'created_at' => 'datetime',
                    'purge_after' => 'datetime?',
                ],
                'unique' => ['booking' => ['booking_id']],
                'indexes' => ['purge' => ['purge_after']],
            ],
        ];
    }

    /**
     * Creates missing tables, adds missing columns to existing ones and removes
     * dropped ones, so it serves as install and upgrade. Columns added later must
     * be nullable or have a default (SQLite cannot add NOT NULL columns without one).
     *
     * @return list<string> names of the tables that were created
     */
    public static function install(Connection $db): array
    {
        $created = [];
        foreach (self::tables() as $name => $def) {
            if ($db->tableExists($name)) {
                $existing = $db->columns($name);
                foreach (array_diff_key($def['columns'], array_flip($existing)) as $column => $spec) {
                    if (!str_contains($spec, '?') && !str_contains($spec, '=')) {
                        throw new \LogicException(sprintf('Column %s.%s must be nullable or have a default to be added later.', $name, $column));
                    }
                    $db->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $db->table($name), $column, self::columnSql($db->platform(), $spec)));
                }
                foreach (array_intersect($def['dropped'] ?? [], $existing) as $column) {
                    if (self::canDropColumns($db)) {
                        $db->exec(sprintf('ALTER TABLE %s DROP COLUMN %s', $db->table($name), $column));
                    }
                }
                continue;
            }
            foreach (self::statements($db->platform(), $db->table($name), $def) as $sql) {
                $db->exec($sql);
            }
            $created[] = $name;
        }
        return $created;
    }

    /**
     * SQLite supports DROP COLUMN from 3.35 on; older versions keep the column,
     * which is harmless because it has a default and is no longer read.
     */
    private static function canDropColumns(Connection $db): bool
    {
        if ($db->platform() !== 'sqlite') {
            return true;
        }
        $version = $db->fetchValue('SELECT sqlite_version()');
        return is_string($version) && version_compare($version, '3.35.0', '>=');
    }

    public static function uninstall(Connection $db): void
    {
        foreach (array_keys(self::tables()) as $name) {
            $db->exec('DROP TABLE IF EXISTS ' . $db->table($name));
        }
    }

    /**
     * @param array{columns: array<string, string>, dropped?: list<string>, primary?: list<string>, indexes?: array<string, list<string>>, unique?: array<string, list<string>>} $def
     * @return list<string>
     */
    public static function statements(string $platform, string $table, array $def): array
    {
        $lines = [];
        foreach ($def['columns'] as $column => $spec) {
            $lines[] = $column . ' ' . self::columnSql($platform, $spec);
        }
        if (($def['primary'] ?? []) !== []) {
            $lines[] = 'PRIMARY KEY (' . implode(', ', $def['primary']) . ')';
        }
        $sql = 'CREATE TABLE ' . $table . " (\n  " . implode(",\n  ", $lines) . "\n)";
        if ($platform === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        $statements = [$sql];
        foreach ($def['indexes'] ?? [] as $index => $columns) {
            $statements[] = sprintf('CREATE INDEX %s_%s ON %s (%s)', $table, $index, $table, implode(', ', $columns));
        }
        foreach ($def['unique'] ?? [] as $index => $columns) {
            $statements[] = sprintf('CREATE UNIQUE INDEX %s_%s ON %s (%s)', $table, $index, $table, implode(', ', $columns));
        }
        return $statements;
    }

    private static function columnSql(string $platform, string $spec): string
    {
        $default = null;
        if (str_contains($spec, '=')) {
            [$spec, $default] = explode('=', $spec, 2);
        }
        $nullable = str_contains($spec, '?');
        $spec = str_replace('?', '', $spec);
        $length = null;
        if (str_contains($spec, ':')) {
            [$spec, $length] = explode(':', $spec, 2);
        }
        $mysql = $platform === 'mysql';

        if ($spec === 'pk') {
            return $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        }
        $type = match ($spec) {
            'int' => $mysql ? 'INT' : 'INTEGER',
            'small' => $mysql ? 'SMALLINT' : 'INTEGER',
            'bool' => $mysql ? 'TINYINT(1)' : 'INTEGER',
            'str' => $mysql ? 'VARCHAR(' . (int) $length . ')' : 'TEXT',
            'text' => 'TEXT',
            'datetime' => $mysql ? 'DATETIME' : 'TEXT',
            'time' => $mysql ? 'TIME' : 'TEXT',
            default => throw new \LogicException('Unknown column spec ' . $spec),
        };
        // MySQL does not allow defaults on TEXT columns; such columns are always written explicitly.
        if ($spec === 'text') {
            return $type . ($nullable ? ' NULL' : ' NOT NULL');
        }
        return $type . ($nullable ? ' NULL' : ' NOT NULL') . ($default !== null ? ' DEFAULT ' . $default : '');
    }
}
