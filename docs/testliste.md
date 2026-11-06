# Testliste für Releases

Der Kern ist automatisch getestet. Die WBCE-Schicht (`request.php`, `save.php`, `mail.php`,
`view.php`) und die Oberfläche werden vor jedem Release von Hand geprüft.

## Automatisch

Im Ordner `core/` mit dem System-PHP:

```bash
php vendor/bin/phpunit
```

```bash
php vendor/bin/phpstan analyse --memory-limit=512M
```

Die MySQL-Tests laufen nur mit `RESERVATIONS_TEST_MYSQL_DSN`, `_USER` und `_PASSWORD` und mit
einem PHP, das `pdo_mysql` hat. Sie gehören vor jedes Release, weil nur sie die Zeilensperre
gegen Doppelbuchungen prüfen.

## Installation und Update

Immer in einer eigenen WBCE-Instanz, nie über die Verzeichnisverknüpfung der Entwicklung.

- [ ] Neuinstallation aus dem ZIP läuft ohne Fehler
- [ ] Neuer Abschnitt hat sechs Status, einen Objekttyp, Anfragen aus, Captcha an (falls vorhanden)
- [ ] Update aus dem letzten ZIP: Buchungen und Einstellungen bleiben, entfallene Spalten sind weg
- [ ] Hilfe-Link im Backend führt zur Hilfe, auch auf Englisch
- [ ] Deinstallation entfernt die Tabellen, Seiten mit dem Droplet bleiben ohne Fehler

## Anfrage im Frontend

- [ ] Formular absenden: Bestätigung erscheint, Buchung steht im Backend, Zeit ist belegt
- [ ] „Weitere Anfrage senden“ zeigt ein leeres Formular
- [ ] Falsches Datum, zu kurze Dauer, belegter Zeitraum: Fehler am Feld, Eingaben bleiben
- [ ] Klick auf freien Tag oder Block füllt das Formular vor, das Formular klappt auf
- [ ] Objekt mit Puffer oder Mindestdauer: Vorschläge werden beim Absenden angenommen
- [ ] Vergangene und nicht anfragbare Zeiten sind schraffiert und nicht klickbar
- [ ] Ohne JavaScript: Formular offen, Links funktionieren

## Spam-Schutz

- [ ] Abgelaufenes Token: Meldung, Eingaben bleiben
- [ ] Captcha falsch: Meldung, Eingaben bleiben, keine Buchung
- [ ] Honeypot gefüllt oder Absenden unter 2 Sekunden: abgelehnt
- [ ] Vierte Anfrage innerhalb einer Stunde: abgelehnt
- [ ] Rücksprungadresse auf eine fremde Seite geändert: Weiterleitung bleibt auf der Site

## Backend

- [ ] Objekt, Status und Objekttyp anlegen, ändern, löschen
- [ ] Objekt mit laufender Buchung lässt sich nicht löschen
- [ ] Buchung mit deaktiviertem Status: Status bleibt beim Speichern erhalten
- [ ] Status mit heller Farbe speichern: Hinweis auf den Kontrast erscheint
- [ ] Anfrage bestätigen und ablehnen, zweiter Klick in einem zweiten Tab wird abgewiesen
- [ ] Buchungsmodus wechseln: geht nur ohne Buchungen, Dauern sind danach leer

## Mail

Mit einem echten Mailserver:

- [ ] Eingang: Mail an die anfragende Person und an die Benachrichtigungsadresse
- [ ] Sofort bestätigt, Bestätigung und Ablehnung
- [ ] An- und Abreisezeit stehen in der Mail
- [ ] Mail an die anfragende Person enthält nichts, was der Besucher eingegeben hat
- [ ] Gescheiterter Versand: Anfrage bleibt, Eintrag im Fehlerprotokoll ohne Adresse

## Datenschutz

- [ ] Abgelaufene Kontaktdaten sind nach dem Aufruf der Kalenderseite leer, die Buchung bleibt
- [ ] Geänderte Löschfrist gilt auch für gespeicherte Anfragen

## Barrierefreiheit

- [ ] axe oder WAVE ohne Fehler in Plan, Kalender, Woche, Liste und Formular
- [ ] Nur Tastatur: alle Ansichten, Formular aufklappen und absenden, sichtbarer Fokus
- [ ] NVDA mit Firefox: Zellen, Formular, Fehlermeldung
- [ ] Kontrastmodus: Muster, Schraffur und Fokus bleiben sichtbar
- [ ] Zoom 200 % und 400 %: keine seitliche Überbreite außer in Tabellen
