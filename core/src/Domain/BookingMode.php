<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

enum BookingMode: string
{
    case Day = 'day';
    case Period = 'period';
    case Appointment = 'appointment';
}
