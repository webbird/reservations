<?php

declare(strict_types=1);

namespace Reservations\Core\Storage\Pdo;

use Reservations\Core\Domain\Pattern;
use Reservations\Core\Domain\Status;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\Visibility;

final class StatusRepository
{
    private const TABLE = 'statuses';

    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(Status $status): int
    {
        return $this->db->insert(self::TABLE, $this->row($status));
    }

    public function update(Status $status): void
    {
        $this->db->update(self::TABLE, $this->row($status), ['status_id' => (int) $status->id, 'section_id' => $status->sectionId]);
    }

    public function delete(int $sectionId, int $statusId): void
    {
        $this->db->delete(self::TABLE, ['status_id' => $statusId, 'section_id' => $sectionId]);
    }

    public function clearRole(int $sectionId, StatusRole $role): void
    {
        $this->db->run(
            'UPDATE ' . $this->db->table(self::TABLE) . ' SET role = NULL WHERE section_id = ? AND role = ?',
            [$sectionId, $role],
        );
    }

    public function find(int $sectionId, int $statusId): ?Status
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? AND status_id = ?',
            [$sectionId, $statusId],
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByRole(int $sectionId, StatusRole $role): ?Status
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? AND role = ?',
            [$sectionId, $role],
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @return list<Status>
     */
    public function listBySection(int $sectionId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? ORDER BY position, status_id',
            [$sectionId],
        );
        return array_map($this->hydrate(...), $rows);
    }

    public function isUsed(int $sectionId, int $statusId): bool
    {
        return $this->db->fetchInt(
            'SELECT COUNT(*) FROM ' . $this->db->table('bookings') . ' WHERE section_id = ? AND status_id = ?',
            [$sectionId, $statusId],
        ) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Status $status): array
    {
        return [
            'section_id' => $status->sectionId,
            'title' => $status->title,
            'blocks' => $status->blocks,
            'visibility' => $status->visibility,
            'color' => $status->color,
            'pattern' => $status->pattern,
            'role' => $status->role,
            'active' => $status->active,
            'position' => $status->position,
        ];
    }

    /**
     * @param array<mixed> $row
     */
    private function hydrate(array $row): Status
    {
        $role = Row::nullableString($row, 'role');
        return new Status(
            id: Row::int($row, 'status_id'),
            sectionId: Row::int($row, 'section_id'),
            title: Row::string($row, 'title'),
            blocks: Row::bool($row, 'blocks'),
            visibility: Visibility::from(Row::string($row, 'visibility')),
            color: Row::string($row, 'color'),
            pattern: Pattern::from(Row::string($row, 'pattern')),
            role: $role === null ? null : StatusRole::from($role),
            active: Row::bool($row, 'active'),
            position: Row::int($row, 'position'),
        );
    }
}
