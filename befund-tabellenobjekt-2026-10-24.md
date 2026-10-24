# Tabellen-Objekt für Folien

**Datum:** 24.10.2026 · **Einordnung: nicht sicherheitsrelevant** (Oberfläche und Darstellung;
keine Rechte, kein Löschen, kein Versand — die Zelltexte gehen durch denselben
Markdown-/Maskier-Weg wie jeder andere Text) · **Stand: gebaut, geprüft, ausgeliefert.**

**Go:** Urs direkt in meinem Fenster, 24.10.: «Kann ich in den Slides eine Tabelle machen?» →
«Ja baue ein Tabellenobjekt ein».

## Was es jetzt gibt

Ein eigener Objekttyp `table` neben Text, Bild, Video, Formen und Gruppen.

- **Einfügen:** Ribbon → Einfügen → **Tabelle** (bei den Textfeldern). Es entsteht eine
  3 × 3-Tabelle mit Kopfzeile.
- **Schreiben:** Doppelklick in eine Zelle öffnet ein Eingabefeld genau über dieser Zelle —
  dasselbe Verhalten wie beim Textfeld. **Tab springt zur nächsten Zelle**, am Zeilenende in
  die nächste Zeile; Esc verwirft.
- **Ändern:** in den Objekt-Eigenschaften gibt es `+ Zeile` / `− Zeile`, `+ Spalte` /
  `− Spalte`, Kopfzeile hervorheben, Schriftgrösse, Textfarbe, Rahmenfarbe und Rahmenstärke.
  Die letzte Zeile und die letzte Spalte lassen sich nicht entfernen — eine Tabelle ohne
  Zellen wäre unsichtbar und unauffindbar.
- **Auszeichnung in Zellen:** `**fett**`, `[color=#hex]`, alles was der Markdown-Konverter
  kennt. Die Zelle läuft durch dieselbe Funktion wie jeder Text, also mit derselben
  Maskierung (geprüft: `<script>` kommt maskiert heraus, nie roh).
- **Anzeige:** eine echte `<table>` — in der Präsentation, der Zuschauer-Ansicht, der
  Vorschau, auf `view.php`, im PDF-Export und im HTML-Export. Überall derselbe Weg
  (`SlideRenderer`).

## Wie Breite und Höhe gemeint sind

**Die Zeilenhöhe richtet sich nach dem Inhalt, nicht nach einem gezogenen Rahmen.** Das ist
die einzige Art, bei der Editor und Folie dasselbe zeigen: Der Editor misst jede Zelle mit
Konva und setzt die Zeilenhöhe auf die höchste Zelle; der Renderer lässt die `<table>`
ebenso vom Inhalt wachsen (darum **kein** `height:100%`). Beim Ziehen am Rahmen wird nur die
**Breite** übernommen; die Höhe ergibt sich neu aus dem Text.

Spaltenbreiten sind **Anteile** (`colWidths`, z. B. `1, 3, 2`), keine Pixel — so bleibt die
Aufteilung beim Verbreitern erhalten. Fehlen sie oder sind sie alle null, werden alle Spalten
gleich breit; beide Seiten (PHP und JavaScript) haben diesen Rückfall.

## Geändert

| Datei | Was |
|---|---|
| `src/SlideRenderer.php` | neuer Zweig `table` + `renderTable()` (echte `<table>`, Anteile als `<col>`, Kopfzeile fett, Streifen- und Kopffarbe) |
| `public_html/assets/js/editor.js` | `TABLE_DEFAULTS`, `normalizeTableRows()`, `tableColPixels()`, `drawTable()`, Zweige in `createNode()`, `nodeToObject()`, `normalizeScale()`, `editTableCell()`, Eigenschaften und ihre Verdrahtung, Symbol in der Ebenenliste |
| `public_html/assets/js/ribbon-renderer.js` | Symbol `table` |
| `config/ribbon_commands.php`, `config/ribbon_default_layout.php` | Befehl `tool:table` und sein Platz in «Einfügen» |
| `public_html/editor.php` | 13 Beschriftungen an das Skript durchgereicht |
| `lang/{de,en,fr,it,rm}.php` | 13 Schlüssel je Sprache |
| `config.php` | `ASSET_VERSION` 679 → **680** (zwei statische Dateien haben sich geändert) |

**Der Eingriff in den Editor ist rein additiv:** 375 Zeilen dazu, **keine entfernt** (`diff`
gegen die laufende Datei). Es wurde kein bestehender Pfad umgebaut; alle neuen Zweige greifen
nur, wenn ein Objekt vom Typ `table` ausgewählt oder gezeichnet wird.

## Geprüft

- `php -l` über sieben PHP-Dateien, `node --check` über beide Skripte — sauber.
- **Renderweg** (`SlideRenderer::renderSections`, der Weg von Präsentation, Vorschau, PDF und
  HTML-Export): Tabelle mit 3 × 3 wird zu neun Zellen an der richtigen Stelle, Rahmenfarbe und
  fette Kopfzeile kommen an, Zellinhalte stehen drin.
