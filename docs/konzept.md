# reservations – Konzept

Stand: 27.10.2026 · Arbeitsentwurf zur Abstimmung, noch kein Code

Nachfolger von `bookings_v2`, als eigenständiges Modul mit neuem Namen. Belegungs- und
Reservierungskalender für beliebige buchbare Dinge: Ferienwohnungen, Besprechungsräume,
Personen (z. B. Masseur), Geräte (z. B. Beamer).

Die Objekte selbst legt der Kern an, in einfacher Form: Name, Typ, Buchungseinstellungen.
Die **Präsentation** der Objekte (Texte, Bilder, Kategorien) übernimmt ein anderes Modul
des CMS: in WBCE **news_img**, in Catamaran ein Content-Model. Der Kern speichert nur einen
neutralen Verweis darauf und hängt von keinem dieser Module ab (siehe Abschnitt 8).

**Aufbau:** ein CMS-unabhängiger **Kern** und schlanke **Wrapper** pro CMS. Zuerst kommt
WBCE, später Catamaran.

**Voraussetzungen:** WBCE ab **1.7.0** (Datenbankklasse auf PDO-Basis), PHP ab **8.2**,
Erweiterung `intl`. Datenbank: MySQL/MariaDB oder SQLite (beides unterstützt WBCE 1.7).

---

## 1. Umfang

### Version 1.0
- Ressourcen mit Buchungsmodus **tageweise** und **Zeitraum**
- **Objekttypen** pro Abschnitt (z. B. Ferienwohnung, Raum, Gerät): Name, Buchungsmodus,
  Vorgabewerte für neue Objekte
- **Konfigurierbare Status** pro Abschnitt: Bezeichnung, Farbe, Muster, Verhalten
  (blockiert ja/nein, öffentliche Darstellung), feste Rollen für den Ablauf bei Anfragen
- Pro Abschnitt einstellbar: **Einzelobjekt** oder **mehrere Objekte** (Belegungsplan)
- Ansichten: Belegungsplan (1 oder 3 Monate bzw. Tag/Woche), Monatskalender, Woche mit
  Stundenraster, Liste
- Buchungen nur im Backend pflegen. Die Service-Schicht ist so gebaut, dass Bearbeiten im
  Frontend später nachgerüstet werden kann.
- **Buchungsanfragen** über das Frontend: Status mit der Rolle „neue Anfrage“, im Backend bestätigen oder
  ablehnen, Mails, Spamschutz, automatisches Löschen der Kontaktdaten. Pro Ressource
  einstellbar, ob eine Buchung sofort verbindlich ist.
- Öffnungs- bzw. Verfügbarkeitszeiten pro Wochentag, Sperrzeiten, Vorlaufzeit, Pufferzeit
- Optionale öffentliche Beschriftung pro Buchung, interne Notiz, Beschriftungen pro
  Abschnitt abschaltbar
- Verweis auf die Präsentation des Objekts (neutral im Kern, in WBCE umgesetzt für news_img),
  Droplet für den Kalender im news_img-Beitrag
- Barrierefreiheit nach WCAG 2.2 AA (siehe Abschnitt 7)
- Sprachen: DE und EN. Die übrigen folgen später.

### Ausbaustufen (nicht in 1.0)
| Version | Inhalt |
|---|---|
| 1.1 | Buchungsmodus **Termin**: Leistungen mit fester Dauer, Berechnung freier Termine, Auswahl im Frontend (Leistung, Tag, Uhrzeit) |
| später | **Import aus bookings_v2** (Abschnitt wird Objekt, Gruppenname wird interne Notiz) |
| später | **LeafletMaps**: Ressourcen als Marker auf einer Karte, nach dem Muster von openimmo (siehe Abschnitt 9) |
| später | Bearbeiten im Frontend für angemeldete Benutzer |
| später | Stückzahl pro Ressource („noch 2 von 3 Beamern frei“) |
| später | Bestätigungslink für Anfragen (Double-Opt-in) |
| später | Weitere Sprachen (DA, FR, NL, NO, SK wie in v2) |

### Bewusst gestrichen
- Gruppen aus v2. Die Unterscheidung übernehmen jetzt die Objekte.
- Quartals- und Drei-Monats-Ansicht aus v2. Der Zeitraum von 3 Monaten im Belegungsplan
  bleibt als Umschaltung erhalten.
