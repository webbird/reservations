<?php
/**
 * Reservations – save.php
 * Handles all backend form posts of a section and redirects back (PRG).
 * @module reservations  @version 0.1.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

// SCRIPT_FILENAME keeps the junction path during development; relative paths and __DIR__ resolve it
require dirname($_SERVER['SCRIPT_FILENAME'], 3) . '/config.php';

$admin_header = false;
require WB_PATH . '/modules/admin.php';
require_once __DIR__ . '/bootstrap.php';

use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\BookingMode;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Result\Result;

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
        ReservationsWbce::flash($sectionId, ['errors' => ReservationsWbce::errorMessages($result), 'old' => $old]);
    }
    header('Location: ' . ReservationsWbce::modifyUrl($pageId, $sectionId, $params));
    exit;
};

switch ($post('rsv_action')) {
    case 'resource_save':
        $id = (int) $post('resource_id');
        $old = ['resource_id' => $id, 'title' => $post('title'), 'type_id' => (int) $post('type_id'), 'active' => $post('active') === '1'];
        $existing = $id > 0 ? ReservationsWbce::resources()->find($sectionId, $id) : null;
        if ($id > 0 && $existing === null) {
            $finish(Result::fail('resource.not_found'), [], $old, '');
        }
        // fields that are not on the form keep their stored values
        $resource = new BookableResource(
            id: $existing?->id,
            sectionId: $sectionId,
            typeId: $old['type_id'],
            title: $old['title'],
            color: $existing?->color,
            active: $old['active'],
            position: $existing->position ?? 0,
            presentationRef: $existing?->presentationRef,
            capacity: $existing?->capacity,
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
        $service = ReservationsWbce::resourceService();
        $result = $existing === null ? $service->create($resource) : $service->update($resource);
        $finish($result, $result->ok ? [] : ['rsv_edit' => $id], $old, ReservationsWbce::t('MSG_RESOURCE_SAVED'));

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
        $wholeDays = ReservationsWbce::types()->find($sectionId, $resource->typeId)?->bookingMode === BookingMode::Day;
        try {
            $range = $wholeDays
                ? TimeRange::fromLocal($old['start'] . ' 00:00', $old['end'] . ' 00:00', ReservationsWbce::timezone())
                : TimeRange::fromLocal(strtr($old['start'], 'T', ' '), strtr($old['end'], 'T', ' '), ReservationsWbce::timezone());
        } catch (InvalidArgumentException) {
            $finish(Result::fail('booking.invalid_range'), $params, $old, '');
        }
        $service = ReservationsWbce::bookingService();
        $result = $bookingId > 0
            ? $service->update($sectionId, $bookingId, $resourceId, $range, $old['status_id'], $old['label'], $old['note'], $userId)
            : $service->create($sectionId, $resourceId, $range, $old['status_id'], $old['label'], $old['note'], $userId);
        $finish($result, $result->ok ? ['rsv_bookings' => $resourceId] : $params, $old, ReservationsWbce::t('MSG_BOOKING_SAVED'));

    case 'booking_delete':
        $resourceId = (int) $post('resource_id');
        ReservationsWbce::bookings()->delete($sectionId, (int) $post('booking_id'));
        $finish(Result::ok(), ['rsv_bookings' => $resourceId], [], ReservationsWbce::t('MSG_BOOKING_DELETED'));

    default:
        header('Location: ' . ReservationsWbce::modifyUrl($pageId, $sectionId));
        exit;
}
