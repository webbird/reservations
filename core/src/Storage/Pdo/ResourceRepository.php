<?php

declare(strict_types=1);

namespace Reservations\Core\Storage\Pdo;

use Reservations\Core\Domain\BookableResource;

final class ResourceRepository
{
    private const TABLE = 'resources';

    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(BookableResource $resource): int
    {
        return $this->db->insert(self::TABLE, $this->row($resource));
    }

    public function update(BookableResource $resource): void
    {
        $this->db->update(self::TABLE, $this->row($resource), ['resource_id' => (int) $resource->id, 'section_id' => $resource->sectionId]);
    }

    /**
     * Removes a resource and all that hangs on it. Call it inside a transaction.
     * A single-object display chosen on it falls back to the first active resource.
     */
    public function delete(int $sectionId, int $resourceId): void
    {
        $where = ['section_id' => $sectionId, 'resource_id' => $resourceId];
        $this->db->run(
            'DELETE FROM ' . $this->db->table('requests') . ' WHERE booking_id IN (SELECT booking_id FROM '
            . $this->db->table('bookings') . ' WHERE section_id = ? AND resource_id = ?)',
            [$sectionId, $resourceId],
        );
        $this->db->delete('bookings', $where);
        $this->db->delete('hours', $where);
        $this->db->update('settings', ['resource_id' => null], $where);
        $this->db->delete(self::TABLE, $where);
    }

    public function clearDurations(int $sectionId): void
    {
        $this->db->update(self::TABLE, ['min_duration' => null, 'max_duration' => null], ['section_id' => $sectionId]);
    }

    public function find(int $sectionId, int $resourceId): ?BookableResource
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? AND resource_id = ?',
            [$sectionId, $resourceId],
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Across all sections, for callers that only know the id (e.g. a droplet).
     */
    public function findById(int $resourceId): ?BookableResource
    {
        $row = $this->db->fetchOne('SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE resource_id = ?', [$resourceId]);
        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * The first active resource that points to this presentation, across all sections.
     *
     * shortcut: several resources may point to the same presentation and only the first
     * is found; enforce uniqueness on save if that turns out to confuse editors.
     */
    public function findByPresentationRef(string $ref): ?BookableResource
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE presentation_ref = ? AND active = 1'
            . ' ORDER BY section_id, position, resource_id LIMIT 1',
            [$ref],
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @return list<BookableResource>
     */
    public function listBySection(int $sectionId, bool $activeOnly = false): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ?'
            . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY position, resource_id',
            [$sectionId],
        );
        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(BookableResource $r): array
    {
        return [
            'section_id' => $r->sectionId,
            'type_id' => $r->typeId,
            'title' => $r->title,
            'active' => $r->active,
            'position' => $r->position,
            'presentation_ref' => $r->presentationRef,
            'capacity' => $r->capacity,
            'min_persons' => $r->minPersons,
            'auto_confirm' => $r->autoConfirm,
            'slot_minutes' => $r->slotMinutes,
            'min_duration' => $r->minDuration,
            'max_duration' => $r->maxDuration,
            'buffer_before' => $r->bufferBefore,
            'buffer_after' => $r->bufferAfter,
            'lead_min_minutes' => $r->leadMinMinutes,
            'lead_max_days' => $r->leadMaxDays,
            'checkin_time' => $r->checkinTime,
            'checkout_time' => $r->checkoutTime,
        ];
    }

    /**
     * @param array<mixed> $row
     */
    private function hydrate(array $row): BookableResource
    {
        return new BookableResource(
            id: Row::int($row, 'resource_id'),
            sectionId: Row::int($row, 'section_id'),
            typeId: Row::int($row, 'type_id'),
            title: Row::string($row, 'title'),
            active: Row::bool($row, 'active'),
            position: Row::int($row, 'position'),
            presentationRef: Row::nullableString($row, 'presentation_ref'),
            capacity: Row::nullableInt($row, 'capacity'),
            minPersons: Row::nullableInt($row, 'min_persons'),
            autoConfirm: Row::bool($row, 'auto_confirm'),
            slotMinutes: Row::int($row, 'slot_minutes'),
            minDuration: Row::nullableInt($row, 'min_duration'),
            maxDuration: Row::nullableInt($row, 'max_duration'),
            bufferBefore: Row::int($row, 'buffer_before'),
            bufferAfter: Row::int($row, 'buffer_after'),
            leadMinMinutes: Row::int($row, 'lead_min_minutes'),
            leadMaxDays: Row::nullableInt($row, 'lead_max_days'),
            checkinTime: Row::nullableString($row, 'checkin_time'),
            checkoutTime: Row::nullableString($row, 'checkout_time'),
        );
    }
}
