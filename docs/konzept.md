# reservations – Konzept

Stand: 05.11.2026 · Modulversion 0.9.0

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
- Buchungsmodus **tageweise** oder **Zeitraum**, pro Abschnitt eingestellt
- **Objekttypen** pro Abschnitt (z. B. Ferienwohnung, Raum, Gerät): Name, Reihenfolge,
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
- Benutzerhilfe als statische Seiten im Modul, DE und EN (siehe Abschnitt 5)

### Ausbaustufen (nicht in 1.0)
| Version | Inhalt |
|---|---|
| 1.1 | Buchungsmodus **Termin**: Leistungen mit fester Dauer, Berechnung freier Termine, Auswahl im Frontend (Leistung, Tag, Uhrzeit) |
| später | **Wählbare Views:** Das Modul liefert mehrere Darstellungen mit, und der Admin kann eigene erstellen und mit anderen teilen. Die Mechanik gibt es bereits in einem anderen Modul und wird von dort übernommen. Erste Darstellung nach dem Vorbild von Vereins-Buchungssystemen (Beispiel: ep-3): **Tagesraster** mit den Objekten als Spalten und den Uhrzeiten als Zeilen, je Tag eine Tabelle, am Desktop mehrere Tage nebeneinander, am Handy einer; Text in den Zellen („frei“, „vorbei“, „geschlossen“), dazu „Heute“ und ein Datumsfeld in der Navigation. So lassen sich mehrere Objekte zur selben Uhrzeit vergleichen, was die Woche mit einer Tabelle je Objekt nicht leistet |
| später | **Reihenfolge der Objekte per Auf- und Ab-Buttons** in der Objektliste, statt nur über das Zahlenfeld |
| später | **Import aus bookings_v2** (Abschnitt wird Objekt, Gruppenname wird interne Notiz) |
| später | **LeafletMaps**: Ressourcen als Marker auf einer Karte, nach dem Muster von openimmo (siehe Abschnitt 9) |
| später | Bearbeiten im Frontend für angemeldete Benutzer |
| später | Stückzahl pro Ressource („noch 2 von 3 Beamern frei“) |
| später | Bestätigungslink für Anfragen (Double-Opt-in) |
| später | **Text beim Bestätigen oder Ablehnen:** der Admin gibt einen eigenen Text für die Mail an die anfragende Person ein oder wählt einen hinterlegten **Textblock** (pro Abschnitt gepflegt, z. B. „Schlüsselübergabe“, „Zeitraum leider belegt“) und passt ihn an. Der Text stammt vom Admin, darf also in die Mail; nur Eingaben des Besuchers bleiben draußen (siehe Abschnitt 6). Zusammen damit: **Gast über Änderungen benachrichtigen** – beim Speichern einer Buchung mit Anfrage (Zeitraum, Objekt oder Status geändert) optional eine Mail mit den neuen Daten und demselben Text bzw. Textblock. Bis dahin lösen Änderungen über das Buchungsformular keine Mail aus |
| später | Weitere Sprachen (DA, FR, NL, NO, SK wie in v2) |
| später | **Ratenbegrenzung einstellbar** je Abschnitt (Anzahl und Zeitfenster); bis dahin fest 3 Anfragen pro Stunde und Besucher |
| später | **Serienbuchungen** (z. B. feste Trainingszeiten wöchentlich) – Voraussetzung für Kontingente und Auto-Stornierung |
| später | **Kapazität nach Personenzahl (PAX):** eine Buchung belegt so viele Einheiten, wie für die Personenzahl nötig sind (z. B. 2 Plätze für 30 Personen). Braucht Buchungen über mehrere Ressourcen; verwandt mit „Stückzahl“ |
| später | **Teilnehmer und Kontingente:** Anmeldungen zu einer Buchung, Auslastung fester Trainingszeiten überwachen |
| später | **Auto-Stornierung:** Freigabe zum Zeitpunkt T−X, wenn die Mindest-Teilnehmerzahl fehlt. Braucht Teilnehmer und einen zeitgesteuerten Auslöser (WBCE hat keinen eigenen Cron) |
| später | **Benachrichtigungs-Ereignisse:** bei jeder Statusänderung Mails an Teilnehmer und externe Stellen (Anlagenbetreiber), ggf. Webhooks. 1.0 kennt nur die Mails zu Anfragen |

Herkunft der letzten fünf Zeilen: Vorschläge eines Interessenten (2026-10-29), Zielgruppe
Vereinssport/Sportanlagen. „Abstrakte Ressourcen unabhängig von der Sportart“ deckt das
Konzept mit generischen Objekten und Objekttypen bereits ab.

### Bewusst gestrichen
- Gruppen aus v2. Die Unterscheidung übernehmen jetzt die Objekte.
- Quartals- und Drei-Monats-Ansicht aus v2. Der Zeitraum von 3 Monaten im Belegungsplan
  bleibt als Umschaltung erhalten.
