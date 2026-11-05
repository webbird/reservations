# Häufige Fragen

Antworten auf Fragen, die bei Einrichtung und Betrieb oft auftauchen.

## Einrichtung

### Warum kann ich den Buchungsmodus nicht ändern?

Der Abschnitt hat schon Buchungen. Der [Buchungsmodus](settings.md#buchungsmodus) lässt sich nur ändern, solange es keine gibt.

### Warum gibt es die Woche nicht in meinem Kalender?

Die [Wochenansicht](views.md#woche) gibt es nur im Buchungsmodus Zeitraum.

### Warum lässt sich die Startansicht nicht speichern?

Sie passt nicht zur Darstellung oder zum Buchungsmodus. Die Tabelle steht unter [Einstellungen](settings.md#darstellung-und-startansicht).

### Wie ändere ich die Reihenfolge der Objekte?

Tragen Sie beim Objekt die [Reihenfolge](objects.md#reihenfolge) ein. Kleinere Zahlen stehen oben.

### Warum kann ich ein Objekt nicht löschen?

Es hat laufende oder künftige Buchungen oder offene Anfragen, siehe [Objekt löschen](objects.md#objekt-loeschen).

## Anfragen

### Warum sieht ein Zeitraum frei aus, die Anfrage wird aber abgelehnt?

Vermutlich liegt dort eine offene Anfrage und der Status „angefragt“ ist nicht öffentlich: Er blockiert, zeigt aber nichts. Das ist bei Abschnitten von vor Version 0.9.0 die Vorgabe und lässt sich beim [Status](statuses.md) ändern.

### Warum erscheint kein Anfrageformular?

Prüfen Sie in den [Einstellungen](settings.md#buchungsanfragen), ob Anfragen eingeschaltet sind, und ob das Objekt aktiv ist. Das Formular ist zugeklappt: Es öffnet sich mit einem Klick auf „Buchung anfragen“.

### Warum kommt keine Benachrichtigung an?

Prüfen Sie die Adresse in den Einstellungen und den Mailversand in den Grundeinstellungen von WBCE. Die Anfrage bleibt auch bei einem Fehler gespeichert und steht im Backend.

### Der Besucher sieht „Sie haben in der letzten Stunde schon … Anfragen gesendet“

Je Besucher und Abschnitt sind 3 Anfragen pro Stunde möglich. Einstellen lässt sich das nicht. Hinter einem gemeinsamen Internetzugang oder einem Reverse-Proxy zählen alle als ein Besucher.

### Wie storniere ich eine bestätigte Buchung?

Setzen Sie den Status der Buchung auf „storniert“ und informieren Sie die Person selbst. Das Ändern einer Buchung löst keine Mail aus.

## Darstellung

### Warum zeigt der Plan bei Uhrzeiten nur Vormittag und Nachmittag?

Im Plan hat jeder Tag nur zwei Hälften. Genaue Zeiten zeigt die [Woche](views.md#woche).

### Warum sieht der Kalender in meinem Template falsch aus?

Vermutlich greift eine Regel mit `!important`, siehe [Aussehen anpassen](styling.md#wenn-das-template-stoert).

### Warum erscheint die Beschriftung einer Buchung nicht?

Sie braucht einen Status „mit Bezeichnung“ und die eingeschalteten Beschriftungen in den [Einstellungen](settings.md#plan-beschriftungen-und-blaettern). Der Status „gesperrt“ zeigt sie nie.
