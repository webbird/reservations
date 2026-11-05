# Update

Ein Update installieren Sie wie jedes WBCE-Modul. Reservations passt dabei die Datenbank selbst an, Ihre Daten bleiben erhalten.

## Voraussetzungen

- WBCE ab 1.7.0
- PHP ab 8.2 mit der Erweiterung intl

Die Installation prüft das und bricht sonst mit einer Meldung ab.

## Update installieren

1. Sichern Sie Datenbank und Dateien.
2. Öffnen Sie im Backend die Verwaltung der Module.
3. Installieren Sie die ZIP-Datei der neuen Version über die vorhandene. WBCE führt dabei das Update aus.
4. Laden Sie eine Seite mit Reservations im Browser neu. Hält der Browser noch die alten Stile, leeren Sie seinen Zwischenspeicher.

> [!WARNING]
> Laden Sie die Dateien nicht einfach per FTP hoch. Ohne das Update fehlen neue Datenbankfelder, und das Frontend meldet einen Fehler, bis jemand das Backend des Abschnitts öffnet.

## Was ein Update ändert

Neue Felder kommen hinzu, nicht mehr gebrauchte fallen weg. Buchungen, Objekte, Status und Einstellungen bleiben, ebenso Ihre Variablen im [Template](styling.md), denn sie stehen nicht im Modul.