- Kopf, Fuß und Stylesheet als Textfelder pro Abschnitt. Stattdessen setzt ein Template
  CSS-Variablen auf `.mod_reservations` (`--rsv-line`, `--rsv-surface`, `--rsv-ink`,
  `--rsv-weekend`, `--rsv-closed`, `--rsv-closed-ground`, `--rsv-error`, `--rsv-success`,
  `--rsv-radius`, `--rsv-space`, `--rsv-plan-row`, `--rsv-month-row`, `--rsv-week-row`). Die
  Standardwerte stehen in `:where(.mod_reservations)` mit Spezifität 0, ein einfacher Selektor
  gewinnt also. Auf `:root` oder `body` wirken sie nicht.
- Darstellungsvarianten als Ordner (`views/<view>/`, Spalte `settings.view`): nie gebaut, mit
  den CSS-Variablen unnötig; die Spalte entfällt.

---

## 2. Begriffe

| Begriff | Bedeutung |
|---|---|
| Abschnitt | WBCE-Section mit dem Modul. Hat eigene Einstellungen und eigene Ressourcen. |
| Ressource | Das buchbare Ding (Wohnung, Raum, Person, Gerät). In der Oberfläche heißt sie „Objekt“. |
| Objekttyp | Art der Ressource (Ferienwohnung, Raum, Person, Gerät …). Liefert Vorgabewerte für neue Objekte. |
| Buchungsmodus | `day` (tageweise), `period` (Zeitraum), `appointment` (Termin, ab 1.1). Gilt für alle Objekte eines Abschnitts. |
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
- **Zeitraum:** Bei Anfragen liegen Beginn und Ende auf dem Raster (`slot_minutes`) und in
  den Öffnungszeiten; was dazwischen liegt, ist frei (Ausleihe über Nacht oder Wochenende).
  Raster und Öffnungszeiten zählen in der Ortszeit; die erlaubten Raster teilen eine Stunde,
  so bleibt das Raster bei der Zeitumstellung erhalten. Im Backend gelten sie nicht.
- **Gleichzeitige Buchungen:** Prüfung auf Überschneidung und Speichern laufen in einer
  Transaktion, die die Ressource sperrt. MySQL: `SELECT … FROM resources WHERE resource_id = ?
  FOR UPDATE`. SQLite kennt kein `FOR UPDATE`; dort startet die Transaktion mit
  `BEGIN IMMEDIATE`, was Schreibzugriffe serialisiert. So können zwei gleichzeitige Anfragen
  nicht denselben Zeitraum belegen.
- Gespeichert wird als `DATETIME` in der Zeitzone von WBCE. Es gibt keine Enden mehr wie
  `23:59:59` (anders als in v2).

### Wochenansicht (0.8.0, nur Modus `period`)
- Je Objekt eine Tabelle untereinander: Tage als Spalten, Uhrzeiten als Zeilen. Die Woche
  beginnt mit dem ersten Wochentag der Sprache; geblättert wird über `rsv_week_<Abschnitt>`
  (Datum des Wochenanfangs), begrenzt auf das Kalenderfenster (`past_months`/`future_months`).
  Ohne Parameter gilt die aktuelle Woche, beim Wechsel aus einem anderen Monat dessen erste
  Woche.
- Zeilen von 30 Minuten, bei einem Raster von 60 Minuten eine Stunde. Die Spanne reicht von der
  frühesten Öffnung bis zur spätesten Schließung der Woche, ohne Öffnungszeiten 08:00–20:00,
  erweitert um Beginn und Ende jeder Buchung der Woche. Was eine Buchung dazwischen abdeckt
  (Ausleihe übers Wochenende), erweitert sie nicht.
- `Calendar\Week` liefert je Tag Blöcke gleichen Zustands (frei, geschlossen, außerhalb, eine Buchung) mit
  der Zahl ihrer Zeilen; die Ausgabe setzt sie als `rowspan`. Eine Zeile gehört zur ersten
  Buchung, die sie berührt, sonst ist sie geschlossen, wenn die Öffnungszeiten sie nicht
  berühren. Die Zeilen zählen in Ortszeit: Die beim Beginn der Sommerzeit fehlende Stunde
  hängt am Block davor, die Beschriftung bleibt gleich.
- Eine Buchung zeigt ihre genauen Zeiten (auf Block und Tag gekürzt), sichtbar ab zwei Zeilen
  Höhe; geschlossene Zeiten sind grau schraffiert und stehen in der Legende.
- Freie Zeiten außerhalb des anfragbaren Fensters (früheste bis späteste Anfrage des Objekts,
  ohne Anfragen nur die Vergangenheit) tragen dieselbe Schraffur, auch als Tage in Plan und
  Kalender. Die Legende nennt alles „nicht verfügbar“, der Screenreader-Text den Grund
  („vorbei“, „nicht anfragbar“). Buchungen bleiben sichtbar, wie sie sind.

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
- *neue Anfrage*: Status für eingehende Anfragen (Vorgabe: „angefragt“, blockierend, seit
  0.9.0 öffentlich mit Bezeichnung: ein blockierter Zeitraum, der frei aussieht, lehnt
  Anfragen ohne erkennbaren Grund ab; bestehende Abschnitte bleiben unverändert)
