# Benutzerhilfe: Quellen

Die Hilfe entsteht aus den Markdown-Dateien hier. `php build/help.php` erzeugt daraus
statische Seiten in `wbce/help/` (nicht im Repository, `build/build.php` ruft das Skript vor
dem ZIP auf). Das System-PHP reicht, gebraucht wird nur `composer install` in `core/`.

## Dateien

- `<sprache>/_nav.md`: Gruppen und Reihenfolge, zugleich die Folge beim Blättern.

      - Objekte
        - [Öffnungszeiten](opening-hours.md)

- `<sprache>/index.md`: Startseite. `{{topics}}` allein in einer Zeile wird zur Themenliste.
- `<sprache>/<seite>.md`: je Seite eine Datei. Dateinamen sind englisch und in allen Sprachen
  gleich, so springt der Sprachwechsel auf dieselbe Seite.
- `<sprache>/img/`: Bildschirmfotos dieser Sprache.
- `help.css`: Aussehen aller Seiten.

## Schreibweise

- Der erste Absatz unter der `#`-Überschrift ist die Einleitung.
- Am Dateianfang sind Kopfzeilen möglich (Etiketten unter dem Titel):

      applies: Buchungsmodus Zeitraum, je Objekt
      since: 0.7.0

- `##`-Überschriften ergeben „Auf dieser Seite“.
- Nummerierte Listen erscheinen als Schritte.
- Hinweiskästen:

      > [!NOTE]
      > Text

      > [!WARNING]
      > Text

- Bild mit Unterschrift, allein im Absatz. Fehlt die Datei, erscheint ein Platzhalter:

      ![Alt-Text](img/name.png "Bildunterschrift")

- Links auf andere Seiten zeigen auf die `.md`-Datei, auch mit Anker:
  `[Raster](booking-rules.md#raster)`. Der Anker ist die Überschrift in Kleinbuchstaben, mit
  ae/oe/ue/ss für Umlaute und Bindestrichen.

## Prüfungen beim Build

Fehler brechen ab: toter Link oder Anker, Seite fehlt in `_nav.md` oder umgekehrt, Seite ohne
`#`-Überschrift. Warnungen nicht: fehlendes Bild, Seite nur in einer Sprache.
