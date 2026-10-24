# Zwei Bibelstellen auf einer Folie — automatisch und von Hand

**Datum:** 24.10.2026 · **Einordnung: nicht sicherheitsrelevant** (Aufbereitung und
Oberfläche) · **Stand: gebaut, geprüft, ausgeliefert.**

**Go und Entwurf von Urs**, direkt in meinem Fenster: «Wir machen das noch so, dass man zwei
bibelvers Folien zusammen fassen kann» · auf die Frage automatisch oder von Hand:
«Automatisch und von Hand…» · und die Bedienung: «Du kannst ja einen grünen Rahmen um die
Folien machen, die man zusammenfassen könnten und dann eine Schaltfläche ‹Zusammenfassen›.
Dann wird die Folie zusammen gefasst und bekommt einen violetten Rahmen. Dann gibt es die
Schaltfläche ‹trennen› … (Schalter als Icon darstellen)».

## Wie es sich bedient

In der Vorschau des Import-Dialogs:

- **Grüner Rahmen:** diese Bibelstelle und die nächste passen zusammen auf eine Folie. Auf der
  Kachel sitzt ein Icon-Knopf **«Mit der nächsten Stelle zusammenfassen»**.
- **Violetter Rahmen:** die Folie ist zusammengelegt. Derselbe Knopf heisst dort **«Wieder in
  zwei Folien trennen»**.
- **Ein Häkchen in den Einstellungen:** «Bibelstellen zusammenlegen, wenn sie passen» — macht
  es überall dort von allein, wo der Platz reicht. Vorgabe **aus**.
- Beides greift ineinander: Trennen hebt die Automatik für **genau diese** Stelle auf,
  Zusammenfassen von Hand gilt auch dort, wo es knapp wird.

## Was «passt» heisst

Gemessen, nicht geraten: `LayoutSet::roleTextCapacity()` (seit heute, aus der Grösse des
Platzhalters und dem Schriftgrad) sagt, wie viele Zeilen in das Feld für den Bibeltext gehen —
im Set «Schlicht - Logos» **9 Zeilen à 50 Zeichen**. Beide Stellen zusammen (jeweils
Stellenangabe und Vers, plus eine Leerzeile dazwischen) müssen hineinpassen. **Dieselbe
Rechnung** benutzt die zusammengefasste Gliederung — damit beide dasselbe «passt» meinen
(`geschaetzteZeilen()`).

**Von Hand gewählt gilt immer**, auch wenn es nicht passt: dann hat die Person es so gewollt.
Automatisch passiert nur, was gemessen hineinpasst.

## Wie es aussieht

Auf der zusammengelegten Folie gibt es **keine eigene Zeile für die Stellenangabe** mehr — bei
zwei Stellen wäre nicht zu erkennen, welche zu welchem Text gehört. Beide stehen **fett über
ihrem Vers** im selben Feld:

```
**2. Chronik 33,11–13**
Darum liess der Herr über sie kommen …

**2. Chronik 33,15–17**
Er entfernte die fremden Götter …
```

Eine zusammengelegte Folie wird **nicht** noch einmal aufgeteilt; dass es passt, ist vorher
gemessen worden.

## Geändert

| Datei | Was |
|---|---|
| `src/LogosSermonImporter.php` | `geschaetzteZeilen()`; Optionen `scriptureMerge` (Automatik), `mergeScripture`, `splitScripture`; `$scriptureKey` / `$scripturePasst` / `$scriptureZweit`; fünfter Parameter an `emitScripture()`; `scriptureMergeInfo()` für die Vorschau |
| `public_html/import.php` | grüner/violetter Rahmen, Icon-Knopf je Kachel, zwei Zustandslisten, Häkchen für die Automatik, zwei versteckte Felder |
| `public_html/assets/css/import-dialog.css` | die beiden Rahmen und der Knopf |
| `src/Auth.php` | das Häkchen wird für den nächsten Import gemerkt |
| `lang/{de,en,fr,it,rm}.php` | drei Schlüssel je Sprache |
| `config.php` | `ASSET_VERSION` 680 → **681** (Stilblatt geändert) |

**Die Stelle, an der es beim Bauen klemmte** — festgehalten, weil sie sich wiederholen kann:
Der Zusammenlege-Schlüssel hiess in der Vorschau zuerst ebenfalls `key`, und
`$vorschau[$i] += $info` **lässt vorhandene Schlüssel stehen**. Der Folienschlüssel blieb also
stehen, der Zusammenlege-Schlüssel kam nie an, und von Hand gewähltes Zusammenlegen tat
nichts. Jetzt heisst er `mergeKey`, und zusammengeführt wird mit `array_merge()`.

## Geprüft

`php -l` über acht Dateien sauber. Am echten Export (`manasse-2026-10-24.html`, Set
«Schlicht - Logos»), je über `planImport` gemessen:

| Fall | Folien | violett | grün |
|---|---|---|---|
| wie bisher | 28 | 0 | 1 |
| Automatik ein | **27** | 1 | 0 |
| von Hand, passende Stelle | **27** | 1 | 0 |
| von Hand, **erzwungen** (passt nicht) | **27** | 1 | 1 |
| Automatik **+ getrennt** | **28** | 0 | 1 |

Die letzte Zeile ist die Gegenprobe zum «Trennen»: die Automatik hätte zusammengelegt, die
Hand-Entscheidung hebt es auf, und die Folie wird wieder grün angeboten. Dazu geprüft, dass
der Dialogwert in beiden Formen ankommt (JSON-Liste und Komma-Liste) und Unsinn herausfällt.

**Takt-Probe** mit Cache-Buster: `login.php` 302, `import.php` 302 (läuft, verlangt Anmeldung),
`register.php` 200/3347 B mit **0** Meldungszeilen, `sw.php` `slideforge-681`, und das
ausgelieferte Stilblatt enthält die beiden Rahmenklassen.

**Was eine Maschine hier nicht prüfen kann:** wie die Rahmen und der Icon-Knopf in der
Vorschau wirken. Das ist Urs' Blick.

## Offen

- Nur **zwei** Stellen zusammen, nicht drei. Mehr würde selten passen und die Kachel-Bedienung
  mehrdeutig machen (welcher Knopf trennt was?).
- Die Automatik legt **paarweise von vorn** zusammen; sie sucht nicht die beste Aufteilung
  über den ganzen Abschnitt.
- `textMaxCharacters: 'layout'` ist weiterhin wirkungslos (eigener Punkt, siehe
  `befund-logos-zusammenfassung-2026-10-24.md`).

> **Rückbau-Satz:** Die zehn Dateien aus `/data/slideforge/.sicherung/bibel-zusammenlegen-20261024/`
> zurückkopieren. Schon angelegte Präsentationen bleiben, wie sie sind — die Entscheidung
> wirkt nur beim Import, nicht in gespeicherten Folien.
