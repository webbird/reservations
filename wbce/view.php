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
use Reservations\Core\Calendar\Week;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\CalendarView;
use Reservations\Core\Domain\DisplayMode;
use Reservations\Core\Domain\OpeningHours;
use Reservations\Core\Domain\Settings;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Domain\Visibility;

$rsvSection = (int) $section_id;
// WBCE has no cron and a quiet site sees no requests and no backend; the form promises the deletion
ReservationsWbce::requestService()->purgeExpired();
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

// the week view pages by weeks; they start on the first day of the week of the language, as the month calendar
// ICU counts 1 = Sunday … 7 = Saturday; converted to ISO 1 = Monday … 7 = Sunday
$rsvIcu = IntlCalendar::createInstance($rsvTz, strtolower(LANGUAGE))?->getFirstDayOfWeek();
$rsvFirstDay = is_int($rsvIcu) ? ($rsvIcu + 5) % 7 + 1 : 1;
$rsvWeekOf = static fn (DateTimeImmutable $d): DateTimeImmutable => $d->setTime(0, 0)->modify('-' . (((int) $d->format('N') - $rsvFirstDay + 7) % 7) . ' days');
$rsvWeekMin = $rsvWeekOf($rsvFirst);
$rsvWeekMax = $rsvWeekOf($rsvLast->modify('+1 month -1 day'));
// the current week, or the week the shown month starts with (coming from the plan or the month calendar)
$rsvWeek = $rsvMonth == $rsvThisMonth ? $rsvWeekOf(new DateTimeImmutable('today', $rsvTz)) : $rsvWeekOf($rsvMonth);
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rsvGet('rsv_week')) === 1) {
    $rsvWeek = $rsvWeekOf(DateTimeImmutable::createFromFormat('!Y-m-d', $rsvGet('rsv_week'), $rsvTz) ?: $rsvWeek);
}
$rsvWeek = max($rsvWeekMin, min($rsvWeekMax, $rsvWeek));
if ($rsvView === CalendarView::Week) {
    // switching to another view keeps the month of the week
    $rsvMonth = $rsvWeek->modify('first day of this month');
}
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
$rsvAll = ReservationsWbce::bookings()->listInRange($rsvSection, $rsvView === CalendarView::Week
    ? new TimeRange($rsvWeek, $rsvWeek->modify('+7 days'))
    // by day a stay ending on the first shown day has its departure there, but it ends at midnight, before that day begins
    : new TimeRange($rsvWholeDays ? $rsvMonth->modify('-1 day') : $rsvMonth, $rsvEnd));
$rsvSeenStatus = [];
foreach (Occupancy::publicBookings($rsvAll, $rsvStatuses) as $rsvBooking) {
    $rsvBookings[$rsvBooking->resourceId][] = $rsvBooking;
    $rsvSeenStatus[$rsvBooking->statusId] = true;
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
// $outside: the text for a day that cannot be chosen
$rsvCell = static function (Day $day, bool $wholeDays, ?string $pick, ?string $outside) use ($rsvStatusText, $rsvHalf, $rsvDateFmt): array {
    // occupied, but neither arrival nor departure: the same booking covers the whole day
    $full = !$day->isFree() && !$day->isArrival() && !$day->isDeparture();
    $text = $outside ?? ReservationsWbce::t('FREE');
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
        'pick'    => $pick,
        'outside' => $outside !== null,
    ];
};

// why a free time cannot be chosen: it is over, or outside the lead times of the object
$rsvNow = new DateTimeImmutable('now', $rsvTz);
$rsvUnavailable = static fn (DateTimeImmutable $end): string => ReservationsWbce::t($end <= $rsvNow ? 'PAST' : 'NOT_REQUESTABLE');

