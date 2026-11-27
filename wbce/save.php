<?php
/**
 * Reservations – save.php
 * Handles all backend form posts of a section and redirects back (PRG).
 * @module reservations  @version 0.1.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

// SCRIPT_FILENAME keeps the junction path during development; relative paths and __DIR__ resolve it
require dirname($_SERVER['SCRIPT_FILENAME'], 3) . '/config.php';

// PHP drops a POST above post_max_size completely, FTAN and IDs included: back to the
// section with the reason instead of a security error (as in articles and listings). Nothing is written.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    require_once __DIR__ . '/bootstrap.php';
    $to = ReservationsWbce::modifyUrl((int) ($_GET['page_id'] ?? 0), (int) ($_GET['section_id'] ?? 0), ['rsv_error' => 'post_too_large']);
    // with display_errors on, PHP has printed its warning already and a Location header fails
    headers_sent() ? print('<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($to) . '">') : header('Location: ' . $to);
    exit;
}

$admin_header = false;
require WB_PATH . '/modules/admin.php';
require_once __DIR__ . '/bootstrap.php';

use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\BookingMode;
use Reservations\Core\Domain\CalendarView;
use Reservations\Core\Domain\DisplayMode;
use Reservations\Core\Domain\OpeningHours;
use Reservations\Core\Domain\Pattern;
use Reservations\Core\Domain\Settings;
use Reservations\Core\Domain\Status;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\Type;
use Reservations\Core\Domain\Visibility;
use Reservations\Core\Result\Result;
use Reservations\Core\Support\Color;

$sectionId = (int) $section_id;
$pageId = (int) $page_id;

// admin.php checks the page permissions, but not that the section belongs to that page
if (!$admin->checkFTAN() || (int) $section['page_id'] !== $pageId || $section['module'] !== 'reservations') {
    $admin->print_header();
    $admin->print_error($MESSAGE['GENERIC_SECURITY_ACCESS'], ADMIN_URL . '/pages/modify.php?page_id=' . $pageId);
}

$post = static fn (string $name): string => is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
$userId = (int) $admin->get_user_id();

/**
 * Redirects back; on failure the form input is kept for the next request.
 *
 * @param array<string, int> $params
 * @param array<string, mixed> $old
 */
$finish = static function (Result $result, array $params, array $old, string $success) use ($pageId, $sectionId, $admin): never {
    if ($result->ok) {
        $admin->touchSection($sectionId);
        ReservationsWbce::flash($sectionId, ['success' => $success]);
    } else {
        ReservationsWbce::flash($sectionId, ['errors' => ReservationsWbce::errorMessages($result, $sectionId), 'old' => $old]);
    }
    header('Location: ' . ReservationsWbce::modifyUrl($pageId, $sectionId, $params));
    exit;
};

