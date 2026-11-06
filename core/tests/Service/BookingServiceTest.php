<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\BookingMode;
use Reservations\Core\Domain\Settings;
use Reservations\Core\Domain\Status;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Port\Clock;
use Reservations\Core\Result\Result;
use Reservations\Core\Service\BookingService;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\SettingsRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

final class BookingServiceTest extends TestCase
{
    private DateTimeZone $tz;
    private BookingService $service;
    private BookingRepository $bookings;
    private StatusRepository $statuses;
    private int $flat;
    private int $buffered;
    private int $room;
    private int $booked;
    private int $roomBooked;
    private int $cancelled;
    private int $foreignStatus;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db = new Connection($pdo, 'test_', $this->tz);
        Schema::install($db);
        $this->statuses = new StatusRepository($db);
        $types = new TypeRepository($db);
        $setup = new SectionSetup($db, $this->statuses, $types);
        $setup->run(1, 10);
        $setup->run(2, 20);
        // section 3 books periods; modes are never mixed within a section
        $setup->run(3, 30);
        $settings = new SettingsRepository($db);
        $settings->save(new Settings(3, bookingMode: BookingMode::Period));

        $resources = new ResourceRepository($db);
        $this->flat = $resources->insert(new BookableResource(null, 1, (int) $types->listBySection(1)[0]->id, 'FeWo Seeblick'));
        $this->buffered = $resources->insert(new BookableResource(null, 1, (int) $types->listBySection(1)[0]->id, 'FeWo aus dem Zeitmodus', bufferBefore: 5, bufferAfter: 10));
        $this->room = $resources->insert(new BookableResource(null, 3, (int) $types->listBySection(3)[0]->id, 'Besprechungsraum', bufferBefore: 5, bufferAfter: 10));

