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