- Kopf, Fuß und Stylesheet als Textfelder pro Abschnitt. Stattdessen gibt es Views mit
  CSS-Variablen wie in news_img.

---

## 2. Begriffe

| Begriff | Bedeutung |
|---|---|
| Abschnitt | WBCE-Section mit dem Modul. Hat eigene Einstellungen und eigene Ressourcen. |
| Ressource | Das buchbare Ding (Wohnung, Raum, Person, Gerät). In der Oberfläche heißt sie „Objekt“. |
| Objekttyp | Art der Ressource (Ferienwohnung, Raum, Person, Gerät …). Legt den Buchungsmodus fest und liefert Vorgabewerte. |
| Buchungsmodus | `day` (tageweise), `period` (Zeitraum), `appointment` (Termin, ab 1.1) |
| Status | Konfigurierbarer Zustand einer Buchung (z. B. angefragt, reserviert, gebucht, gesperrt). Das Verhalten steckt in Eigenschaften, nicht im Namen. |
| Präsentation | Seite in einem anderen Modul, die das Objekt beschreibt (news_img-Beitrag, Catamaran-Content) |
| Buchung | Belegter Zeitraum einer Ressource. Sperrzeiten sind ebenfalls Buchungen, mit einem blockierenden Status wie „gesperrt“. |
| Anfrage | Kontaktdaten zu einer Buchung, die im Frontend angefragt wurde |

---

## 3. Zeitlogik

- Alle Zeiträume sind **halboffen**: `[start_at, end_at)`. Der Beginn gehört dazu, das Ende nicht.
- **Überschneidung:** `a.start_at < b.end_at AND a.end_at > b.start_at`. Einheitlich für alle Modi.
- **Pufferzeit:** Zwischen zwei Buchungen derselben Ressource müssen mindestens
  `buffer_after` (Nachbereitung der früheren) + `buffer_before` (Vorbereitung der späteren)
  Minuten frei bleiben. Beim Prüfen wird der neue Zeitraum deshalb auf beiden Seiten um diese
  Summe erweitert.
- **Tageweise:** `start_at` ist der Anreisetag um 00:00, `end_at` der Abreisetag um 00:00. Damit
  ist ein Wechseltag automatisch erlaubt (2.–6. und 6.–10. überschneiden sich nicht). Die
  Anzeige leitet halbe Tage daraus ab: Anreise rechts, Abreise links. Die Uhrzeiten für An-
  und Abreise (`checkin_time`/`checkout_time`) dienen nur der Information.
- **Zeitraum:** Beginn und Ende liegen auf dem Raster (`slot_minutes`), innerhalb der
  Öffnungszeiten.
- **Gleichzeitige Buchungen:** Prüfung auf Überschneidung und Speichern laufen in einer
  Transaktion, die die Ressource sperrt. MySQL: `SELECT … FROM resources WHERE resource_id = ?
  FOR UPDATE`. SQLite kennt kein `FOR UPDATE`; dort startet die Transaktion mit
  `BEGIN IMMEDIATE`, was Schreibzugriffe serialisiert. So können zwei gleichzeitige Anfragen
  nicht denselben Zeitraum belegen.
- Gespeichert wird als `DATETIME` in der Zeitzone von WBCE. Es gibt keine Enden mehr wie
  `23:59:59` (anders als in v2).

### Status (konfigurierbar)
Status werden pro Abschnitt angelegt. Der Kern wertet **nie den Namen** eines Status aus,
nur seine Eigenschaften:

| Eigenschaft | Werte | Wirkung |
|---|---|---|
| Bezeichnung | Freitext | Anzeige in Backend und Frontend |
| blockiert | ja / nein | belegt den Zeitraum (Überschneidungsprüfung, Verfügbarkeit) |
| öffentliche Darstellung | mit Bezeichnung / als „belegt“ / gar nicht | was Besucher sehen |
| Farbe | Hex-Wert | Schriftfarbe wird automatisch kontrastreich gewählt (wie bei den Tags in news_img) |
| Muster | voll / schraffiert / gepunktet / nur Rahmen | damit der Status nicht nur über die Farbe erkennbar ist |
| Rolle | keine / neue Anfrage / Anfrage bestätigt / Anfrage abgelehnt | Ablauf bei Anfragen (siehe unten) |
| aktiv, Reihenfolge | | |

