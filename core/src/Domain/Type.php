<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

/**
 * Kind of resource (holiday flat, meeting room, …). Provides default values
 * that are copied into new resources of this type. The booking mode belongs
 * to the section (Settings), so all types of a section share it.
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
        public array $defaults = [],
        public int $position = 0,
    ) {
    }
}
