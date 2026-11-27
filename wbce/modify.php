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
// WBCE has no cron; expired contact data of requests is emptied on backend calls and page views
ReservationsWbce::requestService()->purgeExpired();
// URL parameters only count for the section they were meant for
$rsvParam = static fn (string $name): int => (int) ($_GET['rsv_section'] ?? 0) === $rsvSection ? (int) ($_GET[$name] ?? 0) : 0;
$rsvFlash = ReservationsWbce::takeFlash($rsvSection);
$rsvOld = is_array($rsvFlash['old'] ?? null) ? $rsvFlash['old'] : null;

$rsvTypes = [];
foreach (ReservationsWbce::types()->listBySection($rsvSection) as $rsvType) {
    $rsvTypes[(int) $rsvType->id] = $rsvType;
}

// open requests of all objects, for the number at the tab "Objects"
$rsvOpen = array_sum(array_column(ReservationsWbce::bookings()->countCurrentByResource($rsvSection, ReservationsWbce::clock()->now()), 'requests'));

$rsvVars = [
    'page_id'    => $rsvPage,
    'section_id' => $rsvSection,
    // the IDs in the URL as well: a POST above post_max_size arrives without its fields (save.php)
    'action'     => WB_URL . '/modules/reservations/save.php?' . http_build_query(['page_id' => $rsvPage, 'section_id' => $rsvSection], '', '&'),
    'errors'     => array_merge(
        $rsvFlash['errors'] ?? [],
        ($_GET['rsv_error'] ?? '') === 'post_too_large' && (int) ($_GET['rsv_section'] ?? 0) === $rsvSection
            ? [ReservationsWbce::t('ERR_POST_TOO_LARGE', (string) ini_get('post_max_size'))] : []
    ),
    'open_requests' => (int) $rsvOpen,
    // the tabs (twig/_head.twig)
    'url_settings' => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_settings' => 1]),
    'url_types'    => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_types' => 1]),
    'url_statuses' => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_statuses' => 1]),
    'success'    => $rsvFlash['success'] ?? '',
    'url_list'   => ReservationsWbce::modifyUrl($rsvPage, $rsvSection),
    // the static help exists in some languages only
    'help_url'   => WB_URL . '/modules/reservations/help/' . (is_dir(__DIR__ . '/help/' . strtolower(LANGUAGE)) ? strtolower(LANGUAGE) : 'en') . '/',
    // grid choices of the booking rules (objects and types)
    'slot_choices' => Reservations\Core\Service\ResourceService::SLOT_MINUTES,
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
        'description_source' => $rsvSettings->presentationSource ?? '',
        'requests_enabled' => $rsvSettings->requestsEnabled,
        'notify_email'  => $rsvSettings->notifyEmail,
        'retention_days' => $rsvSettings->retentionDays,
        'captcha'       => $rsvSettings->captcha,
    ];
    ReservationsWbce::twig()->display('modify_settings.twig', $rsvVars + [
        'tab'       => 'settings',
        'form'      => $rsvForm,
        'resources' => array_map(static fn ($r) => ['id' => $r->id, 'title' => $r->title], ReservationsWbce::resources()->listBySection($rsvSection)),
        'first'     => $rsvActive[0]->title ?? null,
        'months_max' => Reservations\Core\Service\SettingsService::MONTHS_MAX,
        'retention_max' => Reservations\Core\Service\SettingsService::RETENTION_MAX,
        'description_sources' => ReservationsWbce::descriptionSources(),
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
        'tab'          => 'statuses',
        'statuses'     => $rsvRows,
        'patterns'     => array_column(Reservations\Core\Domain\Pattern::cases(), 'value'),
        'visibilities' => array_column(Reservations\Core\Domain\Visibility::cases(), 'value'),
        'roles'        => array_column(Reservations\Core\Domain\StatusRole::cases(), 'value'),
        'form'         => $rsvOld ?? [
            'status_id'  => $rsvEdit->id ?? 0,
            'title'      => $rsvEdit->title ?? '',
            'color'      => $rsvEdit->color ?? '#4a8fd4',
            'pattern'    => $rsvEdit?->pattern->value ?? 'solid',
            'blocks'     => $rsvEdit->blocks ?? true,
            'visibility' => $rsvEdit?->visibility->value ?? 'label',
            'role'       => $rsvEdit?->role?->value ?? '',
            'active'     => $rsvEdit->active ?? true,
            'position'   => $rsvEdit->position ?? count($rsvStatusList),
        ],
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
    $rsvWholeDays = ReservationsWbce::wholeDays($rsvSection);
    // the defaults shown as the rules of a resource that would take them
    $rsvDefaults = (new Reservations\Core\Domain\BookableResource(null, $rsvSection, 0, ''))->withRules($rsvEdit->defaults ?? []);
    ReservationsWbce::twig()->display('modify_types.twig', $rsvVars + [
        'tab'        => 'types',
        'types'      => $rsvRows,
        'form'       => $rsvOld ?? [
            'type_id'  => $rsvEdit->id ?? 0,
            'title'    => $rsvEdit->title ?? '',
            'position' => $rsvEdit->position ?? count($rsvTypes),
        ] + ReservationsRules::toForm($rsvDefaults, $rsvWholeDays),
        'whole_days' => $rsvWholeDays,
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
    $rsvRequests = ReservationsWbce::requests();
    $rsvRequestIds = array_flip($rsvRequests->bookingIds($rsvSection));
    $rsvRows = [];
    // no upper limit: a booking typed with a wrong year must stay visible and deletable;
    // back at least as far as the website shows, or a wrong booking there could not be found here
    $rsvBack = max(12, ReservationsWbce::settings()->find($rsvSection)->pastMonths ?? 12);
    $rsvWindow = new Reservations\Core\Domain\TimeRange($rsvToday->modify('-' . $rsvBack . ' months'), $rsvToday->modify('+100 years'));
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
        'tab'        => 'objects',
        'resource'   => ['id' => $rsvResource->id, 'title' => $rsvResource->title],
        'whole_days' => $rsvWholeDays,
        'bookings'   => $rsvRows,
        'statuses'   => array_map(static fn ($s) => ['id' => $s->id, 'title' => $s->title], array_values(array_filter($rsvStatuses, static fn ($s) => $s->active || $s->id === (int) $rsvForm['status_id']))),
        'form'       => $rsvForm,
        'contact'    => $rsvContact,
        'url_new'    => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_bookings' => (int) $rsvResource->id]),
    ]);
    return;
}

$rsvRows = [];
$rsvCounts = ReservationsWbce::bookings()->countCurrentByResource($rsvSection, ReservationsWbce::clock()->now());
$rsvItems = ReservationsWbce::resources()->listBySection($rsvSection);
[$rsvAdapter, $rsvSource] = ReservationsWbce::descriptionSource(ReservationsWbce::settings()->find($rsvSection)?->presentationSource) ?? [null, 0];
$rsvPosts = $rsvAdapter === null ? [] : $rsvAdapter::resolveMany($rsvSource, array_values(array_filter(array_map(static fn ($r) => $r->presentationRef, $rsvItems))));
foreach ($rsvItems as $rsvItem) {
    $rsvRows[] = [
        'id'           => $rsvItem->id,
        'title'        => $rsvItem->title,
        'type'         => $rsvTypes[$rsvItem->typeId]->title ?? '?',
        'position'     => $rsvItem->position,
        'active'       => $rsvItem->active,
        'bookings'     => $rsvCounts[(int) $rsvItem->id]['bookings'] ?? 0,
        'requests'     => $rsvCounts[(int) $rsvItem->id]['requests'] ?? 0,
        // null: no description; false: it is gone, not visible or in another section
        'description'  => $rsvItem->presentationRef === null ? null : ($rsvPosts[$rsvItem->presentationRef]['title'] ?? false),
        'url_edit'     => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_edit' => (int) $rsvItem->id]),
        'url_bookings' => ReservationsWbce::modifyUrl($rsvPage, $rsvSection, ['rsv_bookings' => (int) $rsvItem->id]),
    ];
}

$rsvEdit = ReservationsWbce::resources()->find($rsvSection, $rsvParam('rsv_edit'));
$rsvHours = $rsvEdit === null ? [] : ReservationsWbce::hours()->find($rsvSection, (int) $rsvEdit->id)->days;
$rsvForm = $rsvOld ?? [
    'resource_id' => $rsvEdit->id ?? 0,
    'title'       => $rsvEdit->title ?? '',
    'type_id'     => $rsvEdit->typeId ?? array_key_first($rsvTypes),
    'position'    => $rsvEdit->position ?? count($rsvItems),
    'active'      => $rsvEdit->active ?? true,
    'description_ref' => $rsvEdit->presentationRef ?? '',
] + ($rsvEdit === null ? [] : ReservationsRules::toForm($rsvEdit, ReservationsWbce::wholeDays($rsvSection)) + [
    'hours' => array_map(
        static fn (int $day): string => Reservations\Core\Domain\OpeningHours::formatDay($rsvHours[$day] ?? []),
        array_combine(range(1, 7), range(1, 7)),
    ),
]);
// weekday names in the language of the backend, 1 = Monday (5 January 2026 is one)
$rsvDayName = new IntlDateFormatter(strtolower(LANGUAGE), IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'EEEE');
$rsvWeekdays = [];
foreach (range(1, 7) as $rsvDay) {
    $rsvWeekdays[$rsvDay] = (string) $rsvDayName->format(new DateTimeImmutable(sprintf('2026-01-%02d', 4 + $rsvDay)));
}

ReservationsWbce::twig()->display('modify_resources.twig', $rsvVars + [
    'tab'          => 'objects',
    'resources'    => $rsvRows,
    'described'    => $rsvAdapter !== null,
    'descriptions' => $rsvAdapter === null ? [] : $rsvAdapter::choices($rsvSource),
    'types'        => array_map(static fn ($t) => ['id' => $t->id, 'title' => $t->title], array_values($rsvTypes)),
    'form'         => $rsvForm,
    'whole_days'   => ReservationsWbce::wholeDays($rsvSection),
    'weekdays'     => $rsvWeekdays,
]);
