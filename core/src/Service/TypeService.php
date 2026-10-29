<?php

declare(strict_types=1);

namespace Reservations\Core\Service;

use Reservations\Core\Domain\Type;
use Reservations\Core\Result\Result;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\TypeRepository;

/**
 * Maintains the object types of a section. A type in use cannot be deleted,
 * and every section keeps at least one type, so new objects always have one.
 */
final class TypeService
{
    public const TITLE_MAX = 100;

    public function __construct(
        private readonly Connection $db,
        private readonly TypeRepository $types,
    ) {
    }

    public function create(Type $type): Result
    {
        return $this->validate($type) ?? Result::ok($this->types->insert($type));
    }

    public function update(Type $type): Result
    {
        if ($type->id === null || $this->types->find($type->sectionId, $type->id) === null) {
            return Result::fail('type.not_found', ['id' => $type->id]);
        }
        $invalid = $this->validate($type);
        if ($invalid !== null) {
            return $invalid;
        }
        $this->types->update($type);
        return Result::ok($type->id);
    }

    /**
     * shortcut: an object created with this type while it is being deleted is not
     * prevented (no lock on resources); it then shows an unknown type but stays
     * bookable, since the mode belongs to the section. Lock if several admins edit at once.
     */
    public function delete(int $sectionId, int $typeId): Result
    {
        if ($this->types->find($sectionId, $typeId) === null) {
            return Result::fail('type.not_found', ['id' => $typeId]);
        }
        if ($this->types->isUsed($sectionId, $typeId)) {
            return Result::fail('type.in_use', ['id' => $typeId]);
        }
        // the lock keeps two concurrent deletes from removing the last two types
        return $this->db->lockedTransaction('settings', 'section_id', $sectionId, function () use ($sectionId, $typeId): Result {
            if (count($this->types->listBySection($sectionId)) <= 1) {
                return Result::fail('type.last_one');
            }
            $this->types->delete($sectionId, $typeId);
            return Result::ok();
        });
    }

    private function validate(Type $type): ?Result
    {
        $title = trim($type->title);
        if ($title === '') {
            return Result::fail('type.title_required', [], 'title');
        }
        // the database may run without strict mode and would truncate silently
        if (mb_strlen($title) > self::TITLE_MAX) {
            return Result::fail('type.title_too_long', ['max' => self::TITLE_MAX], 'title');
        }
        return null;
    }
}
