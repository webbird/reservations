<?php

declare(strict_types=1);

namespace Reservations\Core\Storage;

use Reservations\Core\Domain\Pattern;
use Reservations\Core\Domain\Settings;
use Reservations\Core\Domain\Status;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\Type;
use Reservations\Core\Domain\Visibility;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;

/**
 * Seeds a new section: settings row, default statuses (with the three roles)
 * and one default type. Titles are passed in by the wrapper in the site language.
 */
final class SectionSetup
{
    public const DEFAULT_TITLES = [
        'requested' => 'Requested',
        'reserved' => 'Reserved',
        'booked' => 'Booked',
        'blocked' => 'Blocked',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'type' => 'Object',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly StatusRepository $statuses,
        private readonly TypeRepository $types,
    ) {
    }

    /**
     * Idempotent: does nothing if the section already has a settings row.
     *
     * @param array<string, string> $titles see DEFAULT_TITLES for the keys
     */
    public function run(int $sectionId, int $pageId, array $titles = []): bool
    {
        $exists = $this->db->fetchInt(
            'SELECT COUNT(*) FROM ' . $this->db->table('settings') . ' WHERE section_id = ?',
            [$sectionId],
        );
        if ($exists > 0) {
            return false;
        }
        $t = array_merge(self::DEFAULT_TITLES, $titles);

        // written explicitly: updates do not change the column default of older installations
        $this->db->insert('settings', ['section_id' => $sectionId, 'page_id' => $pageId, 'past_months' => (new Settings($sectionId))->pastMonths]);

        $defaults = [
            // visible: a blocked period that looks free would turn requests down without a reason
            ['requested', true, Visibility::Label, '#b9770e', Pattern::Dotted, StatusRole::RequestNew],
            ['reserved', true, Visibility::Label, '#4a8fd4', Pattern::Striped, null],
            ['booked', true, Visibility::Label, '#185fa5', Pattern::Solid, StatusRole::RequestConfirmed],
            ['blocked', true, Visibility::Busy, '#5f5e5a', Pattern::Striped, null],
            ['rejected', false, Visibility::Hidden, '#a32d2d', Pattern::Outline, StatusRole::RequestRejected],
            ['cancelled', false, Visibility::Hidden, '#888780', Pattern::Outline, null],
        ];
        foreach ($defaults as $position => [$key, $blocks, $visibility, $color, $pattern, $role]) {
            $this->statuses->insert(new Status(
                id: null,
                sectionId: $sectionId,
                title: $t[$key],
                blocks: $blocks,
                visibility: $visibility,
                color: $color,
                pattern: $pattern,
                role: $role,
                active: true,
                position: $position,
            ));
        }

        $this->types->insert(new Type(null, $sectionId, $t['type'], [], 0));

        return true;
    }

    public function remove(int $sectionId): void
    {
        $this->db->run(
            'DELETE FROM ' . $this->db->table('requests') . ' WHERE booking_id IN (SELECT booking_id FROM '
            . $this->db->table('bookings') . ' WHERE section_id = ?)',
            [$sectionId],
        );
        foreach (['hours', 'bookings', 'resources', 'types', 'statuses', 'settings'] as $table) {
            $this->db->delete($table, ['section_id' => $sectionId]);
        }
    }
}
