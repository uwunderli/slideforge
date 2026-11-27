# Ribbon Menü — konfigurierbares Layout

**Status:** Editor-Ribbon umgesetzt (2026-07-12) · Present-Ribbon Anpassen wie Editor (2026-07-28) → [RELEASES.md](RELEASES.md)

## Konzept

- **Standard-Layout** mit 5 Registerkarten: Start, Einfügen, Entwurf, Präsentation, Ansicht (SoftMaker-orientiert)
- **Nutzer kann alles anpassen:** Tabs, Gruppen, Befehle frei anordnen
- **Kein QAT** — häufige Befehle liegen in den Ribbon-Tabs
- **Properties** (Text, Objekt, Position) in der **rechten Seitenleiste**, nicht im Ribbon
- **Kein Auto-Tab-Wechsel** bei Objektselektion
- Meta (Dateiname, Masse, …) speichert **automatisch** — kein Speichern-Button im Ribbon

## Anpassen

- Button **«Anpassen»** in der Seitenleiste (Auswahl)
- **Rechtsklick** auf freie Ribbon-Fläche
- Dialog: Befehle links, Struktur rechts; Tabs/Gruppen anlegen, umbenennen, löschen
- **Zurücksetzen** stellt Standard-Layout wieder her (`config/ribbon_default_layout.php`)

## Technik (ab 3.12.0: Hub-Bausteine)

Menüband und «Ribbon anpassen» sind **HubRibbon / HubRibbonCustomize / HubShortcuts** aus dem Hub (≥ 2.36.1, Hub `docs/DESIGN.md`
§ Ribbon, Tastenkürzel, Reiter und Raster). SlideForge liefert Katalog, Layout-Ablage, Inhalt der Einträge und die Bedeutung der Kürzel.

| Datei | Rolle |
|-------|--------|
| `config/ribbon_commands.php` | Befehlskatalog, Standard-Kürzel (`shortcut`, Windows-Schreibweise; `Ctrl` = Strg/⌘) |
| `config/ribbon_default_layout.php` | Standard-Layout (Neuinstallation / Zurücksetzen / Ort neuer Befehle) |
| `src/RibbonLayout.php` | Validierung, Speichern in `users.json` (`tabs`, `known`, `shortcuts`, `prefs`), Übernahme alter Layouts |
| `public/ribbon.php` | API (`layout`, `save`, `save_prefs`, `reset`); `save` behält im Kontext ausgeblendete Befehle |
| `public/assets/js/ribbon.js` | Start von HubRibbon (Speicher-Adapter, Klassen-Präfixe), schwebende Einstellungs-Panels, Auslöser, Rechtsklick → Anpassen |
| `public/assets/js/ribbon-renderer.js` | Symbole, Knöpfe aller Arten und Widgets aus dem Vorrat (`createItem`), Raster-Spannen (`spanOf`) |
| `public/assets/js/editor.js` | `SFShortcuts`: Bedeutung der Kürzel (Folie vs. Objekt), feste Bühnen-Kürzel |

**Neue Befehle:** Feld `known` im Layout (Hub-Verfahren). Neues aus dem Standard-Layout erscheint an seiner Stelle, Entferntes bleibt weg.
Die alten Nachrüstungen (Stand 1/2, Präsentieren-Vorgaben, Geräte-Gruppe, Panel-Befehle) laufen nur noch einmal für Layouts ohne `known`.

**Kürzel:** im Anpassen-Dialog je Befehl und je Reiter; Prüfung auf doppelte und Browser-Kürzel macht der Hub.

