<?php

declare(strict_types=1);

namespace Reservations\Core\Storage\Pdo;

use DateTimeImmutable;
use Reservations\Core\Domain\Request;

/**
 * Requests have no section_id of their own; the section comes from the booking.
 * Deleting a booking deletes its request (BookingRepository::delete()).
 */
final class RequestRepository
{
    private const TABLE = 'requests';

    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(Request $r): int
    {
        return $this->db->insert(self::TABLE, [
            'booking_id' => $r->bookingId,
            'name' => $r->name,
            'email' => $r->email,
            'phone' => $r->phone,
            'persons' => $r->persons,
            'message' => $r->message,
            'privacy_accepted_at' => $r->privacyAcceptedAt,
            'created_at' => $r->createdAt,
            'purge_after' => $r->purgeAfter,
        ]);
    }

    public function findByBooking(int $sectionId, int $bookingId): ?Request
    {
        $row = $this->db->fetchOne(
            'SELECT r.* FROM ' . $this->db->table(self::TABLE) . ' r'
            . ' JOIN ' . $this->db->table('bookings') . ' b ON b.booking_id = r.booking_id'
            . ' WHERE b.section_id = ? AND r.booking_id = ?',
            [$sectionId, $bookingId],
        );
        if ($row === null) {
            return null;
        }
        $date = fn (string $column): ?DateTimeImmutable => ($v = Row::nullableString($row, $column)) === null ? null : $this->db->fromDb($v);
        return new Request(
            id: Row::int($row, 'request_id'),
            bookingId: Row::int($row, 'booking_id'),
            name: Row::string($row, 'name'),
            email: Row::string($row, 'email'),
            phone: Row::string($row, 'phone'),
            persons: Row::nullableInt($row, 'persons'),
            message: Row::string($row, 'message'),
            privacyAcceptedAt: $date('privacy_accepted_at'),
            createdAt: $date('created_at'),
            purgeAfter: $date('purge_after'),
        );
    }

    public function setPurgeAfter(int $bookingId, DateTimeImmutable $when): void
    {
        $this->db->update(self::TABLE, ['purge_after' => $when], ['booking_id' => $bookingId]);
    }

    /**
     * Empties the personal fields of all requests due at $now.
     *
     * @return int number of purged requests
     */
    public function purgeExpired(DateTimeImmutable $now): int
    {
        return $this->db->run(
            'UPDATE ' . $this->db->table(self::TABLE)
            . " SET name = '', email = '', phone = '', persons = NULL, message = '', purge_after = NULL"
            . ' WHERE purge_after IS NOT NULL AND purge_after <= ?',
            [$now],
        )->rowCount();
    }
}
