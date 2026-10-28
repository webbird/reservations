<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Calendar\Day;
use Reservations\Core\Calendar\Occupancy;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\Pattern;
use Reservations\Core\Domain\Status;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Domain\Visibility;

final class OccupancyTest extends TestCase
{
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
    }

    private function booking(int $id, string $start, string $end, int $status = 1): Booking
    {
        return new Booking($id, 1, 1, TimeRange::fromLocal($start, $end, $this->tz), $status);
    }

    /**
     * @param list<Booking> $bookings
     * @return list<Day>
     */
    private function days(array $bookings, string $from, string $to, bool $wholeDays = true): array
    {
        return Occupancy::days($bookings, new DateTimeImmutable($from, $this->tz), new DateTimeImmutable($to, $this->tz), $wholeDays);
    }

    /**
     * Compact notation per day: "am/pm" with booking ids, "-" for free.
     *
     * @param list<Day> $days
     */
    private function notation(array $days): string
    {
        return implode(' ', array_map(static fn (Day $d): string => ($d->am->id ?? '-') . '/' . ($d->pm->id ?? '-'), $days));
    }

    public function testArrivalDepartureAndChangeover(): void
    {
        $days = $this->days([
            $this->booking(1, '2026-11-02 00:00', '2026-11-06 00:00'),
            $this->booking(2, '2026-11-06 00:00', '2026-11-09 00:00'),
        ], '2026-11-01', '2026-11-11');

        self::assertSame('-/- -/1 1/1 1/1 1/1 1/2 2/2 2/2 2/- -/-', $this->notation($days));
        self::assertTrue($days[0]->isFree());
        self::assertTrue($days[1]->isArrival());
        self::assertFalse($days[1]->isDeparture());
        self::assertFalse($days[2]->isArrival() || $days[2]->isDeparture());
        self::assertTrue($days[5]->isChangeover());
        self::assertTrue($days[8]->isDeparture());
        self::assertFalse($days[8]->isArrival());
    }

    public function testBookingAcrossMonthStartAndDstChange(): void
    {
        // daylight saving time ends on 2026-10-25
        $days = $this->days([$this->booking(1, '2026-10-24 00:00', '2026-10-27 00:00')], '2026-10-24', '2026-10-28');
        self::assertSame('-/1 1/1 1/1 1/-', $this->notation($days));

        $days = $this->days([$this->booking(1, '2026-10-28 00:00', '2026-11-03 00:00')], '2026-11-01', '2026-11-04');
        self::assertSame('1/1 1/1 1/-', $this->notation($days));
    }

    public function testPeriodModeUsesMorningAndAfternoon(): void
    {
        $days = $this->days([
            $this->booking(1, '2026-11-02 09:00', '2026-11-02 11:00'),
            $this->booking(2, '2026-11-03 13:00', '2026-11-03 14:00'),
            $this->booking(3, '2026-11-04 11:00', '2026-11-04 13:00'),
        ], '2026-11-02', '2026-11-05', false);

        self::assertSame('1/- -/2 3/3', $this->notation($days));
        self::assertFalse($days[2]->isArrival() || $days[2]->isDeparture());
    }

    public function testPublicBookingsHideHiddenInactiveAndUnknownStatuses(): void
    {
        $statuses = [
            1 => new Status(1, 1, 'gebucht', true, Visibility::Label, '#185fa5', Pattern::Solid),
            2 => new Status(2, 1, 'angefragt', true, Visibility::Hidden, '#f5c26b', Pattern::Dotted),
            3 => new Status(3, 1, 'alt', true, Visibility::Busy, '#5f5e5a', Pattern::Striped, active: false),
        ];
        $bookings = [];
        foreach ([1, 2, 3, 4] as $i => $status) {
            $bookings[] = $this->booking($i + 1, '2026-11-0' . ($i + 1) . ' 00:00', '2026-11-0' . ($i + 2) . ' 00:00', $status);
        }
        self::assertSame([1], array_map(static fn (Booking $b): ?int => $b->id, Occupancy::publicBookings($bookings, $statuses)));
    }
}
