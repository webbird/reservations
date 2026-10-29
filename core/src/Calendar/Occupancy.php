<?php

declare(strict_types=1);

namespace Reservations\Core\Calendar;

use DateTimeImmutable;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\Status;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Domain\Visibility;

/**
 * Turns bookings into calendar days for display. Pure functions, no storage.
 */
final class Occupancy
{
    /**
     * Bookings the public may see: status known, active and not hidden.
     *
     * @param list<Booking> $bookings
     * @param array<int, Status> $statuses keyed by status id
     * @return list<Booking>
     */
    public static function publicBookings(array $bookings, array $statuses): array
    {
        return array_values(array_filter($bookings, static function (Booking $b) use ($statuses): bool {
            $status = $statuses[$b->statusId] ?? null;
            return $status !== null && $status->active && $status->visibility !== Visibility::Hidden;
        }));
    }

    /**
     * Days from $from (inclusive) to $to (exclusive), both at local midnight.
     * In day mode bookings are shown from noon of the arrival to noon of the
     * departure, so the changeover day shows both halves.
     *
     * @param list<Booking> $bookings bookings of one object, ordered by start
     * @return list<Day>
     */
    public static function days(array $bookings, DateTimeImmutable $from, DateTimeImmutable $to, bool $wholeDays): array
    {
        $ranges = [];
        foreach ($bookings as $i => $booking) {
            $ranges[$i] = $booking->range;
            $start = $booking->range->start->setTime(12, 0);
            $end = $booking->range->end->setTime(12, 0);
            // a same-day booking left over from period mode would become empty; keep its real times
            if ($wholeDays && $end > $start) {
                $ranges[$i] = new TimeRange($start, $end);
            }
        }

        $days = [];
        for ($day = $from; $day < $to; $day = $day->modify('+1 day')) {
            $noon = $day->setTime(12, 0);
            // shortcut: overlapping visible bookings show the first one per half; fine while blocking statuses cannot overlap
            $days[] = new Day(
                $day,
                self::first($bookings, $ranges, new TimeRange($day, $noon)),
                self::first($bookings, $ranges, new TimeRange($noon, $day->modify('+1 day'))),
            );
        }
        return $days;
    }

    /**
     * @param list<Booking> $bookings
     * @param array<int, TimeRange> $ranges
     */
    private static function first(array $bookings, array $ranges, TimeRange $half): ?Booking
    {
        foreach ($ranges as $i => $range) {
            if ($range->overlaps($half)) {
                return $bookings[$i];
            }
        }
        return null;
    }
}
