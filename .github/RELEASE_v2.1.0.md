# Release v2.1.0 — Folien-Sets & Logos-Import

**Tag:** `v2.1.0`  
**Basis:** v2.0.4 (Feature-Branch) · merge auf `main` nach v2.0.3  
**Shipped:** 2026-07-07 · `ASSET_VERSION` 356

## Highlights

- **Folien-Sets:** Mehrere Layout-Folien pro Set, Set-Editor, „Vorlage anwenden“ in Präsentationen
- **Logos-Importer:** HTML-Export aus Logos Sermon Builder → gestaltete Folien + Sprechernotizen
- **Logos-Zuordnung:** Pro Set steuerbar, welche Elemente Folien vs. Notizen erhalten
- **Vorlageelemente:** Textvorlagen-Verknüpfung, Modal im Set-Editor, Standard-Fallback
- **Import/Export:** `.chs`-Archive (ZIP); Standard-Set **Schlicht** im Seed für Neuinstallationen
- **i18n:** Set-/Logos-UI in DE, EN, FR, IT, RM

## Test plan

- [ ] Neuinstallation: Set „Schlicht“ freigegeben und als Standard sichtbar
- [ ] Logos-HTML importieren mit aktivem Set — Überschriften, Bibelstellen, Blockzitate
- [ ] Set exportieren (.chs) und in zweiter Instanz importieren
- [ ] Präsentation: Folien-Set wählen, Vorlagen-Accordion rechts, Layout anwenden
- [ ] Raster-Ansicht: Toggle über Grid-Symbol, Doppelklick öffnet Folie
- [ ] Vorlageelemente-Modal: Zuordnung speichern, Logos-Symbole im Editor

## Deploy

Prod: `./.deploy/deploy.sh sync-code`  
Demo: `./.deploy/deploy-demo.sh sync-demo`  
Optional Seed auf Prod: `./.deploy/push-layout-set-seed.sh schlicht`
