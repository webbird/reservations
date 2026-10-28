# reservations

Belegungs- und Reservierungskalender für Ferienwohnungen, Räume, Personen und Geräte.
Nachfolger des WBCE-Moduls `bookings_v2`.

Aufbau: ein CMS-unabhängiger Kern und schlanke Wrapper pro CMS. Das Konzept steht in
[docs/konzept.md](docs/konzept.md).

| Ordner | Inhalt |
|---|---|
| `core/` | Kern, Namespace `Reservations\Core`, ohne Abhängigkeiten zur Laufzeit |
| `wbce/` | WBCE-Modul (ab WBCE 1.7.0) |
| `build/` | Build-Skript für das installierbare ZIP |
| `catamaran/` | Symfony-Bundle für Catamaran, später |
| `docs/` | Konzept und Dokumentation |

## Voraussetzungen

PHP ab 8.2 mit `pdo`, `intl` und `mbstring`. Datenbank: MySQL/MariaDB oder SQLite.

## Entwicklung (Kern)

Im Ordner `core/`:

```bash
php composer.phar install
```

```bash
vendor/bin/phpunit
```

Statische Analyse mit PHPStan (Stufe `max`, Strict- und Deprecation-Regeln, geprüft für
PHP 8.2 bis 8.5, Konfiguration in `core/phpstan.neon.dist`):

```bash
vendor/bin/phpstan analyse --memory-limit=512M
```

Die Tests laufen gegen SQLite im Speicher. Mit der Umgebungsvariablen
`RESERVATIONS_DEBUG=1` schreibt der Kern jede SQL-Abfrage samt Parametern ins `error_log`.

## Entwicklung (WBCE-Modul)

Das Modulverzeichnis in WBCE ist eine Verzeichnisverknüpfung auf `wbce/`. Den Kern findet
`wbce/bootstrap.php` dann über `../core/src`, im gebauten Modul über `core/src`:

```bash
cmd /c mklink /J "path\to\modules\reservations" "path\to\reservations\wbce"
```

Danach in WBCE unter Erweiterungen → Module → „Manuell installieren“ das Modul `reservations`
installieren. Entfernen der Verknüpfung (das Repository bleibt unberührt): `rmdir` auf das
Modulverzeichnis.

Die Zeitzone kommt aus der Konstanten `RESERVATIONS_TIMEZONE` (in der `config.php` von WBCE),
sonst gilt `Europe/Berlin`. WBCE selbst kennt nur einen Versatz ohne Sommerzeit.

Installierbares ZIP bauen:

```bash
php build/build.php
```

Das ZIP landet in `build/dist/`.

## Lizenz

GNU General Public License, Version 2 oder (nach Wahl) jede spätere Version
(GPL-2.0-or-later). Lizenztext: [LICENSE](LICENSE), im WBCE-Modul als `LICENSE.txt`.
