<?php

declare(strict_types=1);

namespace Reservations\Core\Service;

use DateInterval;
use DateTimeImmutable;
use Reservations\Core\Domain\BookableResource;
use Reservations\Core\Domain\Booking;
use Reservations\Core\Domain\BookingMode;
use Reservations\Core\Domain\OpeningHours;
use Reservations\Core\Domain\Request;
use Reservations\Core\Domain\Settings;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Port\Clock;
use Reservations\Core\Result\Result;
use Reservations\Core\Storage\Pdo\BookingRepository;
use Reservations\Core\Storage\Pdo\HoursRepository;
use Reservations\Core\Storage\Pdo\RequestRepository;
use Reservations\Core\Storage\Pdo\ResourceRepository;
use Reservations\Core\Storage\Pdo\SettingsRepository;
use Reservations\Core\Storage\Pdo\StatusRepository;

/**
 * Booking requests from the frontend: a booking with the status of role
 * request_new (request_confirmed for objects with auto_confirm) plus the
 * contact data. Spam protection is the wrapper's job.
 */
final class RequestService
{
    public const NAME_MAX = 255;
    public const EMAIL_MAX = 255;
    public const PHONE_MAX = 50;
    /** bytes; the size of a MySQL TEXT column */
    public const MESSAGE_MAX = 65535;
    /** the column is a SMALLINT; the capacity of the object lowers it */
    public const PERSONS_MAX = 32767;
    /** successful requests per visitor and section within RATE_WINDOW; a setting may follow later */
    public const RATE_LIMIT = 3;
    private const RATE_WINDOW = 'PT1H';
    /** how long the visitor hash is kept */
    private const CLIENT_KEEP = 'P1D';
    /** suggested length of a period for objects without a minimum */
    private const SUGGESTED_MINUTES = 60;

