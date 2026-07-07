# SlideForge – Release v2.3.0 (Briefing)

> **Handoff:** In einem neuen Cursor-Chat:
> *„Lies `docs/RELEASE_v2.3.0.md` und setze die Release um — ausser „Nicht in dieser Release“.“*

Stand: Juli 2026 · Basis: **v2.2.0** (main)

---

## Ziel

SlideForge erhält einen **integrierten Medien-Editor**, der sich beim Klick auf ein Medien-Objekt auf der Folie öffnet — in einem grossen Modal (ähnlich Pixabay/WebDAV, fast fensterfüllend). Nutzer können Audio/Video trimmen (In/Out-Marker) sowie Pixelbilder und SVG-Grafiken direkt im Editor bearbeiten, ohne externe Tools.

---

## Features (priorisiert)

1. **[MUSS] Medien-Editor-Modal** — öffnet per **Doppelklick** auf Medien-Objekt oder Button **„Medien bearbeiten“** im Eigenschaften-Panel; Layout wie Pixabay-Dialog
2. **[MUSS] Audio/Video: In-Out-Marker** — Start-/Endpunkt als Metadaten am Objekt; Wiedergabe nur im markierten Bereich
3. **[MUSS] Pixelbilder: Basis-Bearbeitung** — Helligkeit, Kontrast, S/W, Farbanpassungen, gängige Filter (Sepia u. a.)
4. **[SOLL] Pixelbilder: Zuschneiden (Crop)**
5. **[SOLL] SVG-Grafiken: Flächen & Rahmen** — Füllfarbe, Kontur/Rahmen, einfache Eigenschaften pro Element
6. **[SOLL] SVG-Grafiken: Pfade bearbeiten** — Ankerpunkte und **Bézier-Handles** verschieben
7. **[SOLL] SVG: Effekte/Filter** — Gamma, Unschärfe (und ggf. weitere einfache SVG/CSS-Filter)
8. **[NICE] Undo/Redo im Medien-Editor**
9. **[NICE] Vorher/Nachher-Vergleich (Split oder Toggle)**

---

## Feature-Details

### Feature: Medien-Editor-Modal

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Editor — **Doppelklick** auf Medien-Objekt (Bild, Video, Audio, SVG) **oder** Button **„Medien bearbeiten“** im Eigenschaften-Panel |
| **Verhalten** | Grosses Overlay/Modal (Breite/Höhe wie Pixabay-/WebDAV-Dialog, ~90 % Viewport). Oben: Objektname/Typ, Aktionen (Übernehmen, Abbrechen). Mitte: Editor-Canvas bzw. Werkzeugleiste + Vorschau. Unten: typabhängige Werkzeuge. **Übernehmen** überschreibt das bestehende Asset in-place; **Abbrechen** verwirft ungespeicherte Änderungen. |
| **Grenzen** | Nur Medien, die der Nutzer bearbeiten darf (eigene Präsentation, Bearbeitungsrecht). Kein Editor für externe URLs ohne lokales Asset. |
| **Nicht** | Kein separater Vollbild-Tab; kein Batch-Edit mehrerer Objekte gleichzeitig |
| **Akzeptanz** | Doppelklick oder Panel-Button → Modal öffnet sich; nach Bearbeitung und „Übernehmen“ ist die Folie aktualisiert; Modal schliesst sauber (Esc, X, Abbrechen) |
| **Technik** | Neues Modal in `editor.php`; JS-Modul z. B. `media-editor.js`; API-Endpunkt zum Speichern bearbeiteter Assets (`asset.php` / neues `media_edit.php`); Wiederverwendung bestehender Modal-/CSS-Muster aus Pixabay/WebDAV |

---

