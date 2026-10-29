<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

use DateTimeImmutable;

/**
 * Contact data of a booking request from the frontend; 1:1 to its booking.
 * After purgeAfter the personal fields are emptied, the booking stays.
 */
final readonly class Request
{
    public function __construct(
        public ?int $id,
        public int $bookingId,
        public string $name,
        public string $email,
        public string $phone = '',
        public ?int $persons = null,
        public string $message = '',
        public ?DateTimeImmutable $privacyAcceptedAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $purgeAfter = null,
    ) {
    }
}