$rsvLegend = [];
foreach ($rsvStatuses as $rsvStatus) {
    // an inactive status only while the calendar still shows a booking of it
    if (($rsvStatus->active || isset($rsvSeenStatus[(int) $rsvStatus->id])) && $rsvStatus->visibility !== Visibility::Hidden) {
        $rsvLegend[] = [
            'text'    => $rsvStatus->visibility === Visibility::Label ? $rsvStatus->title : ReservationsWbce::t('OCCUPIED'),
            'color'   => $rsvStatus->color,
            'pattern' => $rsvStatus->pattern->value,
        ];
    }
}

// $date: the first day of a week for the week view, any day of the month for the others
// $pick: the values of a click on a free day or block, which leads to the request form
$rsvUrl = static function (DateTimeImmutable $date, CalendarView $view, array $pick = []) use ($rsvSection): string {
    $query = ['rsv_month_' . $rsvSection => $date->format('Y-m'), 'rsv_view_' . $rsvSection => $view->value];
    if ($view === CalendarView::Week) {
        $query['rsv_week_' . $rsvSection] = $date->format('Y-m-d');
    }
    foreach ($pick as $name => $value) {
        $query['rsv_' . $name . '_' . $rsvSection] = $value;
    }
    // explicit separator: news_img sets arg_separator.output to '&amp;' for the whole request
    return '?' . http_build_query($query, '', '&') . ($pick === [] ? '#rsv-' : '#rsv-request-') . $rsvSection;
};
$rsvByWeek = $rsvView === CalendarView::Week;
$rsvVars = [
    'section_id'    => $rsvSection,
    'period'        => $rsvByWeek
        ? ReservationsWbce::formatRange($rsvWeek, $rsvWeek->modify('+6 days'), true)
        : $rsvMonthFmt->format($rsvMonth) . ($rsvMonthCount > 1 ? ' – ' . $rsvMonthFmt->format($rsvEnd->modify('-1 month')) : ''),
    'by_week'       => $rsvByWeek,
    'url_prev'      => $rsvByWeek
        ? ($rsvWeek > $rsvWeekMin ? $rsvUrl($rsvWeek->modify('-7 days'), $rsvView) : null)
        : ($rsvMonth > $rsvFirst ? $rsvUrl($rsvMonth->modify('-1 month'), $rsvView) : null),
    'url_next'      => $rsvByWeek
        ? ($rsvWeek < $rsvWeekMax ? $rsvUrl($rsvWeek->modify('+7 days'), $rsvView) : null)
        : ($rsvMonth < $rsvLast ? $rsvUrl($rsvMonth->modify('+1 month'), $rsvView) : null),
    'views'         => array_map(static fn (CalendarView $v): array => [
        'key'     => strtoupper('VIEW_' . $v->value),
        'url'     => $rsvUrl($v === CalendarView::Week ? $rsvWeek : $rsvMonth, $v),
        'current' => $v === $rsvView,
    ], array_values(array_filter(CalendarView::cases(), static fn (CalendarView $v): bool => $v->fits($rsvSettings->displayMode, $rsvSettings->bookingMode)))),
    'legend'        => $rsvLegend,
    'has_resources' => $rsvResources !== [],
    'request'       => null,
];