**Rollen:** Der Ablauf bei Anfragen braucht drei feste Ziele. Jede Rolle ist pro Abschnitt
genau einem Status zugeordnet:
- *neue Anfrage*: Status für eingehende Anfragen (Vorgabe: „angefragt“, blockierend)
- *Anfrage bestätigt*: Status nach „Bestätigen“ und bei sofort verbindlichen Ressourcen
  (Vorgabe: „gebucht“)
- *Anfrage abgelehnt*: Status nach „Ablehnen“ (Vorgabe: „abgelehnt“, nicht blockierend,
  nicht öffentlich)

**Vorgaben beim Anlegen eines Abschnitts:**
| Status | blockiert | öffentlich | Rolle |
|---|---|---|---|
| angefragt | ja | gar nicht (einstellbar) | neue Anfrage |
| reserviert | ja | mit Bezeichnung | – |
| gebucht | ja | mit Bezeichnung | Anfrage bestätigt |
| gesperrt | ja | als „belegt“ | – |
| abgelehnt | nein | gar nicht | Anfrage abgelehnt |
| storniert | nein | gar nicht | – |

**Regeln:**
- Ein Status, der verwendet wird, lässt sich nicht löschen, nur deaktivieren. Ein Status mit
  Rolle lässt sich erst deaktivieren, wenn die Rolle einem anderen zugeordnet ist.
- Wird ein Status von „nicht blockierend“ auf „blockierend“ umgestellt, prüft der Kern vorher,
  ob dadurch bestehende Buchungen kollidieren, und lehnt die Änderung mit einer Liste der
  Konflikte ab.
- Weil Status pro Abschnitt gepflegt werden, ergeben sich mehrsprachige Bezeichnungen von
  selbst (in WBCE hat jede Sprache eigene Seiten und Abschnitte).

---

## 4. Datenmodell

Tabellennamen: Präfix des CMS + `mod_reservations_` in WBCE. Der Kern bekommt das Präfix
übergeben und kennt keine CMS-Konstanten.

**Portabel für MySQL/MariaDB und SQLite:**
- Keine `ENUM`-Spalten. Statt `ENUM(…)` gilt überall `VARCHAR(20)`. Die erlaubten Werte
  prüft der Kern über PHP-Enums.
- `DATETIME`/`TIME` werden als Text im Format `Y-m-d H:i:s` bzw. `H:i:s` geschrieben und
  verglichen. Das ist in beiden Datenbanken korrekt sortierbar.
- Keine MySQL-Funktionen in Abfragen (`UNIX_TIMESTAMP`, `NOW()`, `ON DUPLICATE KEY` …).
  Zeitwerte kommen als Parameter aus der Uhr des Kerns.
- Das DDL liefert der Kern je Plattform (`mysql`, `sqlite`). Die Auswahl erfolgt über
  `PDO::ATTR_DRIVER_NAME`. MySQL: InnoDB, utf8mb4.
- Gelesene Werte werden im Kern ausdrücklich typisiert (`(int)`, Enums, `DateTimeImmutable`).
  Der Kern verlässt sich nicht auf Fetch-Modus oder Typverhalten des Treibers.

### settings (ein Datensatz pro Abschnitt)
| Spalte | Typ | Hinweis |
|---|---|---|
| section_id | INT PK | |
| page_id | INT | |
| display_mode | VARCHAR(20) | `single` oder `multi`; Einzelobjekt oder Belegungsplan |
| default_view | VARCHAR(20) | `plan`, `month`, `week`, `list` |
| view | VARCHAR(50) | Darstellungsvariante (Ordner unter `views/`) |
| plan_range | TINYINT | 1 oder 3 Monate |
| show_labels | TINYINT(1) | öffentliche Beschriftungen anzeigen |
| show_requests | TINYINT(1) | angefragte Zeiträume öffentlich zeigen |
| requests_enabled | TINYINT(1) | Anfrageformular aktiv |
| notify_email | VARCHAR(255) | Empfänger für Benachrichtigungen |
| retention_days | SMALLINT | Kontaktdaten nach X Tagen löschen (Standard 90) |
| past_months / future_months | SMALLINT | Navigationsgrenzen |

