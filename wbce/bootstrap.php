<?php
/**
 * Reservations – bootstrap.php
 * Loads the CMS-independent core and wires it to WBCE.
 * @module reservations  @version 0.1.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\BookingMode;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Port\Clock;
use Reservations\Core\Result\Result;
use Reservations\Core\Service\BookingService;
use Reservations\Core\Service\RequestService;
use Reservations\Core\Service\ResourceService;
use Reservations\Core\Service\SettingsService;
use Reservations\Core\Service\StatusService;
use Reservations\Core\Service\TypeService;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\Connection;
use Reservations\Core\Storage\Pdo\HoursRepository;
use Reservations\Core\Storage\Pdo\RequestRepository;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\SettingsRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;
use Reservations\Core\Storage\Pdo\TypeRepository;
use Reservations\Core\Storage\Schema;
use Reservations\Core\Storage\SectionSetup;

// Built module: core/src lies inside the module. Development: modules/reservations
// is a junction to the repository's wbce/, realpath() resolves it to reach ../core.
define('RESERVATIONS_CORE', is_dir(__DIR__ . '/core/src')
    ? __DIR__ . '/core/src'
    : dirname((string) realpath(__DIR__)) . '/core/src');

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Reservations\\Core\\')) {
        $file = RESERVATIONS_CORE . '/' . strtr(substr($class, 18), '\\', '/') . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

Lang::loadLanguage(__DIR__);
require_once __DIR__ . '/news_img.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/rules.php';

/**
 * Entry point of the wrapper: database, texts and templates, repositories and
 * services of the core, section setup and droplet, messages and small helpers.
 */
final class ReservationsWbce
{
    public const NS = 'MOD_RESERVATIONS';

    /** @var array<string, object> */
    private static array $services = [];

    // database, time, texts, templates

    public static function db(): Connection
    {
        return self::$services['db'] ??= new Connection(
            $GLOBALS['database']->getPDO(),
            TABLE_PREFIX . 'mod_reservations_',
            self::timezone(),
        );
    }

