<?php

declare(strict_types=1);

namespace Reservations\Core\Storage\Pdo;

use Reservations\Core\Domain\OpeningHours;

/**
 * Opening hours, one row per interval; times are stored as "H:i:s", 24:00 as "24:00:00".
 */
final class HoursRepository
{
    private const TABLE = 'hours';

    public function __construct(private readonly Connection $db)
    {
    }

    public function find(int $sectionId, int $resourceId): OpeningHours
    {
        $rows = $this->db->fetchAll(
            'SELECT weekday, start_time, end_time FROM ' . $this->db->table(self::TABLE)
            . ' WHERE section_id = ? AND resource_id = ? ORDER BY weekday, start_time',
            [$sectionId, $resourceId],
        );
        $days = [];
        foreach ($rows as $row) {
            $days[Row::int($row, 'weekday')][] = [self::minutes(Row::string($row, 'start_time')), self::minutes(Row::string($row, 'end_time'))];
        }
        return new OpeningHours($days);
    }

    /**
     * Replaces all hours of the resource; the caller holds the transaction.
     */
    public function replace(int $sectionId, int $resourceId, OpeningHours $hours): void
    {
        $this->db->delete(self::TABLE, ['section_id' => $sectionId, 'resource_id' => $resourceId]);
        foreach ($hours->days as $weekday => $intervals) {
            foreach ($intervals as [$from, $to]) {
                $this->db->insert(self::TABLE, [
                    'section_id' => $sectionId,
                    'resource_id' => $resourceId,
                    'weekday' => $weekday,
                    'start_time' => self::time($from),
                    'end_time' => self::time($to),
                ]);
            }
        }
    }

    private static function time(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time) + [1 => '0']);
        return $hours * 60 + $minutes;
    }
}