### types (Objekttypen)
| Spalte | Typ | Hinweis |
|---|---|---|
| type_id | INT PK AI | |
| section_id | INT, Index | |
| title | VARCHAR(100) | z. B. „Ferienwohnung“, „Besprechungsraum“ |
| booking_mode | VARCHAR(20) | `day`, `period`, `appointment`; `appointment` erst ab 1.1 auswählbar |
| defaults | TEXT (JSON) | Vorgabewerte für neue Objekte dieses Typs (Raster, Puffer, An- und Abreisezeit …) |
| position | INT | Reihenfolge, auch Gruppierung im Belegungsplan |

Der Buchungsmodus hängt am Typ, nicht am einzelnen Objekt. Die Vorgabewerte werden beim
Anlegen eines Objekts **kopiert**, nicht zur Laufzeit vererbt. Spätere Änderungen am Typ
ändern also keine bestehenden Objekte. Beim Anlegen eines Abschnitts gibt es einen Typ als
Vorgabe, z. B. „Objekt“ mit Modus tageweise.

### statuses
| Spalte | Typ | Hinweis |
|---|---|---|
| status_id | INT PK AI | |
| section_id | INT, Index | |
| title | VARCHAR(100) | |
| blocks | TINYINT(1) | |
| visibility | VARCHAR(20) | `label`, `busy`, `hidden` |
| color | VARCHAR(7) | |
| pattern | VARCHAR(20) | `solid`, `striped`, `dotted`, `outline` |
| role | VARCHAR(20) NULL | `request_new`, `request_confirmed`, `request_rejected`; pro Abschnitt eindeutig |
| active | TINYINT(1) | |
| position | INT | |

### resources
| Spalte | Typ | Hinweis |
|---|---|---|
| resource_id | INT PK AI | |
| section_id | INT, Index | |
| type_id | INT, Index | bestimmt den Buchungsmodus |
| title | VARCHAR(255) | |
| color | VARCHAR(7) | nur als Kennfarbe, nie als einzige Information |
| active | TINYINT(1) | inaktiv: nicht im Frontend und nicht anfragbar |
| position | INT | Reihenfolge |
| presentation_ref | VARCHAR(100) NULL | neutraler Verweis auf die Präsentation, z. B. `news_img:12` (siehe Abschnitt 8) |
| capacity | SMALLINT NULL | Personen, nur zur Information |
| auto_confirm | TINYINT(1) | Anfrage sofort verbindlich |
| slot_minutes | SMALLINT | Raster im Modus `period` (15/30/60) |
| min_duration / max_duration | INT NULL | Minuten bzw. Nächte je nach Modus |
| buffer_before / buffer_after | SMALLINT | Minuten |
| lead_min_minutes | INT | frühestens X Minuten vorher buchbar |
| lead_max_days | INT | höchstens X Tage im Voraus |
| checkin_time / checkout_time | TIME NULL | Modus `day`, nur Anzeige |

### hours (Verfügbarkeit, Modus `period`/`appointment`)
| Spalte | Typ | Hinweis |
|---|---|---|
| hour_id | INT PK AI | |
| resource_id | INT, Index | |
| weekday | TINYINT | 1 = Mo … 7 = So (ISO) |
| start_time / end_time | TIME | mehrere Zeilen pro Tag möglich (Mittagspause) |

Keine Zeilen bedeutet: rund um die Uhr verfügbar.

### bookings
| Spalte | Typ | Hinweis |
|---|---|---|
| booking_id | INT PK AI | |
| section_id | INT | |
| resource_id | INT | Index (resource_id, start_at, end_at) |
| start_at / end_at | DATETIME | halboffen |
| status_id | INT | Index (section_id, status_id); Verhalten über `statuses` |
| label | VARCHAR(255) | öffentliche Beschriftung, optional |
| note | TEXT | interne Notiz, nie im Frontend |
| service_id | INT NULL | ab 1.1 |
| created_at / updated_at | DATETIME | |
| created_by / updated_by | INT NULL | WBCE-User, NULL bei Anfrage aus dem Frontend |

### requests
| Spalte | Typ | Hinweis |
|---|---|---|
| request_id | INT PK AI | |
| booking_id | INT UNIQUE | 1:1 zur Buchung |
| name / email / phone | VARCHAR | personenbezogen |
| persons | SMALLINT NULL | |
| message | TEXT | |
| privacy_accepted_at | DATETIME | |
| created_at | DATETIME | |
| purge_after | DATETIME | danach personenbezogene Felder leeren |

