<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Calendar\Week;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\OpeningHours;
use Reservations\Core\Domain\TimeRange;

final class WeekTest extends TestCase
{
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
    }

    private function booking(int $id, string $start, string $end): Booking
    {
        return new Booking($id, 1, 1, TimeRange::fromLocal($start, $end, $this->tz), 1);
    }

    /**
     * One day as "HH:MM-HH:MM state rows", state = free, closed, outside or the booking id.
     *
     * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable, rows: int, booking: ?Booking, closed: bool, outside: bool}> $blocks
     * @return list<string>
     */
    private function describe(array $blocks): array
    {
        return array_map(static fn (array $b): string => $b['start']->format('H:i') . '-' . $b['end']->format('H:i') . ' '
            . ($b['booking'] !== null ? '#' . $b['booking']->id : ($b['closed'] ? 'closed' : ($b['outside'] ? 'outside' : 'free'))) . ' ' . $b['rows'], $blocks);
    }

    public function testBookingsAsBlocksOfRows(): void
    {
        // week from Monday 2 November 2026; a booking at odd times from the backend fills its rows
        $monday = new DateTimeImmutable('2026-11-02', $this->tz);
        $bookings = [$this->booking(1, '2026-11-02 10:00', '2026-11-02 12:00'), $this->booking(2, '2026-11-03 10:15', '2026-11-03 11:00')];
        $hours = new OpeningHours();
        self::assertSame([480, 1200], Week::span($bookings, $hours, $monday, 30));

        $days = Week::days($bookings, $hours, $monday, 480, 1200, 30);
        self::assertCount(7, $days);
        self::assertSame(['08:00-10:00 free 4', '10:00-12:00 #1 4', '12:00-20:00 free 16'], $this->describe($days[0]));
        self::assertSame(['08:00-10:00 free 4', '10:00-11:00 #2 2', '11:00-20:00 free 18'], $this->describe($days[1]));
        self::assertSame(['08:00-20:00 free 24'], $this->describe($days[6]));
    }

    public function testRowsOutsideTheRequestableTime(): void
    {
        $monday = new DateTimeImmutable('2026-11-02', $this->tz);
        $bookings = [$this->booking(1, '2026-11-02 09:00', '2026-11-02 10:00')];
        $hours = new OpeningHours([1 => [[480, 720]], 2 => [[480, 720]], 3 => [[480, 720]]]);
        $days = Week::days(
            $bookings,
            $hours,
            $monday,
            480,
            840,
            30,
            new DateTimeImmutable('2026-11-02 11:10', $this->tz),
            new DateTimeImmutable('2026-11-03 10:00', $this->tz),
        );

        // bookings and closed times keep their state; the row 11:00 still touches the start at 11:10
        self::assertSame(['08:00-09:00 outside 2', '09:00-10:00 #1 2', '10:00-11:00 outside 2', '11:00-12:00 free 2', '12:00-14:00 closed 4'], $this->describe($days[0]));
        self::assertSame(['08:00-10:30 free 5', '10:30-12:00 outside 3', '12:00-14:00 closed 4'], $this->describe($days[1]));
        self::assertSame(['08:00-12:00 outside 8', '12:00-14:00 closed 4'], $this->describe($days[2]));
    }

    public function testRowsWhollyInsideABufferAreOutside(): void
    {
        $monday = new DateTimeImmutable('2026-11-02', $this->tz);
        $bookings = [$this->booking(1, '2026-11-02 10:00', '2026-11-02 11:00')];
        $days = Week::days($bookings, new OpeningHours(), $monday, 540, 720, 30, busy: [$bookings[0]->range->withBuffer(45, 45)]);

        // 09:15–11:45: the rows 09:00 and 11:30 are only touched and stay free
        self::assertSame(['09:00-09:30 free 1', '09:30-10:00 outside 1', '10:00-11:00 #1 2', '11:00-11:30 outside 1', '11:30-12:00 free 1'], $this->describe($days[0]));
    }

    public function testOpeningHoursAndLendingOverTheWeekend(): void
    {
        $monday = new DateTimeImmutable('2026-11-02', $this->tz);
        // Monday 09–12 and 13–17, Friday 09–17; lent from Friday 16:00 to the next Monday 10:00
        $hours = new OpeningHours([1 => [[540, 720], [780, 1020]], 5 => [[540, 1020]]]);
        $bookings = [$this->booking(7, '2026-11-06 16:00', '2026-11-09 10:00')];
        self::assertSame([540, 1020], Week::span($bookings, $hours, $monday, 60));

        $days = Week::days($bookings, $hours, $monday, 540, 1020, 60);
        self::assertSame(['09:00-12:00 free 3', '12:00-13:00 closed 1', '13:00-17:00 free 4'], $this->describe($days[0]));
        self::assertSame(['09:00-17:00 closed 8'], $this->describe($days[1]));
        self::assertSame(['09:00-16:00 free 7', '16:00-17:00 #7 1'], $this->describe($days[4]));
        // a booking counts over closed days too
        self::assertSame(['09:00-17:00 #7 8'], $this->describe($days[5]));
    }

    public function testSpanWidensForStartsAndEnds(): void
    {
        $monday = new DateTimeImmutable('2026-11-02', $this->tz);
        $hours = new OpeningHours();
        // 07:15 rounds down to 07:00; an end at midnight shows up to 24:00
        self::assertSame([420, 1200], Week::span([$this->booking(1, '2026-11-03 07:15', '2026-11-03 09:00')], $hours, $monday, 30));
        self::assertSame([480, 1440], Week::span([$this->booking(1, '2026-11-03 18:00', '2026-11-04 00:00')], $hours, $monday, 30));
        // bookings outside the week change nothing
        self::assertSame([480, 1200], Week::span([$this->booking(1, '2026-11-12 06:00', '2026-11-12 23:00')], $hours, $monday, 30));
    }

    public function testDaylightSavingStart(): void
    {
        // Sunday 29 March 2026 skips 02:00–03:00; the booking 01:00–04:00 stays one block of all six rows
        $monday = new DateTimeImmutable('2026-03-23', $this->tz);
        $bookings = [$this->booking(3, '2026-03-29 01:00', '2026-03-29 04:00')];
        self::assertSame([60, 1200], Week::span($bookings, new OpeningHours(), $monday, 30));

        $sunday = Week::days($bookings, new OpeningHours(), $monday, 60, 1200, 30)[6];
        self::assertSame('01:00-04:00 #3 6', $this->describe($sunday)[0]);
        self::assertSame(38, array_sum(array_column($sunday, 'rows')), 'every row of the grid is in a block');
    }
}
