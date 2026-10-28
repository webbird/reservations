<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

/**
 * Configurable booking status. The core never interprets the title, only the
 * properties (blocks, visibility, role).
 */
final readonly class Status
{
    public function __construct(
        public ?int $id,
        public int $sectionId,
        public string $title,
        public bool $blocks,
        public Visibility $visibility,
        public string $color,
        public Pattern $pattern,
        public ?StatusRole $role = null,
        public bool $active = true,
        public int $position = 0,
    ) {
    }
}
