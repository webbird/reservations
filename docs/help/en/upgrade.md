# Update

You install an update like any WBCE module. Reservations adapts the database by itself, and your data stays.

## Requirements

- WBCE 1.7.0 or later
- PHP 8.2 or later with the extension intl

The installation checks this and stops with a message otherwise.

## Install an update

1. Back up the database and the files.
2. Open the module management in the backend.
3. Install the ZIP file of the new version over the existing one. WBCE runs the update.
4. Reload a page with Reservations in the browser. If the browser still holds the old styles, clear its cache.

> [!WARNING]
> Do not simply upload the files by FTP. Without the update, new database fields are missing, and the frontend reports an error until someone opens the backend of the section.

## What an update changes

New fields are added, fields no longer needed go away. Bookings, objects, statuses and settings stay, and so do your variables in the [template](styling.md), because they are not in the module.
