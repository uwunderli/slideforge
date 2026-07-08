# SlideForge – Änderungen (Bugs & Features)

Eine Warteschlange für **Bugs** und **Features**, die (noch) keinem Release zugeordnet sind.

Workflow:

1. **A) Neu** — Roh-Einträge mit **Typ** (`Bug` / `Feature`), Beschreibung und gewünschter Änderung.
2. **B) Geplant** — gemeinsam aus A übernehmen, priorisieren, ggf. schärfen.
3. **C) Umgesetzt** — nach Umsetzung hierher verschieben (Was, wo, wann).

**Typ**

| Wert | Bedeutung |
|------|-----------|
| **Bug** | Fehler / falsches Verhalten korrigieren |
| **Feature** | neue oder erweiterte Funktionalität |

**Prio (Vorschlag)**

| Prio | Bedeutung |
|------|-----------|
| **1** | Blocker / kaputte Kernfunktion |
| **2** | Schneller sichtbarer Fix |
| **3** | Klarer UX-Gewinn, begrenzter Scope |
| **4** | Produktnutzen, mittlerer Aufwand |
| **5+** | Gross / braucht Briefing |

**Handoff an Cursor:** *„Lies `docs/AENDERUNGEN.md` und arbeite Abschnitt B ab.“*  
Nach jedem Punkt: Eintrag von B nach C, bei UI-Fixes ggf. `ASSET_VERSION` bumpen.

Verwandt: [BACKLOG.md](BACKLOG.md) · [RELEASES.md](RELEASES.md) · [README.md](README.md)

---

## A) Neu

_Noch nicht priorisiert._

| Typ | Thema | Beschreibung | Änderung |
|-----|-------|--------------|----------|
|     |       |              |          |

---

## B) Geplant

_Bereit zur Umsetzung (Prio niedrig zuerst)._

| # | Typ | Prio | Thema | Beschreibung | Änderung | Notizen |
|---|-----|------|-------|--------------|----------|---------|
| 5 | Feature | **4** | Folie als Vorlage in Set übernehmen | Umweg über separate Folienvorlage nötig. | Aus Präsentation/Editor direkt Layout-Folie ins Set (`layoutKey`, Platzhalter). | `LayoutSet::importSlideTemplate` |
| 6 | Feature | **5** | Editor / Medien — Audio-DB | Neben Pixabay/Icons fehlt offene Audiodatenbank. | Recherche + Vorschlag, dann in Editor/Medien einbinden. | Lizenz klären |
| 7 | Feature | **6** | Logos Importer neu strukturieren | Import heute relativ starr. | Neue Importroutine (separate Beschreibung). | Briefing fehlt |
| 8 | Feature | **7** | Ribbon Menü | Editor- und Present-Menüs uneinheitlich. | Ribbon wie MS Office; Doppelklick-Verhalten. | Abgrenzung v3.1 Editor |

---

## C) Umgesetzt

| # | Typ | Thema | Umsetzung | Datum / Kontext |
|---|-----|-------|-----------|-----------------|
| 1 | Bug | Vorlageelemente Zuordnungen | Speichern schliesst Dialog (`closeModal`). | 2026-07-08 · v2.1.1 |
| 2 | Bug | Editor / Ebenen | Keine Ghost-Ebenen (`restackContentNodes`). | 2026-07-08 · v2.1.1 |
| 3 | Bug | Darstellungsfehler im Editor | Leere Zustände zentriert. | 2026-07-08 · v2.1.1 |
| 4 | Feature | Raster — Sortieren | Drag & Drop (`bindGridReorder`). | 2026-07-08 · v2.1.1 |
| 9 | Feature | Raster — Steuerung | Präsentieren/Duplizieren/Löschen pro Kachel. | 2026-07-08 · v2.1.1 |
| 10 | Feature | Raster — Miniatur-Grösse | Schieberegler, pro User gespeichert. | 2026-07-08 · v2.1.1 |
| 11 | Feature | Raster — Effekt pro Kachel | Layout: ID \| Icon \| Zeit … Effektname rechts (`gridSlideMetaHtml`). | 2026-07-08 · v2.1.2 |
