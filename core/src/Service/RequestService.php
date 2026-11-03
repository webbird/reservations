<?php

declare(strict_types=1);

namespace Reservations\Core\Service;

use DateInterval;
use Reservations\Core\Domain\BookingMode;
use Reservations\Core\Domain\Request;
use Reservations\Core\Domain\StatusRole;
use Reservations\Core\Domain\TimeRange;
use Reservations\Core\Port\Clock;
use Reservations\Core\Result\Result;
use Reservations\Core\Storage\Pdo\BookingRepository;
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

    public function __construct(
        private readonly BookingService $bookingService,
        private readonly SettingsRepository $settings,
        private readonly ResourceRepository $resources,
        private readonly StatusRepository $statuses,
        private readonly BookingRepository $bookings,
        private readonly RequestRepository $requests,
        private readonly Clock $clock,
    ) {
    }

    /**
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
        // whole days start at midnight, so in day mode the earliest arrival is the day after now + lead time
        $earliest = $now->add(new DateInterval('PT' . $resource->leadMinMinutes . 'M'));
        if ($range->start < $earliest) {
            return Result::fail('request.too_early', [], 'range');
        }
        if ($resource->leadMaxDays !== null && $range->start > $now->add(new DateInterval('P' . $resource->leadMaxDays . 'D'))) {
            return Result::fail('request.too_late', ['max' => $resource->leadMaxDays], 'range');
        }
        // nights in day mode (both ends at midnight, so days of the difference), minutes in period mode
        $wholeDays = $settings->bookingMode === BookingMode::Day;
        $duration = $wholeDays ? $range->start->diff($range->end)->days : $range->durationMinutes();
        $unit = $wholeDays ? 'nights' : 'minutes';
        if ($resource->minDuration !== null && $duration < $resource->minDuration) {
            return Result::fail('request.too_short_' . $unit, ['min' => $resource->minDuration], 'range');
        }
        if ($resource->maxDuration !== null && $duration > $resource->maxDuration) {
            return Result::fail('request.too_long_' . $unit, ['max' => $resource->maxDuration], 'range');
        }

        $role = $resource->autoConfirm ? StatusRole::RequestConfirmed : StatusRole::RequestNew;
        $status = $this->statuses->findByRole($sectionId, $role);
        if ($status === null) {
            return Result::fail('request.no_status', ['role' => $role->value]);
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
            $days = $this->settings->find($sectionId)->retentionDays ?? 90;
            $this->requests->setPurgeAfter($bookingId, $this->clock->now()->add(new DateInterval('P' . $days . 'D')));
        }
        return $result;
    }

    /**
     * Empties the contact data of all requests whose retention has ended.
     * WBCE has no cron; the wrapper calls this on backend and request calls.
     */
    public function purgeExpired(): int
    {
        return $this->requests->purgeExpired($this->clock->now());
    }

    private function decide(int $sectionId, int $bookingId, StatusRole $role, ?int $userId): Result
    {
        $booking = $this->bookings->find($sectionId, $bookingId);
        if ($booking === null || $this->requests->findByBooking($sectionId, $bookingId) === null) {
            return Result::fail('request.not_found', ['id' => $bookingId]);
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
