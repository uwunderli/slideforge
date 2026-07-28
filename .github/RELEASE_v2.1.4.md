# Release v2.1.4 — Present-Ribbon

**Tag:** `v2.1.4`  
**Basis:** v2.1.3 · `ASSET_VERSION` 586

## Highlights

- **Konfigurierbares Ribbon im Präsentationsmodus** — wie im Editor: Anpassen, Tabs/Gruppen, Persistenz (`present_ribbon_layout`)
- Tab **Ansicht** (Steuerung: Nächste/Uhr/Timer/Zeitleiste/Medien/Folien/Ghost/Laser) plus Einstellungs-Dialoge
- Tab **Präsentieren:** Fortschritt & Navigation als eigene Toggle-Befehle; Link als schmales Owner-Widget; Bildschirm bei Lokal
- Editor: Anzeige schlank (nur Fortschritt/Navigation als Ribbon-Befehle); Link/Bildschirm nur noch im Present-Modus
- Dezente Spalten-Trenner, einheitliches «Ribbon anpassen»-Icon, etwas mehr Abstand in Zuschauer-/Steuerungs-Gruppen

## Test plan

- [ ] Present: Ribbon Anpassen / Zurücksetzen; Tab Ansicht + Präsentieren
- [ ] Fortschritt/Navigation toggeln → Zuschauer-Fenster; Link kopieren (Owner)
- [ ] Lokal präsentieren inkl. Bildschirmwahl
- [ ] Editor-Tab Präsentation: Fortschritt/Navigation-Icons; kein Link/Bildschirm mehr dort
- [ ] Hard-Reload nach Deploy (ASSET 586)
