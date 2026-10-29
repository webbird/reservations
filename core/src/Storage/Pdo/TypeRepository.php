<?php

declare(strict_types=1);

namespace Reservations\Core\Storage\Pdo;

use Reservations\Core\Domain\Type;

final class TypeRepository
{
    private const TABLE = 'types';

    public function __construct(private readonly Connection $db)
    {
    }

    public function insert(Type $type): int
    {
        return $this->db->insert(self::TABLE, $this->row($type));
    }

    public function update(Type $type): void
    {
        $this->db->update(self::TABLE, $this->row($type), ['type_id' => (int) $type->id, 'section_id' => $type->sectionId]);
    }

    public function find(int $sectionId, int $typeId): ?Type
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? AND type_id = ?',
            [$sectionId, $typeId],
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @return list<Type>
     */
    public function listBySection(int $sectionId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ? ORDER BY position, type_id',
            [$sectionId],
        );
        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Type $type): array
    {
        return [
            'section_id' => $type->sectionId,
            'title' => $type->title,
            'defaults' => json_encode($type->defaults, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'position' => $type->position,
        ];
    }

    /**
     * @param array<mixed> $row
     */
    private function hydrate(array $row): Type
    {
        $defaults = [];
        $decoded = json_decode(Row::string($row, 'defaults'), true);
        foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $defaults[$key] = $value;
            }
        }
        return new Type(
            id: Row::int($row, 'type_id'),
            sectionId: Row::int($row, 'section_id'),
            title: Row::string($row, 'title'),
            defaults: $defaults,
            position: Row::int($row, 'position'),
        );
    }
}