- *Anfrage bestätigt*: Status nach „Bestätigen“ und bei sofort verbindlichen Ressourcen
  (Vorgabe: „gebucht“)
- *Anfrage abgelehnt*: Status nach „Ablehnen“ (Vorgabe: „abgelehnt“, nicht blockierend,
  nicht öffentlich)

**Vorgaben beim Anlegen eines Abschnitts:**
| Status | blockiert | öffentlich | Rolle |
|---|---|---|---|
| angefragt | ja | mit Bezeichnung (einstellbar) | neue Anfrage |
| reserviert | ja | mit Bezeichnung | – |
| gebucht | ja | mit Bezeichnung | Anfrage bestätigt |
| gesperrt | ja | als „belegt“ | – |
| abgelehnt | nein | gar nicht | Anfrage abgelehnt |
| storniert | nein | gar nicht | – |

**Regeln:**
- Ein Status, der verwendet wird, lässt sich nicht löschen, nur deaktivieren. Ein Status mit
  Rolle lässt sich erst deaktivieren, wenn die Rolle einem anderen zugeordnet ist.
- Ein deaktivierter Status lässt sich nicht neu vergeben, bleibt aber an seinen Buchungen
  stehen. Blockiert er, zeigt der Kalender diese Buchungen weiter, sonst sähe die Zeit frei aus.
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
| default_view | VARCHAR(20) | `plan` (nur mehrere Objekte), `month` (nur Einzelobjekt), `week` (nur Modus `period`), `list` |
| plan_range | TINYINT | 1 oder 3 Monate |
| show_labels | TINYINT(1) | öffentliche Beschriftungen anzeigen |
| requests_enabled | TINYINT(1) | Anfrageformular aktiv (Standard aus) |
| notify_email | VARCHAR(255) | Empfänger für Benachrichtigungen über neue Anfragen; leer = keine Mail |
| retention_days | SMALLINT | Kontaktdaten X Tage nach dem angefragten Zeitraum löschen (Standard 90, 1–3650) |
| captcha | TINYINT(1) | Captcha (WBCE: captcha_control) im Anfrageformular; FTAN und Honeypot sind immer aktiv |
| client_secret | VARCHAR(64) | Schlüssel für den Besucher-Hash der Ratenbegrenzung; entsteht beim ersten Gebrauch, nicht Teil der Einstellungen im Formular |
| past_months / future_months | SMALLINT | Navigationsgrenzen |
| resource_id | INT NULL | Objekt für die Einzelobjekt-Darstellung; leer = erstes aktives |
| presentation_source | VARCHAR(100) NULL | Quelle der Objektbeschreibungen, z. B. `news_img:3` (News-Abschnitt; siehe Abschnitt 8) |
| booking_mode | VARCHAR(20) | `day`, `period`; `appointment` kommt mit 1.1 |

Ob angefragte Zeiträume öffentlich zu sehen sind, regelt die Sichtbarkeit des Status mit der
Rolle „neue Anfrage“; die frühere Spalte `show_requests` ist mit 0.5.0 entfallen.

Ein Abschnitt mischt keine Buchungsmodi. Wer Wohnungen und einen Raum anbietet, legt zwei
Abschnitte auf die Seite. Der Modus lässt sich nur ändern, solange der Abschnitt keine
Buchungen hat; sonst gingen beim nächsten Bearbeiten Uhrzeiten verloren.

### types (Objekttypen)
| Spalte | Typ | Hinweis |
|---|---|---|
| type_id | INT PK AI | |
| section_id | INT, Index | |
| title | VARCHAR(100) | z. B. „Ferienwohnung“, „Besprechungsraum“ |
| defaults | TEXT (JSON) | Vorgabewerte für neue Objekte dieses Typs: die Buchungseinstellungen (siehe unten) |
| position | INT | Reihenfolge, auch Gruppierung im Belegungsplan |

