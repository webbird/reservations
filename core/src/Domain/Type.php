<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

/**
 * Kind of resource (holiday flat, meeting room, …). Fixes the booking mode and
 * provides default values that are copied into new resources of this type.
 */
final readonly class Type
{
    /**
     * @param array<string, scalar|null> $defaults
     */
    public function __construct(
        public ?int $id,
        public int $sectionId,
        public string $title,
        public BookingMode $bookingMode,
        public array $defaults = [],
        public int $position = 0,
    ) {
    }
}
