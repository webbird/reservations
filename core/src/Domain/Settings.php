<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

/**
 * Display settings of a section. resourceId picks the object shown in single
 * mode; null means the first active object.
 */
final readonly class Settings
{
    public function __construct(
        public int $sectionId,
        public DisplayMode $displayMode = DisplayMode::Multi,
        public CalendarView $defaultView = CalendarView::Plan,
        public int $planRange = 1,
        public bool $showLabels = true,
        public int $pastMonths = 12,
        public int $futureMonths = 24,
        public ?int $resourceId = null,
    ) {
    }

    /**
     * The requested view if it fits the display mode, otherwise the default one.
     */
    public function view(?CalendarView $requested): CalendarView
    {
        foreach ([$requested, $this->defaultView] as $view) {
            if ($view !== null && $view->fits($this->displayMode)) {
                return $view;
            }
        }
        return CalendarView::defaultFor($this->displayMode);
    }
}