Die Vorgabewerte werden beim Anlegen eines Objekts **kopiert**, nicht zur Laufzeit vererbt.
Spätere Änderungen am Typ ändern also keine bestehenden Objekte. Erlaubt sind nur die
Schlüssel aus `BookableResource::RULE_KEYS` (`capacity`, `minPersons`, `autoConfirm`,
`slotMinutes`, `leadMinMinutes`, `leadMaxDays`, `minDuration`, `maxDuration`,
`bufferBefore`, `bufferAfter`); geprüft wird mit denselben Regeln wie beim Objekt
(`ResourceService::validateRules()`). Öffnungszeiten sind je Objekt verschieden und
gehören nicht zu den Vorgabewerten. Beim Anlegen eines
Abschnitts gibt es einen Typ als Vorgabe („Objekt“). Ein Typ, den Objekte verwenden, und der
letzte Typ eines Abschnitts lassen sich nicht löschen. Bis 0.2.0 stand der Buchungsmodus am
Typ (`types.booking_mode`); die Installation entfernt diese Spalte.

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
| type_id | INT, Index | Objekttyp (Vorgabewerte, Gruppierung) |
| title | VARCHAR(255) | |
| active | TINYINT(1) | inaktiv: nicht im Frontend und nicht anfragbar |
| position | INT | Reihenfolge |
| presentation_ref | VARCHAR(100) NULL | neutraler Verweis auf die Präsentation, z. B. `news_img:12` (siehe Abschnitt 8) |
| capacity | SMALLINT NULL | höchstens so viele Personen je Anfrage |
| min_persons | SMALLINT NULL | mindestens so viele Personen je Anfrage; macht die Personenzahl zur Pflicht |
| auto_confirm | TINYINT(1) | Anfrage sofort verbindlich |
| slot_minutes | SMALLINT | Raster im Modus `period`: 5, 10, 15, 20, 30 oder 60 Minuten (Standard 30); nur für Anfragen |
| min_duration / max_duration | INT NULL | Minuten bzw. Nächte je nach Modus; nur für Anfragen |
| buffer_before / buffer_after | SMALLINT | Minuten, nur Modus `period`; gelten für alle Buchungen |
| lead_min_minutes | INT | Vorlauf; im Modus `day` in ganzen Tagen ab heute gezählt |
| lead_max_days | INT NULL | höchstens X Tage im Voraus |
| checkin_time / checkout_time | TIME NULL | Modus `day`, nur Anzeige |

**Buchungseinstellungen** pflegt der Admin beim Bearbeiten eines Objekts; neue Objekte
übernehmen die Vorgabewerte ihres Typs. Das Formular zeigt die Einheiten des Modus: Vorlauf
in Tagen (`day`) bzw. Stunden (`period`), Dauer in Nächten bzw. Minuten, Raster und Puffer
nur im Modus `period`; gespeichert wird in den Einheiten der Tabelle. Personen, Raster,
Vorlauf und Dauer gelten nur für Anfragen über die Website; im Backend lassen sich
Buchungen frei eintragen.

### hours (Öffnungszeiten, Modus `period`/`appointment`)
| Spalte | Typ | Hinweis |
|---|---|---|
| hour_id | INT PK AI | |
| section_id | INT | seit 0.7.0, mit Standardwert 0 für bestehende Tabellen |
| resource_id | INT, Index | |
| weekday | TINYINT | 1 = Mo … 7 = So (ISO) |
| start_time / end_time | TIME | mehrere Zeilen pro Tag möglich (Mittagspause); 24:00 als Tagesende |

Keine Zeilen bedeutet: rund um die Uhr verfügbar; hat ein Tag Zeilen, sind die übrigen
Tage geschlossen. Ein Beginn liegt in [von, bis), ein Ende in (von, bis]; Mitternacht zählt
auch als 24:00 des Vortags. Im Backend wird je Wochentag ein Textfeld gepflegt
(„09:00–12:00, 13:00–17:00“, auch „9-12“); Lesen, Ausgeben und Prüfen übernimmt
`OpeningHours`. Sperrzeiten (Urlaub, Wartung) sind Buchungen mit blockierendem Status.

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
| client_hash | VARCHAR(64) NULL | HMAC-SHA256 der Besucheradresse mit `settings.client_secret`, für die Ratenbegrenzung; nach einem Tag geleert |

**Ablauf einer Anfrage** (`RequestService`):
- Prüfung: Anfragen im Abschnitt aktiv, Objekt aktiv, Name, E-Mail, Telefon, Personen
  (zwischen `min_persons` und `capacity`, mit Untergrenze Pflicht), Nachricht, Einwilligung,
  im Modus `period` Raster und Öffnungszeiten (vor dem Vorlauf, damit die Meldung
  „geschlossen“ statt „zu früh“ lautet), Vorlauf, Höchstvorlauf, Mindest- und Höchstdauer
  (Nächte bzw. Minuten), zuletzt die Ratenbegrenzung.
- Früheste Anreise (`earliestStart()`): im Modus `day` heute + Vorlauf in Tagen, nie vor
  morgen (2 Tage an einem Montag: Mittwoch); im Modus `period` jetzt + Vorlauf, aufgerundet
  auf das Raster und verschoben auf die nächste Öffnung.
- Spätester Beginn (`latestStart()`): Höchstvorlauf; ohne ihn gilt das Kalenderfenster, also
  Beginn spätestens zum Ende des letzten Monats, den Besucher ansteuern können
  (`future_months`). Ohne eigene Höchstdauer ist auch die Dauer höchstens so lang wie dieses
  Fenster. Sonst könnte eine einzige Anfrage ein Objekt über Jahrzehnte sperren (gefunden
  beim Pentest, 0.6.0).
