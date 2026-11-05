# Frequently asked questions

Answers to questions that often come up during setup and operation.

## Setup

### Why can I not change the booking mode?

The section already has bookings. The [booking mode](settings.md#booking-mode) can only be changed as long as there are none.

### Why is there no week in my calendar?

The [week view](views.md#week) exists only with booking by period.

### Why can the default view not be saved?

It does not fit the display or the booking mode. The table is under [Settings](settings.md#display-and-default-view).

### How do I change the order of the objects?

Enter the [order](objects.md#order) at the object. Smaller numbers come first.

### Why can I not delete an object?

It has running or future bookings or open requests, see [Delete an object](objects.md#delete-an-object).

## Requests

### Why does a period look free, but the request is turned down?

Probably there is an open request and the status "Requested" is not public: it blocks, but shows nothing. This is the default in sections from before version 0.9.0, and you can change it at the [status](statuses.md).

### Why is there no request form?

Check in the [settings](settings.md#booking-requests) whether requests are switched on, and whether the object is active. The form is collapsed: it opens with a click on "Request a booking".

### Why does no notification arrive?

Check the address in the settings and the mail settings in the basic settings of WBCE. The request stays stored even after an error and is in the backend.

### The visitor sees "You have already sent … requests within the last hour"

Each visitor can send 3 requests per hour and section. This cannot be changed. Behind a shared internet connection or a reverse proxy, everyone counts as one visitor.

### How do I cancel a confirmed booking?

Set the status of the booking to "Cancelled" and inform the person yourself. Changing a booking sends no mail.

## Display

### Why does the plan show only morning and afternoon for times?

In the plan each day has only two halves. The [week](views.md#week) shows exact times.

### Why does the calendar look wrong in my template?

Probably a rule with `!important` takes effect, see [Adapt the look](styling.md#when-the-template-interferes).

### Why does the label of a booking not appear?

It needs a status "with its name" and labels switched on in the [settings](settings.md#plan-labels-and-paging). The status "Blocked" never shows it.