- **Maskierung und Auszeichnung in Zellen:** `**fett**` → `<strong>`, `[color=#ff0000]` →
  Farbe, `<script>` → maskiert (0 rohe Treffer), `&` → `&amp;`.
- **Spaltenbreiten und Zeilen** (`node`, neun Fälle gegen die echten Funktionen aus
  `editor.js`): leere Eingabe ergibt 2 × 2, ungleiche Zeilen werden aufgefüllt, Zahlen und
  `null` werden zu Text, Anteile 1 : 3 : 2 von 1200 ergeben 200/600/400, eine falsche Anzahl
  Anteile fällt auf gleiche Breiten zurück.
  **Dieser Lauf hat einen echten Fehler gefunden:** bei lauter Null-Anteilen waren alle
  Spalten null breit — die Tabelle wäre unsichtbar gewesen. Behoben und erneut geprüft.
- **Takt-Probe nach der Lieferung** (mit Cache-Buster): `login.php` 302, `register.php`
  200/3347 B mit 0 Meldungszeilen, `sw.php` liefert `slideforge-680`, `editor.js` kommt mit
  394 700 Bytes und enthält den neuen Tabellen-Teil.

**Was eine Maschine hier nicht prüfen kann:** das Verhalten auf der Zeichenfläche (Einfügen,
Doppelklick, Ziehen) braucht einen Browser mit angemeldeter Sitzung. Das ist Urs' erster
Klick. Geht etwas schief, ist der Rückbau ein Zurückkopieren von zwölf Dateien.

## Was bewusst **nicht** drin ist

1. **PowerPoint- und ODP-Export kennen die Tabelle nicht.** Dort erscheint sie als leeres
   Rechteck: `PptxExporter::presetGeometry()` fällt für unbekannte Typen auf `rect` zurück.
   Eine echte Tabelle in PPTX ist eigenes XML (`<a:tbl>`) — das ist ein eigener Auftrag.
   **Nichts stürzt ab, aber der Inhalt fehlt im Export.** PDF und HTML-Export sind in Ordnung.
2. **Zellen verbinden** (`colspan`/`rowspan`) gibt es nicht.
3. **Spaltenbreiten ziehen** gibt es nicht — sie werden als Anteile im Objekt gespeichert und
   lassen sich heute nur gleichmässig oder über die Datei ändern.
4. **Import** aus PPTX/ODP bringt keine Tabellen herein.

> **Rückbau-Satz:** Die zwölf Dateien aus `/data/slideforge/.sicherung/tabelle-20261024/`
> zurückkopieren (Prüfwerte dort). Folien, in denen schon eine Tabelle steckt, zeigen danach
> an ihrer Stelle nichts mehr — das Objekt bleibt in der Datei, nur der Renderer kennt es
> nicht mehr. Darum vor einem Rückbau fragen, ob schon eine Tabelle im Einsatz ist.

## Nachtrag: der Knopf tat nichts (Urs, 24.10. abends)

Urs: «Und wie füge ich eine Tabelle nun ein?» … «Ich habe das Icon, aber da geschieht nichts wenn
ich darauf klicke» — und dann die entscheidende Zeile aus der Browser-Konsole:

```
editor.js?v=682:3554 Uncaught TypeError: group.hitFunc is not a function
    at drawTable (editor.js?v=682:3554:11)
    at createNode (editor.js?v=682:3712:7)
    at addShape (editor.js?v=682:7367:16)
```

**Ursache:** `drawTable()` gab der Konva-Gruppe eine Trefferfläche über `group.hitFunc(...)`.
`hitFunc` gehört aber zu `Konva.Shape`, nicht zu `Konva.Group` — eine Gruppe zeichnet nichts
Eigenes, ihre Treffer kommen von den Kindern. Der Aufruf warf, **bevor** die Tabelle auf die Folie
kam: kein Objekt, keine Meldung, scheinbar «nichts passiert».

**Korrektur:** dieselbe Trefferfläche, die die Objektgruppen schon benutzen — ein fast
durchsichtiges Rechteck (`fill: rgba(0,0,0,0.001)`) namens `sf-table-hit` zuunterst in der Gruppe.
Nötig ist es, weil alle Zellen und Texte `listening: false` haben; ohne dieses Rechteck wäre die
Tabelle überhaupt nicht anklickbar.

**Gleich mitgenommen,** weil es der nächste Griff gewesen wäre: das Feld «Breite» wirkte nicht
(`applyWidth` setzte `node.width()`, die Tabelle rechnet aber mit `tableW`). Jetzt setzt es `tableW`
und zeichnet neu. «Höhe» tut bei einer Tabelle **absichtlich nichts**: die Zeilen wachsen mit dem
Inhalt, ein Wert von Hand wäre beim nächsten Zeichnen wieder weg.

`ASSET_VERSION` 682 → **683**, ausgeliefert (`public_html/assets/js/editor.js`, `config.php`),
Sicherung `/data/slideforge/.sicherung/tabelle-trefferflaeche-20261024/`.