// the link of a free day or block, null if no request can begin there; only with the form below
$rsvPick = static fn (): ?string => null;
// from when to when each object can be requested; without the form only the past is over
$rsvWindows = [];
$rsvTaken = [];

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
    // what a suggestion keeps clear of; bookings the calendar hides stay hidden here too
    $rsvTaken = array_map(static fn (array $own): array => array_values(array_filter($own, static fn (Booking $b): bool => $rsvStatuses[$b->statusId]->blocks)), $rsvBookings);
    // $before: the end of the day or block
    $rsvPick = static function ($resource, DateTimeImmutable $from, DateTimeImmutable $before) use ($rsvRequests, $rsvSettings, $rsvTaken, $rsvUrl, $rsvFormat, $rsvView, $rsvByWeek, $rsvWeek, $rsvMonth): ?string {
        $range = $rsvRequests->suggest($resource, $rsvSettings, $from, $rsvTaken[$resource->id] ?? []);
        if ($range === null || $range->start >= $before) {
            return null;
        }
        return $rsvUrl($rsvByWeek ? $rsvWeek : $rsvMonth, $rsvView, ['object' => $resource->id, 'from' => $from->format($rsvFormat)]);
    };
    // what such a link asks for: the object and, if it can still begin, its period
    $rsvPicked = array_values(array_filter($rsvResources, static fn ($r): bool => (string) $r->id === $rsvGet('rsv_object')))[0] ?? null;
    $rsvPickFrom = DateTimeImmutable::createFromFormat('!' . $rsvFormat, $rsvGet('rsv_from'), $rsvTz);
    $rsvRange = $rsvPicked === null || $rsvPickFrom === false || $rsvPickFrom->format($rsvFormat) !== $rsvGet('rsv_from')
        ? null
        : $rsvRequests->suggest($rsvPicked, $rsvSettings, $rsvPickFrom, $rsvTaken[$rsvPicked->id] ?? []);
    $rsvPrefill = ($rsvPicked === null ? [] : ['resource_id' => $rsvPicked->id])
        + ($rsvRange === null ? [] : ['start' => $rsvRange->start->format($rsvFormat), 'end' => $rsvRange->end->format($rsvFormat)]);
    $rsvDayName = $rsvFmt('EEEE');
    $rsvDayShort = $rsvFmt('EEEEEE');
    // 5 January 2026 is a Monday
    $rsvDayOfWeek = static fn (int $day): DateTimeImmutable => new DateTimeImmutable(sprintf('2026-01-%02d', 4 + $day), $rsvTz);
    // the limits of each object: as values for the form fields and as text; request.php checks them again
    $rsvItems = [];
    foreach ($rsvResources as $rsvResource) {
        $rsvDays = $rsvWholeDays ? [] : ReservationsWbce::hours()->find($rsvSection, (int) $rsvResource->id)->days;
        $rsvEarliest = $rsvRequests->earliestStart($rsvResource, $rsvWholeDays);
        $rsvLatest = $rsvRequests->latestStart($rsvResource, $rsvSettings);
        $rsvWindows[$rsvResource->id] = [$rsvEarliest, $rsvLatest];
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
        $rsvChecks = $rsvWholeDays ? ReservationsWbce::checkTimes($rsvResource, ReservationsWbce::t(...)) : [];
        $rsvItems[] = [
            'id'            => $rsvResource->id,
            'title'         => $rsvResource->title,
            'capacity'      => $rsvResource->capacity,
            'min_persons'   => $rsvResource->minPersons,
            'persons_note'  => $rsvLimit($rsvResource->minPersons, $rsvResource->capacity),
            'duration_note' => $rsvLimit($rsvResource->minDuration, $rsvResource->maxDuration, $rsvWholeDays ? 'UNIT_NIGHT' : 'UNIT_MINUTE'),
            'min'           => $rsvEarliest->format($rsvFormat),
            'max'           => $rsvLatest->format($rsvFormat),
            'step'          => $rsvWholeDays ? null : $rsvResource->slotMinutes * 60,
            'check'         => implode(' · ', $rsvChecks),
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
                ...$rsvChecks,
                $rsvResource->autoConfirm ? ReservationsWbce::t('LIMITS_AUTO_CONFIRM') : null,
            ])),
        ];
    }
    $rsvOnlyItem = count($rsvItems) === 1 ? $rsvItems[0] : null;
    // the grid only fits the date picker if all objects share it
    $rsvSteps = array_unique(array_column($rsvItems, 'step'));
    // the way back leaves out what belongs to this visit: the mark of request.php and the values of a click
    $rsvReturn = (string) preg_replace('/[?&]rsv_(?:sent|object_\d+|from_\d+)=[^&]*/', '', (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '#'));
    $rsvVars['request'] = [
        'action'     => WB_URL . '/modules/reservations/request.php',
        'return'     => (string) preg_replace('/^([^?]*)&/', '$1?', $rsvReturn),
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
        'old'        => $rsvFlash['old'] ?? $rsvPrefill,
    ];
}

