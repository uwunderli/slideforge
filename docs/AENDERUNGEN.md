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

Verwandt: [BACKLOG.md](BACKLOG.md) · [RELEASES.md](RELEASES.md)

---

## A) Neu

_Noch nicht priorisiert. Format:_

```markdown
| Typ | Thema | Beschreibung | Änderung |
|-----|-------|--------------|----------|
| Bug / Feature | Titel | Was ist kaputt / was fehlt? | Was soll passieren? |
```

| Typ | Thema | Beschreibung | Änderung |
|-----|-------|--------------|----------|
|     |       |              |          |

---

## B) Geplant

_Bereit zur Umsetzung. Reihenfolge = vorgeschlagene Abarbeitung (Prio niedrig zuerst)._

| # | Typ | Prio | Thema | Beschreibung | Änderung | Notizen |
|---|-----|------|-------|--------------|----------|---------|
| 5 | Feature | **4** | Folie als Vorlage in Set übernehmen | Umweg über separate Folienvorlage nötig. | Aus Präsentation/Editor direkt Layout-Folie ins Set (`layoutKey`, Platzhalter). | API-Ansatz: `LayoutSet::importSlideTemplate` erweitern/ähnlich. |
| 6 | Feature | **5** | Editor / Medien — Audio-DB | Neben Pixabay/Icons fehlt offene Audiodatenbank. | Recherche + Vorschlag, dann in `Editor/Medien` einbinden. | Zuerst Anbieter wählen (Lizenz!); ähnlich Medien-Quellen-Pattern. |
| 7 | Feature | **6** | Logos Importer neu strukturieren | Import heute relativ starr. | Neue Importroutine (laut separater Beschreibung). | **Blocker:** Briefing/Beschreibung fehlt. |
| 8 | Feature | **7** | Ribbon Menü | Editor- und Present-Menüs uneinheitlich. | Linke + obere Editor-Menüs und Present in Ribbon; Doppelklick wie MS Office. | Grosses UI-Thema; Abgrenzung zu **v3.1 Editor** klären. |

---

## C) Umgesetzt

| # | Typ | Thema | Umsetzung | Datum / Kontext |
|---|-----|-------|-----------|-----------------|
| 1 | Bug | Vorlageelemente Zuordnungen | Speichern schließt den Dialog nach erfolgreichem API-Call (`closeModal` in `initElementLinksModal`). | 2026-07-08 · `ASSET_VERSION` 357 |
| 2 | Bug | Editor / Ebenen | Layer-Reorder nur unter Content-Nodes (`restackContentNodes` / `nudgeContentLayer`); bgRect/Badges/Transformer bleiben ausserhalb der zIndex-Sequenz — keine Ghost-Ebenen mehr. | 2026-07-08 · `ASSET_VERSION` 357 |
| 3 | Bug | Darstellungsfehler im Editor | Leere Zustände zentriert: `.props-object-panel:has(> .props-empty)`, `.elements-panel-hint-empty`. | 2026-07-08 · `ASSET_VERSION` 357 |
| 4 | Feature | Folien Rasteransicht — Sortieren | Drag & Drop im Raster ohne Sortierstreifen (`bindGridReorder`, gemeinsame `commitSlideOrder` mit Filmstreifen). | 2026-07-08 · `ASSET_VERSION` 357 |
| 9 | Feature | Editor / Rasteransicht — Steuerung | Deaktivieren, Duplizieren, Löschen pro Kachel wie im Filmstreifen (`.editor-slide-grid-actions`). | 2026-07-08 · `ASSET_VERSION` 358 |
| 10 | Feature | Editor / Rasteransicht — Miniatur-Grösse | Schieberegler in Statusleiste unten; Wert pro User (`editor_grid_thumb_min`, `user_api.php`). | 2026-07-08 · `ASSET_VERSION` 358 |
