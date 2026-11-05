# Set up a section

A section is a calendar with its own objects, statuses and settings. A page can hold several sections, and they are independent of each other.

## Add a section

1. Add a new page in WBCE with the module **Reservations**. On an existing page, add a section with this module through the section management instead.
2. Open the page in the backend. You see the list of objects, with the links **Settings**, **Statuses** and **Object types** above it.
3. Start with the [settings](settings.md), because the booking mode can only be changed as long as there are no bookings.
4. Then add the [objects](objects.md).

## What a new section comes with

- the booking mode **By day** and the display **Several objects**
- one object type "Object"
- six ready-made [statuses](statuses.md): Requested, Reserved, Booked, Blocked, Rejected, Cancelled
- booking requests switched off
- the captcha, if the module captcha_control is installed

## Several sections

The booking mode applies to all objects of a section. If you offer holiday flats and a meeting room, add two sections: one by day, one by period.

Objects, statuses and object types each belong to one section. Multilingual sites therefore get their own names automatically, because each language has its own pages and sections in WBCE.

> [!WARNING]
> If you delete a section, WBCE also deletes all its objects, bookings and requests. This cannot be undone.