### Feature: Audio/Video — In-Out-Marker (Trim)

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Medien-Editor, wenn Objekttyp Video oder Audio |
| **Verhalten** | Timeline/Waveform oder einfache Zeitleiste mit **In**- und **Out**-Marker (ziehbar oder per Eingabe in Sekunden). Vorschau spielt nur zwischen In und Out ab (Loop optional in Vorschau). Nach Übernehmen: `trimIn` / `trimOut` (Sekunden) am Objekt gespeichert; Präsentation, Editor und Export respektieren den Ausschnitt. |
| **Grenzen** | In ≥ 0, Out ≤ Dauer, In < Out. Bestehende Startverzögerung / Loop-Einstellungen der Folie weiterhin respektieren oder im Editor sichtbar machen. Original-Datei bleibt ungetrimmt auf dem Server (Metadaten-Ansatz). |
| **Nicht** | Kein Schnitt auf mehrere Clips; kein Audio-Mixing; kein Frame-genaues Keyframe-Editing; **kein FFmpeg** in v2.3.0 |
| **Akzeptanz** | Marker setzen → Vorschau zeigt nur Ausschnitt → nach Übernehmen startet/endet Wiedergabe im Präsentationsmodus am gesetzten In/Out |
| **Technik** | **Nur Metadaten am Objekt** (`trimIn`, `trimOut` in `slides.json`). Client: HTML5 `<video>` / `<audio>` + JS-Stop bei Out; Anpassung in `present.js`, `SlideRenderer`, Offline-Export. Original-Mediendatei bleibt unverändert auf dem Server. |

---

### Feature: Pixelbilder — Bildbearbeitung

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Medien-Editor, wenn Objekttyp Rasterbild (JPEG, PNG, WebP, …) |
| **Verhalten** | Werkzeugleiste: **Helligkeit**, **Kontrast**, **Sättigung/Farbanpassung**, **Schwarz-Weiss**, **Filter** (Sepia, ggf. Weichzeichner/Vignette). **Crop (SOLL):** rechteckiger Zuschnitt mit Live-Vorschau. Live-Vorschau im Modal. |
| **Grenzen** | Bearbeitung im Modal nicht-destruktiv bis „Übernehmen“; danach **In-Place-Überschreiben** des bestehenden Assets (keine zweite Datei). Max. Bildgrösse wie bestehende Upload-Limits. |
| **Nicht** | Keine Ebenen wie Photoshop; keine Retusche-Pinsel; kein RAW-Develop |
| **Akzeptanz** | Slider/Filter ändern Vorschau sofort; Übernehmen speichert sichtbares Ergebnis auf der Folie und in Export |
| **Technik** | Client-seitig: Canvas 2D; Crop per Canvas oder leichtes Crop-UI; gerendertes Bitmap zurück an Server → überschreibt Asset-Datei |

---

### Feature: SVG-Grafiken — Flächen & Rahmen

| Feld | Inhalt |
|------|--------|
| **Priorität** | SOLL |
| **Wo** | Medien-Editor, wenn Objekt SVG (Iconify, Openclipart, WebDAV-SVG, importiertes SVG) |
| **Verhalten** | SVG in Editor laden; Elemente anklickbar (Pfad, Gruppe, `<rect>`, `<circle>`, …). Panel: **Füllfarbe**, **Konturfarbe**, **Strichstärke**, ggf. **Deckkraft**. Änderungen live in Vorschau. |
| **Grenzen** | Einfache Formen und Pfade; komplexe verschachtelte SVGs ggf. nur Teilauswahl |
| **Nicht** | Kein vollständiger Illustrator-Ersatz |
| **Akzeptanz** | Fläche auswählen → Farbe/Rahmen ändern → SVG auf Folie aktualisiert |
| **Technik** | SVG DOM im Editor; Speichern als bereinigtes SVG-Asset; Synergie mit bestehendem `SvgHelper` / Iconify-Tinting prüfen |

---

### Feature: SVG-Grafiken — Pfade bearbeiten

| Feld | Inhalt |
|------|--------|
| **Priorität** | SOLL |
| **Wo** | Medien-Editor, SVG-Modus |
| **Verhalten** | Pfad auswählen; **Ankerpunkte und Bézier-Handles** sichtbar und per Drag verschiebbar. Optional: Punkte hinzufügen/löschen (NICE). |
| **Grenzen** | Fokus auf Korrekturen und Anpassungen, nicht Voll-Illustration |
| **Nicht** | Keine Boolean-Operationen; kein Pfad-Union/Subtract |
| **Akzeptanz** | Punkte und Kurven-Handles verschieben → Form ändert sich → SVG wird gespeichert (überschreibt Asset) |
| **Technik** | SVG + Hit-Testing auf `<path>`; evtl. leichte Lib oder Konva-Overlay nur im Editor |

---

### Feature: SVG — Effekte/Filter

