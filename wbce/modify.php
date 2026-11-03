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
// WBCE has no cron; expired contact data of requests is emptied on backend and request calls
ReservationsWbce::requestService()->purgeExpired();
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
        'news_section'  => ReservationsNewsImg::idOf($rsvSettings->presentationSource) ?? 0,
        'requests_enabled' => $rsvSettings->requestsEnabled,
        'notify_email'  => $rsvSettings->notifyEmail,
        'retention_days' => $rsvSettings->retentionDays,
        'captcha'       => $rsvSettings->captcha,
    ];
    ReservationsWbce::twig()->display('modify_settings.twig', $rsvVars + [
        'form'      => $rsvForm,
        'resources' => array_map(static fn ($r) => ['id' => $r->id, 'title' => $r->title], ReservationsWbce::resources()->listBySection($rsvSection)),
        'first'     => $rsvActive[0]->title ?? null,
        'months_max' => Reservations\Core\Service\SettingsService::MONTHS_MAX,
        'retention_max' => Reservations\Core\Service\SettingsService::RETENTION_MAX,
        'news_sections' => ReservationsNewsImg::sections(),
        'captcha_available' => ReservationsWbce::captchaAvailable(),
    ]);
    return;
}

if ($rsvParam('rsv_statuses') === 1) {
    $rsvRows = [];
    $rsvEdit = null;
    $rsvStatusList = ReservationsWbce::statuses()->listBySection($rsvSection);
    foreach ($rsvStatusList as $rsvStatus) {
        $rsvRows[] = [
            'id'         => $rsvStatus->id,
            'title'      => $rsvStatus->title,
            'color'      => $rsvStatus->color,
            'pattern'    => $rsvStatus->pattern->value,
            'blocks'     => $rsvStatus->blocks,
            'visibility' => $rsvStatus->visibility->value,
            'role'       => $rsvStatus->role?->value,
            'active'     => $rsvStatus->active,
            'position'   => $rsvStatus->position,
            'used'       => ReservationsWbce::statuses()->isUsed($rsvSection, (int) $rsvStatus->id),
            'url_edit'   => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_statuses' => 1, 'rsv_status' => (int) $rsvStatus->id]),
        ];
        if ($rsvStatus->id === $rsvParam('rsv_status')) {
            $rsvEdit = $rsvStatus;
        }
    }
    ReservationsWbce::twig()->display('modify_statuses.twig', $rsvVars + [
        'statuses'     => $rsvRows,
        'patterns'     => array_column(Reservations\Core\Domain\Pattern::cases(), 'value'),
        'visibilities' => array_column(Reservations\Core\Domain\Visibility::cases(), 'value'),
        'roles'        => array_column(Reservations\Core\Domain\StatusRole::cases(), 'value'),
        'form'         => $rsvOld ?? [
            'status_id'  => $rsvEdit->id ?? 0,
            'title'      => $rsvEdit->title ?? '',
            'color'      => $rsvEdit->color ?? '#85b7eb',
            'pattern'    => $rsvEdit?->pattern->value ?? 'solid',
            'blocks'     => $rsvEdit->blocks ?? true,
            'visibility' => $rsvEdit?->visibility->value ?? 'label',
            'role'       => $rsvEdit?->role?->value ?? '',
            'active'     => $rsvEdit->active ?? true,
            'position'   => $rsvEdit->position ?? count($rsvStatusList),
        ],
        'url_statuses' => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_statuses' => 1]),
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
    $rsvRequests = new Reservations\Core\Storage\Pdo\RequestRepository(ReservationsWbce::db());
    $rsvRequestIds = array_flip($rsvRequests->bookingIds($rsvSection));
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
            'request'    => isset($rsvRequestIds[$rsvBooking->id]),
            'url_edit'   => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_bookings' => (int) $rsvResource->id, 'rsv_booking' => (int) $rsvBooking->id]),
        ];
    }

    $rsvEdit = ReservationsWbce::bookings()->find($rsvSection, $rsvParam('rsv_booking'));
    // contact data of a booking that came in as request; decisions only while it is open
    $rsvRequest = $rsvEdit === null ? null : $rsvRequests->findByBooking($rsvSection, (int) $rsvEdit->id);
    $rsvContact = null;
    if ($rsvRequest !== null && $rsvEdit !== null) {
        $rsvDate = static fn (?DateTimeImmutable $d): string => $d === null ? '' : $d->format(DATE_FORMAT . ' ' . TIME_FORMAT);
        $rsvContact = [
            'name'        => $rsvRequest->name,
            'email'       => $rsvRequest->email,
            'phone'       => $rsvRequest->phone,
            'persons'     => $rsvRequest->persons,
            'message'     => $rsvRequest->message,
            'created'     => $rsvDate($rsvRequest->createdAt),
            'purge_after' => $rsvDate($rsvRequest->purgeAfter),
            'purged'      => $rsvRequest->email === '',
            'open'        => ($rsvStatuses[$rsvEdit->statusId]->role ?? null) === Reservations\Core\Domain\StatusRole::RequestNew,
        ];
    }
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
        'contact'    => $rsvContact,
        'url_new'    => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_bookings' => (int) $rsvResource->id]),
    ]);
    return;
}

$rsvRows = [];
$rsvCounts = ReservationsWbce::bookings()->countCurrentByResource($rsvSection, ReservationsWbce::clock()->now());
$rsvItems = ReservationsWbce::resources()->listBySection($rsvSection);
$rsvNews = ReservationsWbce::newsSection($rsvSection);
$rsvPosts = $rsvNews === null ? [] : ReservationsNewsImg::resolveMany($rsvNews, array_values(array_filter(array_map(static fn ($r) => $r->presentationRef, $rsvItems))));
foreach ($rsvItems as $rsvItem) {
    $rsvRows[] = [
        'id'           => $rsvItem->id,
        'title'        => $rsvItem->title,
        'type'         => $rsvTypes[$rsvItem->typeId]->title ?? '?',
        'active'       => $rsvItem->active,
        'bookings'     => $rsvCounts[(int) $rsvItem->id]['bookings'] ?? 0,
        'requests'     => $rsvCounts[(int) $rsvItem->id]['requests'] ?? 0,
        // null: no description; false: the post is gone, inactive or in another news section
        'description'  => $rsvItem->presentationRef === null ? null : ($rsvPosts[$rsvItem->presentationRef]['title'] ?? false),
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
    'post_id'     => ReservationsNewsImg::idOf($rsvEdit?->presentationRef) ?? 0,
] + ($rsvEdit === null ? [] : ReservationsWbce::rulesToForm($rsvEdit, ReservationsWbce::wholeDays($rsvSection)));

ReservationsWbce::twig()->display('modify_resources.twig', $rsvVars + [
    'resources'    => $rsvRows,
    'news'         => $rsvNews !== null,
    'posts'        => $rsvNews === null ? [] : ReservationsNewsImg::choices($rsvNews),
    'types'        => array_map(static fn ($t) => ['id' => $t->id, 'title' => $t->title], array_values($rsvTypes)),
    'form'         => $rsvForm,
    'whole_days'   => ReservationsWbce::wholeDays($rsvSection),
    'url_settings' => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_settings' => 1]),
    'url_types'    => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_types' => 1]),
    'url_statuses' => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_statuses' => 1]),
]);
