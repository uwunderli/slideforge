# SlideForge – Release v2.1.0 (Briefing)

> **Handoff:** *„Lies `docs/RELEASE_v2.1.0.md` — Release ist shipped; bei Nacharbeit nur offene SOLL-Punkte.“*

Stand: Juli 2026 · Basis: **v2.0.2** (PWA) · **Shipped:** 2026-07-07

---

## Ziel

SlideForge erhält **Folien-Sets** (Layout-Sets) mit **Logos-Import**: Predigten aus dem Logos Sermon Builder (HTML-Export) werden in gestaltete Folien mit Platzhalter-Elementen überführt. Sets definieren mehrere Layout-Folien, **Logos-Zuordnung** (Folien vs. Notizen), **Vorlageelemente** und **Import/Export** als `.chs`-Archiv. Das Standard-Set **„Schlicht“** wird bei Neuinstallationen automatisch angelegt.

---

## Highlights (shipped)

### Folien-Sets

- Mehrere Layout-Folien pro Set (Titel, Überschriften, Text, Bibelstelle …)
- Set-Editor mit Element-Zonen, Platzhalter-Rollen (`setRole`) und Filmstreifen-Labels
- **Vorlage anwenden** in Präsentationen: Layout aus verknüpftem Set, bestehender Inhalt bleibt erhalten
- Import/Export als **`.chs`** (ZIP-Archiv mit `meta.json`, `slides.json`, Assets)
- Standard-Seed `seed/layout-sets/schlicht/` — `default_layout_set`, freigegeben für alle

### Logos-Importer

- HTML-Import aus Logos Sermon Builder (Überschriften → Folien, Randnotizen → Sprechernotizen)
- **Logos-Zuordnung** pro Set: welche Elemente eigene Folien erhalten vs. in Notizen landen
- Bibelvers-Block mit Referenz/Vers getrennt; Blockzitate / Illustrationen als Folien
- Import-Seite und Vorlagen-Bereich mit Set-Auswahl und `.chs`-Hinweisen

### Vorlageelemente

- Modal **Vorlageelemente konfigurieren** (Standard-Elemente + Logos-Zuordnung)
- Textvorlagen-Verknüpfung pro Set; globaler Fallback **„Standard“** (nicht löschbar)
- Logos-Symbol an aktiven Import-Elementen im Editor

---

## Technik (Kern)

| Bereich | Dateien / Module |
|---------|------------------|
| Sets | `src/LayoutSet.php`, `src/ElementLink.php` |
| Logos-Import | `src/LogosSermonImporter.php` |
| API / Editor | `public_html/api.php`, `editor.php`, `editor.js` |
| Seed | `seed/layout-sets/schlicht/`, `scripts/build_schlicht_seed.py` |
| i18n | `lang/de.php` … `rm.php` (Set/Logos-Keys) |

---

## Abnahme (erledigt)

- [x] Logos-Elementsteuerung pro Set (aktive/inaktive Rollen)
- [x] Sichtbarkeit Logos-Symbol pro aktivem Import-Element
- [x] Set-Import/Export (`.chs`/`.zip`) Ende-zu-Ende
- [x] Standard-Set für Neuinstallationen (`seed/layout-sets/schlicht`)
- [x] Übersetzungen DE/EN/FR/IT/RM
- [x] Testfall „nur H1 + Bibelstellen + Blockzitate“
- [x] Testfall „H2–H5 deaktiviert“
- [x] Exportiertes Set in neuer Umgebung importierbar
- [x] Neue Layout-Folien ohne `layoutKey` in „Vorlage anwenden“ wählbar

---

## Dokumentation

- [x] CHANGELOG.md → `[2.1.0]`
- [x] `.github/RELEASE_v2.1.0.md`
- [x] `docs/RELEASES.md` — Roadmap angepasst
- [x] `scripts/verify_schlicht_seed.py`, `scripts/sync_set_i18n.py`

---

## Nicht in dieser Release

- church.tools-Integration (→ **v2.2.0**)
- Integrierter Medien-Editor (→ **v2.3.0**)
- reveal.js Auto-Animate (→ **v2.4.0**)
- Visueller Editor v2 / slides.com (→ **v3.1.0**)

---

## Getroffene Entscheidungen

| # | Frage | Entscheidung | Begründung |
|---|--------|--------------|------------|
| 1 | Version | **v2.1.0** (Minor) | Eigene Release-Linie für Sets + Logos vor church.tools. |
| 2 | Standard-Set | **Schlicht** aus Seed | Einheitlicher Einstieg für Neuinstallationen. |
| 3 | Archivformat | **`.chs`** (ZIP) | Export/Import inkl. Meta, Layouts, Assets. |
| 4 | Folien ohne layoutKey | **Auflösung per slide.id** | Manuell angelegte Set-Folien sofort wählbar. |