// a day of the plan or calendar; links by day only: in period mode a day says nothing about the time, the week view does
$rsvAsking = $rsvVars['request'] !== null;
$rsvDayCell = static function ($resource, Day $day) use ($rsvCell, $rsvPick, $rsvWindows, $rsvNow, $rsvUnavailable, $rsvWholeDays, $rsvAsking): array {
    $end = $day->date->modify('+1 day');
    [$notBefore, $notAfter] = $rsvWindows[$resource->id] ?? [$rsvNow, null];
    $outside = $end <= $notBefore || ($notAfter !== null && $day->date > $notAfter);
    $arrival = !$outside && $rsvWholeDays && $day->pm === null;
    $pick = $arrival ? $rsvPick($resource, $day->date, $end) : null;
    // a free afternoon the minimum stay does not fit into is as good as taken
    $outside = $outside || ($arrival && $rsvAsking && $pick === null);
    return $rsvCell($day, $rsvWholeDays, $pick, $outside ? $rsvUnavailable($end) : null);
};

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

if ($rsvView === CalendarView::Week) {
    // a time grid per object: days as columns, rows of 30 minutes (an hour for a grid of 60)
    $rsvTime = static fn (DateTimeImmutable $t, DateTimeImmutable $day): string => $t >= $day->modify('+1 day') ? '24:00' : $t->format('H:i');
    $rsvDates = [];
    for ($i = 0; $i < 7; $i++) {
        $rsvDates[] = $rsvWeek->modify('+' . $i . ' days');
    }
    $rsvDayHeads = array_map(static fn (DateTimeImmutable $d): array => [
        'weekday' => $rsvWeekdayFmt->format($d),
        'date'    => $d->format('j.n.'),
        'long'    => (string) $rsvDateFmt->format($d),
        'weekend' => (int) $d->format('N') >= 6,
    ], $rsvDates);
    $rsvTables = [];
    $rsvAnyClosed = false;
    foreach ($rsvResources as $rsvResource) {
        $rsvRowMinutes = $rsvResource->slotMinutes >= 60 ? 60 : 30;
        $rsvOwn = $rsvBookings[$rsvResource->id] ?? [];
        $rsvOpen = ReservationsWbce::hours()->find($rsvSection, (int) $rsvResource->id);
        [$rsvFrom, $rsvTo] = Week::span($rsvOwn, $rsvOpen, $rsvWeek, $rsvRowMinutes);
        $rsvGrid = [];
        for ($rsvMinute = $rsvFrom; $rsvMinute < $rsvTo; $rsvMinute += $rsvRowMinutes) {
            $rsvGrid[] = ['time' => sprintf('%02d:%02d', intdiv($rsvMinute, 60), $rsvMinute % 60), 'cells' => []];
        }
        // each block becomes a cell spanning its rows, placed in the row it starts in
        [$rsvNotBefore, $rsvNotAfter] = $rsvWindows[$rsvResource->id] ?? [$rsvNow, null];
        $rsvGap = $rsvResource->requiredGapMinutes($rsvSettings->bookingMode);
        $rsvBusy = array_map(static fn (Booking $b): TimeRange => $b->range->withBuffer($rsvGap, $rsvGap), $rsvTaken[$rsvResource->id] ?? []);
        foreach (Week::days($rsvOwn, $rsvOpen, $rsvWeek, $rsvFrom, $rsvTo, $rsvRowMinutes, $rsvNotBefore, $rsvNotAfter, $rsvBusy) as $i => $rsvBlocks) {
            $rsvRow = 0;
            foreach ($rsvBlocks as $rsvBlock) {
                $rsvBooking = $rsvBlock['booking'];
                // a booking shows its own times on this day, the other blocks those of their rows
                $rsvShown = $rsvBooking === null
                    ? [$rsvBlock['start'], $rsvBlock['end']]
                    : [max($rsvBlock['start'], $rsvBooking->range->start, $rsvDates[$i]), min($rsvBlock['end'], $rsvBooking->range->end, $rsvDates[$i]->modify('+1 day'))];
                $rsvTimes = $rsvTime($rsvShown[0], $rsvDates[$i]) . '–' . $rsvTime($rsvShown[1], $rsvDates[$i]);
                $rsvFree = $rsvBooking === null && !$rsvBlock['closed'] && !$rsvBlock['outside'];
                $rsvCellPick = $rsvFree ? $rsvPick($rsvResource, $rsvBlock['start'], $rsvBlock['end']) : null;
                // free, but too short for the minimum duration
                $rsvUnfit = $rsvFree && $rsvCellPick === null && $rsvVars['request'] !== null;
                $rsvState = match (true) {
                    $rsvBooking !== null => $rsvStatusText($rsvBooking),
                    $rsvBlock['closed'] => ReservationsWbce::t('CLOSED'),
                    $rsvBlock['outside'] || $rsvUnfit => $rsvUnavailable($rsvBlock['end']),
                    default => ReservationsWbce::t('FREE'),
                };
                $rsvHatched = $rsvBlock['closed'] || $rsvBlock['outside'] || $rsvUnfit;
                $rsvAnyClosed = $rsvAnyClosed || $rsvHatched;
                $rsvGrid[$rsvRow]['cells'][] = [
                    'rows'    => $rsvBlock['rows'],
                    'state'   => $rsvBooking !== null ? 'booked' : ($rsvHatched ? 'closed' : 'free'),
                    'half'    => $rsvHalf($rsvBooking),
                    'weekend' => $rsvDayHeads[$i]['weekend'],
                    // visible only where the cell is high enough
                    'label'   => $rsvBooking !== null && $rsvBlock['rows'] >= 2 ? $rsvTimes : null,
                    'text'    => $rsvDayHeads[$i]['long'] . ', ' . $rsvTimes . ': ' . $rsvState,
                    'pick'    => $rsvCellPick,
                ];
                $rsvRow += $rsvBlock['rows'];
            }
        }
        $rsvTables[] = ['title' => $rsvResource->title, 'url' => $rsvLink($rsvResource), 'rows' => $rsvGrid, 'row_minutes' => $rsvRowMinutes];
    }
    ReservationsWbce::twig()->display('view_week.twig', $rsvVars + [
        'days'          => $rsvDayHeads,
        'tables'        => $rsvTables,
        'legend_closed' => $rsvAnyClosed,
    ]);
    return;
}

