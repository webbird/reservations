# Views

Visitors switch between the views that fit the section with the buttons above the calendar. Which one comes first is set by the [default view](settings.md#display-and-default-view).

## Occupancy plan

With several objects only. One row per object, one column per day. Visitors see at a glance which object is free when. The plan shows 1 or 3 months.

Days are split. By day this shows arrival and departure, by period morning and afternoon.

## Calendar

With a single object only. One month as a usual calendar table, with days split as in the plan.

In the plan and the calendar, free days have grey hatching if they are past or cannot be requested. The legend calls this "not available", screen readers read out the exact reason. Bookings keep their pattern.

## Week

Booking by period only. One table per object, with the days as columns and the times as rows.

- The table runs from the earliest opening to the latest closing of the week, without [opening hours](opening-hours.md) from 08:00 to 20:00. Bookings extend it if needed.
- One row is 30 minutes, with a [grid](booking-rules.md#grid) of 60 minutes one hour.
- Closed and past times, and times that cannot be requested, have grey hatching.
- Bookings show their exact times if they are at least two rows high.

## List

Always available. The occupied periods as text, grouped by object. This view is the easiest to read on a phone and with screen readers.

## Request right from the calendar

With [booking requests](requests.md) switched on, a click in the calendar leads to the form. It opens with the object, start and end already filled in. Visitors can change everything before they send the request.

- **Plan and calendar, booking by day:** A click on a free day sets the arrival. The departure follows from the [minimum duration](booking-rules.md#duration), otherwise after one night. This also works on changeover days with a free afternoon.
- **Week:** A click on a free time sets the start to that row. The end follows from the minimum duration, otherwise after one hour, and never lies after the next booking. By keyboard the suggestion begins at the start of the free block.
- **Plan when booking by period:** no click, because a day says nothing about the time. The week is there for that.

Days and times outside the [lead time](booking-rules.md#lead-time) are hatched and cannot be clicked. In the plan and the calendar the click is a shortcut for mouse and touch. With a keyboard or screen reader the way there is the form, as the many days would otherwise be a long way to tab through.

> [!NOTE]
> The click is only a suggestion. Whether the whole period is free is checked when the request is sent. If a status hides a booking, the time looks free and can be clicked, but the request is rejected when it is sent.

## What visitors see

What a booking shows on the website depends on its [status](statuses.md):

| Setting at the status | Display |
|---|---|
| with its name | name of the status and, if switched on, the public label |
| as "occupied" | just "occupied" |
| not at all | nothing, the time looks free |

The legend below the calendar shows all visible statuses. Each status has a pattern besides its colour, so it can be told apart without colour vision and in high contrast mode.
