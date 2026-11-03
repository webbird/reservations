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
use Reservations\Core\Domain\OpeningHours;
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
    ], array_values(array_filter(CalendarView::cases(), static fn (CalendarView $v): bool => $v->fits($rsvSettings->displayMode, $rsvSettings->bookingMode)))),
    'legend'        => $rsvLegend,
    'has_resources' => $rsvResources !== [],
    'request'       => null,
];

// booking request form below the calendar; request.php handles the post and redirects back
if ($rsvSettings->requestsEnabled && $rsvResources !== []) {
    $rsvFlash = $_SESSION['mod_reservations_request'][$rsvSection] ?? [];
    unset($_SESSION['mod_reservations_request'][$rsvSection]);
    // request.php rejects forms sent less than two seconds after this
    $_SESSION['mod_reservations_form_time'][$rsvSection] = time();
    $rsvCaptcha = '';
    // after a successful request the confirmation replaces the form, which needs no captcha then
    if (!isset($rsvFlash['success']) && $rsvSettings->captcha && ReservationsWbce::captchaAvailable()) {
        ob_start();
        call_captcha('all', '', 'rsv' . $rsvSection);
        $rsvCaptcha = (string) ob_get_clean();
    }
    // "2 to 6 nights", "at least 2", "at most 6"; null without limits
    $rsvLimit = static function (?int $min, ?int $max, string $unit = ''): ?string {
        if ($min === null && $max === null) {
            return null;
        }
        $text = match (true) {
            $min !== null && $max !== null => ReservationsWbce::t('LIMIT_RANGE', $min, $max),
            $min !== null => ReservationsWbce::t('LIMIT_MIN', $min),
            default => ReservationsWbce::t('LIMIT_MAX', (int) $max),
        };
        // singular only for a single limit of 1 ("at most 1 night")
        return $unit === '' ? $text : $text . ' ' . ReservationsWbce::t($unit . (($min ?? $max) === 1 && ($min === null || $max === null) ? '' : 'S'));
    };
    $rsvRequests = ReservationsWbce::requestService();
    $rsvFormat = $rsvWholeDays ? 'Y-m-d' : 'Y-m-d\TH:i';
    $rsvDayName = $rsvFmt('EEEE');
    $rsvDayShort = $rsvFmt('EEEEEE');
    // 5 January 2026 is a Monday
    $rsvDayOfWeek = static fn (int $day): DateTimeImmutable => new DateTimeImmutable(sprintf('2026-01-%02d', 4 + $day), $rsvTz);
    // the limits of each object: as values for the form fields and as text; request.php checks them again
    $rsvItems = [];
    foreach ($rsvResources as $rsvResource) {
        $rsvDays = $rsvWholeDays ? [] : ReservationsWbce::hours()->find($rsvSection, (int) $rsvResource->id)->days;
        $rsvEarliest = $rsvRequests->earliestStart($rsvResource, $rsvWholeDays);
        // consecutive days with the same hours as one: "Mo.–Fr. 08:00–18:00; Sa. 09:00–12:00"
        $rsvHourGroups = [];
        foreach ($rsvDays as $rsvDay => $rsvIntervals) {
            $rsvTimes = OpeningHours::formatDay($rsvIntervals);
            $rsvPrevGroup = array_key_last($rsvHourGroups);
            if ($rsvPrevGroup !== null && $rsvHourGroups[$rsvPrevGroup]['times'] === $rsvTimes && $rsvHourGroups[$rsvPrevGroup]['to'] === $rsvDay - 1) {
                $rsvHourGroups[$rsvPrevGroup]['to'] = $rsvDay;
            } else {
                $rsvHourGroups[] = ['from' => $rsvDay, 'to' => $rsvDay, 'times' => $rsvTimes];
            }
        }
        $rsvHoursText = implode('; ', array_map(
            static fn (array $g): string => $rsvDayShort->format($rsvDayOfWeek($g['from']))
                . ($g['to'] > $g['from'] ? '–' . $rsvDayShort->format($rsvDayOfWeek($g['to'])) : '') . ' ' . $g['times'],
            $rsvHourGroups,
        ));
        $rsvItems[] = [
            'id'            => $rsvResource->id,
            'title'         => $rsvResource->title,
            'capacity'      => $rsvResource->capacity,
            'min_persons'   => $rsvResource->minPersons,
            'persons_note'  => $rsvLimit($rsvResource->minPersons, $rsvResource->capacity),
            'duration_note' => $rsvLimit($rsvResource->minDuration, $rsvResource->maxDuration, $rsvWholeDays ? 'UNIT_NIGHT' : 'UNIT_MINUTE'),
            'min'           => $rsvEarliest->format($rsvFormat),
            'max'           => $rsvRequests->latestStart($rsvResource, $rsvSettings)->format($rsvFormat),
            'step'          => $rsvWholeDays ? null : $rsvResource->slotMinutes * 60,
            // a single object lists its hours under the date fields, e.g. "Monday: 09:00–12:00, 13:00–17:00"
            'hours'         => array_map(
                static fn (int $day, array $intervals): array => ['day' => $rsvDayName->format($rsvDayOfWeek($day)), 'times' => OpeningHours::formatDay($intervals)],
                array_keys($rsvDays),
                $rsvDays,
            ),
            // with a choice of objects one line each below the choice
            'limits'        => array_values(array_filter([
                $rsvLimit($rsvResource->minPersons, $rsvResource->capacity, 'UNIT_PERSON'),
                $rsvLimit($rsvResource->minDuration, $rsvResource->maxDuration, $rsvWholeDays ? 'UNIT_NIGHT' : 'UNIT_MINUTE'),
                $rsvResource->leadMinMinutes > 0 ? ReservationsWbce::t('LIMITS_EARLIEST', $rsvEarliest->format($rsvWholeDays ? DATE_FORMAT : DATE_FORMAT . ' ' . TIME_FORMAT)) : null,
                $rsvResource->leadMaxDays !== null ? ReservationsWbce::t($rsvResource->leadMaxDays === 1 ? 'LIMITS_LEAD_MAX_ONE' : 'LIMITS_LEAD_MAX', $rsvResource->leadMaxDays) : null,
                $rsvDays === [] ? null : ReservationsWbce::t('LIMITS_HOURS', $rsvHoursText) . (count($rsvDays) < 7 ? ', ' . ReservationsWbce::t('LIMITS_CLOSED_OTHERS') : ''),
                $rsvResource->autoConfirm ? ReservationsWbce::t('LIMITS_AUTO_CONFIRM') : null,
            ])),
        ];
    }
    $rsvOnlyItem = count($rsvItems) === 1 ? $rsvItems[0] : null;
    // the grid only fits the date picker if all objects share it
    $rsvSteps = array_unique(array_column($rsvItems, 'step'));
    $rsvVars['request'] = [
        'action'     => WB_URL . '/modules/reservations/request.php',
        'return'     => (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '#'),
        'ftan'       => $GLOBALS['wb']->getFTAN(),
        'resources'  => $rsvItems,
        'whole_days' => $rsvWholeDays,
        // without JavaScript the date picker offers the widest range of all objects (ISO strings sort like dates)
        'min'        => min(array_column($rsvItems, 'min')),
        'max'        => max(array_column($rsvItems, 'max')),
        'step'       => count($rsvSteps) === 1 ? reset($rsvSteps) : null,
        'hours'      => $rsvOnlyItem['hours'] ?? [],
        'closed_days' => $rsvOnlyItem !== null && $rsvOnlyItem['hours'] !== [] && count($rsvOnlyItem['hours']) < 7,
        'retention'  => $rsvSettings->retentionDays,
        'captcha'    => $rsvCaptcha,
        'errors'     => $rsvFlash['errors'] ?? [],
        'field'      => $rsvFlash['field'] ?? null,
        'success'    => $rsvFlash['success'] ?? null,
        'old'        => $rsvFlash['old'] ?? [],
    ];
}

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
