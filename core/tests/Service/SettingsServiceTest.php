<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Service;

use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\CalendarView;
use Reservations\Core\Domain\DisplayMode;
use Reservations\Core\Domain\Settings;
use Reservations\Core\Service\SettingsService;
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
    private int $flat;
    private int $foreignFlat;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db = new Connection($pdo, 'test_', new DateTimeZone('Europe/Berlin'));
        Schema::install($db);
        $types = new TypeRepository($db);
        $setup = new SectionSetup($db, new StatusRepository($db), $types);
        $setup->run(1, 10);
        $setup->run(2, 20);
        $resources = new ResourceRepository($db);
        $this->flat = $resources->insert(new BookableResource(null, 1, (int) $types->listBySection(1)[0]->id, 'FeWo Seeblick'));
        $this->foreignFlat = $resources->insert(new BookableResource(null, 2, (int) $types->listBySection(2)[0]->id, 'Fremd'));
        $this->settings = new SettingsRepository($db);
        $this->service = new SettingsService($this->settings, $resources);
    }

    public function testDefaultsAfterSetup(): void
    {
        self::assertEquals(new Settings(1), $this->settings->find(1));
    }

    public function testSaveRoundTrip(): void
    {
        $settings = new Settings(1, DisplayMode::Single, CalendarView::Month, 3, false, 0, 6, $this->flat);
        self::assertTrue($this->service->save($settings)->ok);
        self::assertEquals($settings, $this->settings->find(1));
    }

    public function testValidation(): void
    {
        $cases = [
            'settings.invalid_plan_range' => new Settings(1, planRange: 2),
            'settings.invalid_months' => new Settings(1, futureMonths: 0),
            'settings.view_not_available' => new Settings(1, DisplayMode::Single, CalendarView::Plan),
            'resource.not_found' => new Settings(1, resourceId: $this->foreignFlat),
            'settings.not_found' => new Settings(99),
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
    }
}
