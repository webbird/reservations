<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

/**
 * Public views of a section. The plan needs several objects, the month calendar
 * shows exactly one; the list works in both modes and is always reachable.
 */
enum CalendarView: string
{
    case Plan = 'plan';
    case Month = 'month';
    case List = 'list';

    public function fits(DisplayMode $mode): bool
    {
        return match ($this) {
            self::Plan => $mode === DisplayMode::Multi,
            self::Month => $mode === DisplayMode::Single,
            self::List => true,
        };
    }

    public static function defaultFor(DisplayMode $mode): self
    {
        return $mode === DisplayMode::Single ? self::Month : self::Plan;
    }
}
