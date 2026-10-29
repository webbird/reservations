<?php

declare(strict_types=1);

namespace Reservations\Core\Calendar;

use DateTimeImmutable;
use Reservations\Core\Domain\Booking;

/**
 * One calendar day of one object, split into morning (am) and afternoon (pm).
 * In day mode an arrival occupies the afternoon, a departure the morning.
 */
final readonly class Day
{
    public function __construct(
        public DateTimeImmutable $date,
        public ?Booking $am = null,
        public ?Booking $pm = null,
    ) {
    }

    public function isFree(): bool
    {
        return $this->am === null && $this->pm === null;
    }

    public function isArrival(): bool
    {
        return $this->pm !== null && $this->am?->id !== $this->pm->id;
    }

    public function isDeparture(): bool
    {
        return $this->am !== null && $this->pm?->id !== $this->am->id;
    }
}
