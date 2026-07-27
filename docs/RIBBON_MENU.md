# Ribbon Menü — konfigurierbares Layout

**Status:** Umgesetzt (2026-07-12) · Standard = Nutzer-Layout inkl. Ansicht→Vorschau → **v2.1.3** ([RELEASES.md](RELEASES.md), AENDERUNGEN C #8)

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

## Technik

| Datei | Rolle |
|-------|--------|
| `config/ribbon_commands.php` | Befehlskatalog |
| `config/ribbon_default_layout.php` | Standard-Layout (Neuinstallation / Reset) |
| `src/RibbonLayout.php` | Validierung, Speichern in `users.json` |
| `public_html/ribbon.php` | API (`layout`, `save`, `reset`) |
| `public_html/assets/js/ribbon-renderer.js` | DOM aus Layout |
| `public_html/assets/js/ribbon-customize.js` | Anpassungs-Dialog |
| `public_html/assets/js/ribbon.js` | Tabs, Collapse, Events |

Widget-Blöcke (Schrift, Hintergrund, …) bleiben PHP-Templates in `#ribbonWidgetTemplates` und werden vom Renderer platziert.

**Präsentation-Tab:** Anzeige-Widget; Teilen/Export; Einstellungen als Einzel-Widgets (Dateiname, B×H, Randabstand, Dauer, Texte prüfen, Folien-Set) — Auto-Save.

**Ansicht-Tab:** Raster / Masterfolie · **Vorschau** (Tab / Fenster / Lokal) · Zoom.

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
```
