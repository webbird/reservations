# Abschnitt einrichten

Ein Abschnitt ist ein Kalender mit eigenen Objekten, Status und Einstellungen. Eine Seite kann mehrere Abschnitte enthalten, sie sind voneinander unabhängig.

## Abschnitt anlegen

1. Legen Sie in WBCE eine neue Seite mit dem Modul **Reservierungen** an. Auf einer vorhandenen Seite fügen Sie stattdessen über die Abschnittsverwaltung einen Abschnitt mit diesem Modul hinzu.
2. Öffnen Sie die Seite im Backend. Sie sehen die Liste der Objekte, darüber die Links **Einstellungen**, **Status** und **Objekttypen**.
3. Beginnen Sie mit den [Einstellungen](settings.md), denn den Buchungsmodus können Sie später nur ändern, solange es keine Buchungen gibt.
4. Legen Sie danach die [Objekte](objects.md) an.

## Was ein neuer Abschnitt mitbringt

- den Buchungsmodus **Tageweise** und die Darstellung **Mehrere Objekte**
- einen Objekttyp „Objekt“
- sechs fertige [Status](statuses.md): angefragt, reserviert, gebucht, gesperrt, abgelehnt, storniert
- ausgeschaltete Buchungsanfragen
- das Captcha, wenn das Modul captcha_control installiert ist

## Mehrere Abschnitte

Der Buchungsmodus gilt für alle Objekte eines Abschnitts. Wer Ferienwohnungen und einen Tagungsraum anbietet, legt zwei Abschnitte an: einen tageweise, einen nach Zeitraum.

Objekte, Status und Objekttypen gehören jeweils zu einem Abschnitt. Mehrsprachige Seiten bekommen dadurch von selbst eigene Bezeichnungen, weil jede Sprache in WBCE eigene Seiten und Abschnitte hat.

> [!WARNING]
> Wenn Sie einen Abschnitt löschen, löscht WBCE auch alle seine Objekte, Buchungen und Anfragen. Das lässt sich nicht rückgängig machen.
