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

use Reservations\Core\Result\Result;
use Reservations\Core\Support\ReturnUrl;

$post = static fn (string $name): string => is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
$sectionId = (int) $post('section_id');

// back to the page with the form; ReturnUrl keeps the redirect on this site
$location = ReturnUrl::resolve(WB_URL, $post('return'), 'rsv-request-' . $sectionId);
// after a request the page is marked: the link "another request" leads to the unmarked page and so
// loads it again (a link that differs only in its anchor just scrolls); view.php drops the mark from "return"
$sent = ReturnUrl::resolve(WB_URL, $post('return') . (str_contains($post('return'), '?') ? '&' : '?') . 'rsv_sent=' . $sectionId, 'rsv-request-' . $sectionId);

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
/**
 * Redirects back; $after runs once the visitor already has the redirect (mails, which may be slow).
 */
$finish = static function (Result $result, ?callable $after = null) use ($sectionId, $location, $sent, $old): never {
    $_SESSION['mod_reservations_request'][$sectionId] = $result->ok
        ? ['success' => ReservationsWbce::t($result->value === true ? 'MSG_REQUEST_BOOKED' : 'MSG_REQUEST_SENT')]
        : ['errors' => ReservationsWbce::errorMessages($result, $sectionId), 'field' => $result->errors[0]['field'] ?? null, 'old' => $old];
    header('Location: ' . ($result->ok ? $sent : $location));
    if ($after !== null) {
        // the next page reads this session; an open session would keep it waiting for $after
        session_write_close();
        ignore_user_abort(true);
        header('Connection: close');
        header('Content-Length: 0');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
        // PHP-FPM ends the response here; mod_php relies on Content-Length and flush()
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $after();
    }
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

try {
    $range = ReservationsWbce::formRange($old['start'], $old['end'], ReservationsWbce::wholeDays($sectionId));
} catch (InvalidArgumentException) {
    $finish(Result::fail('booking.invalid_range', [], 'range'));
}

$service = ReservationsWbce::requestService();
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
    // for the rate limit; behind a reverse proxy all visitors share its address (X-Forwarded-For can be forged)
    is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '',
);
if ($result->firstErrorKey() === 'booking.overlap') {
    // the conflicting dates may belong to hidden requests; visitors only learn that the period is taken
    $result = Result::fail('request.unavailable', [], 'range');
}
if ($result->ok && is_int($result->value)) {
    unset($_SESSION['mod_reservations_form_time'][$sectionId]);
    $bookingId = $result->value;
    $booked = ReservationsWbce::resources()->find($sectionId, $old['resource_id'])->autoConfirm ?? false;
    // the request is stored either way; a failed mail is logged and the backend still shows the request
    $finish(Result::ok($booked), static fn () => ReservationsMail::request($sectionId, $bookingId, $booked ? 'booked' : 'received'));
}
$finish($result);
