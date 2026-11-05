# Manage bookings

You enter bookings per object in the backend. Requests from the website appear here as well, see [Handle requests](requests.md).

## Add a booking

1. Click **Bookings** at the object in the object list.
2. Under **New booking**, enter arrival and departure, or start and end by period.
3. Choose the [status](statuses.md).
4. Enter a public label and an internal note if needed.
5. Click **Save**.

If the chosen status blocks and the period overlaps another blocking booking, Reservations refuses to save and names that booking. By period the [buffer times](booking-rules.md#buffer-times) count too. Opening hours, lead time and grid do not apply here.

## Label and note

| Field | Visible to |
|---|---|
| Public label | visitors, if the status shows "with its name" and labels are switched on in the [settings](settings.md#plan-labels-and-paging) |
| Internal note | the backend only, never on the website |

## By day: arrival and departure

The departure is the day the object becomes free again. A booking from the 2nd to the 6th and one from the 6th to the 10th do not overlap, the 6th is the changeover day. The calendar shows such days split: arrival on the right, departure on the left.

## Change or delete a booking

Click **Edit** or **Delete** in the list. The list does not show bookings that ended more than 12 months ago.

> [!WARNING]
> A deleted booking cannot be restored. If a request belongs to it, the request is deleted with it. If the case should stay traceable, set the status to "Cancelled" instead: that frees the period.
