# Views

Visitors switch between the views that fit the section with the buttons above the calendar. Which one comes first is set by the [default view](settings.md#display-and-default-view).

## Occupancy plan

With several objects only. One row per object, one column per day. Visitors see at a glance which object is free when. The plan shows 1 or 3 months.

Days are split. By day this shows arrival and departure, by period morning and afternoon.

## Calendar

With a single object only. One month as a usual calendar table, with days split as in the plan.

## Week

Booking by period only. One table per object, with the days as columns and the times as rows.

- The table runs from the earliest opening to the latest closing of the week, without [opening hours](opening-hours.md) from 08:00 to 20:00. Bookings extend it if needed.
- One row is 30 minutes, with a [grid](booking-rules.md#grid) of 60 minutes one hour.
- Closed times have grey hatching.
- Bookings show their exact times if they are at least two rows high.

## List

Always available. The occupied periods as text, grouped by object. This view is the easiest to read on a phone and with screen readers.

## What visitors see

What a booking shows on the website depends on its [status](statuses.md):

| Setting at the status | Display |
|---|---|
| with its name | name of the status and, if switched on, the public label |
| as "occupied" | just "occupied" |
| not at all | nothing, the time looks free |

The legend below the calendar shows all visible statuses. Each status has a pattern besides its colour, so it can be told apart without colour vision and in high contrast mode.