- **Ratenbegrenzung:** höchstens 3 angelegte Anfragen pro Stunde je Besucher und Abschnitt
  (`request.too_many`). Erkannt wird der Besucher an `REMOTE_ADDR`, gespeichert nur der
  Hash (`requests.client_hash`), einen Tag lang. Hinter einem Reverse-Proxy teilen sich alle
  Besucher dessen Adresse; `X-Forwarded-For` wird nicht ausgewertet, weil es sich fälschen
  lässt. Gezählt wird außerhalb der Buchungssperre, gleichzeitige Anfragen eines Besuchers
  können die Grenze knapp überschreiten. Die Einwilligung im Formular nennt den Prüfwert.
- Formular: Die Datumsauswahl reicht von `earliestStart()` bis `latestStart()` (bei mehreren
  Objekten die weiteste Spanne) und hat das Raster als Schrittweite, wenn alle Objekte
  dasselbe haben. Bei einem einzelnen Objekt stehen Personen- und Dauergrenzen im Label und
  als Eingabegrenzen, im Modus `period` die Öffnungszeiten unter den Datumsfeldern. Nach dem
  Absenden ersetzt die Bestätigung das Formular. Die Umleitung nach einer erfolgreichen
  Anfrage trägt `rsv_sent=<Abschnitt>`, die Rücksprungadresse des Formulars nicht; so lädt
  der Link „Weitere Anfrage“ die Seite neu (ein Link, der sich nur im Anker unterscheidet,
  springt nur).
- Aufklappen (0.8.0): `frontend_body.js` macht die Überschrift des Formulars zum Schalter
  (Disclosure-Muster, `aria-expanded`) und klappt das Formular zu. Offen bleibt es ohne
  JavaScript, mit Fehlermeldung, nach dem Absenden und beim Sprung zum Anker
  `#rsv-request-<Abschnitt>`.
- Auswahl mehrerer Objekte (0.7.1): Unter der Auswahl steht je Objekt mit Grenzen eine Zeile
  (Personen, Dauer, frühester Beginn bei Vorlauf, Höchstvorlauf, Öffnungszeiten mit
  zusammengefassten Tagen wie „Mo.–Fr. 08:00–18:00“, „Buchung wird sofort bestätigt“), per
  `aria-describedby` an der Auswahl. Ohne JavaScript sind alle Zeilen sichtbar. Mit
  `assets/frontend_body.js` (ohne Bibliothek; das Droplet bindet die Datei selbst ein) ist
  nur die Zeile des gewählten Objekts sichtbar, in einer Live-Region angesagt, und die Grenzen
  des Objekts stehen an den Feldern (`min`, `max`, `step`, Pflicht der Personenzahl samt
  Sternchen). Die Werte kommen als `data-`Attribute an den Optionen. Der Server prüft wie
  immer je Objekt. Weil `hidden` für den Browser ein Autoren-Hinweis ist, hebt die
  CSS-Rücksetzung des Moduls es auf; eine eigene Regel stellt es wieder her.
- Buchung und Anfrage entstehen in **einer Transaktion** (`BookingService::create()` mit
  Rückruf), samt Prüfung auf Überschneidung. Status: Rolle „neue Anfrage“, bei
  `auto_confirm` gleich „Anfrage bestätigt“.
- Bestätigen und Ablehnen nur für Buchungen mit Anfrage; beim Bestätigen erneute Prüfung auf
  Überschneidung.
- `purge_after` = Ende des Zeitraums + `retention_days`, nach einer Ablehnung ab der
  Ablehnung. Geleert wird bei jedem Aufruf des Backends und der Kalenderseite (WBCE hat
  keinen Cron); die Buchung bleibt. Eine geänderte `retention_days` gilt auch für die
  gespeicherten Anfragen.
- Beim Löschen einer Buchung wird ihre Anfrage mitgelöscht.

### Ab 1.1: services, service_resources
`bookings` bekommt dann die Spalte `service_id`.
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
                   Pattern/StatusRole, Type, Status, Resource, Booking, Request,
                   OpeningHours (unveränderliche Wertobjekte)
      Service/     BookingService (anlegen, ändern, Überschneidung, Puffer, Status-Übergänge)
                   RequestService (Anfrage annehmen mit Vorlauf, Raster, Öffnungszeiten und
                   Ratenbegrenzung; bestätigen, ablehnen, Löschfrist)
                   ResourceService (Objekte, Buchungsregeln, Öffnungszeiten)
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
    twig/ assets/                              Ausgabe (Backend und Frontend), CSS und JavaScript
    languages/                                 Texte der WBCE-Oberfläche
  catamaran/                     später: Symfony-Bundle
  docs/
    help/                        Quellen der Benutzerhilfe (Markdown je Sprache, CSS, Bilder)
  build/                         build.php: kopiert core/src und core/lang nach wbce/core/
                                 und erzeugt das Installations-ZIP; help.php: erzeugt wbce/help/
