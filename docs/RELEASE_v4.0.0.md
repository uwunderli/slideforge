# SlideForge – Release v4.0.0 (Briefing)

Stand: 27.11.2026 · Basis: **v3.12.55** (Zweig `entwickler/slide-start-einzeln`)

---

## Ziel

Abschluss der 3.12-Reihe: Office-nahes Bearbeiten (Texteditor, Kürzel, Tastentipps) und ein Menüband, das ganz aus Einzelbefehlen besteht.

---

## Features (seit 3.12.0)

1. **[MUSS]** Texteditor wie in Office: Bearbeiten an Ort mit Formatierung, Menüband wirkt auf die Markierung.
2. **[MUSS]** Menüband aus Einzelbefehlen: Zeichen, Absatz, Schriftfarbe, Übergänge, Einstellungen, Zoom einzeln anordenbar, je mit Kürzel.
3. **[SOLL]** Zeitstrahl als Objekt (Ereignisse, Aussehen).
4. **[SOLL]** Tastentipps (Alt) für Menüband, Seitenleiste und Filmstreifen.
5. **[SOLL]** Notizen formatiert, Referentenansicht mit Notizen unten/links.
6. **[SOLL]** Übergänge wie PowerPoint, Verläufe mit Farbpunkten.

---

## Feature-Details

### Feature: Menüband aus Einzelbefehlen

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | `config/ribbon_commands.php` (kind `field`), `config/ribbon_default_layout.php`, `src/RibbonLayout.php`, `public/assets/js/ribbon-renderer.js`, `public/includes/editor_ribbon_*.php` |
| **Verhalten** | Jeder Knopf und jedes Feld ist ein Katalog-Befehl mit eigener Breite; das Element (feste ID) wandert aus dem Vorrat in seine Zelle. |
| **Grenzen** | Blöcke bleiben: Folienvorlagen-Galerie, Vorlage-Elemente, Vorschau des Folienhintergrunds. |
| **Nicht** | Kein neuer Layout-Speicherschlüssel. |
| **Akzeptanz** | Vorgabe sieht aus wie bisher; alte Layouts zeigen die Einzelbefehle an derselben Stelle. |
| **Technik** | Übergang beim Laden in `RibbonLayout::sanitizeLayout` (legacyExpand / Aliase). |

---

## Nicht in dieser Release

- PPTX/ODP-Import/-Export überarbeiten, Zeitstrahl-Animation und Excel-Übernahme.

---

## Dokumentation & Demo

- [x] CHANGELOG
- [ ] Ansicht im Browser durch Urs (UI nicht geprüft)
