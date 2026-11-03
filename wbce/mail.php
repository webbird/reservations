<?php
/**
 * Reservations – mail.php
 * Mails about booking requests, sent through the mailer configured in WBCE.
 * @module reservations  @version 0.5.0  @author Bianka Martinovic
 * @license GNU General Public License   @platform WBCE 1.7.0+
 */

defined('WB_PATH') or die('This file cannot be accessed directly!');

final class ReservationsMail
{
    /**
     * Mails about a request, in the language of the page: to the requester,
     * and for a new request ('received', 'booked' = confirmed at once) also to
     * notify_email. Without double opt-in anybody can enter a foreign address,
     * so mails to the requester carry nothing the visitor typed.
     *
     * @param 'received'|'booked'|'confirmed'|'rejected' $event
     * @return bool false if a mail could not be sent
     */
    public static function request(int $sectionId, int $bookingId, string $event): bool
    {
        $booking = ReservationsWbce::bookings()->find($sectionId, $bookingId);
        $request = ReservationsWbce::requests()->findByBooking($sectionId, $bookingId);
        $page = $GLOBALS['database']->fetchRow(
            'SELECT p.`page_id`, p.`language` FROM `{TP}sections` s JOIN `{TP}pages` p ON p.`page_id` = s.`page_id` WHERE s.`section_id` = ?',
            [$sectionId],
        );
        // purged requests have no address any more
        if ($booking === null || $request === null || $request->email === '' || $page === null) {
            return false;
        }
        $texts = self::texts((string) $page['language']);
        $t = static fn (string $key, string ...$args): string => vsprintf($texts[$key] ?? $key, $args);
        $object = ReservationsWbce::resources()->find($sectionId, $booking->resourceId)->title ?? '?';
        $period = ReservationsWbce::formatRange($booking->range->start, $booking->range->end, ReservationsWbce::wholeDays($sectionId));
        $facts = $t('RESOURCE') . ': ' . $object . "\n" . $t('MAIL_PERIOD') . ': ' . $period . "\n";
        $notify = ReservationsWbce::settings()->find($sectionId)->notifyEmail ?? '';

        $sent = self::send(
            $request->email,
            $notify,
            $t('MAIL_SUBJECT_' . strtoupper($event), $object),
            $t('MAIL_TEXT_' . strtoupper($event)) . "\n\n" . $facts . "\n" . $t('MAIL_FOOTER', WEBSITE_TITLE),
        );

        if ($notify !== '' && in_array($event, ['received', 'booked'], true)) {
            $url = ReservationsWbce::modifyUrl((int) $page['page_id'], $sectionId, ['rsv_bookings' => $booking->resourceId, 'rsv_booking' => $bookingId]);
            $sent = self::send(
                $notify,
                $request->email,
                $t('MAIL_ADMIN_SUBJECT_' . strtoupper($event), $object, $period),
                $t('MAIL_ADMIN_TEXT_' . strtoupper($event)) . "\n\n" . $facts
                    . $t('NAME') . ': ' . $request->name . "\n"
                    . $t('EMAIL') . ': ' . $request->email . "\n"
                    . $t('PHONE') . ': ' . $request->phone . "\n"
                    . $t('PERSONS') . ': ' . ($request->persons ?? '') . "\n"
                    . $t('MESSAGE') . ":\n" . $request->message . "\n\n"
                    . $t('MAIL_ADMIN_LINK', $url),
            ) && $sent;
        }
        return $sent;
    }

    /**
     * The module texts of one language, independent of the language of the current user.
     *
     * @return array<string, string>
     */
    private static function texts(string $language): array
    {
        static $all = null;
        $all ??= include __DIR__ . '/languages.php';
        return $all[strtoupper($language)]['MOD_RESERVATIONS'] ?? $all['EN']['MOD_RESERVATIONS'];
    }

    /**
     * Plain text mail through the mailer configured in WBCE.
     */
    private static function send(string $to, string $replyTo, string $subject, string $body): bool
    {
        $mail = new Mailer();
        // WBCE waits 30 s per SMTP connection; two mails would exceed PHP's usual time limit
        $mail->Timeout = 10;
        $mail->isHTML(false);
        $mail->addAddress($to);
        if ($replyTo !== '') {
            $mail->addReplyTo($replyTo);
        }
        $mail->Subject = $subject;
        $mail->Body = $body;
        if ($mail->send()) {
            return true;
        }
        // no address in the log: it is personal data
        error_log('reservations: mail "' . $subject . '" not sent: ' . $mail->ErrorInfo);
        return false;
    }
}
