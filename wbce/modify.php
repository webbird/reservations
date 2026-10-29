<?php
/**
 * Reservations – modify.php
 * Backend of a section: list of objects, or the bookings of one object.
 * Runs inside the section loop of admin/pages/modify.php and shares its
 * variable scope, hence the $rsv prefix.
 * @module reservations  @version 0.1.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

require_once __DIR__ . '/bootstrap.php';

$rsvSection = (int) $section_id;
$rsvPage = (int) $page_id;
ReservationsWbce::ensureSection($rsvSection, $rsvPage);
// URL parameters only count for the section they were meant for
$rsvParam = static fn (string $name): int => (int) ($_GET['rsv_section'] ?? 0) === $rsvSection ? (int) ($_GET[$name] ?? 0) : 0;
$rsvFlash = ReservationsWbce::takeFlash($rsvSection);
$rsvOld = is_array($rsvFlash['old'] ?? null) ? $rsvFlash['old'] : null;

$rsvTypes = [];
foreach (ReservationsWbce::types()->listBySection($rsvSection) as $rsvType) {
    $rsvTypes[(int) $rsvType->id] = $rsvType;
}

$rsvVars = [
    'page_id'    => $rsvPage,
    'section_id' => $rsvSection,
    'action'     => WB_URL . '/modules/reservations/save.php',
    'errors'     => $rsvFlash['errors'] ?? [],
    'success'    => $rsvFlash['success'] ?? '',
    'url_list'   => ReservationsWbce::modifyUrl($rsvPage, $rsvSection),
];

if ($rsvParam('rsv_settings') === 1) {
    $rsvSettings = ReservationsWbce::settings()->find($rsvSection) ?? new Reservations\Core\Domain\Settings($rsvSection);
    $rsvActive = ReservationsWbce::resources()->listBySection($rsvSection, true);
    $rsvForm = $rsvOld ?? [
        'display_mode'  => $rsvSettings->displayMode->value,
        'resource_id'   => $rsvSettings->resourceId ?? 0,
        'default_view'  => $rsvSettings->defaultView->value,
        'plan_range'    => $rsvSettings->planRange,
        'show_labels'   => $rsvSettings->showLabels,
        'past_months'   => $rsvSettings->pastMonths,
        'future_months' => $rsvSettings->futureMonths,
        'booking_mode'  => $rsvSettings->bookingMode->value,
    ];
    ReservationsWbce::twig()->display('modify_settings.twig', $rsvVars + [
        'form'      => $rsvForm,
        'resources' => array_map(static fn ($r) => ['id' => $r->id, 'title' => $r->title], ReservationsWbce::resources()->listBySection($rsvSection)),
        'first'     => $rsvActive[0]->title ?? null,
        'months_max' => Reservations\Core\Service\SettingsService::MONTHS_MAX,
    ]);
    return;
}

if ($rsvParam('rsv_types') === 1) {
    $rsvUsage = array_count_values(array_map(static fn ($r) => $r->typeId, ReservationsWbce::resources()->listBySection($rsvSection)));
    $rsvRows = [];
    foreach ($rsvTypes as $rsvId => $rsvType) {
        $rsvRows[] = [
            'id'        => $rsvId,
            'title'     => $rsvType->title,
            'position'  => $rsvType->position,
            'resources' => $rsvUsage[$rsvId] ?? 0,
            'url_edit'  => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_types' => 1, 'rsv_type' => $rsvId]),
        ];
    }
    $rsvEdit = $rsvTypes[$rsvParam('rsv_type')] ?? null;
    ReservationsWbce::twig()->display('modify_types.twig', $rsvVars + [
        'types'     => $rsvRows,
        'form'      => $rsvOld ?? [
            'type_id'  => $rsvEdit->id ?? 0,
            'title'    => $rsvEdit->title ?? '',
            'position' => $rsvEdit->position ?? count($rsvTypes),
        ],
        'url_types' => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_types' => 1]),
    ]);
    return;
}

$rsvResource = ReservationsWbce::resources()->find($rsvSection, $rsvParam('rsv_bookings'));

if ($rsvResource !== null) {
    $rsvWholeDays = ReservationsWbce::wholeDays($rsvSection);
    $rsvInputFormat = $rsvWholeDays ? 'Y-m-d' : 'Y-m-d\TH:i';

    $rsvStatuses = [];
    foreach (ReservationsWbce::statuses()->listBySection($rsvSection) as $rsvStatus) {
        $rsvStatuses[(int) $rsvStatus->id] = $rsvStatus;
    }

    $rsvToday = new DateTimeImmutable('today', ReservationsWbce::timezone());
    $rsvRows = [];
    // no upper limit: a booking typed with a wrong year must stay visible and deletable
    $rsvWindow = new Reservations\Core\Domain\TimeRange($rsvToday->modify('-12 months'), $rsvToday->modify('+100 years'));
    foreach (ReservationsWbce::bookings()->listInRange($rsvSection, $rsvWindow, (int) $rsvResource->id) as $rsvBooking) {
        $rsvStatus = $rsvStatuses[$rsvBooking->statusId] ?? null;
        $rsvRows[] = [
            'id'         => $rsvBooking->id,
            'range'      => ReservationsWbce::formatRange($rsvBooking->range->start, $rsvBooking->range->end, $rsvWholeDays),
            'past'       => $rsvBooking->range->end <= $rsvToday,
            'status'     => $rsvStatus?->title ?? '?',
            'color'      => $rsvStatus?->color ?? '#888780',
            'text_color' => Reservations\Core\Support\Color::contrastText($rsvStatus?->color ?? '#888780'),
            'label'      => $rsvBooking->label,
            'note'       => $rsvBooking->note,
            'url_edit'   => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_bookings' => (int) $rsvResource->id, 'rsv_booking' => (int) $rsvBooking->id]),
        ];
    }

    $rsvEdit = ReservationsWbce::bookings()->find($rsvSection, $rsvParam('rsv_booking'));
    $rsvDefaultStatus = ReservationsWbce::statuses()->findByRole($rsvSection, Reservations\Core\Domain\StatusRole::RequestConfirmed);
    $rsvForm = $rsvOld ?? ($rsvEdit !== null
        ? [
            'booking_id' => $rsvEdit->id,
            'start'      => $rsvEdit->range->start->format($rsvInputFormat),
            'end'        => $rsvEdit->range->end->format($rsvInputFormat),
            'status_id'  => $rsvEdit->statusId,
            'label'      => $rsvEdit->label,
            'note'       => $rsvEdit->note,
        ]
        : [
            'booking_id' => 0,
            'start'      => $rsvWholeDays ? $rsvToday->format('Y-m-d') : $rsvToday->modify('+9 hours')->format($rsvInputFormat),
            'end'        => $rsvWholeDays ? $rsvToday->modify('+1 day')->format('Y-m-d') : $rsvToday->modify('+10 hours')->format($rsvInputFormat),
            'status_id'  => $rsvDefaultStatus?->id ?? 0,
            'label'      => '',
            'note'       => '',
        ]);

    ReservationsWbce::twig()->display('modify_bookings.twig', $rsvVars + [
        'resource'   => ['id' => $rsvResource->id, 'title' => $rsvResource->title],
        'whole_days' => $rsvWholeDays,
        'bookings'   => $rsvRows,
        'statuses'   => array_map(static fn ($s) => ['id' => $s->id, 'title' => $s->title], array_values(array_filter($rsvStatuses, static fn ($s) => $s->active))),
        'form'       => $rsvForm,
        'url_new'    => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_bookings' => (int) $rsvResource->id]),
    ]);
    return;
}

$rsvRows = [];
$rsvCounts = ReservationsWbce::bookings()->countByResource($rsvSection);
foreach (ReservationsWbce::resources()->listBySection($rsvSection) as $rsvItem) {
    $rsvRows[] = [
        'id'           => $rsvItem->id,
        'title'        => $rsvItem->title,
        'type'         => $rsvTypes[$rsvItem->typeId]->title ?? '?',
        'active'       => $rsvItem->active,
        'bookings'     => $rsvCounts[(int) $rsvItem->id] ?? 0,
        'url_edit'     => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_edit' => (int) $rsvItem->id]),
        'url_bookings' => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_bookings' => (int) $rsvItem->id]),
    ];
}

$rsvEdit = ReservationsWbce::resources()->find($rsvSection, $rsvParam('rsv_edit'));
$rsvForm = $rsvOld ?? [
    'resource_id' => $rsvEdit->id ?? 0,
    'title'       => $rsvEdit->title ?? '',
    'type_id'     => $rsvEdit->typeId ?? array_key_first($rsvTypes),
    'active'      => $rsvEdit->active ?? true,
];

ReservationsWbce::twig()->display('modify_resources.twig', $rsvVars + [
    'resources'    => $rsvRows,
    'types'        => array_map(static fn ($t) => ['id' => $t->id, 'title' => $t->title], array_values($rsvTypes)),
    'form'         => $rsvForm,
    'url_settings' => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_settings' => 1]),
    'url_types'    => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_types' => 1]),
]);
