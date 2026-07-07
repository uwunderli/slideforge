# SlideForge – Release v3.1.0 (Briefing)

> **Handoff:** *„Lies `docs/RELEASE_v3.1.0.md` und setze die Release um — ausser „Nicht in dieser Release“ und ggf. dokumentierte Unterphasen.“*

Stand: Juli 2026 · Basis: **v2.4.0** (letzte v2.x) · **Major Release** — slides.com-artiger **Editor v2**

> **Versionierung:** **v3.1.0** = neuer visueller Editor; **v2.x** (2.0–2.4) = Mobile, Folien-Sets/Logos, church.tools, Medien, Auto-Animate; **v1.x** = Konva-Editor-Ära (endet mit v1.0.4).

---

## Ziel

SlideForge **3.1** bekommt einen **neuen, slides.com-artigen visuellen Editor** („**Editor v2**“): Die Folie wirkt **während der Bearbeitung wie die fertige Präsentation** — kein Markdown-Rohtext, kein „Design-Tool“-Gefühl mit versteckter Vorschau. Text **inline** bearbeiten, Elemente per **+ Inhalt** einfügen, **Layouts** wählen, Markenfarben/Themes sichtbar anwenden. **reveal.js-Export**, bestehende Präsentationen und Medien-Integrationen (Pixabay, WebDAV …) bleiben; der **Konva-Editor (v1)** bleibt als **Klassischer Editor** erreichbar.

---

## Referenz: slides.com / reveal.js

Was Nutzer von slides.com erwarten (Zielbild):

- Folie = **direkte Vorschau** (1920×1080), kein separates „Render“
- **Klick auf Text** → sofort tippen, **Floating Toolbar** (Fett, Listen, Farbe …)
- **+ Element / Layout** — Titel-Folie, Text+Bild, leere Folie, …
- **Folienleiste** mit Thumbnails, Drag-Reorder
- Weniger technische Handles; **smarte Hilfslinien**, einrasten
- **Theme/Markenfarben** greifen sichtbar in die UI ein
- Present/Export = **1:1** das, was im Editor stand

