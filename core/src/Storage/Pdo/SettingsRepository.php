<?php

declare(strict_types=1);

namespace Reservations\Core\Storage\Pdo;

use Reservations\Core\Domain\BookingMode;
use Reservations\Core\Domain\CalendarView;
use Reservations\Core\Domain\DisplayMode;
use Reservations\Core\Domain\Settings;

/**
 * Reads and writes the display columns and the booking mode. Other columns
 * (requests, notifications) are left untouched.
 */
final class SettingsRepository
{
    private const TABLE = 'settings';

    public function __construct(private readonly Connection $db)
    {
    }

    public function find(int $sectionId): ?Settings
    {
        $row = $this->db->fetchOne('SELECT * FROM ' . $this->db->table(self::TABLE) . ' WHERE section_id = ?', [$sectionId]);
        if ($row === null) {
            return null;
        }
        $mode = DisplayMode::tryFrom(Row::string($row, 'display_mode')) ?? DisplayMode::Multi;
        return new Settings(
            sectionId: $sectionId,
            displayMode: $mode,
            defaultView: CalendarView::tryFrom(Row::string($row, 'default_view')) ?? CalendarView::defaultFor($mode),
            planRange: Row::int($row, 'plan_range'),
            showLabels: Row::bool($row, 'show_labels'),
            pastMonths: Row::int($row, 'past_months'),
            futureMonths: Row::int($row, 'future_months'),
            resourceId: Row::nullableInt($row, 'resource_id'),
            bookingMode: BookingMode::tryFrom(Row::string($row, 'booking_mode')) ?? BookingMode::Day,
        );
    }

    public function save(Settings $s): void
    {
        $this->db->update(self::TABLE, [
            'display_mode' => $s->displayMode,
            'default_view' => $s->defaultView,
            'plan_range' => $s->planRange,
            'show_labels' => $s->showLabels,
            'past_months' => $s->pastMonths,
            'future_months' => $s->futureMonths,
            'resource_id' => $s->resourceId,
            'booking_mode' => $s->bookingMode,
        ],['section_id' => $s->sectionId]);
    }
}