### Ab 1.1: services, service_resources
`services` (service_id, section_id, title, duration_minutes, buffer_after, active, position)
sowie die Zuordnung `service_resources` (service_id, resource_id).

---

## 5. Architektur

### Gemeinsame Ablage (ein Repository)
```
reservations/                    Repository
  core/                          CMS-unabhängiger Kern, Namespace Reservations\Core
    src/
      Domain/      TimeRange (halboffen), Enums BookingMode/DisplayMode/Visibility/
                   Pattern/StatusRole, Type, Status, Resource, Booking, Request, Hours
                   (unveränderliche Wertobjekte)
      Service/     BookingService (anlegen, ändern, Überschneidung, Status-Übergänge)
                   AvailabilityService (Öffnungszeiten, Sperrzeiten, Vorlauf, Puffer)
                   RequestService (Anfrage annehmen, bestätigen, ablehnen, Löschfrist)
      Calendar/    Daten für Plan, Monat, Woche, Liste (keine Ausgabe von HTML)
      Port/        Schnittstellen zum CMS (siehe unten)
      Storage/Pdo/ Repositories mit Prepared Statements, Schema je Plattform
      Result/      Ergebnis- und Fehlerobjekte mit Text-Schlüsseln
    lang/          Texte des Kerns (Fehler, Status, Mailvorlagen) als PHP-Arrays, DE und EN
    tests/         PHPUnit
    composer.json  PSR-4-Autoload; PHPUnit nur als Entwicklungsabhängigkeit
  wbce/                          WBCE-Modul (wird als Verzeichnis modules/reservations installiert)
    info.php install.php upgrade.php uninstall.php precheck.php
    add.php delete.php view.php modify.php     Einstiegspunkte, dünn
    backend/ ajax/                             Aktionen
    Adapter/                                   WBCE-Umsetzungen der Ports
    templates/ views/<view>/                   Ausgabe (Backend und Frontend)
    languages/                                 Texte der WBCE-Oberfläche
  catamaran/                     später: Symfony-Bundle
  docs/
  build/                         Skript: kopiert core/src und core/lang nach wbce/core/
                                 und erzeugt das Installations-ZIP
```

Das WBCE-Modul enthält im ZIP eine Kopie des Kerns unter `core/` mit einem kleinen
PSR-4-Autoloader. In WBCE-Modulen gibt es kein Composer. Zur Laufzeit gibt es keine
Abhängigkeiten außer PHP selbst.

### Ports (Schnittstellen zum CMS)
| Port | WBCE (1.7) | Catamaran (später) |
|---|---|---|
| `PDO` + Tabellenpräfix | `$database->getPDO()`, `TABLE_PREFIX` | Doctrine `getNativeConnection()` |
| `Clock` | Systemuhr in der WBCE-Zeitzone | Symfony Clock |
| `Mailer` | `$wb->mail()` | Symfony Mailer |
| `Translator` | Kern-Texte + WBCE-Sprachdatei | Symfony Translation |
| `PermissionChecker` | Backend-Recht `pages_modify` bzw. Rechte des Abschnitts | Symfony Security |
| `UrlGenerator` | Seitenlink + Parameter | Router |
| `PresentationProvider` | news_img-Beiträge | Content-Einträge eines Content-Models |

Der Kern nutzt das PDO-Objekt direkt, nicht die Methoden der WBCE-Klasse `Database`.
Dadurch funktionieren dieselben Repositories auch unter Catamaran. Die WBCE-Verbindung
läuft mit `ERRMODE_EXCEPTION`; der Kern fängt `PDOException` selbst ab und setzt Fetch-Modus
und Typen ausdrücklich. Transaktionen laufen über `PDO::beginTransaction()`. Damit ist das
Anlegen einer Buchung samt Prüfung auf Überschneidung atomar (siehe Abschnitt 3).

### Grundsätze
- **Der Kern weiß nichts vom CMS:** keine Globals, Konstanten, `$_POST`, Sessions oder `echo`.
  Er bekommt Werte und Ports und gibt Ergebnisobjekte zurück. Fehler kommen als Schlüssel mit
  Parametern, übersetzt wird im Wrapper.
