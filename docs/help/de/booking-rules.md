# Buchungsregeln

Buchungsregeln legen fest, was eine Anfrage über die Website darf. Sie stehen beim Objekt unter **Buchungseinstellungen**. Leere Felder bedeuten: keine Grenze.

> [!NOTE]
> Bis auf die Pufferzeiten gelten alle Regeln nur für Anfragen über die Website. Im Backend tragen Sie Buchungen frei ein.

## Personen

**Personen mindestens** und **Personen höchstens (Kapazität)** begrenzen die Personenzahl im Anfrageformular. Mit einer Mindestzahl wird das Feld zum Pflichtfeld.

## Sofort verbindlich buchen

Mit **Anfragen sofort verbindlich buchen** ist eine Anfrage ohne Ihre Bestätigung gebucht. Die Person bekommt gleich die Bestätigung, die Buchung erhält den Status mit der Rolle „Anfrage bestätigt“.

## Vorlauf

Der Vorlauf ist der Mindestabstand zwischen Anfrage und Beginn.

- **Tageweise** zählt er in Tagen ab heute: 2 Tage an einem Montag erlauben die Anreise am Mittwoch. Frühestens ist die Anreise morgen möglich.
- **Im Modus Zeitraum** zählt er in Stunden. Der früheste Beginn wird auf das Raster aufgerundet und auf die nächste Öffnung verschoben.

**Höchstens im Voraus buchbar** begrenzt, wie weit eine Anfrage in der Zukunft liegen darf. Ohne diesen Wert gilt das Ende des letzten Monats, den Besucher [erreichen können](settings.md#plan-beschriftungen-und-blaettern).

## Dauer

**Mindestdauer** und **Höchstdauer** zählen tageweise in Nächten, im Modus Zeitraum in Minuten. Ohne Höchstdauer ist eine Anfrage höchstens so lang wie das Kalenderfenster.

## Raster

Nur im Modus Zeitraum. Beginn und Ende einer Anfrage liegen auf dem Raster: Bei 30 Minuten auf :00 und :30. Möglich sind 5, 10, 15, 20, 30 und 60 Minuten.

Das Raster bestimmt auch die [Wochenansicht](views.md#woche): Bei 60 Minuten hat sie Zeilen von einer Stunde, sonst von 30 Minuten.

## Pufferzeiten

Nur im Modus Zeitraum. **Vorbereitung davor** und **Nachbereitung danach** bleiben zwischen zwei Buchungen frei. Zwischen zwei Buchungen liegen also mindestens die Nachbereitung der früheren und die Vorbereitung der späteren zusammen.

> [!WARNING]
> Pufferzeiten gelten für alle Buchungen, auch für die im Backend.
