<?php

declare(strict_types=1);

namespace Reservations\Core\Port;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
