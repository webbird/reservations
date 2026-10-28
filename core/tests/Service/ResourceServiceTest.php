<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Service;

use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Service\ResourceService;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

final class ResourceServiceTest extends TestCase
{
    private ResourceService $service;
    private ResourceRepository $resources;
    private int $type;
    private int $foreignType;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db = new Connection($pdo, 'test_', new DateTimeZone('Europe/Berlin'));
        Schema::install($db);
        $types = new TypeRepository($db);
        $setup = new SectionSetup($db, new StatusRepository($db), $types);
        $setup->run(1, 10);
        $setup->run(2, 20);
        $this->type = (int) $types->listBySection(1)[0]->id;
        $this->foreignType = (int) $types->listBySection(2)[0]->id;
        $this->resources = new ResourceRepository($db);
        $this->service = new ResourceService($this->resources, $types);
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
        self::assertSame('resource.invalid_color', $this->service->create(new BookableResource(null, 1, $this->type, 'X', 'blue'))->firstErrorKey());
        self::assertSame('type.not_found', $this->service->create(new BookableResource(null, 1, $this->foreignType, 'X'))->firstErrorKey());
    }

    public function testUpdateOfForeignResourceFails(): void
    {
        $id = $this->resources->insert(new BookableResource(null, 2, $this->foreignType, 'Fremd'));
        self::assertSame('resource.not_found', $this->service->update(new BookableResource($id, 1, $this->type, 'X'))->firstErrorKey());
    }
}
