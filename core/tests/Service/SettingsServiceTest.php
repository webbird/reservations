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
use Reservations\Core\Domain\CalendarView;
use Reservations\Core\Domain\DisplayMode;
use Reservations\Core\Domain\Settings;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Domain\Type;
use Reservations\Core\Service\SettingsService;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\SettingsRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

final class SettingsServiceTest extends TestCase
{
    private SettingsService $service;
    private SettingsRepository $settings;
    private BookingRepository $bookings;
    private ResourceRepository $resources;
    private TypeRepository $types;
    private StatusRepository $statuses;
    private DateTimeZone $tz;
    private int $flat;
    private int $foreignFlat;

    public function testAChangeOfModeEmptiesDurationsOnly(): void
    {
        $type = $this->types->listBySection(1)[0];
        $this->types->update(new Type($type->id, 1, $type->title, ['minDuration' => 2, 'maxDuration' => 14, 'capacity' => 4], $type->position));
        $flat = $this->resources->find(1, $this->flat);
        self::assertNotNull($flat);
        $this->resources->update($flat->withRules(['minDuration' => 2, 'maxDuration' => 14, 'capacity' => 4]));

        self::assertTrue($this->service->save(new Settings(1, bookingMode: BookingMode::Day, planRange: 3))->ok, 'same mode');
        self::assertSame(14, $this->resources->find(1, $this->flat)?->maxDuration);

        self::assertTrue($this->service->save(new Settings(1, bookingMode: BookingMode::Period))->ok);
        $changed = $this->resources->find(1, $this->flat);
        self::assertNull($changed?->minDuration);
        self::assertNull($changed?->maxDuration);
        self::assertSame(4, $changed?->capacity);
        self::assertSame(['capacity' => 4], $this->types->listBySection(1)[0]->defaults);
    }

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->tz = new DateTimeZone('Europe/Berlin');
        $db = new Connection($pdo, 'test_', $this->tz);
        Schema::install($db);
        $types = new TypeRepository($db);
        $this->statuses = new StatusRepository($db);
        $setup = new SectionSetup($db, $this->statuses, $types);
        $setup->run(1, 10);
        $setup->run(2, 20);
        $resources = new ResourceRepository($db);
        $this->flat = $resources->insert(new BookableResource(null, 1, (int) $types->listBySection(1)[0]->id, 'FeWo Seeblick'));
        $this->foreignFlat = $resources->insert(new BookableResource(null, 2, (int) $types->listBySection(2)[0]->id, 'Fremd'));
        $this->settings = new SettingsRepository($db);
        $this->bookings = new BookingRepository($db);
        $this->resources = $resources;
        $this->types = $types;
        $this->service = new SettingsService($this->settings, $resources, $this->bookings, $types);
    }

    public function testDefaultsAfterSetup(): void
    {
        self::assertEquals(new Settings(1), $this->settings->find(1));
    }

    public function testSaveRoundTrip(): void
    {
        $settings = new Settings(1, DisplayMode::Single, CalendarView::Month, 3, false, 0, 6, $this->flat, BookingMode::Period, 'news_img:5', true, 'office@example.com', 30, true);
        self::assertTrue($this->service->save($settings)->ok);
        self::assertEquals($settings, $this->settings->find(1));
    }

    public function testBookingModeIsLockedOnceTheSectionHasBookings(): void
    {
        $status = (int) $this->statuses->listBySection(1)[0]->id;
        $now = new DateTimeImmutable('2026-10-29 12:00', $this->tz);
        $range = TimeRange::fromLocal('2026-11-02 00:00', '2026-11-06 00:00', $this->tz);
        $this->bookings->insert(new Booking(null, 1, $this->flat, $range, $status, createdAt: $now, updatedAt: $now));

        self::assertSame('settings.mode_locked', $this->service->save(new Settings(1, bookingMode: BookingMode::Period))->firstErrorKey());
        // other settings stay editable, and section 2 has no bookings
        self::assertTrue($this->service->save(new Settings(1, planRange: 3))->ok);
        self::assertTrue($this->service->save(new Settings(2, bookingMode: BookingMode::Period))->ok);
    }

    public function testValidation(): void
    {
        $cases = [
            'settings.invalid_plan_range' => new Settings(1, planRange: 2),
            'settings.invalid_months' => new Settings(1, futureMonths: 0),
            'settings.view_not_available' => new Settings(1, DisplayMode::Single, CalendarView::Plan),
            'resource.not_found' => new Settings(1, resourceId: $this->foreignFlat),
            'settings.not_found' => new Settings(99),
            'settings.presentation_source_too_long' => new Settings(1, presentationSource: str_repeat('x', 101)),
            'settings.invalid_email' => new Settings(1, notifyEmail: 'office@'),
            'settings.invalid_retention' => new Settings(1, retentionDays: 0),
        ];
        foreach ($cases as $key => $settings) {
            self::assertSame($key, $this->service->save($settings)->firstErrorKey());
        }
        self::assertSame('settings.invalid_months', $this->service->save(new Settings(1, pastMonths: 61))->firstErrorKey());
    }

    public function testViewFallsBackToWhatFitsTheMode(): void
    {
        $single = new Settings(1, DisplayMode::Single, CalendarView::List);
        self::assertSame(CalendarView::List, $single->view(null));
        self::assertSame(CalendarView::Month, $single->view(CalendarView::Month));
        self::assertSame(CalendarView::List, $single->view(CalendarView::Plan));
        self::assertSame(CalendarView::Plan, (new Settings(1, defaultView: CalendarView::Month))->view(null));
        // the week needs times: in period mode with one or several objects, never in day mode
        self::assertSame(CalendarView::Week, (new Settings(1, bookingMode: BookingMode::Period))->view(CalendarView::Week));
        self::assertSame(CalendarView::Week, (new Settings(1, DisplayMode::Single, bookingMode: BookingMode::Period))->view(CalendarView::Week));
        self::assertSame(CalendarView::Plan, (new Settings(1))->view(CalendarView::Week));
        self::assertSame('settings.view_not_available', $this->service->save(new Settings(1, defaultView: CalendarView::Week))->firstErrorKey());
    }

    public function testForResourceShowsOneObjectAsMonth(): void
    {
        $plan = new Settings(1, planRange: 3, showLabels: false, bookingMode: BookingMode::Period, presentationSource: 'news_img:5', requestsEnabled: true, captcha: true);
        self::assertEquals(
            new Settings(1, DisplayMode::Single, CalendarView::Month, 3, false, 1, 24, 7, BookingMode::Period, 'news_img:5', true, '', 90, true),
            $plan->forResource(7),
        );
    }
}