**Weitere Befehle (3.12.1, braucht Hub 2.36.3, Hub-Standard DESIGN.md § Tastenkürzel 8):** alle Tasten, die der Editor auslöst, stehen im Katalog
(`config/ribbon_commands.php`, `kuerzelOnly`, Handler in editor.js). Im Dialog: Kategorie-Auswahl › «Weitere Befehle»:
- Folien (Filmstreifen, nur wenn er den Fokus hat): Vorige Folie ↑ ← Bild↑ · Nächste Folie ↓ → Bild↓ · Erste Folie Pos1 · Letzte Folie Ende
- Präsentieren ab aktueller Folie: Umschalt+F5, Strg+P (ohne Präsentieren-Möglichkeit bleibt Strg+P das Drucken des Browsers)
- Ansicht: Vergrössern, Verkleinern, Zoom einpassen, Notizen bearbeiten (Vorgabe leer; Strg+Mausrad bleibt)
- Folienfläche (scope `stage`): Markierte Objekte löschen Entf, Rücktaste
- Rasteransicht (scope `grid`): Folie links/rechts/darüber/darunter ← → ↑ ↓, Rasteransicht schliessen Esc
- Abspielen (scope `play`, auch bei Fokus im Abspiel-iframe): weiter Leertaste/Eingabe/→/↓/Bild↓, zurück ←/↑/Bild↑, schliessen Esc
- Zweitkürzel von Menüband-Befehlen: Wiederholen Strg+Umschalt+Z, Folien löschen Rücktaste, Folie nach oben/unten Strg+Umschalt+←/→
- Hub: Menüband ein-/ausklappen Strg+F1, Tastentipps zeigen (leer; Alt antippen wirkt immer)

Fest bleiben nur: Pfeiltasten (mit Umschalt 10 px) zum Verschieben markierter Objekte auf der Folienfläche (Bühnen-Standard),
Tab/Eingabe auf ✕ im Abspielen-Fenster, Eingabe/Esc in Textfeldern und Dialogen.

Widget-Blöcke (Schrift, Hintergrund, …) bleiben PHP-Templates in `#ribbonWidgetTemplates` und werden vom Renderer platziert.

**Präsentation-Tab:** Fortschritt/Navigation als Einzelbefehle; Teilen/Export; Einstellungen als Einzel-Widgets (Dateiname, B×H, Randabstand, Dauer, Texte prüfen, Folien-Set) — Auto-Save. Link und Bildschirm nur im Präsentationsmodus.

**Ansicht-Tab:** Raster / Masterfolie · **Vorschau** (Tab / Fenster / Lokal) · Zoom.

## Präsentationsmodus

Auf `present.php` gibt es ein eigenes Ribbon (`#presentRibbon`, Katalog `config/present_ribbon_*.php`):

| Tab | Inhalt |
|-----|--------|
| **Präsentieren** | Fortschritt/Navigation · Link (Owner) · QR+Status · Lokal — Broadcast-Recht |
| **Ansicht** | Steuerungsleiste (…/**Ghost**/**Laser**) · Timer & Zeitleiste (Dialog) · Uhr (Dialog) · Laserpointer (Dialog) · Anpassen |

**Anpassen:** wie Editor — Rechtsklick oder Befehl «Anpassen» im Settings-Tab. Persistenz getrennt: `users.json` → `present_ribbon_layout` via `present_ribbon.php` / `PresentRibbonLayout`.

Peek/Doppelklick wie im Editor.

## Layout-Helfer

| Element | Wirkung |
|---------|---------|
| **Trenner** | Vertikale Linie; mit 2 Zeilen = voller Gruppenhöhe (beendet einen Zeilenstapel) |
| **Zeilentrenner** | Wie `<br>`: bricht innerhalb des Zeilenstapels um. Einzeilige Befehle starten den Stapel; danach gehören auch breitere Widgets (z.B. Farbe) zur aktuellen Zeile, bis ein **Trenner** oder das Gruppenende kommt. |

Entwurf-Standard: Hintergrund · Übergang · Zeitsteuerung · **Einstellungen** («Auf Auswahl» / «Auf alle anwenden»). Raster-Ansicht nutzt dieselben Steuerelemente (Auswahl in der Raster-Leiste).

## Minimieren (Office-Peek)

- **Doppelklick** auf einen Tab-Titel: Ribbon dauerhaft einklappen / wieder ausklappen
- Eingeklappt + **Einfachklick** auf einen Tab: Ribbon temporär als Overlay über dem Editor
- Maus verlässt den Ribbon-Bereich (oder Klick ausserhalb / Esc): Overlay wieder zu
- Zustand `prefs.collapsed` bzw. `localStorage`

```json
// users.json
"ribbon_layout": { "version": 1, "tabs": [...], "prefs": { "activeTab": "start", "collapsed": false } }
"present_ribbon_layout": { "version": 1, "tabs": [...], "prefs": { "activeTab": "present", "collapsed": false } }
```