```

Das WBCE-Modul enthält im ZIP eine Kopie des Kerns unter `core/` mit einem kleinen
PSR-4-Autoloader. In WBCE-Modulen gibt es kein Composer. Zur Laufzeit gibt es keine
Abhängigkeiten außer PHP selbst.

### Benutzerhilfe (0.9.0)
- Quellen sind Markdown-Dateien unter `docs/help/<sprache>/`; die Schreibregeln stehen in
  `docs/help/README.md`. `build/help.php` erzeugt daraus statische Seiten in `wbce/help/`,
  `build/build.php` ruft es vor dem ZIP auf. `wbce/help/` ist nicht im Repository.
- Die Seiten sind eigenständig: keine CMS-Seiten, keine Skripte, keine Schriften oder Dateien
  von außen. Sie funktionieren offline, im dunklen Schema, am Handy und im Druck.
- Dateinamen sind englisch und in allen Sprachen gleich, so führt der Sprachwechsel auf
  dieselbe Seite. Die Navigation kommt aus `_nav.md` je Sprache.
- Der Build bricht bei toten Links und Ankern ab und wenn Seite und Navigation nicht
  zusammenpassen; fehlende Bilder und Seiten in nur einer Sprache ergeben Warnungen.
- Einzige neue Abhängigkeit ist `league/commonmark`, nur für die Entwicklung. Das Modul
  bekommt nur das fertige HTML.
- Das Backend verlinkt je Bereich die passende Seite (`_help_link.twig`) in einem neuen Tab,
  in der Sprache des Backends, sonst auf Englisch.
- Die englischen Bildschirmfotos zeigen das echte Layout mit den Texten aus `languages.php`:
  WBCE erzwingt beim Bearbeiten die Sprache der Seite, und es gibt keine englische Testseite.

### Ports (Schnittstellen zum CMS)
| Port | WBCE (1.7) | Catamaran (später) |
|---|---|---|
| `PDO` + Tabellenpräfix | `$database->getPDO()`, `TABLE_PREFIX` | Doctrine `getNativeConnection()` |
| `Clock` | Systemuhr in der WBCE-Zeitzone | Symfony Clock |
| `Mailer` | `$wb->mail()` | Symfony Mailer |
| `Translator` | Kern-Texte + WBCE-Sprachdatei | Symfony Translation |
| `PermissionChecker` | Backend-Recht `pages_modify` bzw. Rechte des Abschnitts | Symfony Security |
| `UrlGenerator` | Seitenlink + Parameter | Router |
| `PresentationProvider` | news_img-Beiträge (vorerst ohne Port, siehe Abschnitt 8) | Content-Einträge eines Content-Models |

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
- Die WBCE-Wrapper werden von Hand getestet (Checkliste in `docs/testliste.md`).

---

## 6. Sicherheit

Es gelten die Konventionen aus news_img:
- FTAN für jede Statusänderung, auch per GET und AJAX (`getFTAN(false)` / `checkFTAN()`)
- SQL ausschließlich über **Prepared Statements** mit gebundenen Parametern. Kein
  `escapeString()` (in WBCE 1.7 ohnehin veraltet). Tabellennamen kommen nur aus dem
  Präfix und festen Namen im Kern, nie aus Benutzereingaben.
- Jede Ausgabe mit `htmlspecialchars(…, ENT_QUOTES)`
- Anfrageformular (WBCE: eigener Endpunkt `request.php`, danach Rücksprung zur Seite):
  - immer: FTAN, Honeypot, mindestens 2 Sekunden zwischen Anzeige und Absenden
  - optional je Abschnitt: Captcha aus captcha_control. Fehlt das Modul später, läuft das
    Formular ohne Captcha; die Einstellungen zeigen dann einen Hinweis, die Wahl bleibt
    gespeichert und greift wieder, sobald captcha_control zurück ist
  - serverseitige Prüfung auf Verfügbarkeit (dem Browser wird nicht geglaubt); bei einer
    Überschneidung nennt die Meldung keine Termine, sie könnten zu verborgenen Anfragen gehören
  - Rücksprung nur auf Pfade unterhalb von `WB_URL`, der Host kommt immer aus `WB_URL`
  - Ratenbegrenzung: 3 angelegte Anfragen pro Stunde je Besucher (siehe Ablauf einer Anfrage)
- Mails ohne Double-Opt-in: Wer eine fremde Adresse einträgt, löst eine Mail an sie aus.
  Mails an die anfragende Person enthalten deshalb nur festen Text, Objekt und Zeitraum,
  nichts vom Besucher Eingegebenes. Alle Angaben gehen nur an die Benachrichtigungsadresse.
  Sprache der Mails ist die der Seite. Ein gescheiterter Versand wird ohne Adresse
  protokolliert, die Anfrage bleibt.
- Beachten: `print_error()` beendet das Skript, `print_success()` nicht

---

## 7. Barrierefreiheit (WCAG 2.2 AA)

- Plan, Kalender und Woche als `<table>` mit `<th scope>`. Jede Zelle mit vollständigem Text
  für Screenreader (Datum ausgeschrieben, Status, An- oder Abreise; in der Woche Tag, Zeiten
  und Zustand des Blocks).
- Das Anfrageformular ist zugeklappt, der Schalter steckt in der Überschrift
  (`<h3><button aria-expanded>`), damit die Überschriften-Navigation es findet; `<details>`
  nicht, weil `<summary>` als Schalter die Überschrift darin verdeckt.
- Status nie nur über die Farbe, sondern zusätzlich mit Muster oder Symbol und Rand. Muss
  auch im Kontrastmodus (`forced-colors`) funktionieren. Kontrast mindestens 4,5:1 für Text
  und 3:1 für Grafiken.
- Weil Status-Farben frei wählbar sind: Das Backend warnt bei zu geringem Kontrast zum
  Hintergrund, die Schriftfarbe wird automatisch gewählt, und jeder Status hat Pflicht-Muster
  oder Rahmen.
- Die Listenansicht ist immer erreichbar.
- Hauptweg zum Buchen ist das Formular mit nativen Feldern für Datum und Uhrzeit. Die
  Auswahl im Plan ist nur eine Abkürzung: Ein Klick auf einen freien Tag oder Block füllt
  das Formular vor. Das sind echte Links, die `view.php` serverseitig auswertet, also ohne
  JavaScript nutzbar. Plan und Kalender haben dafür keine Tab-Stopps (`tabindex="-1"`,
  `aria-hidden`), sonst wären es bis zu 31 je Objekt und Monat; dort bleibt das Formular der
  Weg für Tastatur und Screenreader. Die wenigen Blöcke der Woche sind erreichbar. Der
  Vorschlag hält Puffer und Mindestdauer gegenüber den sichtbaren Buchungen ein.
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
- `settings.presentation_source` nennt die Quelle der Beschreibungen für einen Abschnitt,
  `resources.presentation_ref` den Eintrag für ein Objekt, beide neutral in der Form
  `<anbieter>:<id>`, z. B. `news_img:3` bzw. `news_img:12` oder `catamaran:content:42`. Der
  Kern prüft nur die Länge und deutet den Inhalt nicht.
- `ResourceRepository::findByPresentationRef()` findet das Objekt zu einem Verweis (für den
  Kalender im Beitrag).
- Ein Port `PresentationProvider` (URL, Titel, Vorschaubild für eine Liste von Verweisen auf
  einmal; Auswahlliste fürs Backend) kommt erst mit Catamaran. Solange es nur news_img gibt,
  wäre er eine Schnittstelle mit einer einzigen Umsetzung; der Adapter liegt im WBCE-Wrapper.
- Ohne Quelle oder bei einem verwaisten Verweis funktioniert alles weiter, nur ohne Link.

**WBCE: news_img**
- **1:1-Zuordnung:** In den Einstellungen eines Reservations-Abschnitts wird genau ein
  news_img-Abschnitt gewählt, dessen Beiträge die Objektbeschreibungen sind. Die Auswahl je
  Objekt zeigt nur dessen aktive Beiträge; es werden nicht alle News durchsucht.
- Der Adapter (`wbce/news_img.php`) liest die Tabellen von news_img nur lesend und baut den
  Link wie news_img selbst. Ist news_img nicht installiert, entfallen Auswahl und Links.
- Frontend: der Objektname in Plan, Liste und Monatsansicht verlinkt auf den Beitrag. Inaktive
  Beiträge oder Beiträge aus einem anderen News-Abschnitt werden nicht verlinkt; das Backend
  zeigt sie als „verwaist“.
- Vorschaubild: vorerst nicht, nur der Link.
- **Droplet** `reservations`, installiert mit dem Modul über die Funktionen des Moduls droplets:
  - `[[reservations]]` im Beitragstext: Kalender des Objekts, dem der Beitrag zugeordnet ist
    (über `POST_ID`), als Monatsansicht und ohne Link auf sich selbst.
  - `[[reservations?resource=ID]]`: ein Objekt als Monatsansicht, auf beliebiger Seite.
  - `[[reservations?section=ID]]`: ein ganzer Abschnitt wie dort eingestellt.
  - Passt nichts, bleibt die Ausgabe leer; Administratoren sehen den Grund als HTML-Kommentar.
  - news_img braucht dafür keine Änderung.
- Bekannte Grenze: erscheint derselbe Abschnitt zweimal auf einer Seite (Abschnitt und
  Droplet, oder zwei Droplets), teilen sich beide Anker und URL-Parameter (`rsv_month_<id>`,
  `rsv_week_<id>`, `rsv_view_<id>`); Blättern wirkt dann auf beide.
- news_img setzt `arg_separator.output` für die ganze Anfrage auf `&amp;`; URLs werden
  deshalb mit ausdrücklichem Trennzeichen gebaut.

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

### Nächste Schritte
Vor Version 1.0:

1. **Abschlussprüfung, noch offen:**
   - Barrierefreiheit von Hand: nur Tastatur, NVDA mit Firefox, Kontrastmodus. Geprüft sind
     Struktur, Beschriftungen, Kontraste, Klickflächen, Reflow bei 320 px, Textabstände und
     die Fehlerausgabe mit erhaltenen Eingaben.
   - Mailversand mit einem echten Mailserver (Eingang, sofort bestätigt, Bestätigung,
     Ablehnung, Benachrichtigung).
   - Installation und Update aus dem ZIP in einer eigenen WBCE-Instanz, nicht über die
     Verzeichnisverknüpfung der Entwicklung. Geprüft sind Inhalt des ZIP und das Schema-Update
     von 0.8.0 auf 0.9.0.

### Weitere offene Punkte
- Puffer im Modus `day` („Tage frei zwischen zwei Buchungen“) bräuchte eigene Logik; nur bei
  Bedarf.

### Erledigt
- Vorbelegen per Klick: `RequestService::suggest()` berechnet den Vorschlag, im Modus `period` gibt es Links nur in der Woche; der Klick lädt die Seite neu, Eingaben im Formular gehen dabei verloren.
- Objekttypen und Status: **pro Abschnitt**.
- Repository: `D:\00_Development\Development\reservations\`.
- Testumgebung mit WBCE 1.7.0: besorgt Bianka.
- Mindest-WBCE-Version: **1.7.0** (Datenbankklasse auf PDO-Basis, auch SQLite).
- Datenbankzugriff: PDO mit Prepared Statements, gemeinsam für alle CMS.
- Ablage: ein Repository mit Kern und Wrappern.
- Tests: PHPUnit für den Kern.
- Entwicklung in WBCE: Verzeichnisverknüpfung von `modules/reservations` auf `reservations/wbce`.
- Einzelobjekt: das angezeigte Objekt wird in den Einstellungen gewählt (Spalte `settings.resource_id`), ohne Auswahl das erste aktive.
- Belegungsplan über 3 Monate: drei Monatstabellen untereinander statt einer breiten Tabelle.
- Wochenansicht mit Stundenraster (0.8.0): nur im Modus `period`, je Objekt eine Tabelle, Spanne aus den Öffnungszeiten (sonst 08:00–20:00), Zeilen von 30 Minuten (60 bei Raster 60); als Startansicht wählbar. Plan und Monat zeigen solche Objekte weiter als Vormittag/Nachmittag.
- Anfrageformular aufklappbar (0.8.0), ohne Einstellung dafür.
- Benutzerhilfe (0.9.0): Markdown mit Build-Skript, DE und EN, siehe Abschnitt 5. Die Warnung vor reinem Dateitausch ohne Modul-Upgrade steht unter „Update“.
- Objekte löschen (0.9.0): nur ohne laufende oder künftige blockierende Buchungen und ohne offene Anfragen, geprüft in der Sperre des Objekts. Vergangene, stornierte und abgelehnte Buchungen gehen samt Anfragen und Öffnungszeiten mit; `settings.resource_id` wird geleert.
- Objekte sortieren (0.9.0): Zahlenfeld „Reihenfolge“ wie bei Status und Typen (`resources.position`).
- An- und Abreisezeit (0.9.0): Teil der Buchungsregeln (`RULE_KEYS`), also auch Vorgabewert am Typ; nur Modus `day`, reine Information im Formular und in den Mails.
- Gemischte Buchungsmodi in einem Abschnitt: **nein**. Der Modus gehört zum Abschnitt (`settings.booking_mode`); für einen anderen Modus einen zweiten Abschnitt anlegen.
- Anfragen ohne Bestätigungslink blockieren den Zeitraum **sofort** (Double-Opt-in später); abschaltbar über „blockiert“ beim Status mit der Rolle „neue Anfrage“.
- Spamschutz: FTAN und Honeypot immer, Captcha je Abschnitt wählbar.
- Buchungseinstellungen je Objekt (0.6.0): Personen mit Unter- und Obergrenze, Vorlauf,
  Höchstvorlauf, Mindest- und Höchstdauer, Puffer; Vorgabewerte am Objekttyp, beim Anlegen
  kopiert. Mindest- und Höchstdauer gelten nur für Anfragen.
- Verfügbarkeitsregeln (0.7.0): Öffnungszeiten je Objekt und Wochentag, Raster im Modus
  `period`, Sperrzeiten über einen blockierenden Status; nur Beginn und Ende müssen in den
  Öffnungszeiten liegen; Raster als Vorgabewert am Typ, Öffnungszeiten nicht.
- Ratenbegrenzung (0.7.0): fest 3 Anfragen pro Stunde je Besucher und Abschnitt über einen
  Hash der IP-Adresse; einstellbar erst später.
- Grenzen bei einer Auswahl mehrerer Objekte im Anfrageformular sichtbar (0.7.1): eine Zeile
  je Objekt, mit JavaScript nur die des gewählten und die Grenzen an den Feldern.
- Objektliste im Backend: Spalten „Aktuelle Buchungen“ (laufende und künftige mit blockierendem Status, ohne offene Anfragen) und „Offene Anfragen“ (Rolle „neue Anfrage“); vergangene, stornierte und abgelehnte zählen nicht.
- Objektbeschreibungen in WBCE: **1:1** ein news_img-Abschnitt je Reservations-Abschnitt (`settings.presentation_source`), Zuordnung je Objekt über `resources.presentation_ref`; Port `PresentationProvider` erst mit Catamaran, Vorschaubild vorerst nicht.