**Was daran zu lernen ist:** Alle Prüfungen zum Tabellen-Objekt liefen am 24.10. **server-seitig**
(`php -l`, Export über `SlideRenderer`, Folienzahlen). Der Editor-Teil läuft im Browser, und dort
hat nie etwas gemessen — `node --check` prüft nur die Syntax, nicht ob eine Konva-Methode existiert.
In dieser Umgebung gibt es keinen Browser, also bleibt dieser Teil **ungeprüft, bis ein Mensch
klickt**. Das gehört so in die Lieferung geschrieben, statt «geprüft» zu melden.

## Nachtrag 2: Farben, Kopfzeile, Streifen, Spaltenbreite (Urs, 24.10. abends)

Urs, mit einem Bild der Eigenschaftsleiste: «das ist noch nicht schön. Dann mach bei den Farben
noch ein Auswahlfeld mit den Marken Farben. Dann muss man noch die Kopfzeile besser formatieren
können. Schriftfarbe und hintergrund. Dann die Tabellen gebündelt (abwechselnde Farben pro Zeile)
und deren Farbe und Füllstärke.» · «dann muss man die Spaltenbreite noch mit dnd verschieben
können.»

### Die Eigenschaftsleiste

Vier Abschnitte statt einer Liste: **Text** (Schriftgrösse, Textfarbe) · **Kopfzeile**
(hervorheben, Schriftfarbe, Hintergrund, Füllstärke) · **Zeilen abwechselnd** (Häkchen, Farbe,
Füllstärke) · **Rahmen** (Farbe, Stärke). **Unter jedem Farbfeld stehen die Markenfarben**
(`miniPaletteHtml`, dieselbe Reihe wie bei Symbolfarbe und Textmarker).

Dazu eine eigene Hülle `props-table-fields` mit ein paar Zeilen Stilblatt: Beschriftung links,
Felder über die ganze Breite. In der schmalen Seitenleiste standen sie vorher mittig und halb so
breit wie der Platz — das war das «nicht schön».

Die Farbfelder für **Hintergrund** und **Streifen** zeigen die erste Markenfarbe, wenn noch nichts
gesetzt ist; ob man sie sieht, entscheidet die **Füllstärke**. 0 % heisst: keine Fläche. So braucht
es kein zweites Häkchen für «kein Hintergrund», und ein Farbfeld muss nie «durchsichtig» anzeigen,
was es gar nicht kann.

### Spaltenbreite ziehen

Über jeder Trennlinie liegt ein 10 px schmaler Griff (`sf-table-col`). Beim Überfahren wird er hell
und der Zeiger wird zu `col-resize`; gezogen wird **nur der Griff**, die Tabelle wird erst beim
Loslassen neu gezeichnet — `destroyChildren()` würde den Griff sonst mitten im Ziehen zerstören.
Beim Loslassen wandert die Linie innerhalb der beiden Nachbarspalten, jede behält mindestens
24 px, und die neuen Breiten werden als Anteile abgelegt (Pixel taugen dafür: `tableColPixels()`
rechnet sie über ihre Summe ohnehin wieder auf die Tabellenbreite um).

### Gespeichert wird

`headerColor`, `headerFill`, `headerFillOpacity`, `stripeRows`, `stripeFill`, `stripeOpacity` —
alle in `TABLE_DEFAULTS`, und `createNode()` übernimmt jetzt **alle** Vorgabe-Schlüssel statt einer
von Hand gepflegten Liste (die wäre beim nächsten neuen Feld wieder auseinandergelaufen).
`src/SlideRenderer.php` rechnet dieselbe Füllfarbe (`tabellenFuellung()` ↔ `tableFuellung()`),
damit die Folie aussieht wie die Zeichenfläche.

### Gemessen

| Fall (Renderweg) | Ergebnis |
|---|---|
| wie bisher, nur fett | keine Zeile gefüllt |
| Kopfschrift + Hintergrund halb | `rgba(135,180,43,0.5)`, Kopfzelle `color:#1b1b1b` |
| Hintergrund voll | `#87b42b` |
| Streifen 15 % | dritte Zeile `rgba(58,108,141,0.15)` |
| **Streifen aus trotz Farbe** | keine Fläche (Gegenprobe) |
| **Füllstärke 0** | keine Fläche (Gegenprobe) |

Dazu 18 Fälle gegen die echten JS-Funktionen aus `editor.js` (Füllfarbe, Spaltenbreiten, Rechnen
beim Loslassen): Anteile 1:2:1 → 300/600/300, gespeicherte Pixel ergeben dieselben Breiten,
Anschlag links und rechts hält 24 px, die Summe bleibt die Tabellenbreite.

`ASSET_VERSION` 683 → **684**. Zehn Dateien ausgeliefert, Sicherung
`/data/slideforge/.sicherung/tabelle-farben-spalten-20261024/`.

**Ungeprüft (Muster 7):** wie die Leiste aussieht, ob sich die Trennlinie gut greifen lässt, ob der
Zeiger wechselt. Das Ziehen ist auf eine **ungedrehte** Tabelle ausgelegt; bei gedrehter Tabelle
kann die Linie springen.
