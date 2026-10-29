<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\Pattern;
use Reservations\Core\Domain\Status;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Domain\Visibility;
use Reservations\Core\Service\StatusService;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;
use Reservations\Core\Support\Color;

final class StatusServiceTest extends TestCase
{
    private DateTimeZone $tz;
    private StatusService $service;
    private StatusRepository $statuses;
    private BookingRepository $bookings;
    private int $flat;

    protected function setUp(): void
    {
        $this->tz = new DateTimeZone('Europe/Berlin');
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db = new Connection($pdo, 'test_', $this->tz);
        Schema::install($db);
        $this->statuses = new StatusRepository($db);
        $types = new TypeRepository($db);
        (new SectionSetup($db, $this->statuses, $types))->run(1, 10);
        $resources = new ResourceRepository($db);
        $this->flat = $resources->insert(new BookableResource(null, 1, (int) $types->listBySection(1)[0]->id, 'FeWo Seeblick'));
        $this->bookings = new BookingRepository($db);
        $this->service = new StatusService($db, $this->statuses, $this->bookings, $resources);
    }

    private function byTitle(string $title): Status
    {
        foreach ($this->statuses->listBySection(1) as $status) {
            if ($status->title === $title) {
                return $status;
            }
        }
        self::fail("Status $title not found");
    }

    /**
     * @param array{title?: string, blocks?: bool, color?: string, role?: ?StatusRole, active?: bool} $changes
     */
    private function with(Status $s, array $changes): Status
    {
        return new Status(
            $s->id,
            $s->sectionId,
            $changes['title'] ?? $s->title,
            $changes['blocks'] ?? $s->blocks,
            $s->visibility,
            $changes['color'] ?? $s->color,
            $s->pattern,
            array_key_exists('role', $changes) ? $changes['role'] : $s->role,
            $changes['active'] ?? $s->active,
            $s->position,
        );
    }

    private function book(string $start, string $end, int $statusId): int
    {
        $now = new DateTimeImmutable('2026-10-27 12:00', $this->tz);
        return $this->bookings->insert(new Booking(null, 1, $this->flat, TimeRange::fromLocal($start, $end, $this->tz), $statusId, createdAt: $now, updatedAt: $now));
    }

    public function testAssigningARoleMovesItFromTheOldStatus(): void
    {
        $result = $this->service->create(new Status(null, 1, 'Fest gebucht', true, Visibility::Label, '#0c447c', Pattern::Solid, StatusRole::RequestConfirmed));
        self::assertTrue($result->ok);
        self::assertSame($result->value, $this->statuses->findByRole(1, StatusRole::RequestConfirmed)?->id);
        self::assertNull($this->byTitle('Booked')->role);
    }

    public function testRoleCannotBeRemovedWithoutReassigning(): void
    {
        $booked = $this->byTitle('Booked');
        $result = $this->service->update($this->with($booked, ['role' => null]));
        self::assertSame('status.role_required', $result->firstErrorKey());
    }

    public function testStatusWithRoleCannotBeDeactivated(): void
    {
        $result = $this->service->update($this->with($this->byTitle('Booked'), ['active' => false]));
        self::assertSame('status.role_needs_active', $result->firstErrorKey());
    }

    public function testRoleCanBeTakenOverViaUpdate(): void
    {
        $reserved = $this->byTitle('Reserved');
        self::assertTrue($this->service->update($this->with($reserved, ['role' => StatusRole::RequestConfirmed]))->ok);
        self::assertSame($reserved->id, $this->statuses->findByRole(1, StatusRole::RequestConfirmed)?->id);
    }

    public function testDeleteRules(): void
    {
        self::assertSame('status.has_role', $this->service->delete(1, (int) $this->byTitle('Booked')->id)->firstErrorKey());

        $reserved = (int) $this->byTitle('Reserved')->id;
        $this->book('2026-11-02 00:00', '2026-11-04 00:00', $reserved);
        self::assertSame('status.in_use', $this->service->delete(1, $reserved)->firstErrorKey());

        $cancelled = (int) $this->byTitle('Cancelled')->id;
        self::assertTrue($this->service->delete(1, $cancelled)->ok);
        self::assertNull($this->statuses->find(1, $cancelled));
    }

    public function testSwitchingToBlockingIsRefusedWhenItCreatesOverlaps(): void
    {
        $booked = (int) $this->byTitle('Booked')->id;
        $cancelledStatus = $this->byTitle('Cancelled');
        $a = $this->book('2026-11-02 00:00', '2026-11-06 00:00', $booked);
        $b = $this->book('2026-11-04 00:00', '2026-11-08 00:00', (int) $cancelledStatus->id);
        $this->book('2026-11-06 00:00', '2026-11-08 00:00', $booked);

        $result = $this->service->update($this->with($cancelledStatus, ['blocks' => true]));
        self::assertSame('status.blocking_conflicts', $result->firstErrorKey());
        $conflicts = $result->errors[0]['params']['conflicts'] ?? null;
        self::assertIsArray($conflicts);
        self::assertContains([min($a, $b), max($a, $b)], $conflicts);
        self::assertFalse($this->statuses->find(1, (int) $cancelledStatus->id)?->blocks);
    }

    public function testSwitchingToBlockingWorksWithoutOverlaps(): void
    {
        $cancelledStatus = $this->byTitle('Cancelled');
        $this->book('2026-11-02 00:00', '2026-11-06 00:00', (int) $this->byTitle('Booked')->id);
        $this->book('2026-11-06 00:00', '2026-11-08 00:00', (int) $cancelledStatus->id);
        self::assertTrue($this->service->update($this->with($cancelledStatus, ['blocks' => true]))->ok);
        self::assertTrue($this->statuses->find(1, (int) $cancelledStatus->id)?->blocks);
    }

    public function testValidation(): void
    {
        $status = new Status(null, 1, ' ', true, Visibility::Label, '#123456', Pattern::Solid);
        self::assertSame('status.title_required', $this->service->create($status)->firstErrorKey());
        self::assertSame('status.title_too_long', $this->service->create($this->with($status, ['title' => str_repeat('ö', 101)]))->firstErrorKey());
        self::assertSame('status.invalid_color', $this->service->create($this->with($status, ['title' => 'X', 'color' => 'red']))->firstErrorKey());
    }

    public function testDefaultStatusColorsGetReadableText(): void
    {
        foreach ($this->statuses->listBySection(1) as $status) {
            $ratio = Color::contrastRatio($status->color, Color::contrastText($status->color));
            self::assertGreaterThanOrEqual(4.5, $ratio, $status->title . ' ' . $status->color);
        }
        self::assertSame('#ffffff', Color::contrastText('#185fa5'));
        self::assertSame('#000000', Color::contrastText('#f5c26b'));
    }
}
