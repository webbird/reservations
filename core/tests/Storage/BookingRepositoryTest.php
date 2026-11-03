<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Storage;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

final class BookingRepositoryTest extends TestCase
{
    private Connection $db;
    private BookingRepository $bookings;
    private DateTimeZone $tz;
    private int $flat;
    private int $otherFlat;
    private int $booked;
    private int $cancelled;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db = new Connection($pdo, 'test_', $this->tz);
        Schema::install($this->db);
        $statuses = new StatusRepository($this->db);
        $types = new TypeRepository($this->db);
        (new SectionSetup($this->db, $statuses, $types))->run(1, 10);
        (new SectionSetup($this->db, $statuses, $types))->run(2, 20);

        $typeId = (int) $types->listBySection(1)[0]->id;
        $resources = new ResourceRepository($this->db);
        $this->flat = $resources->insert(new BookableResource(null, 1, $typeId, 'FeWo Seeblick'));
        $this->otherFlat = $resources->insert(new BookableResource(null, 1, $typeId, 'FeWo Waldrand'));
        $this->booked = (int) $statuses->findByRole(1, StatusRole::RequestConfirmed)?->id;
        $this->cancelled = (int) array_values(array_filter($statuses->listBySection(1), static fn ($s) => !$s->blocks && $s->role === null))[0]->id;
        $this->bookings = new BookingRepository($this->db);
    }

    private function booking(int $resource, string $start, string $end, int $status, int $section = 1): Booking
    {
        $now = new DateTimeImmutable('2026-10-27 12:00', $this->tz);
        return new Booking(null, $section, $resource, TimeRange::fromLocal($start, $end, $this->tz), $status, createdAt: $now, updatedAt: $now);
    }

    public function testInsertAndFindRoundTrip(): void
    {
        $id = $this->bookings->insert(new Booking(
            null, 1, $this->flat, TimeRange::fromLocal('2026-11-02 00:00', '2026-11-06 00:00', $this->tz), $this->booked,
            label: 'Müller', note: 'Hund', createdAt: new \DateTimeImmutable('2026-10-27 12:00', $this->tz),
            updatedAt: new \DateTimeImmutable('2026-10-27 12:00', $this->tz), createdBy: 7,
        ));
        $found = $this->bookings->find(1, $id);
        self::assertNotNull($found);
        self::assertSame('2026-11-02 00:00:00', $found->range->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-06 00:00:00', $found->range->end->format('Y-m-d H:i:s'));
        self::assertSame('Müller', $found->label);
        self::assertSame(7, $found->createdBy);
        self::assertNull($found->updatedBy);
        self::assertNull($this->bookings->find(2, $id), 'other section must not see the booking');
    }

    public function testFindBlockingOverlapsFiltersCorrectly(): void
    {
        $hit = $this->bookings->insert($this->booking($this->flat, '2026-11-02 00:00', '2026-11-06 00:00', $this->booked));
        $this->bookings->insert($this->booking($this->flat, '2026-11-03 00:00', '2026-11-04 00:00', $this->cancelled));
        $this->bookings->insert($this->booking($this->otherFlat, '2026-11-03 00:00', '2026-11-04 00:00', $this->booked));
        $this->bookings->insert($this->booking($this->flat, '2026-11-06 00:00', '2026-11-08 00:00', $this->booked));

        $range = TimeRange::fromLocal('2026-11-03 00:00', '2026-11-06 00:00', $this->tz);
        $found = $this->bookings->findBlockingOverlaps(1, $this->flat, $range);
        self::assertSame([$hit], array_map(static fn (Booking $b) => $b->id, $found));

        self::assertSame([], $this->bookings->findBlockingOverlaps(1, $this->flat, $range, $hit));
        self::assertSame([], $this->bookings->findBlockingOverlaps(2, $this->flat, $range));
    }

    public function testHasBookingsIsPerSection(): void
    {
        $this->bookings->insert($this->booking($this->otherFlat, '2026-11-03 00:00', '2026-11-04 00:00', $this->booked, 2));
        self::assertFalse($this->bookings->hasBookings(1));
        // cancelled bookings count too: they were entered for the current booking mode
        $this->bookings->insert($this->booking($this->flat, '2026-11-08 00:00', '2026-11-09 00:00', $this->cancelled));

        self::assertTrue($this->bookings->hasBookings(1));
    }

    public function testCountCurrentSplitsRequestsAndSkipsPastOrCancelled(): void
    {
        $requested = (int) (new StatusRepository($this->db))->findByRole(1, StatusRole::RequestNew)?->id;
        // past, running, future, cancelled, open request, request of another section
        $this->bookings->insert($this->booking($this->flat, '2026-10-01 00:00', '2026-10-05 00:00', $this->booked));
        $this->bookings->insert($this->booking($this->flat, '2026-10-26 00:00', '2026-10-30 00:00', $this->booked));
        $this->bookings->insert($this->booking($this->flat, '2026-11-02 00:00', '2026-11-06 00:00', $this->booked));
        $this->bookings->insert($this->booking($this->flat, '2026-11-08 00:00', '2026-11-09 00:00', $this->cancelled));
        $this->bookings->insert($this->booking($this->otherFlat, '2026-11-10 00:00', '2026-11-12 00:00', $requested));
        $this->bookings->insert($this->booking($this->flat, '2026-11-03 00:00', '2026-11-04 00:00', $this->booked, 2));

        self::assertSame(
            [$this->flat => ['bookings' => 2, 'requests' => 0], $this->otherFlat => ['bookings' => 0, 'requests' => 1]],
            $this->bookings->countCurrentByResource(1, new DateTimeImmutable('2026-10-27 12:00', $this->tz)),
        );
    }
}
