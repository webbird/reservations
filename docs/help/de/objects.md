# Objekte anlegen und bearbeiten

Ein Objekt ist alles, was gebucht werden kann: eine Ferienwohnung, ein Raum, ein Gerät. Die Objektliste ist die erste Seite, die Sie beim Öffnen des Abschnitts sehen.

## Objekt anlegen

1. Tragen Sie unter der Liste bei **Neues Objekt** einen Namen ein und wählen Sie den [Objekttyp](types.md).
2. Lassen Sie **Aktiv** eingeschaltet, wenn das Objekt auf der Website erscheinen soll.
3. Klicken Sie auf **Speichern**. Das Objekt übernimmt die Vorgabewerte seines Typs.
4. Klicken Sie in der Liste beim Objekt auf **Bearbeiten**, um [Buchungsregeln](booking-rules.md) und [Öffnungszeiten](opening-hours.md) einzustellen. Diese Bereiche erscheinen erst, wenn das Objekt gespeichert ist.

## Reihenfolge

Das Feld **Reihenfolge** bestimmt, in welcher Folge die Objekte in der Liste, auf der Website und im Anfrageformular stehen. Kleinere Zahlen stehen oben. Objekte mit derselben Zahl stehen in der Reihenfolge, in der Sie sie angelegt haben.

Bei der Darstellung als Einzelobjekt ohne eigene Auswahl zeigt der Abschnitt das erste aktive Objekt dieser Folge.

## Die Objektliste

| Spalte | Bedeutung |
|---|---|
| Aktiv | nur aktive Objekte erscheinen auf der Website und im Anfrageformular |
| Aktuelle Buchungen | laufende und künftige Buchungen mit blockierendem Status, ohne offene Anfragen |
| Offene Anfragen | Buchungen mit dem Status der Rolle „neue Anfrage“ |
| Beschreibung | nur mit [news_img oder articles](descriptions.md): der zugeordnete Beitrag oder Artikel |

Über **Buchungen** kommen Sie zu den [Buchungen](bookings.md) des Objekts.

## Objekt vorübergehend abschalten

Nehmen Sie das Häkchen bei **Aktiv** heraus. Das Objekt verschwindet von der Website, seine Buchungen bleiben erhalten. Einzelne Zeiträume sperren Sie besser über [Sperrzeiten](blocked-times.md).

## Objekt löschen

Klicken Sie in der Liste beim Objekt auf **Löschen** und bestätigen Sie die Rückfrage.

Der Link fehlt, solange das Objekt laufende oder künftige Buchungen mit blockierendem Status oder offene Anfragen hat. So kann keine bestätigte Buchung versehentlich verloren gehen. Stornieren oder löschen Sie diese Buchungen zuerst, oder warten Sie, bis sie vorbei sind.

> [!WARNING]
> Mit dem Objekt verschwinden seine vergangenen, stornierten und abgelehnten Buchungen samt Anfragen und Kontaktdaten sowie seine Öffnungszeiten. Das lässt sich nicht rückgängig machen. Brauchen Sie die Belegung noch, deaktivieren Sie das Objekt stattdessen.

War das Objekt für die Darstellung als Einzelobjekt ausgewählt, zeigt der Abschnitt danach das erste aktive Objekt. Ein [Droplet](droplet.md) mit seiner ID bleibt leer.
