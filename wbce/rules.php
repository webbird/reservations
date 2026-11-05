<?php
/**
 * Reservations – rules.php
 * Booking rules between the backend form (_booking_rules.twig) and the core:
 * lead time in days (day mode) or hours, durations in nights or minutes,
 * buffers only in period mode. The core stores minutes and checks the ranges.
 * @module reservations  @version 0.6.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Service\ResourceService;

final class ReservationsRules
{
    /** form field => BookableResource::RULE_KEYS, without the checkbox auto_confirm */
    private const FIELDS = [
        'capacity' => 'capacity', 'min_persons' => 'minPersons', 'slot_minutes' => 'slotMinutes', 'lead_min' => 'leadMinMinutes', 'lead_max_days' => 'leadMaxDays',
        'min_duration' => 'minDuration', 'max_duration' => 'maxDuration', 'buffer_before' => 'bufferBefore', 'buffer_after' => 'bufferAfter',
    ];
    /** fields only the period mode has; in day mode their stored values stay */
    private const PERIOD_ONLY = ['slot_minutes', 'buffer_before', 'buffer_after'];
    /** form field => BookableResource::RULE_KEYS; only the day mode has them, and they are times, not numbers */
    private const DAY_ONLY = ['checkin_time' => 'checkinTime', 'checkout_time' => 'checkoutTime'];

    /**
     * The fields as posted, to show them again after an error.
     *
     * @param callable(string): string $post
     * @return array<string, string|bool>
     */
    public static function input(callable $post): array
    {
        $input = ['auto_confirm' => $post('auto_confirm') === '1'];
        foreach ([...array_keys(self::FIELDS), ...array_keys(self::DAY_ONLY)] as $field) {
            $input[$field] = $post($field);
        }
        return $input;
    }

    /**
     * The rules in the units of the form; empty = no limit.
     *
     * @return array<string, int|string|bool>
     */
    public static function toForm(BookableResource $r, bool $wholeDays): array
    {
        $form = ['auto_confirm' => $r->autoConfirm];
        foreach (self::FIELDS as $field => $key) {
            $form[$field] = $r->{$key} ?? '';
        }
        foreach (self::DAY_ONLY as $field => $key) {
            // MySQL returns 15:00:00, a time input takes 15:00
            $form[$field] = substr($r->{$key} ?? '', 0, 5);
        }
        // rounded up: a lead time entered in the other mode stays at least as long
        $form['lead_min'] = (int) ceil($r->leadMinMinutes / ($wholeDays ? 1440 : 60));
        return $form;
    }

    /**
     * The rules of a form post, named like BookableResource::RULE_KEYS; null if a
     * field is not a whole number. Day mode has no grid and buffer fields, the stored values stay.
     *
     * @param callable(string): string $post
     * @return array<string, int|string|bool|null>|null
     */
    public static function fromPost(callable $post, bool $wholeDays): ?array
    {
        $rules = ['autoConfirm' => $post('auto_confirm') === '1'];
        foreach (self::FIELDS as $field => $key) {
            if ($wholeDays && in_array($field, self::PERIOD_ONLY, true)) {
                continue;
            }
            // the limit keeps the conversion to minutes below overflow
            $value = $post($field) === '' ? null : filter_var($post($field), FILTER_VALIDATE_INT, ['options' => ['max_range' => ResourceService::INT_MAX]]);
            if ($value === false) {
                return null;
            }
            $rules[$key] = $value;
        }
        // these have no "no limit": empty means 0 (for the grid an invalid value the core reports)
        foreach (['slotMinutes', 'leadMinMinutes', 'bufferBefore', 'bufferAfter'] as $key) {
            if (array_key_exists($key, $rules)) {
                $rules[$key] ??= 0;
            }
        }
        $rules['leadMinMinutes'] *= $wholeDays ? 1440 : 60;
        foreach ($wholeDays ? self::DAY_ONLY : [] as $field => $key) {
            $rules[$key] = $post($field) === '' ? null : $post($field);
        }
        return $rules;
    }
}
