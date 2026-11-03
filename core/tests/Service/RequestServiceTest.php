<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\BookingMode;
use Reservations\Core\Domain\Settings;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Port\Clock;
use Reservations\Core\Result\Result;
use Reservations\Core\Service\BookingService;
use Reservations\Core\Service\RequestService;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\RequestRepository;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\SettingsRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

final class RequestServiceTest extends TestCase
{
    private DateTimeZone $tz;
    private Connection $db;
    private RequestService $service;
    private BookingRepository $bookings;
    private StatusRepository $statuses;
    private RequestRepository $requests;
    private ResourceRepository $resources;
    private int $flat;
    private int $instant;
    private int $limited;
    private int $inactive;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db = new Connection($pdo, 'test_', $this->tz);
        Schema::install($this->db);
        $this->statuses = new StatusRepository($this->db);
        $types = new TypeRepository($this->db);
        $setup = new SectionSetup($this->db, $this->statuses, $types);
        $setup->run(1, 10);
        // section 2 keeps requests disabled, the default
        $setup->run(2, 20);
        $settings = new SettingsRepository($this->db);
        $settings->save(new Settings(1, requestsEnabled: true, retentionDays: 30));

        $type = (int) $types->listBySection(1)[0]->id;
        $this->resources = new ResourceRepository($this->db);
        $this->flat = $this->resources->insert(new BookableResource(null, 1, $type, 'FeWo Seeblick', capacity: 4));
        $this->instant = $this->resources->insert(new BookableResource(null, 1, $type, 'FeWo Sofort', autoConfirm: true));
        $this->limited = $this->resources->insert(new BookableResource(null, 1, $type, 'FeWo Vorlauf', leadMinMinutes: 2 * 24 * 60, leadMaxDays: 30));
        $this->inactive = $this->resources->insert(new BookableResource(null, 1, $type, 'FeWo alt', active: false));