        $this->booked = (int) $this->statuses->findByRole(1, StatusRole::RequestConfirmed)?->id;
        $this->roomBooked = (int) $this->statuses->findByRole(3, StatusRole::RequestConfirmed)?->id;
        $this->cancelled = (int) array_values(array_filter($this->statuses->listBySection(1), static fn (Status $s) => !$s->blocks && $s->role === null))[0]->id;
        $this->foreignStatus = (int) $this->statuses->findByRole(2, StatusRole::RequestConfirmed)?->id;

        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-27 12:00:00', new DateTimeZone('Europe/Berlin'));
            }
        };
        $this->bookings = new BookingRepository($db);
        $this->service = new BookingService($db, $resources, $settings, $this->statuses, $this->bookings, $clock);
    }

    private function id(Result $result): int
    {
        self::assertTrue($result->ok);
        self::assertIsInt($result->value);
        return $result->value;
    }

    private function range(string $start, string $end): TimeRange
    {
        return TimeRange::fromLocal($start, $end, $this->tz);
    }

    public function testCreateStoresBookingWithTimestamps(): void
    {
        $result = $this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->booked, ' Müller ', '', 3);
        self::assertTrue($result->ok);
        $booking = $this->bookings->find(1, $this->id($result));
        self::assertNotNull($booking);
        self::assertSame('Müller', $booking->label);
        self::assertSame('2026-10-27 12:00', $booking->createdAt?->format('Y-m-d H:i'));
        self::assertSame(3, $booking->createdBy);
    }

    public function testOverlapIsRejectedWithConflicts(): void
    {
        $first = $this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->booked);
        $second = $this->service->create(1, $this->flat, $this->range('2026-11-05 00:00', '2026-11-08 00:00'), $this->booked);
        self::assertFalse($second->ok);
        self::assertSame('booking.overlap', $second->firstErrorKey());
        self::assertSame('range', $second->errors[0]['field']);
        self::assertSame(
            [['id' => $this->id($first), 'start' => '2026-11-02 00:00:00', 'end' => '2026-11-06 00:00:00']],
            $second->errors[0]['params']['conflicts'],
        );
    }

    public function testOverlongLabelIsRejected(): void
    {
        $result = $this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->booked, str_repeat('ä', 256));
        self::assertSame('booking.label_too_long', $result->firstErrorKey());

        $result = $this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->booked, '', str_repeat('x', BookingService::NOTE_MAX + 1));
        self::assertSame('booking.note_too_long', $result->firstErrorKey());
    }

    public function testChangeoverDayIsAllowed(): void
    {
        self::assertTrue($this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->booked)->ok);
        self::assertTrue($this->service->create(1, $this->flat, $this->range('2026-11-06 00:00', '2026-11-10 00:00'), $this->booked)->ok);
    }

    public function testOverlapAcrossYearEnd(): void
    {
        self::assertTrue($this->service->create(1, $this->flat, $this->range('2026-12-28 00:00', '2027-01-03 00:00'), $this->booked)->ok);
        self::assertFalse($this->service->create(1, $this->flat, $this->range('2027-01-02 00:00', '2027-01-05 00:00'), $this->booked)->ok);
    }

    public function testNonBlockingStatusesNeverConflict(): void
    {
        self::assertTrue($this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->cancelled)->ok);
        self::assertTrue($this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->booked)->ok);
        self::assertTrue($this->service->create(1, $this->flat, $this->range('2026-11-03 00:00', '2026-11-04 00:00'), $this->cancelled)->ok);
    }

    public function testFailingFollowUpLeavesNoBooking(): void
    {
        foreach ([$this->booked, $this->cancelled] as $status) {
            try {
                $this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $status, then: static function (int $id): void {
                    throw new \RuntimeException('request insert failed');
                });
                self::fail('Exception expected.');
            } catch (\RuntimeException $e) {
                self::assertSame('request insert failed', $e->getMessage());
            }
        }
        self::assertFalse($this->bookings->hasBookings(1));
    }

    public function testBufferRequiresGapBetweenBookings(): void
    {
        self::assertTrue($this->service->create(3, $this->room, $this->range('2026-11-02 10:00', '2026-11-02 11:00'), $this->roomBooked)->ok);
        // gap needed: 10 min clean-up + 5 min preparation
        self::assertFalse($this->service->create(3, $this->room, $this->range('2026-11-02 11:10', '2026-11-02 12:00'), $this->roomBooked)->ok);
        self::assertFalse($this->service->create(3, $this->room, $this->range('2026-11-02 09:00', '2026-11-02 09:50'), $this->roomBooked)->ok);
        self::assertTrue($this->service->create(3, $this->room, $this->range('2026-11-02 11:15', '2026-11-02 12:00'), $this->roomBooked)->ok);
        self::assertTrue($this->service->create(3, $this->room, $this->range('2026-11-02 08:00', '2026-11-02 09:45'), $this->roomBooked)->ok);
    }

    public function testUpdateDoesNotConflictWithItself(): void
    {
        $id = $this->id($this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->booked));
        $result = $this->service->update(1, $id, $this->flat, $this->range('2026-11-03 00:00', '2026-11-07 00:00'), $this->booked, 'neu', '', 5);
        self::assertTrue($result->ok);
        $booking = $this->bookings->find(1, $id);
        self::assertNotNull($booking);
        self::assertSame('2026-11-03', $booking->range->start->format('Y-m-d'));
        self::assertSame(5, $booking->updatedBy);
    }

    public function testUpdateStillDetectsOtherBookings(): void
    {
        $this->service->create(1, $this->flat, $this->range('2026-11-10 00:00', '2026-11-12 00:00'), $this->booked);
        $id = $this->id($this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->booked));
        $result = $this->service->update(1, $id, $this->flat, $this->range('2026-11-08 00:00', '2026-11-11 00:00'), $this->booked);
        self::assertSame('booking.overlap', $result->firstErrorKey());
    }

    public function testDayModeRequiresWholeDays(): void
    {
        $result = $this->service->create(1, $this->flat, $this->range('2026-11-02 15:00', '2026-11-06 10:00'), $this->booked);
        self::assertSame('booking.day_mode_requires_whole_days', $result->firstErrorKey());
    }

    public function testForeignOrUnknownReferencesAreRejected(): void
    {
        $range = $this->range('2026-11-02 00:00', '2026-11-06 00:00');
        self::assertSame('resource.not_found', $this->service->create(2, $this->flat, $range, $this->foreignStatus)->firstErrorKey());
        self::assertSame('status.not_found', $this->service->create(1, $this->flat, $range, $this->foreignStatus)->firstErrorKey());
        self::assertSame('booking.not_found', $this->service->update(1, 999, $this->flat, $range, $this->booked)->firstErrorKey());
    }

    public function testBuffersDoNotCountInDayMode(): void
    {
        $this->id($this->service->create(1, $this->buffered, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->booked));
        $this->id($this->service->create(1, $this->buffered, $this->range('2026-11-06 00:00', '2026-11-08 00:00'), $this->booked));
    }

    public function testInactiveStatusStaysButIsNotGiven(): void
    {
        $range = $this->range('2026-11-02 00:00', '2026-11-06 00:00');
        $later = $this->range('2026-11-10 00:00', '2026-11-12 00:00');
        $old = $this->id($this->service->create(1, $this->flat, $range, $this->cancelled));
        $other = $this->id($this->service->create(1, $this->flat, $later, $this->booked));
        $status = $this->statuses->find(1, $this->cancelled);
        self::assertNotNull($status);
        $this->statuses->update(new Status($status->id, 1, $status->title, false, $status->visibility, $status->color, $status->pattern, null, false));

        $this->id($this->service->update(1, $old, $this->flat, $range, $this->cancelled, 'neue Notiz'));
        self::assertSame('status.not_found', $this->service->update(1, $other, $this->flat, $later, $this->cancelled)->firstErrorKey());
    }

    public function testInactiveStatusIsRejected(): void
    {
        $status = $this->statuses->find(1, $this->cancelled);
        self::assertNotNull($status);
        $this->statuses->update(new Status($status->id, 1, $status->title, false, $status->visibility, $status->color, $status->pattern, null, false));
        $result = $this->service->create(1, $this->flat, $this->range('2026-11-02 00:00', '2026-11-06 00:00'), $this->cancelled);
        self::assertSame('status.not_found', $result->firstErrorKey());
    }
}
