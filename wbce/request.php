<?php
/**
 * Reservations – request.php
 * Receives the booking request form of the frontend and redirects back (PRG).
 * Spam protection: FTAN and honeypot always, the captcha if the section uses it.
 * @module reservations  @version 0.5.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

// SCRIPT_FILENAME keeps the junction path during development; relative paths and __DIR__ resolve it
require dirname($_SERVER['SCRIPT_FILENAME'], 3) . '/config.php';
require_once __DIR__ . '/bootstrap.php';

use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Result\Result;

$post = static fn (string $name): string => is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
$sectionId = (int) $post('section_id');

// back to the page with the form: only a path below WB_URL, the host always comes from WB_URL
$base = (string) parse_url(WB_URL, PHP_URL_PATH);
$return = strtok($post('return'), '#') ?: '';
if (!str_starts_with($return, $base . '/') || str_starts_with($return, '//') || preg_match('/[\r\n\\\\]/', $return) === 1) {
    $return = $base . '/';
}
$location = preg_replace('#^(https?://[^/]+).*$#', '$1', WB_URL) . $return . '#rsv-request-' . $sectionId;

$module = $GLOBALS['database']->fetchValue('SELECT `module` FROM `{TP}sections` WHERE `section_id` = ?', [$sectionId]);
$settings = ReservationsWbce::settings()->find($sectionId);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $module !== 'reservations' || $settings === null) {
    header('Location: ' . $location);
    exit;
}

$old = [
    'resource_id' => (int) $post('resource_id'),
    'start'       => $post('start'),
    'end'         => $post('end'),
    'name'        => $post('name'),
    'email'       => $post('email'),
    'phone'       => $post('phone'),
    'persons'     => $post('persons'),
    'message'     => $post('message'),
    'privacy'     => $post('privacy') === '1',
];
$finish = static function (Result $result) use ($sectionId, $location, $old): never {
    $_SESSION['mod_reservations_request'][$sectionId] = $result->ok
        ? ['success' => ReservationsWbce::t($result->value === true ? 'MSG_REQUEST_BOOKED' : 'MSG_REQUEST_SENT')]
        : ['errors' => ReservationsWbce::errorMessages($result, $sectionId), 'field' => $result->errors[0]['field'] ?? null, 'old' => $old];
    header('Location: ' . $location);
    exit;
};

$wb = new Frontend();
if (!$wb->checkFTAN()) {
    $finish(Result::fail('request.expired'));
}
// bots fill every field and send at once; people need more than two seconds
$shown = $_SESSION['mod_reservations_form_time'][$sectionId] ?? null;
if ($post('rsv_hp') !== '' || !is_int($shown) || time() - $shown < 2) {
    $finish(Result::fail('request.rejected'));
}
if ($settings->captcha && ReservationsWbce::captchaAvailable() && !Captcha::verify($post('captcha'), 'rsv' . $sectionId)) {
    $finish(Result::fail('request.captcha', [], 'captcha'));
}

$tz = ReservationsWbce::timezone();
try {
    // day mode sends dates, period mode datetime-local values (2026-11-02T14:00)
    $range = ReservationsWbce::wholeDays($sectionId)
        ? TimeRange::fromLocal($old['start'] . ' 00:00', $old['end'] . ' 00:00', $tz)
        : TimeRange::fromLocal(strtr($old['start'], 'T', ' '), strtr($old['end'], 'T', ' '), $tz);
} catch (InvalidArgumentException) {
    $finish(Result::fail('booking.invalid_range', [], 'range'));
}

$service = ReservationsWbce::requestService();
$service->purgeExpired();
$result = $service->submit(
    $sectionId,
    $old['resource_id'],
    $range,
    $old['name'],
    $old['email'],
    $old['phone'],
    $old['persons'] === '' ? null : (int) $old['persons'],
    $old['message'],
    $old['privacy'],
);
if ($result->firstErrorKey() === 'booking.overlap') {
    // the conflicting dates may belong to hidden requests; visitors only learn that the period is taken
    $result = Result::fail('request.unavailable', [], 'range');
}
if ($result->ok) {
    unset($_SESSION['mod_reservations_form_time'][$sectionId]);
    $result = Result::ok(ReservationsWbce::resources()->find($sectionId, $old['resource_id'])->autoConfirm ?? false);
}
$finish($result);
