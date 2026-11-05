# Statuses

The status of a booking decides whether it occupies the period and what visitors see of it. Each section has its own statuses, and you can name them as you like.

## The statuses that come with the module

| Status | Blocks | On the website | Role |
|---|---|---|---|
| Requested | yes | with its name | new request |
| Reserved | yes | with its name | none |
| Booked | yes | with its name | request confirmed |
| Blocked | yes | as "occupied" | none |
| Rejected | no | not at all | request rejected |
| Cancelled | no | not at all | none |

## Properties

| Property | Meaning |
|---|---|
| Name | shown in the backend and, depending on the setting, in the legend on the website |
| Colour | Reservations picks a matching text colour |
| Pattern | solid, striped, dotted or outline only, so the status can be told apart without its colour |
| Blocks the period | other bookings cannot overlap it |
| On the website | with its name, as "occupied" or not at all |
| Role | see below |
| Active, Order | only active statuses can be chosen for bookings |

> [!NOTE]
> "Not at all" only hides the display. A status with "Blocks the period" still occupies the period. If you set "Requested" to "not at all", an open request looks free on the website, but a second request for the same period is turned down.

Sections created before version 0.9.0 do not show "Requested" publicly yet. Change that at the status under **On the website**. If a request should not occupy the period at all, untick **Blocks the period** there.

## Roles

The request workflow needs three fixed targets. Each role belongs to exactly one status:

- **new request:** status for incoming requests
- **request confirmed:** status after confirming and for objects that book at once
- **request rejected:** status after rejecting

If you give a role to another status, the previous one loses it. A status with a role must stay active.

## Delete a status

You can only delete statuses without a role and without bookings. If a status is still in use, deactivate it instead. If it has a role, give the role to another status first.

If you change a status to "Blocks the period", Reservations checks the existing bookings. If they would overlap, it refuses the change and names the bookings.
