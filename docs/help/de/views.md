# Ansichten

Besucher wechseln über die Schalter über dem Kalender zwischen den Ansichten, die zum Abschnitt passen. Welche zuerst erscheint, legt die [Startansicht](settings.md#darstellung-und-startansicht) fest.

## Belegungsplan

Nur bei mehreren Objekten. Eine Zeile je Objekt, eine Spalte je Tag. So sehen Besucher auf einen Blick, welches Objekt wann frei ist. Der Plan zeigt 1 oder 3 Monate.

Tage sind geteilt. Tageweise zeigt das Anreise und Abreise, im Modus Zeitraum Vormittag und Nachmittag.

## Kalender

Nur bei einem Einzelobjekt. Ein Monat als übliche Kalendertabelle, die Tage sind wie im Plan geteilt.

In Plan und Kalender sind freie Tage grau schraffiert, wenn sie vorbei oder nicht anfragbar sind. Die Legende nennt das „nicht verfügbar“, Screenreader lesen den genauen Grund vor. Buchungen behalten ihr Muster.

## Woche

Nur im Buchungsmodus Zeitraum. Je Objekt eine Tabelle mit den Tagen als Spalten und den Uhrzeiten als Zeilen.

- Die Tabelle reicht von der frühesten Öffnung bis zur spätesten Schließung der Woche, ohne [Öffnungszeiten](opening-hours.md) von 08:00 bis 20:00. Buchungen erweitern sie bei Bedarf.
- Eine Zeile sind 30 Minuten, bei einem [Raster](booking-rules.md#raster) von 60 Minuten eine Stunde.
- Geschlossene, vergangene und nicht anfragbare Zeiten sind grau schraffiert.
- Buchungen zeigen ihre genauen Zeiten, wenn sie mindestens zwei Zeilen hoch sind.

## Liste

Immer verfügbar. Die belegten Zeiträume als Text, je Objekt gruppiert. Diese Ansicht ist am Handy und mit Screenreadern am einfachsten zu lesen.

## Direkt aus dem Kalender anfragen

Sind [Buchungsanfragen](requests.md) eingeschaltet, führt ein Klick in den Kalender zum Formular. Es klappt auf und enthält schon Objekt, Beginn und Ende. Der Besucher kann alles ändern, bevor er die Anfrage abschickt.

- **Plan und Kalender, Buchungsmodus tageweise:** Ein Klick auf einen freien Tag setzt die Anreise, die Abreise ergibt sich aus der [Mindestdauer](booking-rules.md#dauer), sonst nach einer Nacht. An Wechseltagen mit freiem Nachmittag geht das auch. Reicht die Mindestdauer nicht bis zur nächsten Buchung, ist der Tag nicht klickbar.
- **Woche:** Ein Klick auf eine freie Zeit setzt den Beginn auf diese Zeile. Das Ende ergibt sich aus der Mindestdauer, sonst nach einer Stunde, und liegt nie nach der nächsten Buchung. [Pufferzeiten](booking-rules.md#pufferzeiten) bleiben frei. Per Tastatur beginnt der Vorschlag am Anfang des freien Blocks.
- **Plan im Buchungsmodus Zeitraum:** kein Klick, denn ein Tag sagt nichts über die Uhrzeit. Dafür ist die Woche da.

Tage und Zeiten außerhalb des [Vorlaufs](booking-rules.md#vorlauf) sind schraffiert und nicht klickbar. In Plan und Kalender ist der Klick eine Abkürzung für Maus und Touch. Mit Tastatur und Screenreader geht es dort über das Formular, durch die vielen Tage müsste man sonst einzeln durchspringen.

> [!NOTE]
> Der Klick ist nur ein Vorschlag. Ob der ganze Zeitraum frei ist, prüft Reservations erst beim Absenden. Zeigt ein Status eine Buchung gar nicht an, sieht die Zeit frei aus und lässt sich anklicken, die Anfrage wird beim Absenden aber abgelehnt.

## Was Besucher sehen

Was eine Buchung auf der Website zeigt, hängt von ihrem [Status](statuses.md) ab:

| Einstellung beim Status | Anzeige |
|---|---|
| mit Bezeichnung | Name des Status und, wenn eingeschaltet, die öffentliche Beschriftung |
| als „belegt“ | nur „belegt“ |
| gar nicht | nichts, die Zeit sieht frei aus |

Die Legende unter dem Kalender zeigt alle sichtbaren Status. Jeder Status hat neben der Farbe ein Muster, damit er sich auch ohne Farbsehen und im Kontrastmodus erkennen lässt.