    /**
     * WBCE only knows a UTC offset (no DST), so the zone comes from config.php.
     */
    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone(defined('RESERVATIONS_TIMEZONE') ? RESERVATIONS_TIMEZONE : 'Europe/Berlin');
    }

    public static function clock(): Clock
    {
        return new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('now', ReservationsWbce::timezone());
            }
        };
    }

    public static function t(string $key, string|int ...$args): string
    {
        $text = Lang::get(self::NS, $key, $key);
        return $args === [] ? $text : vsprintf($text, $args);
    }

    /**
     * WBCE's getTwig() turns autoescaping off; our templates escape by default.
     */
    public static function twig(): \Twig\Environment
    {
        $twig = getTwig(__DIR__ . '/twig/');
        $twig->getExtension(\Twig\Extension\EscaperExtension::class)->setDefaultStrategy('html');
        $twig->addFunction(new \Twig\TwigFunction('t', self::t(...)));
        return $twig;
    }

    // repositories, one per request

    public static function statuses(): StatusRepository
    {
        return self::$services['statuses'] ??= new StatusRepository(self::db());
    }

    public static function types(): TypeRepository
    {
        return self::$services['types'] ??= new TypeRepository(self::db());
    }

    public static function resources(): ResourceRepository
    {
        return self::$services['resources'] ??= new ResourceRepository(self::db());
    }

    public static function bookings(): BookingRepository
    {
        return self::$services['bookings'] ??= new BookingRepository(self::db());
    }

    public static function requests(): RequestRepository
    {
        return self::$services['requests'] ??= new RequestRepository(self::db());
    }

    public static function settings(): SettingsRepository
    {
        return self::$services['settings'] ??= new SettingsRepository(self::db());
    }

    public static function hours(): HoursRepository
    {
        return self::$services['hours'] ??= new HoursRepository(self::db());
    }

    // services of the core

    public static function settingsService(): SettingsService
    {
        return new SettingsService(self::settings(), self::resources(), self::bookings());
    }

    public static function resourceService(): ResourceService
    {
        return new ResourceService(self::db(), self::resources(), self::types(), self::hours(), self::bookings(), self::clock());
    }

    public static function statusService(): StatusService
    {
        return new StatusService(self::db(), self::statuses(), self::bookings(), self::resources(), self::settings());
    }

    public static function typeService(): TypeService
    {
        return new TypeService(self::db(), self::types());
    }

    public static function bookingService(): BookingService
    {
        return new BookingService(self::db(), self::resources(), self::settings(), self::statuses(), self::bookings(), self::clock());
    }

    public static function requestService(): RequestService
    {
        return new RequestService(
            self::bookingService(),
            self::settings(),
            self::resources(),
            self::statuses(),
            self::bookings(),
            self::requests(),
            self::hours(),
            self::clock(),
        );
    }

    public static function sectionSetup(): SectionSetup
    {
        return new SectionSetup(self::db(), self::statuses(), self::types());
    }

    // section setup and droplet

    /**
     * Creates missing tables and the section's defaults (statuses, a type, settings);
     * a new section starts with the captcha if captcha_control is there. Also imports
     * the droplet if it is missing. Idempotent; used by add.php and as fallback in
     * modify.php, because WBCE can register a module without running install.php
     * (addon reload) and manual_install.php refuses junctioned module dirs.
     */
    public static function ensureSection(int $sectionId, int $pageId): void
    {
        Schema::install(self::db());
        $titles = [];
        foreach (array_keys(SectionSetup::DEFAULT_TITLES) as $key) {
            $titles[$key] = self::t('SEED_' . strtoupper($key));
        }
        // new sections start with the captcha when it is there: without it bots can block the calendar with fake requests
        if (self::sectionSetup()->run($sectionId, $pageId, $titles) && self::captchaAvailable()) {
            self::db()->update('settings', ['captcha' => true], ['section_id' => $sectionId]);
        }
        $droplets = is_file(WB_PATH . '/modules/droplets/functions.inc.php');
        if ($droplets && $GLOBALS['database']->fetchValue("SELECT `id` FROM `{TP}mod_droplets` WHERE `name` = 'reservations'") === '') {
            self::installDroplet();
        }
    }

    /**
     * Imports droplets/reservations.php into the droplets module; replaces an
     * older version of the droplet with the same name.
     */
    public static function installDroplet(): void
    {
        $functions = WB_PATH . '/modules/droplets/functions.inc.php';
        if (!is_file($functions)) {
            return;
        }
        // included here, its file-level variables ($module_directory …) stay local to this method
        require_once $functions;
        importDropletFromFile(__DIR__ . '/droplets/reservations.php');
    }

    /**
     * [[reservations]]: the object whose description is the current news_img post;
     * ?resource=ID: one object as month calendar; ?section=ID: a whole section.
     * Returns '' when nothing fits; admins get the reason as an HTML comment.
     */
    public static function droplet(?int $section, ?int $resource): string
    {
        $item = null;
        if ($resource !== null) {
            $item = self::resources()->findById($resource);
        } elseif ($section === null && defined('POST_ID')) {
            $item = self::resources()->findByPresentationRef(ReservationsNewsImg::PREFIX . (int) POST_ID);
        }
        $sectionId = $item->sectionId ?? $section;
        $reason = match (true) {
            $resource === null && $section === null && !defined('POST_ID') => 'no resource, section or news_img post',
            $sectionId === null || ($item !== null && !$item->active) => 'no active object found',
            self::settings()->find($sectionId) === null => 'section ' . $sectionId . ' is not a reservations section',
            default => null,
        };
        if ($reason !== null) {
            $admin = isset($GLOBALS['wb']) && $GLOBALS['wb']->isAdmin();
            return $admin ? '<!-- reservations droplet: ' . htmlspecialchars($reason) . ' -->' : '';
        }

        // WBCE loads these for sections of the module, a droplet on another page has to ask for them
        I::insertCssFile(WB_URL . '/modules/reservations/assets/frontend.css', 'head_late', [], 'mod_reservations_frontend');
        I::insertJsFile(WB_URL . '/modules/reservations/assets/frontend_body.js', 'body_late', [], 'mod_reservations_frontend_js');
        $section_id = $sectionId;
        $rsvOnlyResource = $item?->id;
        // inside its own description a link to that description would lead nowhere new
        $rsvNoLinks = $item !== null && defined('POST_ID') && $item->presentationRef === ReservationsNewsImg::PREFIX . (int) POST_ID;
        ob_start();
        try {
            require __DIR__ . '/view.php';
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    // messages

    /**
     * Error keys from the core ("booking.overlap") map to ERR_BOOKING_OVERLAP.
     * Conflicts come as booking rows (booking.overlap) or as pairs of booking
     * ids (status.blocking_conflicts).
     *
     * @return list<string>
     */
    public static function errorMessages(Result $result, int $sectionId): array
    {
        $messages = [];
        foreach ($result->errors as $error) {
            $message = self::t('ERR_' . strtoupper(strtr($error['key'], '.', '_')));
            // numeric limits fill the placeholders in this order: min, max
            $limits = array_values(array_filter([$error['params']['min'] ?? null, $error['params']['max'] ?? null], 'is_int'));
            if ($limits !== []) {
                $message = vsprintf($message, $limits);
            }
            $pairs = [];
            foreach ((array) ($error['params']['conflicts'] ?? []) as $conflict) {
                if (is_array($conflict) && is_string($conflict['start'] ?? null) && is_string($conflict['end'] ?? null)) {
                    $range = new TimeRange(self::db()->fromDb($conflict['start']), self::db()->fromDb($conflict['end']));
                    $message .= ' ' . self::formatRange($range->start, $range->end, $range->startsAndEndsAtMidnight());
                } elseif (is_array($conflict) && is_int($conflict[0] ?? null) && is_int($conflict[1] ?? null)) {
                    $pairs[] = self::conflictPair($sectionId, $conflict[0], $conflict[1]);
                }
            }
            $messages[] = $pairs === [] ? $message : $message . ' ' . implode('; ', $pairs);
        }
        return $messages;
    }

    /**
     * "FeWo 1: 01.11.2026 – 08.11.2026 / 05.11.2026 – 06.11.2026"
     */
    private static function conflictPair(int $sectionId, int $a, int $b): string
    {
        $parts = [];
        $resourceId = 0;
        foreach ([$a, $b] as $id) {
            $booking = self::bookings()->find($sectionId, $id);
            if ($booking !== null) {
                $resourceId = $booking->resourceId;
                $parts[] = self::formatRange($booking->range->start, $booking->range->end, $booking->range->startsAndEndsAtMidnight());
            }
        }
        return (self::resources()->find($sectionId, $resourceId)->title ?? '?') . ': ' . implode(' / ', $parts);
    }

    /**
     * A period from a form: dates by day, datetime-local values (2026-11-02T14:00) by period.
     *
     * @throws InvalidArgumentException if a value is no valid moment or the end is not after the start
     */
    public static function formRange(string $start, string $end, bool $wholeDays): TimeRange
    {
        return $wholeDays
            ? TimeRange::fromLocal($start . ' 00:00', $end . ' 00:00', self::timezone())
            : TimeRange::fromLocal(strtr($start, 'T', ' '), strtr($end, 'T', ' '), self::timezone());
    }

    /**
     * Day mode shows arrival and departure date; the departure is the exclusive end.
     */
    public static function formatRange(DateTimeImmutable $start, DateTimeImmutable $end, bool $wholeDays): string
    {
        $format = $wholeDays ? DATE_FORMAT : DATE_FORMAT . ' ' . TIME_FORMAT;
        return $start->format($format) . ' – ' . $end->format($format);
    }

    /**
     * Messages and form input survive the redirect after save.php, per section.
     *
     * @param array<string, mixed> $data
     */
    public static function flash(int $sectionId, array $data): void
    {
        $_SESSION['mod_reservations_flash'][$sectionId] = $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function takeFlash(int $sectionId): array
    {
        $data = $_SESSION['mod_reservations_flash'][$sectionId] ?? [];
        unset($_SESSION['mod_reservations_flash'][$sectionId]);
        return is_array($data) ? $data : [];
    }

    // helpers

    /**
     * Whole days (date only) or period (date and time); the mode belongs to the
     * section. Missing settings count as day mode, the default of every section.
     */
    public static function wholeDays(int $sectionId): bool
    {
        return (self::settings()->find($sectionId)->bookingMode ?? BookingMode::Day) === BookingMode::Day;
    }

    /**
     * The captcha of the module captcha_control (ALTCHA, without HTTPS a math task).
     */
    public static function captchaAvailable(): bool
    {
        return is_file(WB_PATH . '/modules/captcha_control/Captcha.php');
    }

    /**
     * The news_img section that holds the object descriptions, or null.
     */
    public static function newsSection(int $sectionId): ?int
    {
        return ReservationsNewsImg::idOf(self::settings()->find($sectionId)?->presentationSource);
    }

    /**
     * Arrival and departure time as lines of text, for the form and the mails.
     *
     * @param callable(string, string): string $t text of a language key with one value
     * @return list<string>
     */
    public static function checkTimes(BookableResource $resource, callable $t): array
    {
        return array_values(array_filter([
            $resource->checkinTime === null ? '' : $t('CHECKIN_FROM', substr($resource->checkinTime, 0, 5)),
            $resource->checkoutTime === null ? '' : $t('CHECKOUT_UNTIL', substr($resource->checkoutTime, 0, 5)),
        ], static fn (string $line): bool => $line !== ''));
    }

    /**
     * @param array<string, int|string> $params
     */
    public static function modifyUrl(int $pageId, int $sectionId, array $params = []): string
    {
        $query = ['page_id' => $pageId] + ($params === [] ? [] : ['rsv_section' => $sectionId] + $params);
        $anchor = defined('SEC_ANCHOR') && SEC_ANCHOR !== '' ? '#' . SEC_ANCHOR . $sectionId : '';
        // explicit separator: news_img sets arg_separator.output to '&amp;' for the whole request
        return ADMIN_URL . '/pages/modify.php?' . http_build_query($query, '', '&') . $anchor;
    }
}