if ($rsvView === CalendarView::Month) {
    $rsvResource = $rsvResources[0] ?? null;
    $rsvWeeks = [];
    $rsvWeekdays = [];
    $rsvCells = [];
    if ($rsvResource !== null) {
        $rsvCells = array_fill(0, ((int) $rsvMonth->format('N') - $rsvFirstDay + 7) % 7, null);
        foreach (Occupancy::days($rsvBookings[$rsvResource->id] ?? [], $rsvMonth, $rsvEnd, $rsvWholeDays) as $rsvDay) {
            $rsvCells[] = $rsvDayCell($rsvResource, $rsvDay);
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
        'legend_closed' => in_array(true, array_column($rsvCells, 'outside'), true),
    ]);
    return;
}

$rsvTables = [];
$rsvAnyOutside = false;
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
        $rsvDayCells = array_map(
            static fn (Day $d): array => $rsvDayCell($rsvResource, $d),
            Occupancy::days($rsvBookings[$rsvResource->id] ?? [], $rsvM, $rsvNext, $rsvWholeDays),
        );
        $rsvAnyOutside = $rsvAnyOutside || in_array(true, array_column($rsvDayCells, 'outside'), true);
        $rsvRows[] = ['title' => $rsvResource->title, 'url' => $rsvLink($rsvResource), 'cells' => $rsvDayCells];
    }
    $rsvTables[] = ['caption' => $rsvMonthFmt->format($rsvM), 'days' => $rsvHeads, 'rows' => $rsvRows];
}
ReservationsWbce::twig()->display('view_plan.twig', $rsvVars + ['tables' => $rsvTables, 'legend_closed' => $rsvAnyOutside]);
