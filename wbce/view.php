<?php
/**
 * Reservations – view.php
 * Frontend: occupancy plan of one month, objects as rows and days as columns.
 * @module reservations  @version 0.1.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

$rsvSection = (int) $section_id;
$rsvTz = ReservationsWbce::timezone();
$rsvDb = ReservationsWbce::db();
$rsvSettings = $rsvDb->fetchOne('SELECT * FROM ' . $rsvDb->table('settings') . ' WHERE section_id = ?', [$rsvSection]) ?? [];
$rsvShowLabels = (bool) ($rsvSettings['show_labels'] ?? true);

// month navigation is limited to the configured window; the parameter is per section
$rsvThisMonth = new DateTimeImmutable('first day of this month midnight', $rsvTz);
$rsvFirst = $rsvThisMonth->modify('-' . (int) ($rsvSettings['past_months'] ?? 12) . ' months');
$rsvLast = $rsvThisMonth->modify('+' . (int) ($rsvSettings['future_months'] ?? 24) . ' months');
$rsvParam = 'rsv_month_' . $rsvSection;
$rsvMonth = $rsvThisMonth;
if (is_string($_GET[$rsvParam] ?? null) && preg_match('/^\d{4}-\d{2}$/', $_GET[$rsvParam]) === 1) {
    $rsvMonth = DateTimeImmutable::createFromFormat('!Y-m', $_GET[$rsvParam], $rsvTz) ?: $rsvThisMonth;
}
$rsvMonth = max($rsvFirst, min($rsvLast, $rsvMonth));
$rsvNext = $rsvMonth->modify('+1 month');

$rsvLocale = strtolower(LANGUAGE);
$rsvFmt = static fn (string $pattern): IntlDateFormatter => new IntlDateFormatter($rsvLocale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $rsvTz, null, $pattern);
$rsvMonthFmt = $rsvFmt('LLLL yyyy');
$rsvWeekdayFmt = $rsvFmt('EEEEEE');
$rsvDateFmt = new IntlDateFormatter($rsvLocale, IntlDateFormatter::FULL, IntlDateFormatter::NONE, $rsvTz);

$rsvDays = [];
for ($rsvD = $rsvMonth; $rsvD < $rsvNext; $rsvD = $rsvD->modify('+1 day')) {
    $rsvDays[] = [
        'date'    => $rsvD,
        'number'  => (int) $rsvD->format('j'),
        'weekday' => $rsvWeekdayFmt->format($rsvD),
        'long'    => $rsvDateFmt->format($rsvD),
        'weekend' => (int) $rsvD->format('N') >= 6,
    ];
}

$rsvStatuses = [];
foreach (ReservationsWbce::statuses()->listBySection($rsvSection) as $rsvStatus) {
    if ($rsvStatus->active && $rsvStatus->visibility !== Reservations\Core\Domain\Visibility::Hidden) {
        $rsvStatuses[$rsvStatus->id] = $rsvStatus;
    }
}

$rsvBookings = [];
foreach (ReservationsWbce::bookings()->listInRange($rsvSection, new Reservations\Core\Domain\TimeRange($rsvMonth, $rsvNext)) as $rsvBooking) {
    if (isset($rsvStatuses[$rsvBooking->statusId])) {
        $rsvBookings[$rsvBooking->resourceId][] = $rsvBooking;
    }
}

$rsvRows = [];
foreach (ReservationsWbce::resources()->listBySection($rsvSection, true) as $rsvResource) {
    $rsvCells = [];
    foreach ($rsvDays as $rsvDay) {
        $rsvDayRange = new Reservations\Core\Domain\TimeRange($rsvDay['date'], $rsvDay['date']->modify('+1 day'));
        $rsvCell = ['weekend' => $rsvDay['weekend'], 'long' => $rsvDay['long'], 'text' => ReservationsWbce::t('FREE'), 'status' => null];
        foreach ($rsvBookings[$rsvResource->id] ?? [] as $rsvBooking) {
            if (!$rsvBooking->range->overlaps($rsvDayRange)) {
                continue;
            }
            $rsvStatus = $rsvStatuses[$rsvBooking->statusId];
            $rsvIsLabel = $rsvStatus->visibility === Reservations\Core\Domain\Visibility::Label;
            $rsvCell['status'] = $rsvStatus->id;
            $rsvCell['text'] = $rsvIsLabel ? $rsvStatus->title : ReservationsWbce::t('OCCUPIED');
            $rsvCell['label'] = $rsvIsLabel && $rsvShowLabels ? $rsvBooking->label : '';
            break;
        }
        $rsvCells[] = $rsvCell;
    }
    $rsvRows[] = ['title' => $rsvResource->title, 'cells' => $rsvCells];
}

$rsvLegend = [];
foreach ($rsvStatuses as $rsvStatus) {
    $rsvLegend[$rsvStatus->id] = [
        'text'       => $rsvStatus->visibility === Reservations\Core\Domain\Visibility::Label ? $rsvStatus->title : ReservationsWbce::t('OCCUPIED'),
        'color'      => $rsvStatus->color,
        'text_color' => Reservations\Core\Support\Color::contrastText($rsvStatus->color),
        'pattern'    => $rsvStatus->pattern->value,
    ];
}

$rsvUrl = static fn (DateTimeImmutable $month): string => '?' . http_build_query([$rsvParam => $month->format('Y-m')]) . '#rsv-' . $rsvSection;

ReservationsWbce::twig()->display('view_plan.twig', [
    'section_id' => $rsvSection,
    'month'      => $rsvMonthFmt->format($rsvMonth),
    'days'       => $rsvDays,
    'rows'       => $rsvRows,
    'legend'     => $rsvLegend,
    'url_prev'   => $rsvMonth > $rsvFirst ? $rsvUrl($rsvMonth->modify('-1 month')) : null,
    'url_next'   => $rsvMonth < $rsvLast ? $rsvUrl($rsvNext) : null,
]);
