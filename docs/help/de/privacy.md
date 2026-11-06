# Datenschutz und Löschfristen

Eine Anfrage enthält personenbezogene Daten. Reservations speichert sie nur so lange wie eingestellt und löscht sie danach selbst.

> [!NOTE]
> Diese Seite beschreibt, was das Modul tut. Sie ersetzt keine Rechtsberatung. Nehmen Sie das Anfrageformular in die Datenschutzerklärung Ihrer Website auf.

## Was gespeichert wird

| Daten | Wie lange |
|---|---|
| Name, E-Mail, Telefon, Personenzahl, Nachricht | bis zur Löschfrist |
| Prüfwert der IP-Adresse (für die Begrenzung auf 3 Anfragen pro Stunde) | einen Tag |
| Buchung mit Zeitraum und Status | bleibt, ohne Kontaktdaten |

Die IP-Adresse selbst wird nicht gespeichert, nur ein nicht umkehrbarer Prüfwert daraus. Das Formular nennt diesen Prüfwert in der Einwilligung, und ohne Zustimmung lässt es sich nicht absenden.

## Die Löschfrist

Die Frist stellen Sie in den [Einstellungen](settings.md#buchungsanfragen) ein, Vorgabe sind 90 Tage.

- Sie zählt ab dem Ende des angefragten Zeitraums.
- Nach einer Ablehnung zählt sie ab der Ablehnung.
- Eine geänderte Frist gilt auch für schon gespeicherte Anfragen.
- Ändern Sie den Zeitraum einer Buchung, wird die Frist neu berechnet.

Das Datum steht in der Buchung unter **Kontaktdaten werden gelöscht am**.

WBCE hat keinen zeitgesteuerten Dienst. Reservations löscht deshalb, sobald jemand die Seite mit dem Kalender oder das Backend des Abschnitts aufruft. Ohne Besucher kann das etwas später sein als das angezeigte Datum.

## Anfrage vorzeitig löschen

Löschen Sie die [Buchung](bookings.md#buchung-aendern-oder-loeschen), dann ist auch die Anfrage mit allen Kontaktdaten sofort entfernt.

## Mails

Benachrichtigungen enthalten die Angaben aus dem Formular und liegen danach in Ihrem Postfach. Die Löschfrist gilt dort nicht.

Reservations prüft nicht, ob eine eingetragene Adresse wirklich der anfragenden Person gehört. Deshalb enthält die Mail an diese Adresse keine Eingaben aus dem Formular.
