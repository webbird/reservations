<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

enum Visibility: string
{
    case Label = 'label';
    case Busy = 'busy';
    case Hidden = 'hidden';
}
