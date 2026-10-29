<?php

declare(strict_types=1);

namespace Reservations\Core\Domain;

/**
 * Settings of a section. resourceId picks the object shown in single mode;
 * null means the first active object. The booking mode applies to all objects
 * of the section; sections do not mix modes. presentationSource is a neutral
 * reference to where the object descriptions live (e.g. "news_img:5"); the
 * core never interprets it.
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
        public BookingMode $bookingMode = BookingMode::Day,
        public ?string $presentationSource = null,
    ) {
    }

    /**
     * The same section shown as month calendar of one object, e.g. inside its description.
     */
    public function forResource(int $resourceId): self
    {
        return new self(
            $this->sectionId,
            DisplayMode::Single,
            CalendarView::Month,
            $this->planRange,
            $this->showLabels,
            $this->pastMonths,
            $this->futureMonths,
            $resourceId,
            $this->bookingMode,
            $this->presentationSource,
        );
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
