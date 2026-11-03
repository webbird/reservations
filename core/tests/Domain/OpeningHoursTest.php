<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Domain;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\OpeningHours;

final class OpeningHoursTest extends TestCase
{
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
    }

    private function at(string $local): DateTimeImmutable
    {
        return new DateTimeImmutable($local, $this->tz);
    }

    public function testWithoutHoursAlwaysOpen(): void
    {
        $hours = new OpeningHours();
        self::assertTrue($hours->allowsStart($this->at('2026-11-08 03:00')));
        self::assertTrue($hours->allowsEnd($this->at('2026-11-08 03:00')));
    }

    public function testStartAndEndWithinTheHours(): void
    {
        // Monday 09–12 and 13–17; 2026-11-02 is a Monday
        $hours = new OpeningHours([1 => [[540, 720], [780, 1020]]]);
        self::assertTrue($hours->allowsStart($this->at('2026-11-02 09:00')));
        self::assertFalse($hours->allowsStart($this->at('2026-11-02 08:59')));
        self::assertFalse($hours->allowsStart($this->at('2026-11-02 12:00')), 'the end of an interval is no start');
        self::assertTrue($hours->allowsEnd($this->at('2026-11-02 12:00')));
        self::assertFalse($hours->allowsEnd($this->at('2026-11-02 09:00')), 'the start of an interval is no end');
        self::assertFalse($hours->allowsStart($this->at('2026-11-02 12:30')), 'lunch break');
        self::assertTrue($hours->allowsEnd($this->at('2026-11-02 17:00')));
        // days without hours are closed once any day has some
        self::assertFalse($hours->allowsStart($this->at('2026-11-03 10:00')));
    }

    public function testEndAtMidnightBelongsToTheDayBefore(): void
    {
        // Saturday 18–24; 2026-11-07 is a Saturday, Sunday has no hours
        $hours = new OpeningHours([6 => [[1080, OpeningHours::DAY_END]]]);
        self::assertTrue($hours->allowsEnd($this->at('2026-11-08 00:00')));
        self::assertFalse($hours->allowsStart($this->at('2026-11-08 00:00')));
        // Sunday until midnight ends on Monday 00:00
        $sunday = new OpeningHours([7 => [[600, OpeningHours::DAY_END]]]);
        self::assertTrue($sunday->allowsEnd($this->at('2026-11-09 00:00')));
    }

    public function testDaylightSavingDaysKeepTheWallClock(): void
    {
        // Sunday 01:00–04:00; 29 March 2026 skips 02:00–03:00, 25 October 2026 repeats it
        $hours = new OpeningHours([7 => [[60, 240]]]);
        self::assertTrue($hours->allowsStart($this->at('2026-03-29 03:30')));
        self::assertTrue($hours->allowsEnd($this->at('2026-03-29 04:00')));
        self::assertTrue($hours->allowsStart(new DateTimeImmutable('2026-10-25 02:30+01:00')));
        self::assertTrue($hours->allowsStart((new DateTimeImmutable('2026-10-25 02:30+02:00'))->setTimezone($this->tz)));
        self::assertFalse($hours->allowsStart($this->at('2026-10-25 04:00')));
    }

    public function testProblems(): void
    {
        self::assertNull((new OpeningHours([1 => [[540, 720], [720, 1020]]]))->problem(), 'touching intervals are fine');
        self::assertSame('hours.invalid_weekday', (new OpeningHours([0 => [[540, 600]]]))->problem());
        self::assertSame('hours.invalid_interval', (new OpeningHours([1 => [[540, 1441]]]))->problem());
        self::assertSame('hours.invalid_interval', (new OpeningHours([1 => [[600, 600]]]))->problem());
        self::assertSame('hours.overlap', (new OpeningHours([1 => [[780, 1020], [540, 800]]]))->problem());
    }
}
