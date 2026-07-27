# Release v2.1.3 — Ribbon & Editor-Feinschliff

**Tag:** `v2.1.3`  
**Basis:** v2.1.2 · `ASSET_VERSION` 553

## Highlights

- **Konfigurierbares Ribbon** (SoftMaker-Ansatz): Tabs/Gruppen/Befehle anpassen, Peek, Standard = aktuelles Nutzerlayout
- **Ansicht:** Vorschau Tab / Fenster / Lokal im Default-Ribbon
- **Theme** Tag / Nacht / System über Prefs + `cf_theme`-Cookie (ChurchForge-weit)
- Einheitliche Dialoge (`.sf-dialog-*`), Zoom-Widget, Einstellungen als Ribbon-Widgets
- Dashboard Phase 1, diverse Editor-/Raster-Fixes
- **Fix:** Hub→SlideForge ohne Shared-Mount im Docker (Asset-URL-Fallbacks, `hub_session`)

## Pendent (nicht in diesem Release)

- Präsentationsansicht → konfigurierbares Ribbon
- Text-Animationen Animate.css
- Logos-Importer neu (pausiert)

## Test plan

- [ ] Editor: Ribbon Anpassen / Zurücksetzen → Default inkl. Ansicht→Vorschau
- [ ] Theme Tag/Nacht/System bleibt über Hub und SlideForge hinweg
- [ ] Login Hub → Klick FolienSchmiede → Dashboard ohne 500
- [ ] Teilen / Export / Cliparts als einheitliche Dialoge
