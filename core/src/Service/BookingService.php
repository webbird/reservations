<?php

declare(strict_types=1);

namespace Reservations\Core\Service;

use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\BookingMode;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Port\Clock;
use Reservations\Core\Result\Result;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;

/**
 * Creates and changes bookings. Knows nothing about backend or frontend; the
 * caller decides who may do what.
 */
final class BookingService
{
    public const LABEL_MAX = 255;
    /** bytes; the size of a MySQL TEXT column */
    public const NOTE_MAX = 65535;

    public function __construct(
        private readonly Connection $db,
        private readonly ResourceRepository $resources,
        private readonly TypeRepository $types,
        private readonly StatusRepository $statuses,
        private readonly BookingRepository $bookings,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return Result value: new booking id; on overlap params.conflicts lists the bookings
     */
    public function create(
        int $sectionId,
        int $resourceId,
        TimeRange $range,
        int $statusId,
        string $label = '',
        string $note = '',
        ?int $userId = null,
    ): Result {
        $now = $this->clock->now();
        $booking = new Booking(
            id: null,
            sectionId: $sectionId,
            resourceId: $resourceId,
            range: $range,
            statusId: $statusId,
            label: trim($label),
            note: trim($note),
            createdAt: $now,
            updatedAt: $now,
            createdBy: $userId,
            updatedBy: $userId,
        );
        return $this->save($booking);
    }

    public function update(
        int $sectionId,
        int $bookingId,
        int $resourceId,
        TimeRange $range,
        int $statusId,
        string $label = '',
        string $note = '',
        ?int $userId = null,
    ): Result {
        $existing = $this->bookings->find($sectionId, $bookingId);
        if ($existing === null) {
            return Result::fail('booking.not_found', ['id' => $bookingId]);
        }
        $booking = new Booking(
            id: $bookingId,
            sectionId: $sectionId,
            resourceId: $resourceId,
            range: $range,
            statusId: $statusId,
            label: trim($label),
            note: trim($note),
            serviceId: $existing->serviceId,
            createdAt: $existing->createdAt,
            updatedAt: $this->clock->now(),
            createdBy: $existing->createdBy,
            updatedBy: $userId,
        );
        return $this->save($booking);
    }

    private function save(Booking $booking): Result
    {
        // the database may run without strict mode and would truncate silently
        if (mb_strlen($booking->label) > self::LABEL_MAX) {
            return Result::fail('booking.label_too_long', ['max' => self::LABEL_MAX], 'label');
        }
        if (strlen($booking->note) > self::NOTE_MAX) {
            return Result::fail('booking.note_too_long', [], 'note');
        }
        $resource = $this->resources->find($booking->sectionId, $booking->resourceId);
        if ($resource === null) {
            return Result::fail('resource.not_found', ['id' => $booking->resourceId], 'resource');
        }
        $type = $this->types->find($booking->sectionId, $resource->typeId);
        if ($type === null) {
            return Result::fail('type.not_found', ['id' => $resource->typeId], 'resource');
        }
        $status = $this->statuses->find($booking->sectionId, $booking->statusId);
        if ($status === null || !$status->active) {
            return Result::fail('status.not_found', ['id' => $booking->statusId], 'status');
        }
        if ($type->bookingMode === BookingMode::Day && !$booking->range->startsAndEndsAtMidnight()) {
            return Result::fail('booking.day_mode_requires_whole_days', [], 'range');
        }

        $persist = function () use ($booking): int {
            if ($booking->id === null) {
                return $this->bookings->insert($booking);
            }
            $this->bookings->update($booking);
            return $booking->id;
        };

        if (!$status->blocks) {
            return Result::ok($persist());
        }

        $gap = $resource->requiredGapMinutes();
        $checkRange = $gap > 0 ? $booking->range->withBuffer($gap, $gap) : $booking->range;

        return $this->db->lockedTransaction('resources', 'resource_id', $resource->id ?? 0, function () use ($booking, $checkRange, $persist): Result {
            $conflicts = $this->bookings->findBlockingOverlaps($booking->sectionId, $booking->resourceId, $checkRange, $booking->id);
            if ($conflicts !== []) {
                return Result::fail('booking.overlap', [
                    'conflicts' => array_map(fn (Booking $c): array => [
                        'id' => $c->id,
                        'start' => $this->db->toDb($c->range->start),
                        'end' => $this->db->toDb($c->range->end),
                    ], $conflicts),
                ], 'range');
            }
            return Result::ok($persist());
        });
    }
}
