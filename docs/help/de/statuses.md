# Status

Der Status einer Buchung bestimmt, ob sie den Zeitraum belegt und was Besucher davon sehen. Jeder Abschnitt hat eigene Status, und Sie können sie frei benennen.

## Die mitgelieferten Status

| Status | Blockiert | Auf der Website | Rolle |
|---|---|---|---|
| angefragt | ja | mit Bezeichnung | neue Anfrage |
| reserviert | ja | mit Bezeichnung | keine |
| gebucht | ja | mit Bezeichnung | Anfrage bestätigt |
| gesperrt | ja | als „belegt“ | keine |
| abgelehnt | nein | gar nicht | Anfrage abgelehnt |
| storniert | nein | gar nicht | keine |

## Eigenschaften

| Eigenschaft | Bedeutung |
|---|---|
| Name | erscheint im Backend und, je nach Einstellung, in der Legende auf der Website |
| Farbe | die Schriftfarbe wählt Reservations passend dazu |
| Muster | voll, schraffiert, gepunktet oder nur Rahmen; so ist der Status nicht allein an der Farbe zu erkennen |
| Blockiert den Zeitraum | andere Buchungen dürfen sich nicht damit überschneiden |
| Auf der Website | mit Bezeichnung, als „belegt“ oder gar nicht |
| Rolle | siehe unten |
| Aktiv, Reihenfolge | neu wählen lassen sich nur aktive Status; eine Buchung behält ihren deaktivierten Status, und ist er blockierend, zeigt der Kalender sie weiter |

> [!NOTE]
> „Gar nicht“ versteckt nur die Anzeige. Ein Status mit „Blockiert den Zeitraum“ belegt den Zeitraum trotzdem. Stellen Sie „angefragt“ auf „gar nicht“, sieht eine offene Anfrage auf der Website frei aus, eine zweite Anfrage für denselben Zeitraum wird aber abgelehnt.

Abschnitte, die vor Version 0.9.0 angelegt wurden, zeigen „angefragt“ noch nicht öffentlich. Das ändern Sie beim Status unter **Auf der Website**. Soll eine Anfrage den Zeitraum gar nicht belegen, nehmen Sie dort das Häkchen bei **Blockiert den Zeitraum** heraus.

## Rollen

Der Ablauf bei Anfragen braucht drei feste Ziele. Jede Rolle gehört genau einem Status:

- **neue Anfrage:** Status für eingehende Anfragen
- **Anfrage bestätigt:** Status nach dem Bestätigen und bei sofort verbindlichen Objekten
- **Anfrage abgelehnt:** Status nach dem Ablehnen

Geben Sie eine Rolle einem anderen Status, verliert sie der bisherige. Ein Status mit Rolle muss aktiv bleiben.

## Status löschen

Löschen können Sie nur Status ohne Rolle und ohne Buchungen. Wird ein Status noch verwendet, deaktivieren Sie ihn stattdessen. Hat er eine Rolle, geben Sie sie zuerst einem anderen Status.

Stellen Sie einen Status auf „Blockiert den Zeitraum“ um, prüft Reservations die vorhandenen Buchungen. Würden sie sich dann überschneiden, lehnt es die Änderung ab und nennt die Buchungen.
