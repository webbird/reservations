# Aussehen anpassen

Reservations übernimmt Schrift und Linkfarbe Ihres Templates und bringt für alles andere eigene Stile mit. Farben und Abstände ändern Sie über CSS-Variablen im Stylesheet des Templates.

## Variablen setzen

Tragen Sie die Variablen in das CSS Ihres Templates ein, auf `.mod_reservations`:

```css
.mod_reservations {
    --rsv-line: #999;
    --rsv-weekend: #eef3f8;
    --rsv-week-row: 1.6em;
}
```

> [!WARNING]
> Auf `:root` oder `body` wirken die Variablen nicht. Sie müssen auf `.mod_reservations` stehen.

## Die Variablen

| Variable | Vorgabe | Wirkung |
|---|---|---|
| `--rsv-line` | `#c8ccd2` | Linien von Tabellen und Listen, Rahmen der Legende |
| `--rsv-surface` | `#fff` | Grund der Status-Muster und von Beschriftungen darauf |
| `--rsv-ink` | `#1f2933` | Text auf diesem Grund |
| `--rsv-weekend` | `#f1f3f5` | Hintergrund von Wochenenden |
| `--rsv-closed` | `#838c99` | Schraffur nicht verfügbarer Zeiten |
| `--rsv-closed-ground` | `#f6f7f9` | Grund dieser Schraffur |
| `--rsv-error` | `#a32d2d` | Fehlermeldungen im Formular |
| `--rsv-success` | `#1e6b34` | Bestätigungen im Formular |
| `--rsv-radius` | `4px` | Ecken der Ansichtsschalter |
| `--rsv-space` | `1rem` | Abstand unter Tabellen, Gruppen und Meldungen |
| `--rsv-plan-row` | `1.8em` | Zeilenhöhe im Belegungsplan |
| `--rsv-month-row` | `2.8em` | Zeilenhöhe im Kalender |
| `--rsv-week-row` | `1.3em` | Zeilenhöhe in der Woche |

Bei dunklen Templates setzen Sie mindestens `--rsv-surface`, `--rsv-ink`, `--rsv-weekend` und die beiden Variablen für geschlossene Zeiten.

## Farben der Status

Die Farben der Status stellen Sie nicht im CSS ein, sondern im Backend unter [Status](statuses.md).

## Wenn das Template stört

Das Modul setzt die Stile des Templates für seine Tabellen und Listen zurück und gibt seinen eigenen Regeln hohe Priorität. So bleibt der Kalender in den meisten Templates unverändert.

Nur Template-Regeln mit `!important` setzen sich dagegen durch. Passt ein Kalender trotzdem nicht, suchen Sie im Stylesheet des Templates nach solchen Regeln für `table`, `td` oder `ul`.

> [!NOTE]
> Formularfelder und Knöpfe behalten das Aussehen des Templates. Das Captcha bringt eigene Stile mit und bleibt davon unberührt.
