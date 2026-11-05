# Booking rules

Booking rules decide what a request via the website may do. You find them at the object under **Booking settings**. Empty fields mean no limit.

> [!NOTE]
> Except for the buffer times, all rules apply only to requests via the website. In the backend you enter bookings freely.

## Persons

**Minimum number of persons** and **Maximum number of persons (capacity)** limit the number of persons in the request form. With a minimum the field becomes required.

## Book at once

With **Book requests at once**, a request is booked without your confirmation. The person gets the confirmation right away, and the booking gets the status with the role "request confirmed".

## Lead time

The lead time is the minimum time between the request and the start.

- **By day** it counts in days from today: 2 days on a Monday allow arrival on Wednesday. Arrival is possible from tomorrow at the earliest.
- **By period** it counts in hours. The earliest start is rounded up to the grid and moved to the next opening.

**Bookable in advance at most** limits how far in the future a request may lie. Without this value, the end of the last month visitors [can reach](settings.md#plan-labels-and-paging) applies.

## Duration

The minimum and maximum count in nights by day, and in minutes by period. Without a maximum, a request is at most as long as the calendar window.

## Arrival and departure time

By day only. **Arrival from** and **Departure until** appear in the request form and in the mails, for example "Arrival from 15:00". They are for information only and do not limit bookings.

## Grid

By period only. Start and end of a request lie on the grid: with 30 minutes at :00 and :30. Possible values are 5, 10, 15, 20, 30 and 60 minutes.

The grid also shapes the [week view](views.md#week): with 60 minutes its rows are one hour, otherwise 30 minutes.

## Buffer times

By period only. **Preparation before** and **Clean-up after** stay free between two bookings. Two bookings are therefore at least the clean-up of the earlier one plus the preparation of the later one apart.

> [!WARNING]
> Buffer times apply to all bookings, including those in the backend.
