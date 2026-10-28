<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Half-open time range [start, end): start belongs to the range, end does not.
 */
final readonly class TimeRange
{
    public DateTimeImmutable $start;
    public DateTimeImmutable $end;

    public function __construct(DateTimeImmutable $start, DateTimeImmutable $end)
    {
        if ($end <= $start) {
            throw new InvalidArgumentException('End must be after start.');
        }
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Builds a range from local wall-clock strings (Y-m-d H:i or Y-m-d H:i:s).
     * Rejects times that do not exist in the given time zone, e.g. 02:30 on the
     * day daylight saving time starts, instead of silently shifting them.
     */
    public static function fromLocal(string $start, string $end, DateTimeZone $timezone): self
    {
        return new self(self::parseLocal($start, $timezone), self::parseLocal($end, $timezone));
    }

    public static function parseLocal(string $value, DateTimeZone $timezone): DateTimeImmutable
    {
        $value = trim($value);
        $format = strlen($value) === 16 ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
        $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException(sprintf('Invalid date/time "%s".', $value));
        }
        if ($date->format(substr($format, 1)) !== $value) {
            throw new InvalidArgumentException(sprintf('Local time "%s" does not exist in %s.', $value, $timezone->getName()));
        }
        return $date;
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function withBuffer(int $minutesBefore, int $minutesAfter): self
    {
        if ($minutesBefore < 0 || $minutesAfter < 0) {
            throw new InvalidArgumentException('Buffers must not be negative.');
        }
        return new self(
            $this->start->sub(new DateInterval('PT' . $minutesBefore . 'M')),
            $this->end->add(new DateInterval('PT' . $minutesAfter . 'M')),
        );
    }

    public function durationMinutes(): int
    {
        return intdiv($this->end->getTimestamp() - $this->start->getTimestamp(), 60);
    }

    public function startsAndEndsAtMidnight(): bool
    {
        return $this->start->format('H:i:s') === '00:00:00' && $this->end->format('H:i:s') === '00:00:00';
    }
}
