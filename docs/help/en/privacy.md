# Privacy and retention

A request contains personal data. Reservations stores it only as long as set, and deletes it by itself afterwards.

> [!NOTE]
> This page describes what the module does. It is not legal advice. Include the request form in the privacy policy of your website.

## What is stored

| Data | How long |
|---|---|
| Name, e-mail, phone, number of persons, message | until the retention period ends |
| Check value of the IP address (for the limit of 3 requests per hour) | one day |
| Booking with period and status | stays, without contact data |

The IP address itself is not stored, only a non-reversible check value derived from it. The form mentions this check value in the consent, and it cannot be sent without agreement.

## The retention period

You set the period in the [settings](settings.md#booking-requests), the default is 90 days.

- It counts from the end of the requested period.
- After a rejection it counts from the rejection.
- A changed period also applies to stored requests.
- If you change the period of a booking, the date is calculated again.

The date is shown in the booking under **Contact data will be deleted**.

WBCE has no scheduled tasks. Reservations therefore deletes when someone opens the page with the calendar or the backend of the section. Without visitors this can be somewhat later than the date shown.

## Delete a request early

Delete the [booking](bookings.md#change-or-delete-a-booking), and the request with all contact data is gone at once.

## Mails

Notifications contain the details from the form and stay in your mailbox afterwards. The retention period does not apply there.

Reservations does not check whether an entered address really belongs to the person. That is why the mail to this address contains nothing typed into the form.
