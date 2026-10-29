<?php
/**
 * Reservations – view.php
 * Frontend: occupancy plan (several objects), month calendar (single object)
 * or list. Day cells come from the core (Calendar\Occupancy) with half days
 * for arrival and departure.
 * @module reservations  @version 0.2.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

use Reservations\Core\Calendar\Day;
use Reservations\Core\Calendar\Occupancy;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\CalendarView;
use Reservations\Core\Domain\DisplayMode;
use Reservations\Core\Domain\Settings;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Domain\Visibility;

$rsvSection = (int) $section_id;
$rsvTz = ReservationsWbce::timezone();
$rsvSettings = ReservationsWbce::settings()->find($rsvSection) ?? new Settings($rsvSection);
// set by ReservationsWbce::droplet(): one object as month calendar
if (isset($rsvOnlyResource)) {
    $rsvSettings = $rsvSettings->forResource($rsvOnlyResource);
}
$rsvSingle = $rsvSettings->displayMode === DisplayMode::Single;
// URL parameters carry the section id, so several sections on one page stay independent
$rsvGet = static fn (string $name): string => is_string($_GET[$name . '_' . $rsvSection] ?? null) ? $_GET[$name . '_' . $rsvSection] : '';
$rsvView = $rsvSettings->view(CalendarView::tryFrom($rsvGet('rsv_view')));

$rsvThisMonth = new DateTimeImmutable('first day of this month midnight', $rsvTz);
$rsvFirst = $rsvThisMonth->modify('-' . $rsvSettings->pastMonths . ' months');
$rsvLast = $rsvThisMonth->modify('+' . $rsvSettings->futureMonths . ' months');
$rsvMonth = $rsvThisMonth;
if (preg_match('/^\d{4}-\d{2}$/', $rsvGet('rsv_month')) === 1) {
    $rsvMonth = DateTimeImmutable::createFromFormat('!Y-m', $rsvGet('rsv_month'), $rsvTz) ?: $rsvThisMonth;
}
$rsvMonth = max($rsvFirst, min($rsvLast, $rsvMonth));
$rsvMonthCount = $rsvSingle ? 1 : $rsvSettings->planRange;
$rsvEnd = $rsvMonth->modify('+' . $rsvMonthCount . ' months');

// objects: all active ones, or the chosen (else the first active) one in single mode
$rsvResources = ReservationsWbce::resources()->listBySection($rsvSection, true);
if ($rsvSingle) {
    $rsvChosen = array_values(array_filter($rsvResources, static fn ($r) => $r->id === $rsvSettings->resourceId));
    $rsvResources = array_slice($rsvChosen !== [] ? $rsvChosen : $rsvResources, 0, 1);
}
$rsvWholeDays = ReservationsWbce::wholeDays($rsvSection);

// object names link to their description, resolved in one query; not inside that description itself
$rsvNews = ReservationsNewsImg::idOf($rsvSettings->presentationSource);
$rsvPosts = $rsvNews === null || !empty($rsvNoLinks) ? [] : ReservationsNewsImg::resolveMany($rsvNews, array_values(array_filter(array_map(static fn ($r) => $r->presentationRef, $rsvResources))));
$rsvLink = static fn ($r): ?string => $rsvPosts[$r->presentationRef ?? '']['url'] ?? null;

$rsvStatuses = [];
foreach (ReservationsWbce::statuses()->listBySection($rsvSection) as $rsvStatus) {
    $rsvStatuses[(int) $rsvStatus->id] = $rsvStatus;
}
$rsvBookings = [];
$rsvAll = ReservationsWbce::bookings()->listInRange($rsvSection, new TimeRange($rsvMonth, $rsvEnd));
foreach (Occupancy::publicBookings($rsvAll, $rsvStatuses) as $rsvBooking) {
    $rsvBookings[$rsvBooking->resourceId][] = $rsvBooking;
}

$rsvLocale = strtolower(LANGUAGE);
$rsvFmt = static fn (string $pattern): IntlDateFormatter => new IntlDateFormatter($rsvLocale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $rsvTz, null, $pattern);
$rsvMonthFmt = $rsvFmt('LLLL yyyy');
$rsvWeekdayFmt = $rsvFmt('EEEEEE');
$rsvDateFmt = new IntlDateFormatter($rsvLocale, IntlDateFormatter::FULL, IntlDateFormatter::NONE, $rsvTz);

$rsvStatusText = static function (Booking $b) use ($rsvStatuses, $rsvSettings): string {
    $status = $rsvStatuses[$b->statusId];
    if ($status->visibility !== Visibility::Label) {
        return ReservationsWbce::t('OCCUPIED');
    }
    return $status->title . ($rsvSettings->showLabels && $b->label !== '' ? ' (' . $b->label . ')' : '');
};
$rsvHalf = static fn (?Booking $b): ?array => $b === null ? null : [
    'pattern' => $rsvStatuses[$b->statusId]->pattern->value,
    'color'   => $rsvStatuses[$b->statusId]->color,
];
// one table cell: two halves plus the complete text for screen readers
$rsvCell = static function (Day $day, bool $wholeDays) use ($rsvStatusText, $rsvHalf, $rsvDateFmt): array {
    // occupied, but neither arrival nor departure: the same booking covers the whole day
    $full = !$day->isFree() && !$day->isArrival() && !$day->isDeparture();
    $text = ReservationsWbce::t('FREE');
    if ($full) {
        $text = $rsvStatusText($day->am);
    } elseif (!$day->isFree()) {
        $parts = [];
        if ($day->isDeparture()) {
            $parts[] = ReservationsWbce::t($wholeDays ? 'SR_DEPARTURE' : 'SR_MORNING', $rsvStatusText($day->am));
        }
        if ($day->isArrival()) {
            $parts[] = ReservationsWbce::t($wholeDays ? 'SR_ARRIVAL' : 'SR_AFTERNOON', $rsvStatusText($day->pm));
        }
        $text = implode(', ', $parts);
    }
    return [
        'number'  => (int) $day->date->format('j'),
        'long'    => (string) $rsvDateFmt->format($day->date),
        'weekend' => (int) $day->date->format('N') >= 6,
        'full'    => $full,
        'am'      => $rsvHalf($day->am),
        'pm'      => $rsvHalf($day->pm),
        'text'    => $text,
    ];
};

$rsvLegend = [];
foreach ($rsvStatuses as $rsvStatus) {
    if ($rsvStatus->active && $rsvStatus->visibility !== Visibility::Hidden) {
        $rsvLegend[] = [
            'text'    => $rsvStatus->visibility === Visibility::Label ? $rsvStatus->title : ReservationsWbce::t('OCCUPIED'),
            'color'   => $rsvStatus->color,
            'pattern' => $rsvStatus->pattern->value,
        ];
    }
}

$rsvUrl = static function (DateTimeImmutable $month, CalendarView $view) use ($rsvSection): string {
    // explicit separator: news_img sets arg_separator.output to '&amp;' for the whole request
    return '?' . http_build_query(['rsv_month_' . $rsvSection => $month->format('Y-m'), 'rsv_view_' . $rsvSection => $view->value], '', '&') . '#rsv-' . $rsvSection;
};
$rsvVars = [
    'section_id'    => $rsvSection,
    'period'        => $rsvMonthFmt->format($rsvMonth) . ($rsvMonthCount > 1 ? ' – ' . $rsvMonthFmt->format($rsvEnd->modify('-1 month')) : ''),
    'url_prev'      => $rsvMonth > $rsvFirst ? $rsvUrl($rsvMonth->modify('-1 month'), $rsvView) : null,
    'url_next'      => $rsvMonth < $rsvLast ? $rsvUrl($rsvMonth->modify('+1 month'), $rsvView) : null,
    'views'         => array_map(static fn (CalendarView $v): array => [
        'key'     => strtoupper('VIEW_' . $v->value),
        'url'     => $rsvUrl($rsvMonth, $v),
        'current' => $v === $rsvView,
    ], array_values(array_filter(CalendarView::cases(), static fn (CalendarView $v): bool => $v->fits($rsvSettings->displayMode)))),
    'legend'        => $rsvLegend,
    'has_resources' => $rsvResources !== [],
];

// debug aid for admins: ?rsv_debug=1 prints the computed half days as an HTML comment
if (isset($_GET['rsv_debug']) && isset($wb) && $wb->isAdmin()) {
    foreach ($rsvResources as $rsvResource) {
        $rsvDays = Occupancy::days($rsvBookings[$rsvResource->id] ?? [], $rsvMonth, $rsvEnd, $rsvWholeDays);
        echo "\n<!-- rsv #", (int) $rsvResource->id, ' ', implode(' ', array_map(static fn (Day $d): string => ($d->am->id ?? '.') . '/' . ($d->pm->id ?? '.'), $rsvDays)), " -->\n";
    }
}

if ($rsvView === CalendarView::List) {
    $rsvGroups = [];
    foreach ($rsvResources as $rsvResource) {
        $rsvEntries = [];
        foreach ($rsvBookings[$rsvResource->id] ?? [] as $rsvBooking) {
            $rsvEntries[] = [
                'range' => ReservationsWbce::formatRange($rsvBooking->range->start, $rsvBooking->range->end, $rsvWholeDays),
                'text'  => $rsvStatusText($rsvBooking),
                'half'  => $rsvHalf($rsvBooking),
            ];
        }
        $rsvGroups[] = ['title' => $rsvResource->title, 'url' => $rsvLink($rsvResource), 'entries' => $rsvEntries];
    }
    ReservationsWbce::twig()->display('view_list.twig', $rsvVars + ['groups' => $rsvGroups]);
    return;
}

if ($rsvView === CalendarView::Month) {
    $rsvResource = $rsvResources[0] ?? null;
    $rsvWeeks = [];
    $rsvWeekdays = [];
    if ($rsvResource !== null) {
        // ICU counts 1 = Sunday … 7 = Saturday; converted to ISO 1 = Monday … 7 = Sunday
        $rsvIcu = IntlCalendar::createInstance($rsvTz, $rsvLocale)?->getFirstDayOfWeek();
        $rsvFirstDay = is_int($rsvIcu) ? ($rsvIcu + 5) % 7 + 1 : 1;
        $rsvCells = array_fill(0, ((int) $rsvMonth->format('N') - $rsvFirstDay + 7) % 7, null);
        foreach (Occupancy::days($rsvBookings[$rsvResource->id] ?? [], $rsvMonth, $rsvEnd, $rsvWholeDays) as $rsvDay) {
            $rsvCells[] = $rsvCell($rsvDay, $rsvWholeDays);
        }
        $rsvWeeks = array_chunk(array_pad($rsvCells, (int) ceil(count($rsvCells) / 7) * 7, null), 7);
        $rsvLongFmt = $rsvFmt('EEEE');
        for ($i = 0; $i < 7; $i++) {
            // 2024-01-01 was a Monday
            $rsvWeekday = new DateTimeImmutable('2024-01-0' . (($rsvFirstDay - 1 + $i) % 7 + 1), $rsvTz);
            $rsvWeekdays[] = ['short' => $rsvWeekdayFmt->format($rsvWeekday), 'long' => $rsvLongFmt->format($rsvWeekday)];
        }
    }
    ReservationsWbce::twig()->display('view_month.twig', $rsvVars + [
        'title'    => $rsvResource?->title,
        'url'      => $rsvResource === null ? null : $rsvLink($rsvResource),
        'weekdays' => $rsvWeekdays,
        'weeks'    => $rsvWeeks,
    ]);
    return;
}

$rsvTables = [];
for ($rsvM = $rsvMonth; $rsvM < $rsvEnd; $rsvM = $rsvM->modify('+1 month')) {
    $rsvNext = $rsvM->modify('+1 month');
    $rsvHeads = [];
    for ($rsvD = $rsvM; $rsvD < $rsvNext; $rsvD = $rsvD->modify('+1 day')) {
        $rsvHeads[] = [
            'number'  => (int) $rsvD->format('j'),
            'weekday' => $rsvWeekdayFmt->format($rsvD),
            'long'    => $rsvDateFmt->format($rsvD),
            'weekend' => (int) $rsvD->format('N') >= 6,
        ];
    }
    $rsvRows = [];
    foreach ($rsvResources as $rsvResource) {
        $rsvRows[] = [
            'title' => $rsvResource->title,
            'url'   => $rsvLink($rsvResource),
            'cells' => array_map(
                static fn (Day $d): array => $rsvCell($d, $rsvWholeDays),
                Occupancy::days($rsvBookings[$rsvResource->id] ?? [], $rsvM, $rsvNext, $rsvWholeDays),
            ),
        ];
    }
    $rsvTables[] = ['caption' => $rsvMonthFmt->format($rsvM), 'days' => $rsvHeads, 'rows' => $rsvRows];
}
ReservationsWbce::twig()->display('view_plan.twig', $rsvVars + ['tables' => $rsvTables]);
