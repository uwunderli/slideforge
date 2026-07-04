# SlideForge – Release v2.3.0 (Briefing)

> **Handoff:** *„Lies `docs/RELEASE_v2.3.0.md` und setze die Release um — ausser „Nicht in dieser Release“.“*

Stand: Juli 2026 · Basis: **v2.2.0** (main)

---

## Ziel

SlideForge unterstützt **reveal.js Auto-Animate**: Objekte auf aufeinanderfolgenden Folien werden automatisch zugeordnet und beim Folienwechsel **flüssig animiert** (Position, Grösse, Farbe, Form). Im Editor lassen sich diese **Zustandsübergänge** visuell bearbeiten und in der Vorschau prüfen — vergleichbar mit [reveal.js Demo Folien 8–9](https://revealjs.com/#/8) (Titel verschiebt/s färbt sich, Rechteck ändert Höhe und Farbe).

> **Hinweis:** In der Demo geht es um **Cross-Slide-Morphing** (Auto-Animate), nicht um Fragment-Schritte innerhalb einer Folie (die gibt es bereits) und nicht primär um Bewegung **entlang einer Pfadkurve** (eigenes Thema, siehe NICE).

---

## Referenz (reveal.js)

Was Auto-Animate kann ([Doku](https://revealjs.com/auto-animate/)):

- Zwei **benachbarte** `<section data-auto-animate>` — matching Elemente werden interpoliert
- Automatisches Matching: gleicher Text/Typ, gleiche `src` bei Bildern, DOM-Reihenfolge
- Manuelles Matching: gleiche **`data-id`** / `autoAnimateId` am Objekt
- Animiert u. a.: Position, Grösse, Farbe, Padding, Margin, Schriftgrösse
- **Implizite Layout-Animation:** neue/entfernte Objekte → übrige Elemente gleiten zur neuen Position
- Einstellungen pro Folie: Dauer, Easing, `data-auto-animate-id`, `data-auto-animate-restart`

**Demo-Beispiele zum Nachbauen:**

1. Überschrift „Auto-Animate“ — Folie 2: andere Position + Farbe
2. Farbiger Balken mit gleicher ID — Folie 2: andere Höhe + Farbe (`data-id="box"`)
3. „Implicit Animation“ — Folie 2: zusätzliche Überschrift, bestehende Elemente verschieben sich

---

## Features (priorisiert)

1. **[MUSS] Auto-Animate zwischen Folien aktivieren** — Folie(n) als Auto-Animate-Gruppe markieren; Export setzt `data-auto-animate` auf `<section>`
2. **[MUSS] Objekt-Matching** — automatisch wo möglich; manuell über **`autoAnimateId`** am Objekt (Editor-Feld, Export als `data-id`)
3. **[MUSS] Editor: Zustand über zwei Folien bearbeiten** — Folie N und N+1 im Kontext bearbeiten; geänderte Position/Grösse/Farbe/Form der gematchten Objekte = Zielzustand für Animation
4. **[MUSS] Export & Präsentation** — Auto-Animate in `SlideRenderer`, `preview.php`, `view.php`, Präsentationsmodus, Offline-HTML
5. **[SOLL] Editor-Vorschau** — beim Wechsel N→N+1 kurze Auto-Animate-Vorschau (reveal.js im Editor oder Mini-Preview)
6. **[SOLL] Ghost-Overlay** — auf Folie N die Position der gematchten Objekte von Folie N+1 als Hilfslinien/Ghosts anzeigen
7. **[SOLL] Folien-Einstellungen** — Dauer, Easing, `autoAnimateId` (Gruppe), Restart-Flag
8. **[NICE] Pfad-Animation** — Objekt bewegt sich entlang einer Kurve (Bézier/Spline) statt linearer Interpolation *(nicht Teil von reveal.js Auto-Animate; separates Konzept)*
9. **[NICE] Auto-Animate für Gruppen & Text (zeilenweise)**

---

## Feature-Details

### Feature: Auto-Animate Folien-Paar

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Folien-Einstellungen / Eigenschaften-Panel (Folien-Tab oder Kontextmenü Folie) |
| **Verhalten** | Checkbox **„Auto-Animate mit nächster Folie“** (oder explizit Folienbereich). Wenn aktiv: diese Folie und die **nächste** Folie bilden ein Auto-Animate-Paar. Beim Vorwärts-Wechsel animiert reveal.js gematchte Objekte. |
| **Grenzen** | Nur **aufeinanderfolgende** Folien (horizontal, gleiche Ebene). Keine Auto-Animate-Ketten über nicht markierte Folien ohne `auto-animate-restart`-Logik. |
| **Nicht** | Kein Auto-Animate über vertikale (verschachtelte) Folien in v2.3.0 |
| **Akzeptanz** | Zwei Folien markiert → Export enthält `data-auto-animate` → in Präsentation morpht Überschrift/Balken wie in reveal.js-Demo |
| **Technik** | `slides.json`: z. B. `autoAnimate: true`, `autoAnimateDuration`, `autoAnimateEasing`, `autoAnimateGroupId`, `autoAnimateRestart` pro Folie; `SlideRenderer` setzt Section-Attribute |

---

### Feature: Objekt-Matching (`autoAnimateId`)

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Eigenschaften-Panel → Effekt oder eigener Bereich **Auto-Animate** |
| **Verhalten** | Feld **Auto-Animate-ID** am Objekt. Gleiche ID auf Folie N und N+1 = explizites Paar (wie `data-id` in reveal.js). Ohne ID: automatisches Matching durch reveal.js (gleicher Objekttyp + Name/Layer, ggf. gleicher Text, gleiche Asset-`src`). |
| **Grenzen** | ID pro Präsentation sinnvoll eindeutig; UI-Hinweis wenn Folie N+1 kein Partner mit gleicher ID hat |
| **Akzeptanz** | Zwei Rechtecke mit ID `box` — Höhe/Farbe ändern → weicher Übergang beim Folienwechsel |
| **Technik** | Objekt-JSON: `autoAnimateId`; Export: `data-id="…"` am gerenderten Element |

---

### Feature: Editor — Zwei-Folien-Bearbeitung

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Editor — Modus **„Auto-Animate bearbeiten“** oder automatisch wenn Folie in Auto-Animate-Paar |
| **Verhalten** | Nutzer bearbeitet **Folie N** (Ausgangszustand) und **Folie N+1** (Zielzustand) nacheinander — wie heute, aber mit visueller Kopplung: gleiche `autoAnimateId` = dasselbe logische Objekt. Typische Aktionen: Objekt verschieben, skalieren, Farbe ändern, Rechteckhöhe ändern, Ellipse deformieren, Objekt auf Folie N+1 hinzufügen (implizite Layout-Animation). Button **„Vorschau Auto-Animate“** spielt Übergang N→N+1 ab. |
| **Grenzen** | Bearbeitung weiterhin folienweise (kein simultanes Canvas beider Folien als MUSS); Ghost-Overlay als SOLL |
| **Nicht** | Kein Keyframe-Timeline-Editor wie After Effects |
| **Akzeptanz** | Balken auf Folie 1 klein/rot, Folie 2 gross/blau → im Present-Modus animiert reveal.js dazwischen |
| **Technik** | Editor-JS: Modus-Flag, optional Split-/Toggle zwischen N und N+1; bestehende Konva-Objekte + `autoAnimateId`; Vorschau via eingebettetes reveal.js oder `Reveal.slide()` zwischen zwei gerenderten Sections |

---

### Feature: Export & Laufzeit

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | `SlideRenderer`, Präsentation, Export-Pipelines |
| **Verhalten** | HTML-Export erzeugt benachbarte `<section data-auto-animate>` mit korrekten `data-id`-Attributen an Objekt-Wrappern. reveal.js-Config: `autoAnimateDuration`, `autoAnimateEasing` aus Präsentations-/Folieneinstellungen. |
| **Grenzen** | PPTX/ODP: Auto-Animate vereinfacht oder statisch (wie bei anderen Animationen dokumentieren) |
| **Akzeptanz** | Offline-HTML und `view.php` zeigen gleiche Auto-Animate-Effekte wie revealjs.com-Demo |
| **Technik** | reveal.js 4.x Auto-Animate ist eingebaut; prüfen ob bundled Version in SlideForge aktuell genug ist |

---

### Feature: Ghost-Overlay (Hilfslinien)

| Feld | Inhalt |
|------|--------|
| **Priorität** | SOLL |
| **Wo** | Editor, Folie N wenn N+1 Auto-Animate-Partner hat |
| **Verhalten** | Halbtransparente Silhouetten der gematchten Objekte von Folie N+1 auf Folie N einblenden (Position/Grösse), damit Zielzustand sichtbar ist beim Platzieren auf Folie N. |
| **Akzeptanz** | Ghosts ein/aus; verschieben auf Folie N mit sichtbarem Ziel auf N+1 |

---

## Nicht in dieser Release

- Marketing / Bekanntmachen
- **Pfad-Animation entlang Kurven** (Bézier-Motion-Paths) — eigenes Feature, höchstens NICE-Vorbereitung
- Auto-Animate über nicht-benachbarte Folien ohne Restart-Semantik
- Auto-Animate für PPTX/ODP-Export (volle Treue)
- Code-Block-Zeilen-Animation wie in reveal.js-Code-Demo (Text-Code-Objekte)

---

## Technische Rahmenbedingungen

- reveal.js **Auto-Animate 4.x** — in bundled reveal-Version prüfen/upgraden
- Bestehende **Fragment-Animationen** (innerhalb einer Folie) bleiben unverändert; Auto-Animate ist ** zwischen Folien**
- `slides.json`-Schema erweitern (Folie + Objekt-Felder)
- Mehrsprachigkeit: DE / EN / FR / IT / RM
- Deploy Prod / Demo wie üblich

---

## Dokumentation & Demo

- [ ] README — Abschnitt Animation: Auto-Animate vs. Fragmente erklären
- [ ] CHANGELOG.md → `[2.3.0]`
- [ ] `.github/RELEASE_v2.3.0.md`
- [ ] Demo-Showcase: 2–3 Folien nach reveal.js-Auto-Animate-Demo (Titel + farbiger Balken)
- [ ] Feature-Tour: Bullet „Auto-Animate zwischen Folien“

---

## Release-Checkliste (am Ende)

- [ ] Code + i18n
- [ ] Manuell: Titel-Morph, Box-Morph, Implicit Layout
- [ ] Export: view, present, offline HTML
- [ ] PR → main · Tag `v2.3.0` · Deploy

---

## Getroffene Entscheidungen

| # | Frage | Entscheidung | Begründung |
|---|--------|--------------|------------|
| 1 | Was meint die Idee (Demo #/7–9)? | **reveal.js Auto-Animate** (Cross-Slide-Morphing) | Folien 8–9 der Demo: Objekte ändern Position/Form/Farbe zwischen Folien; kein separater Pfad-Editor in reveal.js. |
| 2 | Pfad entlang Kurve? | **Nicht in v2.3.0 MUSS** (NICE / später) | Auto-Animate interpoliert linear/transform-basiert; Kurvenpfade wären Custom-Feature. |

---

## Ursprüngliche Idee (Rohtext)

> Guck dir mal die Slide https://revealjs.com/#/7 bis https://revealjs.com/#/9 an. Das will ich auch können und es so im Editor bearbeiten. Es geht wohl darum, objekte einer Kurve nach zu animieren und die Formen zu verändern…
>
> **Einordnung:** Demo zeigt primär **Auto-Animate** — gematchte Objekte zwischen zwei Folien morphen (Position, Grösse, Farbe, Form). „Kurve“ im Sinne von Motion Paths ist ein optionales Erweiterungs-Thema.
