<?php

declare(strict_types=1);

namespace Reservations\Core\Storage\Pdo;

use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\TimeRange;

final class BookingRepository
{
    private const TABLE = 'bookings';

    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(Booking $booking): int
    {
        return $this->db->insert(self::TABLE, $this->row($booking));
    }

    public function update(Booking $booking): void
    {
        $row = $this->row($booking);
        unset($row['created_at'], $row['created_by']);
        $this->db->update(self::TABLE, $row, ['booking_id' => (int) $booking->id, 'section_id' => $booking->sectionId]);
    }

    public function find(int $sectionId, int $bookingId): ?Booking
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? AND booking_id = ?',
            [$sectionId, $bookingId],
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function delete(int $sectionId, int $bookingId): void
    {
        $this->db->run(
            'DELETE FROM ' . $this->db->table('requests') . ' WHERE booking_id IN (SELECT booking_id FROM '
            . $this->db->table(self::TABLE) . ' WHERE section_id = ? AND booking_id = ?)',
            [$sectionId, $bookingId],
        );
        $this->db->delete(self::TABLE, ['section_id' => $sectionId, 'booking_id' => $bookingId]);
    }

    /**
     * Number of bookings per resource; resources without bookings are missing.
     *
     * @return array<int, int> resource id => count
     */
    public function countByResource(int $sectionId): array
    {
        $counts = [];
        $rows = $this->db->fetchAll(
            'SELECT resource_id, COUNT(*) AS cnt FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? GROUP BY resource_id',
            [$sectionId],
        );
        foreach ($rows as $row) {
            $counts[Row::int($row, 'resource_id')] = Row::int($row, 'cnt');
        }
        return $counts;
    }

    /**
     * All bookings of the section that overlap $range, optionally for one resource only.
     *
     * @return list<Booking>
     */
    public function listInRange(int $sectionId, TimeRange $range, ?int $resourceId = null): array
    {
        $sql = 'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? AND start_at < ? AND end_at > ?';
        $params = [$sectionId, $range->end, $range->start];
        if ($resourceId !== null) {
            $sql .= ' AND resource_id = ?';
            $params[] = $resourceId;
        }
        $sql .= ' ORDER BY resource_id, start_at';
        return array_map($this->hydrate(...), $this->db->fetchAll($sql, $params));
    }

    /**
     * Bookings of the resource whose status blocks time and that overlap $range.
     *
     * @return list<Booking>
     */
    public function findBlockingOverlaps(int $sectionId, int $resourceId, TimeRange $range, ?int $excludeBookingId = null): array
    {
        $sql = 'SELECT b.* FROM ' . $this->db->table(self::TABLE) . ' b'
            . ' JOIN ' . $this->db->table('statuses') . ' s ON s.status_id = b.status_id AND s.section_id = b.section_id'
            . ' WHERE b.section_id = ? AND b.resource_id = ? AND s.blocks = 1'
            . ' AND b.start_at < ? AND b.end_at > ?';
        $params = [$sectionId, $resourceId, $range->end, $range->start];
        if ($excludeBookingId !== null) {
            $sql .= ' AND b.booking_id <> ?';
            $params[] = $excludeBookingId;
        }
        $sql .= ' ORDER BY b.start_at, b.booking_id';
        return array_map($this->hydrate(...), $this->db->fetchAll($sql, $params));
    }

    /**
     * @return list<Booking>
     */
    public function listByStatus(int $sectionId, int $statusId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? AND status_id = ? ORDER BY resource_id, start_at',
            [$sectionId, $statusId],
        );
        return array_map($this->hydrate(...), $rows);
    }

    /**
     * All bookings with a blocking status, optionally limited to some resources.
     *
     * @param list<int> $resourceIds
     * @return list<Booking>
     */
    public function listBlocking(int $sectionId, array $resourceIds = []): array
    {
        $sql = 'SELECT b.* FROM ' . $this->db->table(self::TABLE) . ' b'
            . ' JOIN ' . $this->db->table('statuses') . ' s ON s.status_id = b.status_id AND s.section_id = b.section_id'
            . ' WHERE b.section_id = ? AND s.blocks = 1';
        $params = [$sectionId];
        if ($resourceIds !== []) {
            $sql .= ' AND b.resource_id IN (' . implode(', ', array_fill(0, count($resourceIds), '?')) . ')';
            array_push($params, ...array_map('intval', $resourceIds));
        }
        $sql .= ' ORDER BY b.resource_id, b.start_at';
        return array_map($this->hydrate(...), $this->db->fetchAll($sql, $params));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Booking $b): array
    {
        if ($b->createdAt === null || $b->updatedAt === null) {
            throw new \LogicException('Bookings need createdAt and updatedAt before they are stored.');
        }
        return [
            'section_id' => $b->sectionId,
            'resource_id' => $b->resourceId,
            'start_at' => $b->range->start,
            'end_at' => $b->range->end,
            'status_id' => $b->statusId,
            'label' => $b->label,
            'note' => $b->note,
            'service_id' => $b->serviceId,
            'created_at' => $b->createdAt,
            'updated_at' => $b->updatedAt,
            'created_by' => $b->createdBy,
            'updated_by' => $b->updatedBy,
        ];
    }

    /**
     * @param array<mixed> $row
     */
    private function hydrate(array $row): Booking
    {
        return new Booking(
            id: Row::int($row, 'booking_id'),
            sectionId: Row::int($row, 'section_id'),
            resourceId: Row::int($row, 'resource_id'),
            range: new TimeRange($this->db->fromDb(Row::string($row, 'start_at')), $this->db->fromDb(Row::string($row, 'end_at'))),
            statusId: Row::int($row, 'status_id'),
            label: Row::string($row, 'label'),
            note: Row::string($row, 'note'),
            serviceId: Row::nullableInt($row, 'service_id'),
            createdAt: $this->db->fromDb(Row::string($row, 'created_at')),
            updatedAt: $this->db->fromDb(Row::string($row, 'updated_at')),
            createdBy: Row::nullableInt($row, 'created_by'),
            updatedBy: Row::nullableInt($row, 'updated_by'),
        );
    }
}
