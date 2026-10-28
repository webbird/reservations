<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

enum StatusRole: string
{
    case RequestNew = 'request_new';
    case RequestConfirmed = 'request_confirmed';
    case RequestRejected = 'request_rejected';
}
