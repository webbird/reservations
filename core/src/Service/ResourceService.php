<?php

declare(strict_types=1);

namespace Reservations\Core\Service;

use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Result\Result;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Support\Color;

/**
 * Creates and changes resources. Validates lengths itself because the database
 * may run without strict mode and would silently truncate.
 */
final class ResourceService
{
    public const TITLE_MAX = 255;

    public function __construct(
        private readonly ResourceRepository $resources,
        private readonly TypeRepository $types,
    ) {
    }

    public function create(BookableResource $resource): Result
    {
        return $this->validate($resource) ?? Result::ok($this->resources->insert($resource));
    }

    public function update(BookableResource $resource): Result
    {
        if ($resource->id === null || $this->resources->find($resource->sectionId, $resource->id) === null) {
            return Result::fail('resource.not_found', ['id' => $resource->id]);
        }
        $invalid = $this->validate($resource);
        if ($invalid !== null) {
            return $invalid;
        }
        $this->resources->update($resource);
        return Result::ok($resource->id);
    }

    private function validate(BookableResource $resource): ?Result
    {
        $title = trim($resource->title);
        if ($title === '') {
            return Result::fail('resource.title_required', [], 'title');
        }
        if (mb_strlen($title) > self::TITLE_MAX) {
            return Result::fail('resource.title_too_long', ['max' => self::TITLE_MAX], 'title');
        }
        if ($resource->color !== null && !Color::isValidHex($resource->color)) {
            return Result::fail('resource.invalid_color', ['color' => $resource->color], 'color');
        }
        if ($this->types->find($resource->sectionId, $resource->typeId) === null) {
            return Result::fail('type.not_found', ['id' => $resource->typeId], 'type');
        }
        return null;
    }
}
