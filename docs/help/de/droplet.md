# Kalender im Beitrag (Droplet)

Mit dem Droplet `[[reservations]]` zeigen Sie einen Kalender an beliebiger Stelle, etwa im news_img-Beitrag oder Artikel eines Objekts. Das Droplet wird mit dem Modul installiert.

## Schreibweisen

| Droplet | Zeigt |
|---|---|
| `[[reservations]]` | im news_img-Beitrag oder Artikel: den Kalender des Objekts, dem er [zugeordnet](descriptions.md) ist |
| `[[reservations?resource=ID]]` | ein Objekt als Monatskalender, auf beliebiger Seite |
| `[[reservations?section=ID]]` | einen ganzen Abschnitt, wie dort eingestellt |

Die ID eines Objekts finden Sie in der Adresse beim Bearbeiten, hinter `rsv_edit=`. Die ID des Abschnitts steht dort hinter `rsv_section=`.

## Wenn nichts erscheint

Passt nichts, bleibt die Ausgabe leer: Das Objekt ist inaktiv oder gelöscht, der Beitrag keinem Objekt zugeordnet oder die ID falsch. Angemeldete Administratoren finden den Grund als HTML-Kommentar im Quelltext der Seite.

> [!NOTE]
> Erscheint derselbe Abschnitt zweimal auf einer Seite, etwa als Abschnitt und als Droplet, wirkt das Blättern auf beide.
