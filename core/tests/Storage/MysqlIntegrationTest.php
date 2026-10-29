<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Storage;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Port\Clock;
use Reservations\Core\Service\BookingService;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\SettingsRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

/**
 * Runs the MySQL-specific paths (DDL, ALTER TABLE, SELECT … FOR UPDATE) against a
 * real server. Skipped unless RESERVATIONS_TEST_MYSQL_DSN is set, e.g.
 * "mysql:host=127.0.0.1;port=3306;dbname=test;charset=utf8mb4", plus
 * RESERVATIONS_TEST_MYSQL_USER and RESERVATIONS_TEST_MYSQL_PASSWORD.
 * Uses a random table prefix and drops its tables afterwards.
 */
final class MysqlIntegrationTest extends TestCase
{
    private ?Connection $db = null;
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        $dsn = getenv('RESERVATIONS_TEST_MYSQL_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('RESERVATIONS_TEST_MYSQL_DSN is not set.');
        }
        $user = getenv('RESERVATIONS_TEST_MYSQL_USER');
        $password = getenv('RESERVATIONS_TEST_MYSQL_PASSWORD');
        $pdo = new PDO($dsn, is_string($user) ? $user : null, is_string($password) ? $password : null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // same as WBCE 1.7: no strict mode
        $pdo->exec("SET @@sql_mode = ''");
        $this->tz = new DateTimeZone('Europe/Berlin');
        $this->db = new Connection($pdo, 'rsvtest_' . bin2hex(random_bytes(3)) . '_', $this->tz);
        Schema::install($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            Schema::uninstall($this->db);
        }
    }

    private function db(): Connection
    {
        self::assertNotNull($this->db);
        return $this->db;
    }

    public function testInstallAddsColumnsMissingInOlderTables(): void
    {
        $this->db()->exec('ALTER TABLE ' . $this->db()->table('settings') . ' DROP COLUMN resource_id');
        self::assertNotContains('resource_id', $this->db()->columns('settings'));

        Schema::install($this->db());
        self::assertContains('resource_id', $this->db()->columns('settings'));
    }

    public function testInstallRemovesDroppedColumns(): void
    {
        $this->db()->exec('ALTER TABLE ' . $this->db()->table('types') . " ADD COLUMN booking_mode VARCHAR(20) NOT NULL DEFAULT 'day'");
        self::assertContains('booking_mode', $this->db()->columns('types'));

        Schema::install($this->db());
        self::assertNotContains('booking_mode', $this->db()->columns('types'));
    }

    public function testOverlapCheckUnderRowLock(): void
    {
        $db = $this->db();
        $statuses = new StatusRepository($db);
        $types = new TypeRepository($db);
        (new SectionSetup($db, $statuses, $types))->run(1, 10);
        $resources = new ResourceRepository($db);
        $flat = $resources->insert(new BookableResource(null, 1, (int) $types->listBySection(1)[0]->id, 'FeWo Seeblick'));
        $booked = (int) $statuses->findByRole(1, StatusRole::RequestConfirmed)?->id;
        $clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-29 12:00', new DateTimeZone('Europe/Berlin'));
            }
        };
        $service = new BookingService($db, $resources, new SettingsRepository($db), $statuses, new BookingRepository($db), $clock);
        $range = fn (string $start, string $end): TimeRange => TimeRange::fromLocal($start, $end, $this->tz);

        self::assertTrue($service->create(1, $flat, $range('2026-11-02 00:00', '2026-11-06 00:00'), $booked)->ok);
        self::assertSame('booking.overlap', $service->create(1, $flat, $range('2026-11-05 00:00', '2026-11-08 00:00'), $booked)->firstErrorKey());
        self::assertTrue($service->create(1, $flat, $range('2026-11-06 00:00', '2026-11-08 00:00'), $booked)->ok);
    }

    public function testLockedTransactionRollsBackOnError(): void
    {
        $db = $this->db();
        $db->insert('settings', ['section_id' => 1, 'page_id' => 10]);
        try {
            $db->lockedTransaction('settings', 'section_id', 1, static function () use ($db): void {
                $db->update('settings', ['plan_range' => 3], ['section_id' => 1]);
                throw new \RuntimeException('abort');
            });
        } catch (\RuntimeException $e) {
            self::assertSame('abort', $e->getMessage());
        }
        self::assertSame(1, $db->fetchInt('SELECT plan_range FROM ' . $db->table('settings') . ' WHERE section_id = 1'));
    }
}
