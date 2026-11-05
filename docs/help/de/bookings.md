# Buchungen pflegen

Buchungen tragen Sie je Objekt im Backend ein. Anfragen von der Website erscheinen hier ebenfalls, siehe [Anfragen bearbeiten](requests.md).

## Buchung anlegen

1. Klicken Sie in der Objektliste beim Objekt auf **Buchungen**.
2. Tragen Sie unter **Neue Buchung** Anreise und Abreise ein, im Modus Zeitraum Beginn und Ende.
3. Wählen Sie den [Status](statuses.md).
4. Tragen Sie bei Bedarf eine öffentliche Beschriftung und eine interne Notiz ein.
5. Klicken Sie auf **Speichern**.

Blockiert der gewählte Status und überschneidet sich der Zeitraum mit einer anderen blockierenden Buchung, lehnt Reservations das Speichern ab und nennt diese Buchung. Im Modus Zeitraum zählen dabei auch die [Pufferzeiten](booking-rules.md#pufferzeiten). Öffnungszeiten, Vorlauf und Raster gelten hier nicht.

## Beschriftung und Notiz

| Feld | Sichtbar für |
|---|---|
| Öffentliche Beschriftung | Besucher, wenn der Status „mit Bezeichnung“ zeigt und die Beschriftungen in den [Einstellungen](settings.md#plan-beschriftungen-und-blaettern) eingeschaltet sind |
| Interne Notiz | nur im Backend, nie auf der Website |

## Tageweise: Anreise und Abreise

Die Abreise ist der Tag, an dem das Objekt wieder frei wird. Eine Buchung vom 2. bis 6. und eine vom 6. bis 10. überschneiden sich nicht, der 6. ist der Wechseltag. Der Kalender zeigt solche Tage geteilt: Anreise rechts, Abreise links.

## Buchung ändern oder löschen

Klicken Sie in der Liste auf **Bearbeiten** oder **Löschen**. Die Liste zeigt keine Buchungen, die vor mehr als 12 Monaten endeten.

> [!WARNING]
> Eine gelöschte Buchung lässt sich nicht wiederherstellen. Gehört eine Anfrage dazu, wird sie mitgelöscht. Soll der Vorgang nachvollziehbar bleiben, setzen Sie den Status stattdessen auf „storniert“: Das gibt den Zeitraum frei.
