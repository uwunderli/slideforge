# Release v2.0.0 — Mobile Fernsteuerung

**Tag:** `v2.0.0`  
**Basis:** v1.0.4  
**Shipped:** 2026-07-04 · Prod: `slides.bkbiel.ch` · `ASSET_VERSION` 268

## Highlights

- **Smartphone-UI:** Reduziertes Dashboard nach Login — Präsentation auswählen und **Fernsteuern**
- **Desktop Present + Mobile Remote:** Laptop/Beamer bleibt Hauptfenster; Handy steuert Folien über HTTPS
- **Remote-Tabs:** Folie, Vorschau, Uhr, Timer, Laser — Fortschrittsbalken rechts am Handy
- **Laser vom Handy:** Touch → Laser auf dem Beamer
- **QR-Code** im Present-Menü für Remote-Zugang
- **Editor auf Handys gesperrt** — Tablets/iPads behalten die volle Desktop-Oberfläche

## Test plan

- [x] iPhone/Android (schmal): reduziertes Dashboard, kein Editor
- [x] iPad (≥768px): normale Desktop-UI inkl. Editor
- [x] Laptop: `present.php` + Handy: `present_remote.php` — Vor/Zurück über Internet
- [x] Laser vom Handy sichtbar am Beamer
- [x] Fortschrittsbalken am Handy (Timer-Fortschritt)
- [x] Badge „Handy verbunden“ im Present-Modus
- [x] Remote-Link und QR-Code vom Desktop-Present

## Deploy

Prod: `./.deploy/deploy.sh sync-code` → `ftp.bkbiel.ch` (ohne `data/` und `uploads/`).
