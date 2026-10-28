<?php

declare(strict_types=1);

namespace Reservations\Core\Service;

use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\Status;
use Reservations\Core\Result\Result;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Support\Color;

/**
 * Maintains the configurable statuses of a section and guards their rules:
 * each role stays assigned to exactly one active status, used statuses cannot
 * be deleted, and switching a status to "blocks" must not create overlaps.
 * Changes are serialised per section via a lock on the settings row.
 */
final class StatusService
{
    public function __construct(
        private readonly Connection $db,
        private readonly StatusRepository $statuses,
        private readonly BookingRepository $bookings,
        private readonly ResourceRepository $resources,
    ) {
    }

    public function create(Status $status): Result
    {
        $invalid = $this->validate($status);
        if ($invalid !== null) {
            return $invalid;
        }
        if ($status->role !== null && !$status->active) {
            return Result::fail('status.role_needs_active', ['role' => $status->role->value], 'active');
        }
        return $this->db->lockedTransaction('settings', 'section_id', $status->sectionId, function () use ($status): Result {
            if ($status->role !== null) {
                $this->statuses->clearRole($status->sectionId, $status->role);
            }
            return Result::ok($this->statuses->insert($status));
        });
    }

    public function update(Status $status): Result
    {
        $existing = $status->id === null ? null : $this->statuses->find($status->sectionId, $status->id);
        if ($existing === null) {
            return Result::fail('status.not_found', ['id' => $status->id]);
        }
        $invalid = $this->validate($status);
        if ($invalid !== null) {
            return $invalid;
        }
        if ($existing->role !== null && $status->role !== $existing->role) {
            return Result::fail('status.role_required', ['role' => $existing->role->value], 'role');
        }
        if ($status->role !== null && !$status->active) {
            return Result::fail('status.role_needs_active', ['role' => $status->role->value], 'active');
        }

        return $this->db->lockedTransaction('settings', 'section_id', $status->sectionId, function () use ($status, $existing): Result {
            if (!$existing->blocks && $status->blocks) {
                $conflicts = $this->conflictsIfBlocking($status->sectionId, $existing->id ?? 0);
                if ($conflicts !== []) {
                    return Result::fail('status.blocking_conflicts', ['conflicts' => $conflicts], 'blocks');
                }
            }
            if ($status->role !== null && $status->role !== $existing->role) {
                $this->statuses->clearRole($status->sectionId, $status->role);
            }
            $this->statuses->update($status);
            return Result::ok($status->id);
        });
    }

    public function delete(int $sectionId, int $statusId): Result
    {
        $status = $this->statuses->find($sectionId, $statusId);
        if ($status === null) {
            return Result::fail('status.not_found', ['id' => $statusId]);
        }
        if ($status->role !== null) {
            return Result::fail('status.has_role', ['role' => $status->role->value]);
        }
        if ($this->statuses->isUsed($sectionId, $statusId)) {
            return Result::fail('status.in_use', ['id' => $statusId]);
        }
        $this->statuses->delete($sectionId, $statusId);
        return Result::ok();
    }

    /**
     * Pairs of bookings that would overlap if the status started to block time.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function conflictsIfBlocking(int $sectionId, int $statusId): array
    {
        $own = $this->bookings->listByStatus($sectionId, $statusId);
        if ($own === []) {
            return [];
        }
        $resourceIds = array_values(array_unique(array_map(static fn (Booking $b): int => $b->resourceId, $own)));
        $candidates = [...$this->bookings->listBlocking($sectionId, $resourceIds), ...$own];

        $gaps = [];
        foreach ($resourceIds as $id) {
            $gaps[$id] = $this->resources->find($sectionId, $id)?->requiredGapMinutes() ?? 0;
        }

        $pairs = [];
        foreach ($own as $booking) {
            $gap = $gaps[$booking->resourceId];
            $range = $gap > 0 ? $booking->range->withBuffer($gap, $gap) : $booking->range;
            foreach ($candidates as $other) {
                // bookings loaded from the database always have an id
                $a = $booking->id ?? 0;
                $b = $other->id ?? 0;
                if ($a === $b || $other->resourceId !== $booking->resourceId || !$range->overlaps($other->range)) {
                    continue;
                }
                $pair = [min($a, $b), max($a, $b)];
                $pairs[implode('-', $pair)] = $pair;
            }
        }
        return array_values($pairs);
    }

    private function validate(Status $status): ?Result
    {
        if (trim($status->title) === '') {
            return Result::fail('status.title_required', [], 'title');
        }
        if (!Color::isValidHex($status->color)) {
            return Result::fail('status.invalid_color', ['color' => $status->color], 'color');
        }
        return null;
    }
}
