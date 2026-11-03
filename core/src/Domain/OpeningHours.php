<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

use DateTimeImmutable;

/**
 * Opening hours of a resource per ISO weekday (1 = Monday … 7 = Sunday), as
 * minutes after midnight on the wall clock; several intervals per day allow a
 * lunch break, 1440 is the end of the day. Without any interval the resource
 * is open around the clock; once a day has intervals, days without are closed.
 * Bookings must start and end within the hours, what lies between is free
 * (a device can be lent over the weekend).
 */
final readonly class OpeningHours
{
    public const DAY_END = 1440;

    /**
     * @param array<int, list<array{int, int}>> $days weekday => [[from, to], …], to exclusive
     */
    public function __construct(public array $days = [])
    {
    }

    public function isAlwaysOpen(): bool
    {
        return $this->days === [];
    }

    /**
     * One day as typed in a form: "09:00–12:00, 13:00–17:00", also "9-12" or with ";".
     * An empty text means no hours that day; null if the text is unreadable. Ranges
     * and overlaps are left to problem().
     *
     * @return list<array{int, int}>|null
     */
    public static function parseDay(string $text): ?array
    {
        $intervals = [];
        foreach (explode(',', strtr($text, ';', ',')) as $part) {
            if (trim($part) === '') {
                continue;
            }
            if (preg_match('/^\s*(\d{1,2})(?::([0-5]\d))?\s*[-–—]\s*(\d{1,2})(?::([0-5]\d))?\s*$/u', $part, $m) !== 1) {
                return null;
            }
            $intervals[] = [(int) $m[1] * 60 + (int) $m[2], (int) $m[3] * 60 + (int) ($m[4] ?? 0)];
        }
        return $intervals;
    }

    /**
     * @param list<array{int, int}> $intervals
     */
    public static function formatDay(array $intervals): string
    {
        $time = static fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        return implode(', ', array_map(static fn (array $i): string => $time($i[0]) . '–' . $time($i[1]), $intervals));
    }

    /**
     * Problems that make the hours unusable: a key for the first one, or null.
     */
    public function problem(): ?string
    {
        foreach ($this->days as $weekday => $intervals) {
            if ($weekday < 1 || $weekday > 7) {
                return 'hours.invalid_weekday';
            }
            // arrays sort by their first element, then the second: by start time
            sort($intervals);
            $previousTo = 0;
            foreach ($intervals as [$from, $to]) {
                if ($from < 0 || $to > self::DAY_END || $from >= $to) {
                    return 'hours.invalid_interval';
                }
                if ($from < $previousTo) {
                    return 'hours.overlap';
                }
                $previousTo = $to;
            }
        }
        return null;
    }

    /**
     * A booking may start at a moment inside [from, to) of its weekday.
     */
    public function allowsStart(DateTimeImmutable $moment): bool
    {
        return $this->isAlwaysOpen() || $this->contains((int) $moment->format('N'), self::minutes($moment), false);
    }

    /**
     * A booking may end at a moment inside (from, to] of its weekday; midnight
     * also counts as 24:00 of the day before.
     */
    public function allowsEnd(DateTimeImmutable $moment): bool
    {
        if ($this->isAlwaysOpen()) {
            return true;
        }
        $weekday = (int) $moment->format('N');
        $minutes = self::minutes($moment);
        return $this->contains($weekday, $minutes, true)
            || ($minutes === 0 && $this->contains($weekday === 1 ? 7 : $weekday - 1, self::DAY_END, true));
    }

    /**
     * Whether the hours of a weekday touch the minutes [from, to), e.g. for one
     * row of a time grid; a row only partly open counts as open.
     */
    public function opensDuring(int $weekday, int $from, int $to): bool
    {
        if ($this->isAlwaysOpen()) {
            return true;
        }
        foreach ($this->days[$weekday] ?? [] as [$open, $close]) {
            if ($open < $to && $close > $from) {
                return true;
            }
        }
        return false;
    }

    private function contains(int $weekday, int $minutes, bool $asEnd): bool
    {
        foreach ($this->days[$weekday] ?? [] as [$from, $to]) {
            if ($asEnd ? ($minutes > $from && $minutes <= $to) : ($minutes >= $from && $minutes < $to)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Wall-clock minutes, so a day with a daylight saving change keeps its hours.
     */
    private static function minutes(DateTimeImmutable $moment): int
    {
        return (int) $moment->format('G') * 60 + (int) $moment->format('i');
    }
}
