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
    /** booking rules a type presets for its new resources (Type::$defaults), named like the properties */
    public const RULE_KEYS = ['capacity', 'minPersons', 'autoConfirm', 'leadMinMinutes', 'leadMaxDays', 'minDuration', 'maxDuration', 'bufferBefore', 'bufferAfter'];

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

    /**
     * A copy with the given booking rules; keys other than RULE_KEYS are ignored.
     *
     * @param array<string, scalar|null> $rules
     * @throws \TypeError if a value has the wrong type, e.g. "4" for the capacity
     */
    public function withRules(array $rules): self
    {
        $rules = array_intersect_key($rules, array_flip(self::RULE_KEYS));
        // strict_types makes the constructor reject wrongly typed values, which PHPStan cannot see in a spread
        return new self(...array_replace(get_object_vars($this), $rules)); // @phpstan-ignore argument.type
    }
}