switch ($post('rsv_action')) {
    case 'resource_save':
        $id = (int) $post('resource_id');
        $old = ['resource_id' => $id, 'title' => $post('title'), 'type_id' => (int) $post('type_id'), 'position' => (int) $post('position'), 'active' => $post('active') === '1', 'description_ref' => $post('description_ref')]
            + ReservationsRules::input($post);
        // opening hours as typed, one text per weekday (edit form in period mode only)
        if (is_array($_POST['hours'] ?? null)) {
            foreach (range(1, 7) as $day) {
                $old['hours'][$day] = is_string($_POST['hours'][$day] ?? null) ? trim($_POST['hours'][$day]) : '';
            }
        }
        $existing = $id > 0 ? ReservationsWbce::resources()->find($sectionId, $id) : null;
        if ($id > 0 && $existing === null) {
            $finish(Result::fail('resource.not_found'), [], $old, '');
        }
        // without a description source the field is not on the form and the stored ref stays
        $presentationRef = $existing?->presentationRef;
        [$adapter, $source] = ReservationsWbce::descriptionSource(ReservationsWbce::settings()->find($sectionId)?->presentationSource) ?? [null, 0];
        if ($adapter !== null) {
            $presentationRef = $old['description_ref'] !== '' ? $old['description_ref'] : null;
            // an orphaned description may stay as it is, a new one must belong to the source
            $unchanged = $presentationRef === $existing?->presentationRef;
            if ($presentationRef !== null && !$unchanged && !isset($adapter::choices($source)[$presentationRef])) {
                $finish(Result::fail('settings.invalid_value'), ['rsv_edit' => $id], $old, '');
            }
        }
        // fields that are not on the form keep their stored values
        $resource = new BookableResource(
            id: $existing?->id,
            sectionId: $sectionId,
            typeId: $old['type_id'],
            title: $old['title'],
            active: $old['active'],
            position: $old['position'],
            presentationRef: $presentationRef,
            capacity: $existing?->capacity,
            minPersons: $existing?->minPersons,
            autoConfirm: $existing->autoConfirm ?? false,
            slotMinutes: $existing->slotMinutes ?? 30,
            minDuration: $existing?->minDuration,
            maxDuration: $existing?->maxDuration,
            bufferBefore: $existing->bufferBefore ?? 0,
            bufferAfter: $existing->bufferAfter ?? 0,
            leadMinMinutes: $existing->leadMinMinutes ?? 0,
            leadMaxDays: $existing?->leadMaxDays,
            checkinTime: $existing?->checkinTime,
            checkoutTime: $existing?->checkoutTime,
        );
        // only the edit form has the booking rules; new objects take those of their type
        $wholeDays = ReservationsWbce::wholeDays($sectionId);
        $hours = null;
        if ($existing !== null) {
            $rules = ReservationsRules::fromPost($post, $wholeDays);
            if ($rules === null) {
                $finish(Result::fail('settings.invalid_value'), ['rsv_edit' => $id], $old, '');
            }
            $resource = $resource->withRules($rules);
            // checked before anything is saved, so the object is not stored with half of the changes
            if (!$wholeDays && isset($old['hours'])) {
                $days = array_map(OpeningHours::parseDay(...), $old['hours']);
                if (in_array(null, $days, true)) {
                    $finish(Result::fail('hours.invalid_format', [], 'hours'), ['rsv_edit' => $id], $old, '');
                }
                $hours = new OpeningHours(array_filter($days));
                $problem = $hours->problem();
                if ($problem !== null) {
                    $finish(Result::fail($problem, [], 'hours'), ['rsv_edit' => $id], $old, '');
                }
            }
        }
        $service = ReservationsWbce::resourceService();
        $result = $existing === null ? $service->create($resource) : $service->update($resource);
        if ($result->ok && $hours !== null) {
            $result = $service->saveHours($sectionId, $id, $hours);
        }
        $finish($result, $result->ok ? [] : ['rsv_edit' => $id], $old, ReservationsWbce::t('MSG_RESOURCE_SAVED'));

    case 'resource_delete':
        $result = ReservationsWbce::resourceService()->delete($sectionId, (int) $post('resource_id'));
        $finish($result, [], [], ReservationsWbce::t('MSG_RESOURCE_DELETED'));

    case 'booking_save':
        $resourceId = (int) $post('resource_id');
        $bookingId = (int) $post('booking_id');
        $old = [
            'booking_id' => $bookingId,
            'start'      => $post('start'),
            'end'        => $post('end'),
            'status_id'  => (int) $post('status_id'),
            'label'      => $post('label'),
            'note'       => $post('note'),
        ];
        $params = ['rsv_bookings' => $resourceId] + ($bookingId > 0 ? ['rsv_booking' => $bookingId] : []);
        $resource = ReservationsWbce::resources()->find($sectionId, $resourceId);
        if ($resource === null) {
            $finish(Result::fail('resource.not_found'), [], $old, '');
        }
        $wholeDays = ReservationsWbce::wholeDays($sectionId);
        try {
            $range = ReservationsWbce::formRange($old['start'], $old['end'], $wholeDays);
        } catch (InvalidArgumentException) {
            $finish(Result::fail('booking.invalid_range'), $params, $old, '');
        }
        $service = ReservationsWbce::bookingService();
        $result = $bookingId > 0
            ? $service->update($sectionId, $bookingId, $resourceId, $range, $old['status_id'], $old['label'], $old['note'], $userId)
            : $service->create($sectionId, $resourceId, $range, $old['status_id'], $old['label'], $old['note'], $userId);
        // a request keeps its contact data relative to the new period or status
        if ($result->ok && $bookingId > 0) {
            ReservationsWbce::requestService()->bookingChanged($sectionId, $bookingId);
        }
        $finish($result, $result->ok ? ['rsv_bookings' => $resourceId] : $params, $old, ReservationsWbce::t('MSG_BOOKING_SAVED'));

    case 'settings_save':
        $old = [
            'display_mode'  => $post('display_mode'),
            'resource_id'   => (int) $post('resource_id'),
            'default_view'  => $post('default_view'),
            'plan_range'    => (int) $post('plan_range'),
            'show_labels'   => $post('show_labels') === '1',
            'past_months'   => (int) $post('past_months'),
            'future_months' => (int) $post('future_months'),
            'booking_mode'  => $post('booking_mode'),
            'description_source' => $post('description_source'),
            'requests_enabled' => $post('requests_enabled') === '1',
            'notify_email'  => trim($post('notify_email')),
            'retention_days' => (int) $post('retention_days'),
            // without captcha_control the form has no captcha field: the stored choice stays, so the
            // warning remains and the captcha returns with the module
            'captcha'       => ReservationsWbce::captchaAvailable()
                ? $post('captcha') === '1'
                : (ReservationsWbce::settings()->find($sectionId)->captcha ?? false),
        ];
        $mode = DisplayMode::tryFrom($old['display_mode']);
        $view = CalendarView::tryFrom($old['default_view']);
        $bookingMode = BookingMode::tryFrom($old['booking_mode']);
        $sourceValid = $old['description_source'] === ''
            || array_filter(ReservationsWbce::descriptionSources(), static fn (array $s): bool => isset($s[$old['description_source']])) !== [];
        if ($mode === null || $view === null || $bookingMode === null || !$sourceValid) {
            $finish(Result::fail('settings.invalid_value'), ['rsv_settings' => 1], $old, '');
        }
        $retentionBefore = ReservationsWbce::settings()->find($sectionId)?->retentionDays;
        $result = ReservationsWbce::settingsService()->save(new Settings(
            sectionId: $sectionId,
            displayMode: $mode,
            defaultView: $view,
            planRange: $old['plan_range'],
            showLabels: $old['show_labels'],
            pastMonths: $old['past_months'],
            futureMonths: $old['future_months'],
            resourceId: $old['resource_id'] > 0 ? $old['resource_id'] : null,
            bookingMode: $bookingMode,
            presentationSource: $old['description_source'] !== '' ? $old['description_source'] : null,
            requestsEnabled: $old['requests_enabled'],
            notifyEmail: $old['notify_email'],
            retentionDays: $old['retention_days'],
            captcha: $old['captcha'],
        ));
        if ($result->ok && $retentionBefore !== $old['retention_days']) {
            ReservationsWbce::requestService()->retentionChanged($sectionId);
        }
        $finish($result, $result->ok ? [] : ['rsv_settings' => 1], $old, ReservationsWbce::t('MSG_SETTINGS_SAVED'));

    case 'status_save':
        $id = (int) $post('status_id');
        $old = [
            'status_id'  => $id,
            'title'      => $post('title'),
            'color'      => strtolower($post('color')),
            'pattern'    => $post('pattern'),
            'blocks'     => $post('blocks') === '1',
            'visibility' => $post('visibility'),
            'role'       => $post('role'),
            'active'     => $post('active') === '1',
            'position'   => (int) $post('position'),
        ];
        $params = ['rsv_statuses' => 1, 'rsv_status' => $id];
        $pattern = Pattern::tryFrom($old['pattern']);
        $visibility = Visibility::tryFrom($old['visibility']);
        $role = $old['role'] === '' ? null : StatusRole::tryFrom($old['role']);
        if ($pattern === null || $visibility === null || ($old['role'] !== '' && $role === null)) {
            $finish(Result::fail('settings.invalid_value'), $params, $old, '');
        }
        $status = new Status($id > 0 ? $id : null, $sectionId, $old['title'], $old['blocks'], $visibility, $old['color'], $pattern, $role, $old['active'], $old['position']);
        $service = ReservationsWbce::statusService();
        $result = $id > 0 ? $service->update($status) : $service->create($status);
        // patterns must stand out from the white calendar: 3:1 for graphics (WCAG 1.4.11)
        $faint = $result->ok && Color::contrastRatio($old['color'], '#ffffff') < 3;
        $finish($result, $result->ok ? ['rsv_statuses' => 1] : $params, $old, ReservationsWbce::t('MSG_STATUS_SAVED') . ($faint ? ' ' . ReservationsWbce::t('MSG_STATUS_FAINT') : ''));

    case 'status_delete':
        $result = ReservationsWbce::statusService()->delete($sectionId, (int) $post('status_id'));
        $finish($result, ['rsv_statuses' => 1], [], ReservationsWbce::t('MSG_STATUS_DELETED'));

    case 'type_save':
        $id = (int) $post('type_id');
        $old = ['type_id' => $id, 'title' => $post('title'), 'position' => (int) $post('position')] + ReservationsRules::input($post);
        $existing = $id > 0 ? ReservationsWbce::types()->find($sectionId, $id) : null;
        $rules = ReservationsRules::fromPost($post, ReservationsWbce::wholeDays($sectionId));
        if ($rules === null) {
            $finish(Result::fail('settings.invalid_value'), ['rsv_types' => 1, 'rsv_type' => $id], $old, '');
        }
        // rules missing on the form (buffers in day mode) keep their stored defaults
        $type = new Type($existing?->id, $sectionId, $old['title'], array_replace($existing->defaults ?? [], $rules), $old['position']);
        $service = ReservationsWbce::typeService();
        $result = $id > 0 ? $service->update($type) : $service->create($type);
        $finish($result, ['rsv_types' => 1] + ($result->ok ? [] : ['rsv_type' => $id]), $old, ReservationsWbce::t('MSG_TYPE_SAVED'));

    case 'type_delete':
        $result = ReservationsWbce::typeService()->delete($sectionId, (int) $post('type_id'));
        $finish($result, ['rsv_types' => 1], [], ReservationsWbce::t('MSG_TYPE_DELETED'));

    case 'booking_delete':
        $resourceId = (int) $post('resource_id');
        ReservationsWbce::bookings()->delete($sectionId, (int) $post('booking_id'));
        $finish(Result::ok(), ['rsv_bookings' => $resourceId], [], ReservationsWbce::t('MSG_BOOKING_DELETED'));

    case 'request_confirm':
    case 'request_reject':
        $resourceId = (int) $post('resource_id');
        $bookingId = (int) $post('booking_id');
        $confirm = $post('rsv_action') === 'request_confirm';
        $service = ReservationsWbce::requestService();
        $result = $confirm ? $service->confirm($sectionId, $bookingId, $userId) : $service->reject($sectionId, $bookingId, $userId);
        // the decision stands even if the mail fails; the admin is told to contact the person
        $mailed = $result->ok && ReservationsMail::request($sectionId, $bookingId, $confirm ? 'confirmed' : 'rejected');
        $message = ReservationsWbce::t($confirm ? 'MSG_REQUEST_CONFIRMED' : 'MSG_REQUEST_REJECTED') . ($mailed ? '' : ' ' . ReservationsWbce::t('MSG_MAIL_FAILED'));
        $finish($result, ['rsv_bookings' => $resourceId, 'rsv_booking' => $bookingId], [], $message);

    default:
        header('Location: ' . ReservationsWbce::modifyUrl($pageId, $sectionId));
        exit;
}
