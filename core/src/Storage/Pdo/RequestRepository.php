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
            'client_hash' => $r->clientHash,
        ]);
    }

    /**
     * Requests of one visitor in the section since $since, for the rate limit.
     */
    public function countRecent(int $sectionId, string $clientHash, DateTimeImmutable $since): int
    {
        return $this->db->fetchInt(
            'SELECT COUNT(*) FROM ' . $this->db->table(self::TABLE) . ' r'
            . ' JOIN ' . $this->db->table('bookings') . ' b ON b.booking_id = r.booking_id'
            . ' WHERE b.section_id = ? AND r.client_hash = ? AND r.created_at > ?',
            [$sectionId, $clientHash, $since],
        );
    }

    /**
     * Empties the visitor hashes of requests created before $before.
     */
    public function forgetClients(DateTimeImmutable $before): void
    {
        $this->db->run(
            'UPDATE ' . $this->db->table(self::TABLE) . ' SET client_hash = NULL WHERE client_hash IS NOT NULL AND created_at < ?',
            [$before],
        );
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
            clientHash: Row::nullableString($row, 'client_hash'),
        );
    }

    /**
     * Ids of the section's bookings that came in as requests, for marking them in lists.
     *
     * @return list<int>
     */
    public function bookingIds(int $sectionId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT r.booking_id FROM ' . $this->db->table(self::TABLE) . ' r'
            . ' JOIN ' . $this->db->table('bookings') . ' b ON b.booking_id = r.booking_id'
            . ' WHERE b.section_id = ?',
            [$sectionId],
        );
        return array_map(static fn (array $row): int => Row::int($row, 'booking_id'), $rows);
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
