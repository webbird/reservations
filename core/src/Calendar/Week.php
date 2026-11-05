<?php

declare(strict_types=1);

namespace Reservations\Core\Calendar;

use DateTimeImmutable;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\OpeningHours;
use Reservations\Core\Domain\TimeRange;

/**
 * One week of one object in period mode as a time grid: per day the rows
 * between two minutes of the day, joined into blocks of the same state (free,
 * closed, one booking). Rows count in wall-clock time, so the labels stay the
 * same on a day with a daylight saving change. Pure functions, no storage.
 *
 * @phpstan-type Block array{start: DateTimeImmutable, end: DateTimeImmutable, rows: int, booking: ?Booking, closed: bool, outside: bool}
 */
final class Week
{
    /** shown without opening hours: 08:00–20:00, widened for bookings of the week */
    public const DEFAULT_FROM = 480;
    public const DEFAULT_TO = 1200;

    /**
     * Minutes of the day the grid covers, on whole rows: from the earliest opening
     * to the latest closing of the week (DEFAULT_FROM/TO without hours), widened so
     * that every start and end of a booking in the week shows. What a booking
     * covers in between (a device lent over the weekend) does not widen it.
     *
     * @param list<Booking> $bookings
     * @return array{int, int}
     */
    public static function span(array $bookings, OpeningHours $hours, DateTimeImmutable $firstDay, int $rowMinutes): array
    {
        [$from, $to] = [self::DEFAULT_FROM, self::DEFAULT_TO];
        if (!$hours->isAlwaysOpen()) {
            [$from, $to] = [OpeningHours::DAY_END, 0];
            foreach ($hours->days as $intervals) {
                foreach ($intervals as [$open, $close]) {
                    [$from, $to] = [min($from, $open), max($to, $close)];
                }
            }
        }
        $weekEnd = $firstDay->modify('+7 days');
        foreach ($bookings as $booking) {
            $start = $booking->range->start;
            if ($start >= $firstDay && $start < $weekEnd) {
                $from = min($from, self::minutes($start));
                $to = max($to, self::minutes($start) + $rowMinutes);
            }
            $end = $booking->range->end;
            if ($end > $firstDay && $end <= $weekEnd) {
                // an end at midnight is 24:00 of the day before
                $minutes = self::minutes($end) === 0 ? OpeningHours::DAY_END : self::minutes($end);
                $from = min($from, $minutes - $rowMinutes);
                $to = max($to, $minutes);
            }
        }
        $from = max(0, intdiv($from, $rowMinutes) * $rowMinutes);
        $to = min(OpeningHours::DAY_END, (int) ceil($to / $rowMinutes) * $rowMinutes);
        return [$from, $to];
    }

    /**
     * Seven days from $firstDay (local midnight), each a list of blocks covering
     * the minutes [$from, $to). A row belongs to the first booking that touches
     * it; without one it is closed if the hours do not touch it, and otherwise
     * outside if it ends by $notBefore or starts after $notAfter.
     *
     * @param list<Booking> $bookings bookings of one object, ordered by start
     * @return list<list<Block>>
     */
    public static function days(array $bookings, OpeningHours $hours, DateTimeImmutable $firstDay, int $from, int $to, int $rowMinutes, ?DateTimeImmutable $notBefore = null, ?DateTimeImmutable $notAfter = null): array
    {
        $days = [];
        for ($d = 0; $d < 7; $d++) {
            $day = $firstDay->modify('+' . $d . ' days');
            $weekday = (int) $day->format('N');
            $blocks = [];
            for ($minute = $from; $minute < $to; $minute += $rowMinutes) {
                $start = self::at($day, $minute);
                $end = self::at($day, min($minute + $rowMinutes, $to));
                $last = array_key_last($blocks);
                // the hour skipped when daylight saving starts has no time of its own: it joins the block before
                if ($end <= $start) {
                    if ($last === null) {
                        $blocks[] = ['start' => $start, 'end' => $start, 'rows' => 1, 'booking' => null, 'closed' => false, 'outside' => false];
                    } else {
                        $blocks[$last]['rows']++;
                    }
                    continue;
                }
                // shortcut: overlapping visible bookings show the first one, as in Occupancy
                $booking = null;
                foreach ($bookings as $candidate) {
                    if ($candidate->range->overlaps(new TimeRange($start, $end))) {
                        $booking = $candidate;
                        break;
                    }
                }
                $closed = $booking === null && !$hours->opensDuring($weekday, $minute, $minute + $rowMinutes);
                $outside = $booking === null && !$closed && (($notBefore !== null && $end <= $notBefore) || ($notAfter !== null && $start > $notAfter));
                if ($last !== null && $blocks[$last]['booking']?->id === $booking?->id && $blocks[$last]['closed'] === $closed && $blocks[$last]['outside'] === $outside) {
                    $blocks[$last]['rows']++;
                    $blocks[$last]['end'] = $end;
                } else {
                    $blocks[] = ['start' => $start, 'end' => $end, 'rows' => 1, 'booking' => $booking, 'closed' => $closed, 'outside' => $outside];
                }
            }
            $days[] = $blocks;
        }
        return $days;
    }

    private static function at(DateTimeImmutable $day, int $minutes): DateTimeImmutable
    {
        return $minutes >= OpeningHours::DAY_END ? $day->modify('+1 day') : $day->setTime(intdiv($minutes, 60), $minutes % 60);
    }

    private static function minutes(DateTimeImmutable $moment): int
    {
        return (int) $moment->format('G') * 60 + (int) $moment->format('i');
    }
}
