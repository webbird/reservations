<?php

declare(strict_types=1);

namespace Reservations\Core\Service;

use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\OpeningHours;
use Reservations\Core\Result\Result;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\HoursRepository;
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
    public const PRESENTATION_REF_MAX = 100;
    /** column limits; the database may run without strict mode and would clip silently */
    public const SMALLINT_MAX = 32767;
    public const INT_MAX = 2147483647;
    /** grid in period mode; each divides an hour, so a daylight saving change keeps it */
    public const SLOT_MINUTES = [5, 10, 15, 20, 30, 60];

    public function __construct(
        private readonly Connection $db,
        private readonly ResourceRepository $resources,
        private readonly TypeRepository $types,
        private readonly HoursRepository $hours,
    ) {
    }

    /**
     * Replaces the opening hours of a resource; no intervals = open around the clock.
     */
    public function saveHours(int $sectionId, int $resourceId, OpeningHours $hours): Result
    {
        if ($this->resources->find($sectionId, $resourceId) === null) {
            return Result::fail('resource.not_found', ['id' => $resourceId]);
        }
        $problem = $hours->problem();
        if ($problem !== null) {
            return Result::fail($problem, [], 'hours');
        }
        // all intervals or none: a failed insert must not leave half of the week
        return $this->db->lockedTransaction('resources', 'resource_id', $resourceId, function () use ($sectionId, $resourceId, $hours): Result {
            $this->hours->replace($sectionId, $resourceId, $hours);
            return Result::ok($resourceId);
        });
    }

    /**
     * A new resource starts with the booking rules its type presets; they replace
     * the passed values and can be changed per resource with update().
     */
    public function create(BookableResource $resource): Result
    {
        $type = $this->types->find($resource->sectionId, $resource->typeId);
        if ($type !== null) {
            $resource = $resource->withRules($type->defaults);
        }
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
        if ($resource->presentationRef !== null && mb_strlen($resource->presentationRef) > self::PRESENTATION_REF_MAX) {
            return Result::fail('resource.presentation_ref_too_long', ['max' => self::PRESENTATION_REF_MAX], 'presentation_ref');
        }
        if ($resource->color !== null && !Color::isValidHex($resource->color)) {
            return Result::fail('resource.invalid_color', ['color' => $resource->color], 'color');
        }
        if ($this->types->find($resource->sectionId, $resource->typeId) === null) {
            return Result::fail('type.not_found', ['id' => $resource->typeId], 'type');
        }
        return self::validateRules($resource);
    }

    /**
     * The booking rules of a resource: persons, lead times, durations, buffers.
     */
    public static function validateRules(BookableResource $r): ?Result
    {
        $between = static fn (?int $value, int $min, int $max): bool => $value === null || ($value >= $min && $value <= $max);
        if (!$between($r->capacity, 1, self::SMALLINT_MAX) || !$between($r->minPersons, 1, self::SMALLINT_MAX)) {
            return Result::fail('resource.invalid_persons', ['max' => self::SMALLINT_MAX], 'capacity');
        }
        if ($r->minPersons !== null && $r->capacity !== null && $r->minPersons > $r->capacity) {
            return Result::fail('resource.min_persons_above_capacity', [], 'min_persons');
        }
        if (!$between($r->leadMinMinutes, 0, self::INT_MAX) || !$between($r->leadMaxDays, 1, self::INT_MAX)) {
            return Result::fail('resource.invalid_lead', [], 'lead_min');
        }
        if (!$between($r->minDuration, 1, self::INT_MAX) || !$between($r->maxDuration, 1, self::INT_MAX)) {
            return Result::fail('resource.invalid_duration', [], 'min_duration');
        }
        if ($r->minDuration !== null && $r->maxDuration !== null && $r->minDuration > $r->maxDuration) {
            return Result::fail('resource.min_duration_above_max', [], 'min_duration');
        }
        if (!$between($r->bufferBefore, 0, self::SMALLINT_MAX) || !$between($r->bufferAfter, 0, self::SMALLINT_MAX)) {
            return Result::fail('resource.invalid_buffer', ['max' => self::SMALLINT_MAX], 'buffer_before');
        }
        if (!in_array($r->slotMinutes, self::SLOT_MINUTES, true)) {
            return Result::fail('resource.invalid_slot', [], 'slot_minutes');
        }
        return null;
    }
}
