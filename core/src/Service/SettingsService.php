<?php

declare(strict_types=1);

namespace Reservations\Core\Service;

use Reservations\Core\Domain\Settings;
use Reservations\Core\Result\Result;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\SettingsRepository;

final class SettingsService
{
    public const PLAN_RANGES = [1, 3];
    public const MONTHS_MAX = 60;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ResourceRepository $resources,
    ) {
    }

    public function save(Settings $s): Result
    {
        if ($this->settings->find($s->sectionId) === null) {
            return Result::fail('settings.not_found', ['id' => $s->sectionId]);
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
        if ($s->resourceId !== null && $this->resources->find($s->sectionId, $s->resourceId) === null) {
            return Result::fail('resource.not_found', ['id' => $s->resourceId], 'resource_id');
        }
        $this->settings->save($s);
        return Result::ok();
    }
}
