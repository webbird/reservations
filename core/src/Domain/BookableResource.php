<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

/**
 * Bookable thing (flat, room, person, device). Shown as "Objekt" in the UI.
 * Durations count nights in day mode and minutes in period mode; the lead
 * time is always stored in minutes.
 */
final readonly class BookableResource
{
    public function __construct(
        public ?int $id,
        public int $sectionId,
        public int $typeId,
        public string $title,
        public ?string $color = null,
        public bool $active = true,
        public int $position = 0,
        public ?string $presentationRef = null,
        public ?int $capacity = null,
        public ?int $minPersons = null,
        public bool $autoConfirm = false,
        public int $slotMinutes = 30,
        public ?int $minDuration = null,
        public ?int $maxDuration = null,
        public int $bufferBefore = 0,
        public int $bufferAfter = 0,
        public int $leadMinMinutes = 0,
        public ?int $leadMaxDays = null,
        public ?string $checkinTime = null,
        public ?string $checkoutTime = null,
    ) {
    }

    /**
     * Minimum gap between two bookings of this resource: the clean-up time
     * after the earlier one plus the preparation time before the later one.
     */
    public function requiredGapMinutes(): int
    {
        return $this->bufferBefore + $this->bufferAfter;
    }
}
