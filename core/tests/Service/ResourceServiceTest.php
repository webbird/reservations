<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\OpeningHours;
use Reservations\Core\Domain\Request;
use Reservations\Core\Domain\Status;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Domain\Type;
use Reservations\Core\Domain\Visibility;
use Reservations\Core\Port\Clock;
use Reservations\Core\Service\ResourceService;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\HoursRepository;
use Reservations\Core\Storage\Pdo\RequestRepository;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

final class ResourceServiceTest extends TestCase
{
    private ResourceService $service;
    private ResourceRepository $resources;
    private TypeRepository $types;
    private HoursRepository $hours;
    private Connection $db;
    private StatusRepository $statuses;
    private BookingRepository $bookings;
    private int $type;
    private int $foreignType;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db = new Connection($pdo, 'test_', new DateTimeZone('Europe/Berlin'));
        Schema::install($db);
        $this->types = new TypeRepository($db);
        $setup = new SectionSetup($db, new StatusRepository($db), $this->types);
        $setup->run(1, 10);
        $setup->run(2, 20);
        $this->type = (int) $this->types->listBySection(1)[0]->id;
        $this->foreignType = (int) $this->types->listBySection(2)[0]->id;
        $this->resources = new ResourceRepository($db);
        $this->hours = new HoursRepository($db);
        $this->db = $db;
        $this->statuses = new StatusRepository($db);
        $this->bookings = new BookingRepository($db);
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-05 12:00', new DateTimeZone('Europe/Berlin'));
            }
        };
        $this->service = new ResourceService($db, $this->resources, $this->types, $this->hours, $this->bookings, $clock);
    }

    private function book(int $resource, string $start, string $end, ?StatusRole $role = null, string $title = ''): int
    {
        $statuses = $this->statuses->listBySection(1);
        $status = $title !== '' ? array_values(array_filter($statuses, static fn (Status $s): bool => $s->title === $title))[0] : $this->statuses->findByRole(1, $role ?? StatusRole::RequestConfirmed);
        $tz = new DateTimeZone('Europe/Berlin');
        $now = new DateTimeImmutable('2026-01-01 12:00', $tz);
        return $this->bookings->insert(new Booking(null, 1, $resource, TimeRange::fromLocal($start, $end, $tz), (int) $status?->id, createdAt: $now, updatedAt: $now));
    }

    public function testDeleteRemovesPastBookingsRequestsAndHours(): void
    {
        $id = $this->service->create(new BookableResource(null, 1, $this->type, 'Raum'))->value;
        $other = $this->service->create(new BookableResource(null, 1, $this->type, 'Anderer Raum'))->value;
        self::assertIsInt($id);
        self::assertIsInt($other);
        // over, or cancelled and rejected ones in the future: none of them keeps the object
        $past = $this->book($id, '2026-10-01 00:00', '2026-10-03 00:00');
        $this->book($id, '2026-12-01 00:00', '2026-12-03 00:00', title: 'Cancelled');
        $this->book($id, '2026-12-05 00:00', '2026-12-07 00:00', StatusRole::RequestRejected);
        $kept = $this->book($other, '2026-10-01 00:00', '2026-10-03 00:00');
        $requests = new RequestRepository($this->db);
        $when = new DateTimeImmutable('2026-01-01 12:00', new DateTimeZone('Europe/Berlin'));
        $requests->insert(new Request(null, $past, 'Gast', 'gast@example.com', privacyAcceptedAt: $when, createdAt: $when, purgeAfter: $when));
        $requests->insert(new Request(null, $kept, 'Gast', 'gast@example.com', privacyAcceptedAt: $when, createdAt: $when, purgeAfter: $when));
        $this->service->saveHours(1, $id, new OpeningHours([1 => [[540, 1020]]]));
        $this->db->update('settings', ['resource_id' => $id], ['section_id' => 1]);

        // a foreign section cannot delete it
        self::assertSame('resource.not_found', $this->service->delete(2, $id)->firstErrorKey());
        self::assertTrue($this->service->delete(1, $id)->ok);

        self::assertNull($this->resources->find(1, $id));
        self::assertNull($this->bookings->find(1, $past));
        self::assertNull($requests->findByBooking(1, $past));
        self::assertTrue($this->hours->find(1, $id)->isAlwaysOpen());
        self::assertNull($this->db->fetchValue('SELECT resource_id FROM test_settings WHERE section_id = 1'));
        // the other object keeps everything
        self::assertNotNull($this->bookings->find(1, $kept));
        self::assertNotNull($requests->findByBooking(1, $kept));
    }

    public function testRunningAndFutureBookingsAndOpenRequestsKeepTheObject(): void
    {
        $cases = [
            'running' => ['2026-11-04 00:00', '2026-11-06 00:00', null],
            'future' => ['2026-12-01 00:00', '2026-12-03 00:00', null],
            'open request' => ['2026-12-01 00:00', '2026-12-03 00:00', StatusRole::RequestNew],
        ];
        foreach ($cases as $name => [$start, $end, $role]) {
            $id = $this->service->create(new BookableResource(null, 1, $this->type, $name))->value;
            self::assertIsInt($id);
            $booking = $this->book($id, $start, $end, $role);
            self::assertSame('resource.in_use', $this->service->delete(1, $id)->firstErrorKey(), $name);
            self::assertNotNull($this->resources->find(1, $id), $name);
            self::assertNotNull($this->bookings->find(1, $booking), $name);
        }
    }

    public function testCheckTimes(): void
    {
        $base = new BookableResource(null, 1, $this->type, 'FeWo');
        foreach (['15:00', '15:00:00', '00:00', '23:59'] as $time) {
            self::assertTrue($this->service->create($base->withRules(['checkinTime' => $time]))->ok, $time);
        }
        foreach (['24:00', '9:00', '15', 'nachmittags', '15:00; DROP'] as $time) {
            self::assertSame('resource.invalid_time', $this->service->create($base->withRules(['checkinTime' => $time]))->firstErrorKey(), $time);
            self::assertSame('resource.invalid_time', $this->service->create($base->withRules(['checkoutTime' => $time]))->firstErrorKey(), $time);
        }
        // a type presets them for new objects
        $type = $this->types->listBySection(1)[0];
        $this->types->update(new Type($type->id, 1, $type->title, ['checkinTime' => '15:00', 'checkoutTime' => '10:00'], 0));
        $id = $this->service->create($base)->value;
        self::assertIsInt($id);
        $stored = $this->resources->find(1, $id);
        self::assertSame(['15:00', '10:00'], [$stored?->checkinTime, $stored?->checkoutTime]);
    }

    public function testNewSectionShowsRequests(): void
    {
        // a blocked period that looks free would turn requests down without a reason
        self::assertSame(Visibility::Label, $this->statuses->findByRole(1, StatusRole::RequestNew)?->visibility);
    }

    public function testOpeningHours(): void
    {
        $id = $this->service->create(new BookableResource(null, 1, $this->type, 'Raum'))->value;
        self::assertIsInt($id);
        // Monday with lunch break (unsorted on purpose), Saturday until midnight
        $hours = new OpeningHours([1 => [[13 * 60, 17 * 60], [9 * 60, 12 * 60]], 6 => [[18 * 60, OpeningHours::DAY_END]]]);
        self::assertTrue($this->service->saveHours(1, $id, $hours)->ok);
        self::assertSame([1 => [[540, 720], [780, 1020]], 6 => [[1080, 1440]]], $this->hours->find(1, $id)->days);
        // another section neither sees nor changes them
        self::assertTrue($this->hours->find(2, $id)->isAlwaysOpen());
        self::assertSame('resource.not_found', $this->service->saveHours(2, $id, new OpeningHours())->firstErrorKey());

        $invalid = [
            'hours.invalid_weekday' => [8 => [[540, 600]]],
            'hours.invalid_interval' => [1 => [[600, 540]]],
            'hours.overlap' => [1 => [[540, 720], [700, 800]]],
        ];
        foreach ($invalid as $key => $days) {
            self::assertSame($key, $this->service->saveHours(1, $id, new OpeningHours($days))->firstErrorKey());
        }
        // a failed save keeps the stored hours; an empty one means around the clock
        self::assertCount(2, $this->hours->find(1, $id)->days);
        self::assertTrue($this->service->saveHours(1, $id, new OpeningHours())->ok);
        self::assertTrue($this->hours->find(1, $id)->isAlwaysOpen());
    }

    public function testCreateAndUpdate(): void
    {
        $result = $this->service->create(new BookableResource(null, 1, $this->type, 'FeWo Seeblick'));
        self::assertTrue($result->ok);
        self::assertIsInt($result->value);

        self::assertTrue($this->service->update(new BookableResource($result->value, 1, $this->type, 'FeWo Bergblick', active: false))->ok);
        self::assertSame('FeWo Bergblick', $this->resources->find(1, $result->value)?->title);
    }

    public function testValidation(): void
    {
        self::assertSame('resource.title_required', $this->service->create(new BookableResource(null, 1, $this->type, '  '))->firstErrorKey());
        self::assertSame('resource.title_too_long', $this->service->create(new BookableResource(null, 1, $this->type, str_repeat('ü', 256)))->firstErrorKey());
        self::assertSame('type.not_found', $this->service->create(new BookableResource(null, 1, $this->foreignType, 'X'))->firstErrorKey());
        self::assertSame(
            'resource.presentation_ref_too_long',
            $this->service->create(new BookableResource(null, 1, $this->type, 'X', presentationRef: str_repeat('x', 101)))->firstErrorKey(),
        );
    }

    public function testBookingRules(): void
    {
        $cases = [
            'resource.invalid_persons' => new BookableResource(null, 1, $this->type, 'X', capacity: 0),
            'resource.min_persons_above_capacity' => new BookableResource(null, 1, $this->type, 'X', capacity: 4, minPersons: 5),
            'resource.invalid_lead' => new BookableResource(null, 1, $this->type, 'X', leadMaxDays: 0),
            'resource.invalid_duration' => new BookableResource(null, 1, $this->type, 'X', minDuration: 0),
            'resource.min_duration_above_max' => new BookableResource(null, 1, $this->type, 'X', minDuration: 7, maxDuration: 3),
            'resource.invalid_buffer' => new BookableResource(null, 1, $this->type, 'X', bufferAfter: 40000),
            'resource.invalid_slot' => new BookableResource(null, 1, $this->type, 'X', slotMinutes: 7),
        ];
        foreach ($cases as $key => $resource) {
            self::assertSame($key, $this->service->create($resource)->firstErrorKey());
        }

        $result = $this->service->create(new BookableResource(null, 1, $this->type, 'FeWo', capacity: 8, minPersons: 2, minDuration: 3, maxDuration: 21, leadMinMinutes: 1440, leadMaxDays: 365));
        self::assertTrue($result->ok);
        self::assertIsInt($result->value);
        $stored = $this->resources->find(1, $result->value);
        self::assertSame([2, 8, 3, 21, 1440, 365], [$stored?->minPersons, $stored?->capacity, $stored?->minDuration, $stored?->maxDuration, $stored?->leadMinMinutes, $stored?->leadMaxDays]);
    }

    public function testNewResourceTakesTheRulesOfItsType(): void
    {
        $type = $this->types->insert(new Type(null, 1, 'Ferienhaus', ['capacity' => 8, 'minPersons' => 2, 'autoConfirm' => true, 'minDuration' => 3]));
        $result = $this->service->create(new BookableResource(null, 1, $type, 'Haus am See', capacity: 4, leadMaxDays: 365));
        self::assertIsInt($result->value);
        $stored = $this->resources->find(1, $result->value);
        // preset rules replace the passed ones, the others stay
        self::assertSame([8, 2, true, 3, 365], [$stored?->capacity, $stored?->minPersons, $stored?->autoConfirm, $stored?->minDuration, $stored?->leadMaxDays]);

        // afterwards the resource keeps its own rules, a changed type does not reach it
        self::assertTrue($this->service->update(new BookableResource($result->value, 1, $type, 'Haus am See', capacity: 6))->ok);
        self::assertSame(6, $this->resources->find(1, $result->value)?->capacity);
    }

    public function testFindAcrossSections(): void
    {
        $inactive = $this->resources->insert(new BookableResource(null, 1, $this->type, 'Alt', active: false, presentationRef: 'news_img:12'));
        $flat = $this->resources->insert(new BookableResource(null, 2, $this->foreignType, 'FeWo', presentationRef: 'news_img:12'));

        self::assertSame('Alt', $this->resources->findById($inactive)?->title);
        self::assertNull($this->resources->findById(999));
        // inactive objects are skipped, the section does not matter
        self::assertSame($flat, $this->resources->findByPresentationRef('news_img:12')?->id);
        self::assertNull($this->resources->findByPresentationRef('news_img:13'));
    }

    public function testUpdateOfForeignResourceFails(): void
    {
        $id = $this->resources->insert(new BookableResource(null, 2, $this->foreignType, 'Fremd'));
        self::assertSame('resource.not_found', $this->service->update(new BookableResource($id, 1, $this->type, 'X'))->firstErrorKey());
    }
}
