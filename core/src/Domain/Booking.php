<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

use DateTimeImmutable;

final readonly class Booking
{
    public function __construct(
        public ?int $id,
        public int $sectionId,
        public int $resourceId,
        public TimeRange $range,
        public int $statusId,
        public string $label = '',
        public string $note = '',
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?int $createdBy = null,
        public ?int $updatedBy = null,
    ) {
    }
}