- **Die Wrapper sind dünn:** Anfrage lesen, FTAN prüfen, Kern aufrufen, Template ausgeben.
  Keine Fachlogik im Wrapper. Bearbeiten im Frontend ist später nur ein weiterer Wrapper-Weg
  mit anderem `PermissionChecker`.
- Anders als die `cmsbridge` in openimmo gibt es **keine Erkennung des CMS über Konstanten**
  und keine Abzweigungen „wenn WBCE, dann …“. Jeder Wrapper übergibt seine eigenen Adapter.
- Kein Code auf oberster Ebene, der Funktionen deklariert, die bei mehreren Abschnitten auf
  einer Seite doppelt geladen werden (Fehler aus v2). Nur Klassen über den Autoloader.
- PHP ab 8.2, getestet bis 8.5. `declare(strict_types=1)`, Enums, `readonly`-Klassen. Kurze
  Array-Syntax.
- Datum und Uhrzeit über `DateTimeImmutable` und `IntlDateFormatter`.
- Keine externen Abhängigkeiten zur Laufzeit. Vanilla-JS, nur als Ergänzung (progressive
  enhancement).

### Tests
- PHPUnit für den Kern, Schwerpunkt `TimeRange`, Überschneidung, Pufferzeit, Öffnungszeiten,
  Vorlauf, Status-Übergänge, Wechseltag, Grenzen bei Monats-/Jahreswechsel und Sommerzeit.
- Die Repositories laufen in den Tests gegen **SQLite im Speicher**. Optional derselbe
  Testlauf gegen MySQL/MariaDB über eine Umgebungsvariable.
- Die Uhr ist ein Port, deshalb sind Tests zeitunabhängig.
- Die WBCE-Wrapper werden von Hand getestet (Checkliste in `docs/`).

---

## 6. Sicherheit

Es gelten die Konventionen aus news_img:
- FTAN für jede Statusänderung, auch per GET und AJAX (`getFTAN(false)` / `checkFTAN()`)
- SQL ausschließlich über **Prepared Statements** mit gebundenen Parametern. Kein
  `escapeString()` (in WBCE 1.7 ohnehin veraltet). Tabellennamen kommen nur aus dem
  Präfix und festen Namen im Kern, nie aus Benutzereingaben.
- Jede Ausgabe mit `htmlspecialchars(…, ENT_QUOTES)`
- Anfrageformular: Honeypot, Zeitprüfung (Mindestdauer bis zum Absenden), Ratenbegrenzung
  pro Sitzung, serverseitige Prüfung auf Verfügbarkeit (dem Browser wird nicht geglaubt)
- Beachten: `print_error()` beendet das Skript, `print_success()` nicht

---

## 7. Barrierefreiheit (WCAG 2.2 AA)

- Plan und Kalender als `<table>` mit `<th scope>`. Jede Zelle mit vollständigem Text für
  Screenreader (Datum ausgeschrieben, Status, An- oder Abreise).
- Status nie nur über die Farbe, sondern zusätzlich mit Muster oder Symbol und Rand. Muss
  auch im Kontrastmodus (`forced-colors`) funktionieren. Kontrast mindestens 4,5:1 für Text
  und 3:1 für Grafiken.
- Weil Status-Farben frei wählbar sind: Das Backend warnt bei zu geringem Kontrast zum
  Hintergrund, die Schriftfarbe wird automatisch gewählt, und jeder Status hat Pflicht-Muster
  oder Rahmen.
- Die Listenansicht ist immer erreichbar.
- Hauptweg zum Buchen ist das Formular mit nativen Feldern für Datum und Uhrzeit. Die
  Auswahl im Plan ist nur eine Abkürzung.
- Termin-Auswahl (1.1) als Radio-Buttons in `<fieldset>`.
- Ergebnis der Verfügbarkeitsprüfung über eine `aria-live`-Region. Fehler mit
  `aria-describedby` und `aria-invalid`, nach dem Absenden eine Fehlerzusammenfassung.
- Klickflächen mindestens 24 × 24 px, sichtbarer Fokus, `prefers-reduced-motion`, kein
  Bild-Captcha. Läuft das Sicherheitstoken ab, bleiben die Eingaben erhalten.
- Tests: axe/WAVE, nur Tastatur, NVDA mit Firefox, Kontrastmodus, Zoom 200 % und 400 %

---

## 8. Präsentation der Objekte

