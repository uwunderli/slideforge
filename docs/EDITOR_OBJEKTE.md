# Objekte auf der Zeichenfläche (Konva)

Technische Regeln für `public_html/assets/js/editor.js`. Hier steht, was man beim Bauen eines
Objekttyps wissen muss — **nicht** Design (das liegt im Hub, siehe [UI.md](UI.md)) und nicht die
Bedienung (siehe [RIBBON_MENU.md](RIBBON_MENU.md)).

## Form oder Gruppe — das entscheidet, welche Methoden es gibt

Ein Objekt ist entweder eine **Form** (`Konva.Shape`: Text, Rechteck, Ellipse, Linie, Bild) oder
eine **Gruppe** (`Konva.Group`: Tabelle, Video, Audio, Objektgruppe). Eine Gruppe zeichnet nichts
Eigenes, sie hält nur Kinder — darum fehlen ihr alle Methoden, die zum Zeichnen gehören:

| Gehört zu `Shape`, **nicht** zu `Group` | Was man bei einer Gruppe stattdessen tut |
|---|---|
| `hitFunc()` | Ein fast durchsichtiges Rechteck zuunterst in die Gruppe legen (`fill: 'rgba(0,0,0,0.001)'`). Siehe unten. |
| `fill()`, `stroke()`, `strokeWidth()` | An den Kindern setzen und die Gruppe neu zeichnen |
| `width()`, `height()` sinnvoll setzen | Eigenes Attribut führen (`tableW`, `baseW`/`baseH`) und daraus neu zeichnen |

**Das kostet schon einmal einen Tag:** Am 24.10.2026 gab `drawTable()` der Tabellen-Gruppe ihre
Trefferfläche über `group.hitFunc(...)`. Der Aufruf warf `TypeError: group.hitFunc is not a
function`, und zwar **bevor** das Objekt auf die Folie kam: kein Objekt, keine Meldung, aus Sicht
der Bedienung «der Knopf tut nichts». `node --check` sieht das nicht — die Syntax ist
einwandfrei. Vor der Lieferung also jede Konva-Methode gegen die **Klasse** prüfen, auf der sie
aufgerufen wird.

## Trefferfläche für Gruppen

Kinder, die nur Darstellung sind, bekommen `listening: false` (so fängt kein Zellenrahmen den
Klick ab). Dann hat die Gruppe aber **gar keine** Trefferfläche mehr und ist nicht anklickbar.
Mittel dagegen, zweimal im Einsatz:

| Name | Wo | Grösse |
|---|---|---|
| `sf-group-hit` | `updateGroupHitRect()` für Objektgruppen | umschliessendes Rechteck aller Kinder |
| `sf-table-hit` | `drawTable()` für Tabellen | `tableW` × Summe der Zeilenhöhen |

Beide sind `Konva.Rect` mit `fill: 'rgba(0,0,0,0.001)'` — ein Hauch Farbe, weil Konva eine Form
ohne Füllung nicht in die Treffer-Ebene zeichnet — und liegen mit `moveToBottom()` zuunterst.
`drawTable()` ruft `destroyChildren()` auf, legt das Rechteck also bei jedem Zeichnen neu an.

## Grösse: was eingebacken wird und was nicht

`normalizeScale()` setzt nach dem Ziehen die Skalierung auf 1 zurück und rechnet sie in die
Masse ein. Bei der Tabelle wird **nur die Breite** eingebacken (`tableW`); die Höhe ergibt sich
aus dem Inhalt, weil die Darstellung (hier **und** im `SlideRenderer`) die Zeilen vom Text
wachsen lässt. Dazu passend:

- `applyWidth()` setzt bei `table` das Attribut `tableW` und zeichnet neu — `node.width()` hätte
  keine Wirkung.
- `applyHeight()` tut bei `table` **absichtlich nichts**. Ein Wert von Hand wäre beim nächsten
  Zeichnen wieder weg.

## Ein neuer Objekttyp — die Stellen in der Reihenfolge

1. `addShape()` — Vorgabe-Objekt (so gefüllt, dass man sofort etwas sieht)
2. `createNode()` — Attribute setzen, zeichnen
3. `nodeToObject()` — zurück in die gespeicherte Form (Attribute, nicht Kinder, auslesen)
4. `normalizeScale()` — was beim Ziehen eingebacken wird
5. `bindNodeEvents()` — Doppelklick zum Bearbeiten
6. `buildRibbonShapesPropsHtml()` + `wirePropsPanel()` — Eigenschaften im Menüband
7. `LAYER_ICON_SVG` — Symbol in der Ebenen-Liste
8. `config/ribbon_commands.php` + `ribbon_default_layout.php`, `ribbon-renderer.js` (Symbol),
   `lang/*` — der Knopf
9. `src/SlideRenderer.php` — Darstellung in Präsentation, Vorschau, PDF und HTML-Export
10. PPTX/ODP-Export — kennt neue Typen **nicht** von selbst

**Achtung beim Menüband:** Ein Benutzer mit eigenem Menüband (`ribbon_layout` im Benutzersatz)
bekommt neue Knöpfe **nicht** automatisch; `sanitizeLayout()` trägt nichts nach. Beim Einbauen
also dazusagen, dass die Schaltfläche bei angepassten Menübändern von Hand hineingezogen werden
muss.

## Prüfen

Der Browser-Teil ist in der Arbeitsumgebung **nicht** prüfbar: es gibt keinen Browser, und
`node --check` prüft nur die Syntax. Was geht: der Renderweg server-seitig (`SlideRenderer`,
Export, Folienzahlen) und die reinen Rechenfunktionen, nachgebaut in `node`. Was nicht geht: der
Klick selbst. Das gehört so in die Lieferung geschrieben — «server-seitig gemessen, Bedienung
ungeprüft» statt eines pauschalen «geprüft». Siehe [PRUEFUNGEN.md](PRUEFUNGEN.md) und
`hub/docs/FEHLERMUSTER.md` (Muster 7).
