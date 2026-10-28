<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

enum Pattern: string
{
    case Solid = 'solid';
    case Striped = 'striped';
    case Dotted = 'dotted';
    case Outline = 'outline';
}