        $this->now = new DateTimeImmutable('2026-10-29 15:00', $this->tz);
        $clock = new class ($this->now) implements Clock {
            public function __construct(private readonly DateTimeImmutable $now)
            {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
        $this->bookings = new BookingRepository($this->db);
        $this->requests = new RequestRepository($this->db);
        $bookingService = new BookingService($this->db, $this->resources, $settings, $this->statuses, $this->bookings, $clock);
        $this->service = new RequestService($bookingService, $settings, $this->resources, $this->statuses, $this->bookings, $this->requests, $clock);
    }

    private function range(string $start, string $end): TimeRange
    {
        return TimeRange::fromLocal($start . ' 00:00', $end . ' 00:00', $this->tz);
    }

    private function submit(int $resource, TimeRange $range, int $section = 1): Result
    {
        return $this->service->submit($section, $resource, $range, ' Erika Muster ', 'erika@example.com', '0170 123', 2, 'Mit Hund', true);
    }

    private function id(Result $result): int
    {
        self::assertTrue($result->ok, (string) $result->firstErrorKey());
        self::assertIsInt($result->value);
        return $result->value;
    }

    public function testSubmitStoresBookingAndContactData(): void
    {
        $id = $this->id($this->submit($this->flat, $this->range('2026-11-02', '2026-11-06')));

        self::assertSame($this->statuses->findByRole(1, StatusRole::RequestNew)?->id, $this->bookings->find(1, $id)?->statusId);
        $request = $this->requests->findByBooking(1, $id);
        self::assertNotNull($request);
        self::assertSame('Erika Muster', $request->name);
        self::assertSame(2, $request->persons);
        self::assertEquals($this->now, $request->privacyAcceptedAt);
        // retention counts from the end of the period
        self::assertEquals(new DateTimeImmutable('2026-12-06 00:00', $this->tz), $request->purgeAfter);
        // not visible through another section
        self::assertNull($this->requests->findByBooking(2, $id));
        self::assertSame([$id], $this->requests->bookingIds(1));
        self::assertSame([], $this->requests->bookingIds(2));
    }

    public function testAutoConfirmBooksDirectly(): void
    {
        $id = $this->id($this->submit($this->instant, $this->range('2026-11-02', '2026-11-06')));
        self::assertSame($this->statuses->findByRole(1, StatusRole::RequestConfirmed)?->id, $this->bookings->find(1, $id)?->statusId);
    }

    public function testRequestBlocksThePeriod(): void
    {
        $this->id($this->submit($this->flat, $this->range('2026-11-02', '2026-11-06')));
        self::assertSame('booking.overlap', $this->submit($this->flat, $this->range('2026-11-05', '2026-11-08'))->firstErrorKey());
        self::assertSame(1, $this->db->fetchInt('SELECT COUNT(*) FROM ' . $this->db->table('requests')));
    }

    public function testEarliestArrivalIsTomorrowInDayMode(): void
    {
        self::assertSame('request.too_early', $this->submit($this->flat, $this->range('2026-10-29', '2026-10-31'))->firstErrorKey());
        $this->id($this->submit($this->flat, $this->range('2026-10-30', '2026-11-01')));
    }

    public function testLeadTimes(): void
    {
        // two days lead count from today (29 Oct), so from 31 Oct on; at most 30 days (until 28 Nov 15:00)
        self::assertSame('request.too_early', $this->submit($this->limited, $this->range('2026-10-30', '2026-11-02'))->firstErrorKey());
        $this->id($this->submit($this->limited, $this->range('2026-10-31', '2026-11-03')));
        $this->id($this->submit($this->limited, $this->range('2026-11-28', '2026-11-30')));
        self::assertSame('request.too_late', $this->submit($this->limited, $this->range('2026-11-29', '2026-12-01'))->firstErrorKey());

        // the date picker uses the same limit; in period mode it counts from now
        $limited = $this->resources->find(1, $this->limited);
        self::assertNotNull($limited);
        self::assertEquals(new DateTimeImmutable('2026-10-31 00:00', $this->tz), $this->service->earliestStart($limited, true));
        self::assertEquals(new DateTimeImmutable('2026-10-31 15:00', $this->tz), $this->service->earliestStart($limited, false));
    }

    public function testValidation(): void
    {
        $range = $this->range('2026-11-02', '2026-11-06');
        $cases = [
            'request.disabled' => [2, $this->flat, 'Erika', 'erika@example.com', '', null, true],
            'resource.not_found' => [1, $this->inactive, 'Erika', 'erika@example.com', '', null, true],
            'request.name_required' => [1, $this->flat, '  ', 'erika@example.com', '', null, true],
            'request.name_too_long' => [1, $this->flat, str_repeat('x', 256), 'erika@example.com', '', null, true],
            'request.invalid_email' => [1, $this->flat, 'Erika', 'erika@', '', null, true],
            'request.phone_too_long' => [1, $this->flat, 'Erika', 'erika@example.com', str_repeat('1', 51), null, true],
            'request.invalid_persons' => [1, $this->flat, 'Erika', 'erika@example.com', '', 5, true],
            'request.privacy_required' => [1, $this->flat, 'Erika', 'erika@example.com', '', null, false],
        ];
        foreach ($cases as $key => [$section, $resource, $name, $email, $phone, $persons, $privacy]) {
            $result = $this->service->submit($section, $resource, $range, $name, $email, $phone, $persons, '', $privacy);
            self::assertSame($key, $result->firstErrorKey());
        }
        self::assertSame('booking.day_mode_requires_whole_days', $this->service->submit(
            1, $this->flat, TimeRange::fromLocal('2026-11-02 14:00', '2026-11-06 10:00', $this->tz), 'Erika', 'erika@example.com', '', null, '', true,
        )->firstErrorKey());
        self::assertSame(0, $this->db->fetchInt('SELECT COUNT(*) FROM ' . $this->db->table('bookings')));
    }

    public function testPersonsLimitsAndNights(): void
    {
        $type = (int) (new TypeRepository($this->db))->listBySection(1)[0]->id;
        $big = $this->resources->insert(new BookableResource(null, 1, $type, 'FeWo groß', capacity: 8, minPersons: 2, minDuration: 3, maxDuration: 7));
        $send = fn (?int $persons, string $start, string $end): Result => $this->service->submit(
            1, $big, $this->range($start, $end), 'Erika', 'erika@example.com', '', $persons, '', true,
        );

        self::assertSame('request.persons_required', $send(null, '2026-11-02', '2026-11-05')->firstErrorKey());
        $tooFew = $send(1, '2026-11-02', '2026-11-05');
        self::assertSame('request.invalid_persons', $tooFew->firstErrorKey());
        self::assertSame(['min' => 2, 'max' => 8], $tooFew->errors[0]['params']);
        self::assertSame('request.invalid_persons', $send(9, '2026-11-02', '2026-11-05')->firstErrorKey());
        self::assertSame('request.too_short_nights', $send(2, '2026-11-02', '2026-11-04')->firstErrorKey());
        self::assertSame('request.too_long_nights', $send(2, '2026-11-02', '2026-11-10')->firstErrorKey());
        // 3 and 7 nights are the limits themselves
        $this->id($send(2, '2026-11-02', '2026-11-05'));
        $this->id($send(8, '2026-11-10', '2026-11-17'));
    }

    public function testDurationInMinutesInPeriodMode(): void
    {
        $types = new TypeRepository($this->db);
        (new SectionSetup($this->db, $this->statuses, $types))->run(3, 30);
        (new SettingsRepository($this->db))->save(new Settings(3, bookingMode: BookingMode::Period, requestsEnabled: true));
        $room = $this->resources->insert(new BookableResource(null, 3, (int) $types->listBySection(3)[0]->id, 'Raum', minDuration: 60, maxDuration: 240));
        $send = fn (string $start, string $end): Result => $this->service->submit(
            3, $room, TimeRange::fromLocal($start, $end, $this->tz), 'Erika', 'erika@example.com', '', null, '', true,
        );

        self::assertSame('request.too_short_minutes', $send('2026-11-02 10:00', '2026-11-02 10:30')->firstErrorKey());
        self::assertSame('request.too_long_minutes', $send('2026-11-02 10:00', '2026-11-02 14:30')->firstErrorKey());
        $this->id($send('2026-11-02 10:00', '2026-11-02 14:00'));
    }

    public function testConfirmAndReject(): void
    {
        $a = $this->id($this->submit($this->flat, $this->range('2026-11-02', '2026-11-06')));
        $b = $this->id($this->submit($this->flat, $this->range('2026-11-10', '2026-11-12')));

        self::assertTrue($this->service->confirm(1, $a)->ok);
        self::assertSame($this->statuses->findByRole(1, StatusRole::RequestConfirmed)?->id, $this->bookings->find(1, $a)?->statusId);

        self::assertTrue($this->service->reject(1, $b)->ok);
        self::assertSame($this->statuses->findByRole(1, StatusRole::RequestRejected)?->id, $this->bookings->find(1, $b)?->statusId);
        // after a rejection the retention counts from now
        self::assertEquals($this->now->modify('+30 days'), $this->requests->findByBooking(1, $b)?->purgeAfter);
        // the rejected period is free again
        $this->id($this->submit($this->flat, $this->range('2026-11-10', '2026-11-12')));

        // decided requests stay as they are, e.g. a confirm from an older second tab
        self::assertSame('request.already_decided', $this->service->confirm(1, $b)->firstErrorKey());
        self::assertSame('request.already_decided', $this->service->reject(1, $a)->firstErrorKey());
        self::assertSame($this->statuses->findByRole(1, StatusRole::RequestRejected)?->id, $this->bookings->find(1, $b)?->statusId);
    }

    public function testChangedBookingMovesTheRetention(): void
    {
        $id = $this->id($this->submit($this->flat, $this->range('2026-12-10', '2026-12-12')));
        $booking = $this->bookings->find(1, $id);
        self::assertNotNull($booking);
        $change = function (TimeRange $range, StatusRole $role) use ($booking): ?DateTimeImmutable {
            $status = (int) $this->statuses->findByRole(1, $role)?->id;
            $this->bookings->update(new Booking($booking->id, 1, $booking->resourceId, $range, $status, createdAt: $this->now, updatedAt: $this->now));
            $this->service->bookingChanged(1, (int) $booking->id);
            return $this->requests->findByBooking(1, (int) $booking->id)?->purgeAfter;
        };

        // moved to March: kept 30 days after the new end, not after the old one (11 Jan)
        self::assertEquals(new DateTimeImmutable('2027-04-04 00:00', $this->tz), $change($this->range('2027-03-01', '2027-03-05'), StatusRole::RequestConfirmed));
        // rejected by editing the status: 30 days from now
        self::assertEquals($this->now->modify('+30 days'), $change($this->range('2027-03-01', '2027-03-05'), StatusRole::RequestRejected));
        // editing a rejected booking again does not extend the retention
        self::assertEquals($this->now->modify('+30 days'), $change($this->range('2027-06-01', '2027-06-05'), StatusRole::RequestRejected));
        // taken back into the plan: again after the end of the period
        self::assertEquals(new DateTimeImmutable('2027-07-05 00:00', $this->tz), $change($this->range('2027-06-01', '2027-06-05'), StatusRole::RequestConfirmed));

        // emptied requests and bookings without a request stay as they are
        $this->requests->setPurgeAfter((int) $booking->id, $this->now);
        $this->service->purgeExpired();
        $this->service->bookingChanged(1, (int) $booking->id);
        self::assertNull($this->requests->findByBooking(1, (int) $booking->id)?->purgeAfter);
        $this->service->bookingChanged(1, 999);
    }

    public function testChangedRetentionAppliesToStoredRequests(): void
    {
        $open = $this->id($this->submit($this->flat, $this->range('2026-12-10', '2026-12-12')));
        $rejected = $this->id($this->submit($this->flat, $this->range('2026-12-20', '2026-12-22')));
        self::assertTrue($this->service->reject(1, $rejected)->ok);

        (new SettingsRepository($this->db))->save(new Settings(1, requestsEnabled: true, retentionDays: 10));
        $this->service->retentionChanged(1);

        self::assertEquals(new DateTimeImmutable('2026-12-22 00:00', $this->tz), $this->requests->findByBooking(1, $open)?->purgeAfter);
        self::assertEquals($this->now->modify('+10 days'), $this->requests->findByBooking(1, $rejected)?->purgeAfter);
    }

    public function testDecisionNeedsARequestOfTheSection(): void
    {
        $booked = (int) $this->statuses->findByRole(1, StatusRole::RequestConfirmed)?->id;
        $plain = $this->bookings->insert(new Booking(null, 1, $this->flat, $this->range('2026-12-01', '2026-12-03'), $booked, createdAt: $this->now, updatedAt: $this->now));
        $request = $this->id($this->submit($this->flat, $this->range('2026-11-02', '2026-11-06')));

        self::assertSame('request.not_found', $this->service->confirm(1, $plain)->firstErrorKey());
        self::assertSame('request.not_found', $this->service->reject(2, $request)->firstErrorKey());
    }

    public function testPurgeEmptiesContactDataOnly(): void
    {
        $due = $this->id($this->submit($this->flat, $this->range('2026-11-02', '2026-11-06')));
        $later = $this->id($this->submit($this->flat, $this->range('2026-11-10', '2026-11-12')));
        $this->requests->setPurgeAfter($due, $this->now);

        self::assertSame(1, $this->service->purgeExpired());
        $purged = $this->requests->findByBooking(1, $due);
        self::assertSame(['', '', '', null, '', null], [$purged?->name, $purged?->email, $purged?->phone, $purged?->persons, $purged?->message, $purged?->purgeAfter]);
        self::assertNotNull($this->bookings->find(1, $due));
        self::assertSame('Erika Muster', $this->requests->findByBooking(1, $later)?->name);
        self::assertSame(0, $this->service->purgeExpired());
    }

    public function testDeletingTheBookingDeletesTheRequest(): void
    {
        $id = $this->id($this->submit($this->flat, $this->range('2026-11-02', '2026-11-06')));
        $this->bookings->delete(1, $id);
        self::assertSame(0, $this->db->fetchInt('SELECT COUNT(*) FROM ' . $this->db->table('requests')));
    }
}
