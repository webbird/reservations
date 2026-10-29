<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Storage;

use DateTimeZone;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\Pattern;
use Reservations\Core\Domain\Status;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\Visibility;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

final class SchemaTest extends TestCase
{
    private Connection $db;
    private StatusRepository $statuses;
    private SectionSetup $setup;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db = new Connection($pdo, 'test_mod_reservations_', new DateTimeZone('Europe/Berlin'));
        $this->statuses = new StatusRepository($this->db);
        $this->setup = new SectionSetup($this->db, $this->statuses, new TypeRepository($this->db));
    }

    public function testInstallCreatesAllTablesOnce(): void
    {
        self::assertSame(['settings', 'types', 'statuses', 'resources', 'hours', 'bookings', 'requests'], Schema::install($this->db));
        self::assertSame([], Schema::install($this->db));
    }

    public function testInstallAddsColumnsMissingInOlderTables(): void
    {
        $old = Schema::tables()['settings'];
        unset($old['columns']['resource_id']);
        foreach (Schema::statements('sqlite', $this->db->table('settings'), $old) as $sql) {
            $this->db->exec($sql);
        }
        $this->db->insert('settings', ['section_id' => 1, 'page_id' => 10]);

        self::assertNotContains('settings', Schema::install($this->db));
        self::assertContains('resource_id', $this->db->columns('settings'));
        self::assertNull($this->db->fetchValue('SELECT resource_id FROM ' . $this->db->table('settings')));
    }

    public function testInstallRemovesDroppedColumns(): void
    {
        $old = Schema::tables()['types'];
        $old['columns']['booking_mode'] = "str:20='day'";
        foreach (Schema::statements('sqlite', $this->db->table('types'), $old) as $sql) {
            $this->db->exec($sql);
        }
        $version = $this->db->fetchValue('SELECT sqlite_version()');
        if (!is_string($version) || version_compare($version, '3.35.0', '<')) {
            self::markTestSkipped('SQLite ' . (is_string($version) ? $version : '?') . ' cannot drop columns.');
        }

        Schema::install($this->db);
        self::assertNotContains('booking_mode', $this->db->columns('types'));
    }

    public function testUninstallDropsTables(): void
    {
        Schema::install($this->db);
        Schema::uninstall($this->db);
        self::assertFalse($this->db->tableExists('bookings'));
    }

    public function testMysqlDdlRendersPlatformSpecifics(): void
    {
        $sql = implode("\n", Schema::statements('mysql', 'wbce_mod_reservations_bookings', Schema::tables()['bookings']));
        self::assertStringContainsString('AUTO_INCREMENT', $sql);
        self::assertStringContainsString('ENGINE=InnoDB', $sql);
        self::assertStringContainsString('start_at DATETIME NOT NULL', $sql);
        self::assertStringContainsString('CREATE INDEX wbce_mod_reservations_bookings_range', $sql);
        self::assertStringNotContainsString('ENUM', $sql);
    }

    public function testConnectionRejectsSilentErrorMode(): void
    {
        $this->expectException(\LogicException::class);
        new Connection(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]), 'x_');
    }

    public function testSectionSetupSeedsDefaultsOnce(): void
    {
        Schema::install($this->db);
        self::assertTrue($this->setup->run(1, 10, ['booked' => 'gebucht']));
        self::assertFalse($this->setup->run(1, 10));

        $statuses = $this->statuses->listBySection(1);
        self::assertCount(6, $statuses);
        self::assertSame('gebucht', $this->statuses->findByRole(1, StatusRole::RequestConfirmed)?->title);
        foreach (StatusRole::cases() as $role) {
            self::assertNotNull($this->statuses->findByRole(1, $role), $role->value);
        }
        self::assertCount(1, (new TypeRepository($this->db))->listBySection(1));
        self::assertSame(1, $this->db->fetchInt('SELECT COUNT(*) FROM ' . $this->db->table('settings')));
    }

    public function testRoleIsUniquePerSectionInTheDatabase(): void
    {
        Schema::install($this->db);
        $this->setup->run(1, 10);
        $this->setup->run(2, 20);
        $this->expectException(PDOException::class);
        $this->statuses->insert(new Status(null, 1, 'Second', true, Visibility::Label, '#000000', Pattern::Solid, StatusRole::RequestNew));
    }

    public function testRemoveClearsOnlyThatSection(): void
    {
        Schema::install($this->db);
        $this->setup->run(1, 10);
        $this->setup->run(2, 20);
        $this->setup->remove(1);
        self::assertCount(0, $this->statuses->listBySection(1));
        self::assertCount(6, $this->statuses->listBySection(2));
    }
}