Der Kern kennt Objekte nur mit Name, Typ und Buchungseinstellungen. Alles Weitere (Texte,
Bilder, Galerie, Kategorien) liegt in einem Modul des CMS, das dafür ohnehin gemacht ist.

**Im Kern:**
- `resources.presentation_ref` speichert einen neutralen Verweis in der Form
  `<anbieter>:<id>`, z. B. `news_img:12` oder `catamaran:content:42`. Der Kern deutet den
  Inhalt nicht.
- Der Port `PresentationProvider` liefert zu einer Liste von Verweisen auf einmal URL, Titel
  und optional ein Vorschaubild (`resolveMany()`, keine einzelne Abfrage pro Objekt). Er
  liefert außerdem die Auswahlliste für das Backend (`choices()`).
- Ohne Anbieter oder bei einem verwaisten Verweis funktioniert alles weiter, nur ohne Link
  und Vorschaubild.

**WBCE: news_img**
- Der Adapter liest die Beiträge aus den Tabellen von news_img (nur lesend) und baut den
  Link wie news_img selbst. Ist news_img nicht installiert, ist der Anbieter einfach nicht
  verfügbar.
- **Droplet** `[[reservations?resource=ID]]` bzw. `?section=ID`: zeigt den Kalender im
  Beitragstext. news_img braucht dafür keine Änderung.
- Optional später: Platzhalter `[RESERVATIONS]` in news_img.

**Catamaran: Content-Model**
- Ein Content-Model wie „Objekt“ (Titel, Beschreibung, Bilder, Kategorie) übernimmt die Rolle
  von news_img. Das passt zum modellgetriebenen Ansatz von Catamaran.
- Der Adapter löst `catamaran:content:<id>` über die Content-Entities auf.
- Denkbar später: ein Feldtyp (`ElementType`) „Belegungskalender“, damit der Kalender direkt
  im Content erscheint, als Gegenstück zum Droplet.

---

## 9. Ausbaustufe LeafletMaps (Notiz)

Vorbild ist das Modul openimmo (`WBPortable/root/wbdemo/modules/openimmo`, `inc/class.openimmo.php`):
- Einstellungen `leaflet_section` (Abschnitt des Moduls LeafletMaps, Auswahl über
  `getModuleSections('LeafletMaps')`) und `leaflet_icon`
- Marker werden in `{TP}mod_leafletmaps_markers` geschrieben, mit `marker_name` als Verweis
  auf das eigene Objekt. Beim Löschen oder Neuaufbau werden die Marker des Abschnitts entfernt.
- Für reservations: Koordinaten bzw. Adresse an der Ressource (neue Spalten, per Upgrade
  ergänzbar), Marker-Popup mit Link zur Belegung bzw. zur Objektbeschreibung

---

## 10. Offene Punkte
- Sollen Anfragen ohne Bestätigungslink den Zeitraum sofort blockieren, oder erst nach der
  Bestätigung per Mail (Double-Opt-in)? 1.0 sieht Ersteres vor; mit konfigurierbaren Status
  lässt sich das ohnehin über „blockiert“ beim Status „angefragt“ abschalten.
- Darf ein Abschnitt gemischte Buchungsmodi enthalten (z. B. Wohnungen und ein Raum)? Das
  Datenmodell erlaubt es über die Objekttypen. Der Belegungsplan würde dann nach Typ
  getrennte Blöcke zeigen.
- Welche Buchungen zählt die Spalte „Buchungen“ in der Objektliste des Backends? Derzeit alle
  (auch vergangene, stornierte, abgelehnte). Tendenz: nur laufende (heute liegt zwischen
  Beginn und Ende) und zukünftige. Entscheidung später.

### Erledigt
- Objekttypen und Status: **pro Abschnitt**.
- Repository: `D:\00_Development\Development\reservations\`.
- Testumgebung mit WBCE 1.7.0: besorgt Bianka.
- Mindest-WBCE-Version: **1.7.0** (Datenbankklasse auf PDO-Basis, auch SQLite).
- Datenbankzugriff: PDO mit Prepared Statements, gemeinsam für alle CMS.
- Ablage: ein Repository mit Kern und Wrappern.
- Tests: PHPUnit für den Kern.
- Entwicklung in WBCE: Verzeichnisverknüpfung von `modules/reservations` auf `reservations/wbce`.
