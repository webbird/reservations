<?php

declare(strict_types=1);

namespace Reservations\Core\Tests\Service;

use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\Type;
use Reservations\Core\Service\TypeService;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

final class TypeServiceTest extends TestCase
{
    private TypeService $service;
    private TypeRepository $types;
    private ResourceRepository $resources;
    private int $seeded;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db = new Connection($pdo, 'test_', new DateTimeZone('Europe/Berlin'));
        Schema::install($db);
        $this->types = new TypeRepository($db);
        $setup = new SectionSetup($db, new StatusRepository($db), $this->types);
        $setup->run(1, 10);
        $setup->run(2, 20);
        $this->seeded = (int) $this->types->listBySection(1)[0]->id;
        $this->resources = new ResourceRepository($db);
        $this->service = new TypeService($db, $this->types);
    }

    public function testCreateAndUpdate(): void
    {
        $result = $this->service->create(new Type(null, 1, 'Ferienwohnung'));
        self::assertTrue($result->ok);
        self::assertIsInt($result->value);

        self::assertTrue($this->service->update(new Type($result->value, 1, 'Ferienhaus', position: 2))->ok);
        self::assertSame('Ferienhaus', $this->types->find(1, $result->value)?->title);
    }

    public function testValidation(): void
    {
        self::assertSame('type.title_required', $this->service->create(new Type(null, 1, '  '))->firstErrorKey());
        self::assertSame('type.title_too_long', $this->service->create(new Type(null, 1, str_repeat('ä', 101)))->firstErrorKey());
        // a type of another section is not found
        self::assertSame('type.not_found', $this->service->update(new Type($this->seeded, 2, 'Fremd'))->firstErrorKey());
    }

    public function testDeleteKeepsUsedAndLastTypes(): void
    {
        self::assertSame('type.last_one', $this->service->delete(1, $this->seeded)->firstErrorKey());

        $second = $this->service->create(new Type(null, 1, 'Raum'))->value;
        self::assertIsInt($second);
        $this->resources->insert(new BookableResource(null, 1, $second, 'Besprechungsraum'));
        self::assertSame('type.in_use', $this->service->delete(1, $second)->firstErrorKey());

        self::assertTrue($this->service->delete(1, $this->seeded)->ok);
        self::assertNull($this->types->find(1, $this->seeded));
        self::assertSame('type.not_found', $this->service->delete(2, $second)->firstErrorKey());
    }
}