    public function __construct(
        private readonly BookingService $bookingService,
        private readonly SettingsRepository $settings,
        private readonly ResourceRepository $resources,
        private readonly StatusRepository $statuses,
        private readonly BookingRepository $bookings,
        private readonly RequestRepository $requests,
        private readonly HoursRepository $hours,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param string $client the visitor's address for the rate limit; '' = unknown, no limit
     * @return Result value: new booking id
     */
    public function submit(
        int $sectionId,
        int $resourceId,
        TimeRange $range,
        string $name,
        string $email,
        string $phone,
        ?int $persons,
        string $message,
        bool $privacyAccepted,
        string $client = '',
    ): Result {
        $name = trim($name);
        $email = trim($email);
        $phone = trim($phone);
        $message = trim($message);

        $settings = $this->settings->find($sectionId);
        if ($settings === null || !$settings->requestsEnabled) {
            return Result::fail('request.disabled');
        }
        $resource = $this->resources->find($sectionId, $resourceId);
        if ($resource === null || !$resource->active) {
            return Result::fail('resource.not_found', ['id' => $resourceId], 'resource');
        }
        if ($name === '') {
            return Result::fail('request.name_required', [], 'name');
        }
        if (mb_strlen($name) > self::NAME_MAX) {
            return Result::fail('request.name_too_long', ['max' => self::NAME_MAX], 'name');
        }
        if (strlen($email) > self::EMAIL_MAX || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return Result::fail('request.invalid_email', [], 'email');
        }
        if (mb_strlen($phone) > self::PHONE_MAX) {
            return Result::fail('request.phone_too_long', ['max' => self::PHONE_MAX], 'phone');
        }
        // with a lower limit the number is required, otherwise an empty field would bypass it
        if ($persons === null && $resource->minPersons !== null) {
            return Result::fail('request.persons_required', [], 'persons');
        }
        $personsMin = $resource->minPersons ?? 1;
        $personsMax = $resource->capacity ?? self::PERSONS_MAX;
        if ($persons !== null && ($persons < $personsMin || $persons > $personsMax)) {
            return Result::fail('request.invalid_persons', ['min' => $personsMin, 'max' => $personsMax], 'persons');
        }
        if (strlen($message) > self::MESSAGE_MAX) {
            return Result::fail('request.message_too_long', [], 'message');
        }
        if (!$privacyAccepted) {
            return Result::fail('request.privacy_required', [], 'privacy');
        }

        $now = $this->clock->now();
        $wholeDays = $settings->bookingMode === BookingMode::Day;
        // whole days have no times; in period mode start and end lie on the grid and in the opening
        // hours, checked first because earliestStart() moves to the next opening
        if (!$wholeDays) {
            if (!self::onGrid($range->start, $resource->slotMinutes) || !self::onGrid($range->end, $resource->slotMinutes)) {
                return Result::fail('request.off_grid', ['min' => $resource->slotMinutes], 'range');
            }
            $hours = $this->hours->find($sectionId, $resourceId);
            if (!$hours->allowsStart($range->start) || !$hours->allowsEnd($range->end)) {
                return Result::fail('request.closed', [], 'range');
            }
        }
        if ($range->start < $this->earliestStart($resource, $wholeDays)) {
            return Result::fail('request.too_early', [], 'range');
        }
        $today = $now->setTime(0, 0);
        $horizon = $this->horizon($settings);
        if ($range->start > $this->latestStart($resource, $settings)) {
            return Result::fail('request.too_late', ['max' => $resource->leadMaxDays ?? $today->diff($horizon)->days], 'range');
        }
        // nights in day mode (both ends at midnight, so days of the difference), minutes in period mode
        $duration = $wholeDays ? $range->start->diff($range->end)->days : $range->durationMinutes();
        $unit = $wholeDays ? 'nights' : 'minutes';
        if ($resource->minDuration !== null && $duration < $resource->minDuration) {
            return Result::fail('request.too_short_' . $unit, ['min' => $resource->minDuration], 'range');
        }
        $maxDuration = $resource->maxDuration ?? ($wholeDays ? $today->diff($horizon)->days : intdiv($horizon->getTimestamp() - $today->getTimestamp(), 60));
        if ($duration > $maxDuration) {
            return Result::fail('request.too_long_' . $unit, ['max' => $maxDuration], 'range');
        }

        $role = $resource->autoConfirm ? StatusRole::RequestConfirmed : StatusRole::RequestNew;
        $status = $this->statuses->findByRole($sectionId, $role);
        if ($status === null) {
            return Result::fail('request.no_status', ['role' => $role->value]);
        }
        // every request occupies a period and sends mails, also to foreign addresses; only the
        // keyed hash is stored, the address itself never
        // shortcut: counted outside the booking lock, so simultaneous requests of one visitor can
        // pass the limit by a few; count inside a section lock if that is ever abused
        $clientHash = $client === '' ? null : hash_hmac('sha256', $client, $this->settings->clientSecret($sectionId));
        if ($clientHash !== null && $this->requests->countRecent($sectionId, $clientHash, $now->sub(new DateInterval(self::RATE_WINDOW))) >= self::RATE_LIMIT) {
            return Result::fail('request.too_many', ['max' => self::RATE_LIMIT]);
        }

        $request = fn (int $bookingId): Request => new Request(
            id: null,
            bookingId: $bookingId,
            name: $name,
            email: $email,
            phone: $phone,
            persons: $persons,
            message: $message,
            privacyAcceptedAt: $now,
            createdAt: $now,
            purgeAfter: $range->end->add(new DateInterval('P' . $settings->retentionDays . 'D')),
            clientHash: $clientHash,
        );
        return $this->bookingService->create(
            $sectionId,
            $resourceId,
            $range,
            $status->id ?? 0,
            then: function (int $bookingId) use ($request): void {
                $this->requests->insert($request($bookingId));
            },
        );
    }

    public function confirm(int $sectionId, int $bookingId, ?int $userId = null): Result
    {
        return $this->decide($sectionId, $bookingId, StatusRole::RequestConfirmed, $userId);
    }

    /**
     * The contact data is kept retention_days from now instead of from the end
     * of the requested period.
     */
    public function reject(int $sectionId, int $bookingId, ?int $userId = null): Result
    {
        $result = $this->decide($sectionId, $bookingId, StatusRole::RequestRejected, $userId);
        if ($result->ok) {
            $this->bookingChanged($sectionId, $bookingId);
        }
        return $result;
    }

    /**
     * The earliest start a request may have, after the lead time of the object;
     * in period mode on its grid and in its opening hours.
     */
    public function earliestStart(BookableResource $resource, bool $wholeDays): DateTimeImmutable
    {
        $now = $this->clock->now();
        if ($wholeDays) {
            // days count from today (2 days lead on Monday: Wednesday), but today has begun, so never before tomorrow
            $leadDays = max(1, (int) ceil($resource->leadMinMinutes / 1440));
            return $now->setTime(0, 0)->add(new DateInterval('P' . $leadDays . 'D'));
        }
        $start = self::ceilToGrid($now->add(new DateInterval('PT' . $resource->leadMinMinutes . 'M')), $resource->slotMinutes);
        return self::nextOpening($this->hours->find($resource->sectionId, (int) $resource->id), $start, $resource->slotMinutes);
    }

    /**
     * The latest start a request may have: the maximum advance of the object,
     * without one the calendar window.
     */
    public function latestStart(BookableResource $resource, Settings $settings): DateTimeImmutable
    {
        return $resource->leadMaxDays !== null
            ? $this->clock->now()->add(new DateInterval('P' . $resource->leadMaxDays . 'D'))
            : $this->horizon($settings);
    }

    /**
     * What a click on the calendar suggests for the request form. Whole days: the
     * day as arrival, for the minimum stay. Periods: the first possible start from
     * $from, for the minimum duration or else an hour; shorter if the opening hours
     * or the next booking end it sooner. Keeps clear of $taken and its buffers.
     * Only a suggestion: bookings the caller does not know, submit() finds out.
     *
     * @param list<Booking> $taken blocking bookings of the object, ordered by start
     * @return ?TimeRange null if no request can begin there
     */
    public function suggest(BookableResource $resource, Settings $settings, DateTimeImmutable $from, array $taken = []): ?TimeRange
    {
        $wholeDays = $settings->bookingMode === BookingMode::Day;
        $earliest = $this->earliestStart($resource, $wholeDays);
        $latest = $this->latestStart($resource, $settings);
        // as the booking service sees them: each with the gap the object needs around it
        $gap = $resource->requiredGapMinutes($settings->bookingMode);
        $busy = array_map(static fn (Booking $b): TimeRange => $b->range->withBuffer($gap, $gap), $taken);
        if ($wholeDays) {
            $start = $from->setTime(0, 0);
            $stay = new TimeRange($start, $start->add(new DateInterval('P' . max(1, $resource->minDuration ?? 1) . 'D')));
            foreach ($busy as $range) {
                if ($range->overlaps($stay)) {
                    return null;
                }
            }
            return $start < $earliest || $start > $latest ? null : $stay;
        }
        // shortcut: two queries per free block of a week; hand the hours in if pages with many objects get slow
        $hours = $this->hours->find($resource->sectionId, (int) $resource->id);
        $start = self::nextOpening($hours, max($earliest, self::ceilToGrid($from, $resource->slotMinutes)), $resource->slotMinutes);
        $limit = null;
        foreach ($busy as $range) {
            if ($start >= $range->start && $start < $range->end) {
                $start = self::nextOpening($hours, self::ceilToGrid($range->end, $resource->slotMinutes), $resource->slotMinutes);
            } elseif ($range->start > $start) {
                $limit ??= $range->start;
            }
        }
        if ($start > $latest || !$hours->allowsStart($start)) {
            return null;
        }
        $length = static fn (DateTimeImmutable $end): int => intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
        $wanted = $resource->minDuration ?? min(self::SUGGESTED_MINUTES, $resource->maxDuration ?? self::SUGGESTED_MINUTES);
        $slot = new DateInterval('PT' . $resource->slotMinutes . 'M');
        $end = null;
        // the last end on the grid that the hours allow, up to the wanted length
        for ($next = $start->add($slot); $limit === null || $next <= $limit; $next = $next->add($slot)) {
            if ($resource->maxDuration !== null && $length($next) > $resource->maxDuration) {
                break;
            }
            if ($hours->allowsEnd($next)) {
                $end = $next;
            }
            if ($length($next) >= $wanted) {
                break;
            }
        }
        return $end === null || $length($end) < ($resource->minDuration ?? 0) ? null : new TimeRange($start, $end);
    }

    /**
     * The end of the last month visitors can browse to. It bounds objects without
     * limits of their own, so a single request cannot block one for decades.
     */
    private function horizon(Settings $settings): DateTimeImmutable
    {
        $today = $this->clock->now()->setTime(0, 0);
        return $today->setDate((int) $today->format('Y'), (int) $today->format('n') + $settings->futureMonths + 1, 1);
    }

    /**
     * Recomputes when the contact data of a request is emptied, after its booking
     * was changed (new period, new status): retention_days after the end of the
     * period, or - once rejected - after now at the latest. Bookings without a
     * request and requests already emptied stay as they are.
     */
    public function bookingChanged(int $sectionId, int $bookingId): void
    {
        $booking = $this->bookings->find($sectionId, $bookingId);
        $request = $this->requests->findByBooking($sectionId, $bookingId);
        if ($booking === null || $request === null || $request->purgeAfter === null) {
            return;
        }
        $days = new DateInterval('P' . ($this->settings->find($sectionId)->retentionDays ?? 90) . 'D');
        $purge = $booking->range->end->add($days);
        if ($this->statuses->find($sectionId, $booking->statusId)?->role === StatusRole::RequestRejected) {
            $purge = min($request->purgeAfter, $this->clock->now()->add($days));
        }
        $this->requests->setPurgeAfter($bookingId, $purge);
    }

    /**
     * A changed retention applies to the stored requests as well, not only to new ones.
     */
    public function retentionChanged(int $sectionId): void
    {
        foreach ($this->requests->bookingIds($sectionId) as $bookingId) {
            $this->bookingChanged($sectionId, $bookingId);
        }
    }

    /**
     * Empties the contact data of all requests whose retention has ended, and the
     * visitor hashes after a day. WBCE has no cron; the wrapper calls this on
     * backend calls and page views.
     *
     * @return int number of requests whose contact data was emptied
     */
    public function purgeExpired(): int
    {
        $now = $this->clock->now();
        $this->requests->forgetClients($now->sub(new DateInterval(self::CLIENT_KEEP)));
        return $this->requests->purgeExpired($now);
    }

    /**
     * Wall-clock minutes on a multiple of the slot; the allowed slots divide an
     * hour, so a daylight saving change keeps the grid.
     */
    private static function onGrid(DateTimeImmutable $moment, int $slotMinutes): bool
    {
        return $moment->format('s') === '00' && ((int) $moment->format('G') * 60 + (int) $moment->format('i')) % $slotMinutes === 0;
    }

    /**
     * The first opening from $start on, in grid steps; any opening day comes within a week.
     */
    private static function nextOpening(OpeningHours $hours, DateTimeImmutable $start, int $slotMinutes): DateTimeImmutable
    {
        $slot = new DateInterval('PT' . $slotMinutes . 'M');
        for ($step = 0; $step < 7 * 1440 / $slotMinutes && !$hours->allowsStart($start); $step++) {
            $start = $start->add($slot);
        }
        return $start;
    }

    private static function ceilToGrid(DateTimeImmutable $moment, int $slotMinutes): DateTimeImmutable
    {
        $minute = $moment->setTime((int) $moment->format('G'), (int) $moment->format('i'));
        // a started minute counts as a whole one
        if ($moment->format('s') !== '00') {
            $minute = $minute->add(new DateInterval('PT1M'));
        }
        $rest = ((int) $minute->format('G') * 60 + (int) $minute->format('i')) % $slotMinutes;
        return $rest === 0 ? $minute : $minute->add(new DateInterval('PT' . ($slotMinutes - $rest) . 'M'));
    }

    private function decide(int $sectionId, int $bookingId, StatusRole $role, ?int $userId): Result
    {
        $booking = $this->bookings->find($sectionId, $bookingId);
        if ($booking === null || $this->requests->findByBooking($sectionId, $bookingId) === null) {
            return Result::fail('request.not_found', ['id' => $bookingId]);
        }
        // only open requests: a second tab or a resent form must not decide again and mail the guest twice
        if ($this->statuses->find($sectionId, $booking->statusId)?->role !== StatusRole::RequestNew) {
            return Result::fail('request.already_decided', ['id' => $bookingId]);
        }
        $status = $this->statuses->findByRole($sectionId, $role);
        if ($status === null) {
            return Result::fail('request.no_status', ['role' => $role->value]);
        }
        // confirming checks for overlaps again; the booking service does that for blocking statuses
        return $this->bookingService->update(
            $sectionId,
            $bookingId,
            $booking->resourceId,
            $booking->range,
            $status->id ?? 0,
            $booking->label,
            $booking->note,
            $userId,
        );
    }
}
