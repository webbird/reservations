<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\TimeRange;

final class TimeRangeTest extends TestCase
{
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
    }

    private function range(string $start, string $end): TimeRange
    {
        return TimeRange::fromLocal($start, $end, $this->tz);
    }

    public function testEndMustBeAfterStart(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->range('2026-11-02 10:00', '2026-11-02 10:00');
    }

    public function testAdjacentRangesDoNotOverlap(): void
    {
        $a = $this->range('2026-11-02 00:00', '2026-11-06 00:00');
        $b = $this->range('2026-11-06 00:00', '2026-11-10 00:00');
        self::assertFalse($a->overlaps($b));
        self::assertFalse($b->overlaps($a));
    }

    public function testOverlappingAndContainedRanges(): void
    {
        $a = $this->range('2026-11-02 10:00', '2026-11-02 12:00');
        self::assertTrue($a->overlaps($this->range('2026-11-02 11:59', '2026-11-02 13:00')));
        self::assertTrue($a->overlaps($this->range('2026-11-02 10:30', '2026-11-02 11:00')));
        self::assertTrue($a->overlaps($this->range('2026-11-02 09:00', '2026-11-02 13:00')));
        self::assertFalse($a->overlaps($this->range('2026-11-02 12:00', '2026-11-02 13:00')));
    }

    public function testBufferExtendsBothSides(): void
    {
        $r = $this->range('2026-11-02 10:00', '2026-11-02 11:00')->withBuffer(15, 30);
        self::assertSame('2026-11-02 09:45', $r->start->format('Y-m-d H:i'));
        self::assertSame('2026-11-02 11:30', $r->end->format('Y-m-d H:i'));
    }

    public function testNegativeBufferIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->range('2026-11-02 10:00', '2026-11-02 11:00')->withBuffer(-1, 0);
    }

    public function testNonExistentLocalTimeAtDstStartIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        $this->range('2026-03-29 02:30', '2026-03-29 04:00');
    }

    public function testAmbiguousLocalTimeAtDstEndIsAccepted(): void
    {
        $r = $this->range('2026-10-25 02:30', '2026-10-25 04:00');
        self::assertSame('02:30', $r->start->format('H:i'));
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->range('2026-02-30 10:00', '2026-03-01 10:00');
    }

    public function testDurationAcrossDstChangeCountsRealMinutes(): void
    {
        self::assertSame(23 * 60, $this->range('2026-03-29 00:00', '2026-03-30 00:00')->durationMinutes());
        self::assertSame(25 * 60, $this->range('2026-10-25 00:00', '2026-10-26 00:00')->durationMinutes());
    }

    public function testMidnightCheck(): void
    {
        self::assertTrue($this->range('2026-12-30 00:00', '2027-01-02 00:00')->startsAndEndsAtMidnight());
        self::assertFalse($this->range('2026-12-30 00:00', '2027-01-02 10:00')->startsAndEndsAtMidnight());
    }

    public function testConstructorAcceptsDateTimeObjects(): void
    {
        $r = new TimeRange(new DateTimeImmutable('2026-11-02 10:00', $this->tz), new DateTimeImmutable('2026-11-02 11:00', $this->tz));
        self::assertSame(60, $r->durationMinutes());
    }
}