| Feld | Inhalt |
|------|--------|
| **Priorität** | SOLL |
| **Wo** | Medien-Editor, SVG (ggf. auch Raster als SOLL) |
| **Verhalten** | Effekt-Panel: **Gamma**, **Unschärfe (Gaussian Blur)**, ggf. weitere SVG-`<filter>`-Presets. Live-Vorschau. |
| **Grenzen** | Preset-Filter; keine freie Filtergraph-Editor-UI |
| **Nicht** | Keine 3D-Effekte; kein Export nach CSS-Filter ausserhalb SVG |
| **Akzeptanz** | Filter wählen/intensität → Vorschau → in exportiertem SVG/PPTX sichtbar (soweit Export-Pipeline mitzieht) |
| **Technik** | SVG `<defs><filter>…`; Export-Pipeline (`SlideRenderer`, PPTX/ODP) auf Filter-Kompatibilität testen |

---

## Nicht in dieser Release

- Marketing / Bekanntmachen
- Medien-Editor für Text-Objekte oder Formen (nur importierte Medien: Bild, Video, Audio, SVG)
- Kollaboratives Bearbeiten mehrerer Nutzer gleichzeitig im Editor
- KI-gestützte Bildbearbeitung (Hintergrund entfernen existiert bereits separat im Eigenschaften-Panel)
- Vollständiger Audio-DAW / Video-Schnittprogramm-Ersatz
- Bearbeitung direkt aus Pixabay/WebDAV-Suche heraus (nur nach Import auf Folie)
- Serverseitiges Video/Audio-Trim via FFmpeg (evtl. spätere Release)

---

## Technische Rahmenbedingungen

- PHP 8.2+, kein Composer, dateibasiert (`data/`)
- Mehrsprachigkeit: DE / EN / FR / IT / RM (`lang/`)
- UI-Stil: bestehende Modal-Patterns (Pixabay, WebDAV, Iconify) wiederverwenden
- Deploy Prod: `./.deploy/deploy.sh sync-code`
- Deploy Demo: `./.deploy/deploy-demo.sh sync-demo`
- **Assets:** Bearbeitetes Ergebnis **überschreibt** die bestehende Asset-Datei (gleicher Dateiname/Pfad)
- **Video/Audio-Trim:** ausschliesslich Metadaten `trimIn` / `trimOut` am Objekt — **ohne FFmpeg**

---

## Dokumentation & Demo

- [ ] README — Abschnitt Editor: Medien-Editor erwähnen
- [ ] CHANGELOG.md → `[2.3.0]`
- [ ] `.github/RELEASE_v2.3.0.md`
- [ ] Demo-Showcase: Folie „Medien bearbeiten“ (Before/After oder kurze Demo-Grafik)
- [ ] Feature-Tour: Bullet im Editor-/Medien-Slide

---

## Release-Checkliste (am Ende)

- [ ] Code + i18n
- [ ] Manuell getestet: Raster, SVG, Video, Audio
- [ ] Export geprüft: Präsentation, Offline-HTML, PPTX/ODP (soweit relevant)
- [ ] Branch + PR → Merge `main`
- [ ] Tag `v2.3.0` + GitHub Release
- [ ] Prod deploy + Demo sync

---

## Getroffene Entscheidungen

| # | Frage | Entscheidung | Begründung |
|---|--------|--------------|------------|
| 1 | Video/Audio-Trim: FFmpeg oder Metadaten? | **Metadaten only, ohne FFmpeg** | Läuft auf jedem PHP-Host; Originaldatei bleibt; Trim über Player/Export-Logik. |
| 2 | Asset nach Bearbeitung? | **Überschreiben** (in-place) | Einfacher, kein Datei-Müll, Folien-Referenzen bleiben gültig. |
| 3 | SVG-Pfade? | **Punkte + Bézier-Handles** | Kurven editierbar, nicht nur Eckpunkte. |
| 4 | Crop für Bilder? | **SOLL** (empfohlen) | Gehört zu „üblicher Bildbearbeitung“; rein clientseitig, kein Server-Extra. |
| 5 | Editor öffnen? | **Doppelklick + Button** im Eigenschaften-Panel | Für Entdecker und für Nutzer, die gezielt suchen. |

---

## Ursprüngliche Idee (Rohtext)

> Editor für Medien — Klick auf Grafik öffnet Editor im Fenster (fast so gross wie Pixabay).
>
> **Audio/Video:** In/Out-Marker, Wiedergabe nur zwischen Markern.
>
> **Bilder:** Übliche Bildbearbeitung (Pixel), Farbanpassungen, Helligkeit/Kontrast, S/W, Filter (Sepia …).
>
> **Grafiken (SVG):** Pfade bearbeiten, Flächen (Farbe, Rahmen), Effekte/Filter (Gamma, Unschärfe …).
