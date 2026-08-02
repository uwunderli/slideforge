# Release v2.1.6 — Notizen einklappbar

**Tag:** `v2.1.6`  
**Basis:** v2.1.5 · `ASSET_VERSION` 587

## Highlights

- **Notizen-Overlay** im Präsentationsmodus: Tippen auf die Fläche schiebt die Notizen sanft zur Seite
- Rechts bleibt ein **touch-taugliches Register** «Notizen»; Tippen darauf blendet sie wieder ein
- Zustand bleibt in `localStorage`

## Test plan

- [ ] Present: Folie mit langen Notizen → Tippen auf Notizfläche → nur Register sichtbar
- [ ] Tippen auf Register → Notizen gleiten wieder ein
- [ ] Scrollen in langen Notizen löst kein Einklappen aus
- [ ] Hard-Reload (ASSET 587)