Reveal.js-Hinweis in der [Demo](https://revealjs.com/demo/): *„Not a coder? Try slides.com“* — SlideForge 3.1 soll **diesen Komfort self-hosted** liefern.

---

## Ist-Zustand (SlideForge 1.x / Editor v1)

- **Konva.js**-Canvas, technisches Eigenschaften-Panel (Tabs Format/Form/Position/Effekt)
- Text: **Markdown-Textarea**, Formatierung oft erst in Vorschau
- Mächtig (Animationen, Gruppen, präzise Koordinaten), aber **steile Lernkurve**
- Kein Layout-Picker, kein durchgängiges WYSIWYG

---

## Strategie: Editor v2 parallel zu v1

| Aspekt | Entscheidung |
|--------|--------------|
| **Route** | `editor.php?mode=visual` oder `editor_v2.php` — Default für **neue** Präsentationen: v2 |
| **Datenmodell** | Weiter **`slides.json`** — erweiterte Felder (`editorVersion`, `textHtml`, Layout-Metadaten); **kein zweites Format** |
| **Migration** | Bestehende Decks: **„Im visuellen Editor öffnen“** — Konva → v2 (best-effort); v1 = **„Klassischer Editor“** |
| **Export** | `SlideRenderer` / Present kompatibel erweitern |

**Implementierung:** In **Unterphasen** (3.1-alpha → 3.1-rc → 3.1.0), Briefing = Gesamtziel.

---

## Features (priorisiert)

### Editor v2 — Kern

1. **[MUSS] Visueller Editor (Editor v2)** — zentrale Folienfläche, Toolbar, Folienliste + kontextuelles Panel
2. **[MUSS] WYSIWYG durchgängig** — Folie wie in Present/Export
3. **[MUSS] Inline-Textbearbeitung** — Rich Text, Floating Format-Bar (kein Markdown für Folientext)
4. **[MUSS] Inhalt einfügen (+)** — Text, Bild, Form, Video/Audio, Medien (Pixabay, Iconify, WebDAV …)
5. **[MUSS] Folien-Layouts** — Titel, Titel+Untertitel, Text+Bild, Leer, Zwei Spalten
6. **[MUSS] Folienleiste** — Thumbnails, Drag & Drop
7. **[MUSS] Objekt-Auswahl vereinfacht** — Klick, Resize, Kontext-Toolbar
8. **[MUSS] Kompatibilität** — Present, view, Offline-HTML, Fragments
9. **[MUSS] Migration v1→v2** — bestehende Decks bearbeitbar

### UX & Design

10. **[SOLL] Smart Guides** · **[SOLL] Inline-Hintergrund** · **[SOLL] Markenfarben in Toolbar**
11. **[SOLL] Undo/Redo** · **[SOLL] Tastaturkürzel**
12. **[SOLL] Editor-Umschalter** Visuell ↔ Klassisch (Konva)

### Erweiterungen

13. **[NICE] Tabellen** · **[NICE] Block-Snippets**

---

## Unterphasen (empfohlene Umsetzungsreihenfolge)

| Phase | Inhalt | Lieferbar als |
|-------|--------|----------------|
| **3.1-alpha** | Editor-v2-Shell, WYSIWYG-Text, Folienleiste, +Text, Speichern, Present-Export | Feature-Flag / Beta |
| **3.1-rc** | Layouts, Medien-Insert, Objekt-Toolbar | Release Candidate |
| **3.1.0** | Migration v1→v2, Animationen in v2, PPTX-Smoke, Doku | **v3.1.0** final |

---

## Feature-Details

### Feature: Editor v2 — Oberfläche

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Neuer Editor (`editor-v2.js`, eigenes CSS) |
| **Verhalten** | Mitte: Folie 1920×1080. Oben: Speichern, Vorschau, Present. Links: Thumbnails. Rechts: Layout/Hintergrund/Notizen oder Kontext-Panel. |
| **Technik** | DOM-first **oder** Konva + HTML-Overlay — bei Implementierung entscheiden |

### Feature: WYSIWYG & Inline-Text

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Verhalten** | Rich-Text (TipTap/ProseMirror/Quill, CDN). Speicherung: `textHtml` + `format: 'html'`. Markdown-Legacy konvertieren. **Export = Editor.** |
| **Akzeptanz** | Neuer Nutzer erstellt Folie ohne Markdown-Doku |

---

### Feature: Layouts

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Verhalten** | **+ Layout**: Platzhalter (Titel, Body, Bild). Layouts aus `seed/templates/` / Admin erweiterbar. |
| **Akzeptanz** | Titel-Folie in &lt; 30 Sekunden |

---

### Feature: Insert-Menü (+)

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Verhalten** | **+** für Text, Bild (Upload/Pixabay/WebDAV …), Formen, Video, Audio, Icon, ClipArt — bestehende Medien-APIs wiederverwenden. |

---

### Feature: Migration Editor v1 → v2

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Verhalten** | Konva → v2; Markdown → HTML; `editorVersion: 1|2` in Meta. Gruppen/komplexe Animationen: best-effort. |
| **Akzeptanz** | Demo-Showcase in v2 öffenbar |

---

### Feature: Klassischer Editor (v1)

| Feld | Inhalt |
|------|--------|
| **Priorität** | SOLL |
| **Verhalten** | Link **„Klassischer Editor“** für Konva/Power-User. v2 = Default für Neulinge. |

---

## Nicht in dieser Release

- Marketing / Bekanntmachen
- slides.com Cloud (Hosting, Echtzeit-Kollaboration)
- v1-Editor **entfernen**
- Neues Datenformat statt `slides.json`
- Mobile **Bearbeitung** (v2.0.0: Remote only)

---

## Technische Rahmenbedingungen

- PHP 8.2+, kein Composer · Mehrsprachigkeit DE/EN/FR/IT/RM
- `SlideRenderer`, Export-Pipelines für HTML-Text anpassen

---

## Dokumentation & Demo

- [ ] README — SlideForge **2.0**, zwei Editoren
- [ ] CHANGELOG.md → `[3.1.0]`
- [ ] `.github/RELEASE_v3.1.0.md`
- [ ] Demo-Showcase im visuellen Editor
- [ ] Feature-Tour: „Visueller Editor“
- [ ] Optional: `docs/EDITOR_V2.md`

---

## Release-Checkliste (am Ende)

- [ ] Editor v2 Kern + Migration + Export
- [ ] PR → main · Tag **`v3.1.0`** · Deploy Prod + Demo

---

## Getroffene Entscheidungen

| # | Frage | Entscheidung | Begründung |
|---|--------|--------------|------------|
| 1 | Scope | **slides.com-artiger Editor v2** | User: grösserer visueller Editor. |
| 2 | v1 Konva | **Behalten** als „Klassischer Editor“ | Power-User, Fallback. |
| 3 | Datenformat | **`slides.json` erweitern** | Backup, Migration. |
| 4 | Default neue Decks | **Editor v2** (SOLL) | Einstieg für Neulinge. |
| 5 | Versionsnummer | **`v3.1.0`** (Major) | User: Versionsprung; Editor-Neugeneration. |

---

## Offene Fragen (optional klären)

1. **Technik Canvas:** DOM-first vs. Konva + HTML-Overlays?
2. **Default:** Neue Decks sofort v2, oder Opt-in bis 2.0-rc?

---

## Ursprüngliche Idee (Rohtext)

> WYSIWYG-Editor → **grösserer slides.com-Editor** → **SlideForge v3.1.0**
