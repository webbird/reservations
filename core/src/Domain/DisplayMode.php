<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

enum DisplayMode: string
{
    case Single = 'single';
    case Multi = 'multi';
}
