<?php

declare(strict_types=1);

namespace Reservations\Core\Service;

use Reservations\Core\Domain\Settings;
use Reservations\Core\Result\Result;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\SettingsRepository;

final class SettingsService
{
    public const PLAN_RANGES = [1, 3];
    public const MONTHS_MAX = 60;
    public const PRESENTATION_SOURCE_MAX = 100;
    public const EMAIL_MAX = 255;
    /** ten years; the column is a SMALLINT */
    public const RETENTION_MAX = 3650;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ResourceRepository $resources,
        private readonly BookingRepository $bookings,
    ) {
    }

    public function save(Settings $s): Result
    {
        $existing = $this->settings->find($s->sectionId);
        if ($existing === null) {
            return Result::fail('settings.not_found', ['id' => $s->sectionId]);
        }
        // existing bookings were entered for the old mode; day mode would drop their times on the next edit
        if ($s->bookingMode !== $existing->bookingMode && $this->bookings->countByResource($s->sectionId) !== []) {
            return Result::fail('settings.mode_locked', [], 'booking_mode');
        }
        if (!in_array($s->planRange, self::PLAN_RANGES, true)) {
            return Result::fail('settings.invalid_plan_range', [], 'plan_range');
        }
        if ($s->pastMonths < 0 || $s->pastMonths > self::MONTHS_MAX) {
            return Result::fail('settings.invalid_months', ['max' => self::MONTHS_MAX], 'past_months');
        }
        if ($s->futureMonths < 1 || $s->futureMonths > self::MONTHS_MAX) {
            return Result::fail('settings.invalid_months', ['max' => self::MONTHS_MAX], 'future_months');
        }
        if (!$s->defaultView->fits($s->displayMode)) {
            return Result::fail('settings.view_not_available', [], 'default_view');
        }
        if ($s->presentationSource !== null && mb_strlen($s->presentationSource) > self::PRESENTATION_SOURCE_MAX) {
            return Result::fail('settings.presentation_source_too_long', ['max' => self::PRESENTATION_SOURCE_MAX], 'presentation_source');
        }
        if ($s->notifyEmail !== '' && (strlen($s->notifyEmail) > self::EMAIL_MAX || filter_var($s->notifyEmail, FILTER_VALIDATE_EMAIL) === false)) {
            return Result::fail('settings.invalid_email', [], 'notify_email');
        }
        if ($s->retentionDays < 1 || $s->retentionDays > self::RETENTION_MAX) {
            return Result::fail('settings.invalid_retention', ['max' => self::RETENTION_MAX], 'retention_days');
        }
        if ($s->resourceId !== null && $this->resources->find($s->sectionId, $s->resourceId) === null) {
            return Result::fail('resource.not_found', ['id' => $s->resourceId], 'resource_id');
        }
        $this->settings->save($s);
        return Result::ok();
    }
}
