# SlideForge — Anpassungen Plan B (Archiv)

> **Status:** Abgeschlossen · nicht mehr aktiv pflegen  
> **Zeitraum:** 2026-07-05 – 2026-07-06  
> **Modus damals:** Agent codet · Owner testet · **i.O.**

Diese Datei war das **Freigabe-Protokoll** für die „Plan B“-Nacharbeiten nach v2.0.x (Present, Filmstreifen, Ghost-Layer, Tablet, Laser). Alle Punkte sind umgesetzt und in Releases gelandet.

**Laufende Arbeit ab jetzt:**

| Zweck | Datei |
|--------|--------|
| Bugs & Features (A → B → C) | [AENDERUNGEN.md](AENDERUNGEN.md) |
| Ideen ohne Release | [BACKLOG.md](BACKLOG.md) |
| Release-Planung | [RELEASES.md](RELEASES.md) |
| Tages-Agent-Tasks | `docs/AGENT_TASK_YYYY-MM-DD.md` (pro Tag, danach Archiv) |

---

## Erledigt (Übersicht)

| # | Thema | Release / Kontext |
|---|--------|-------------------|
| 1 | Folienvorlagen Neuinstallation | v2.0.x |
| 2a | Steuerung Master/Slave + UI | v2.0.3 |
| 2b | Tablet: grössere Touch-Buttons (Present + Remote) | v2.0.3 |
| 2c | Filmstreifen: Klick = Schrittweise | v2.0.3 · `present.js` |
| 2d | Ghost-Layer / Automatik (Present-Bug) | v2.0.3 · `SlideRenderer.php`, `present_frame.php` |
| 2e | Folien-Raster + Batch-Übergänge | v2.0.3 / v2.1.x Editor |
| 2f | Folien beim Präsentieren deaktivieren | v2.0.3 |
| 2g | Laserpointer ein/aus + Schnellschalter | v2.0.3 · `presentLayout` pro User |
| — | Ghost-/Laser-Toolbar (Laser \| Transparenz \| Ghost) | v2.0.3 |
| — | QR-Code lokal (`SLIDEFORGE_PRESENT_HOST` / `present_reachable_host`) | v2.0.1 |

---

## Kurznotizen (Referenz)

**#2c Filmstreifen:** Klick auf andere Folie → Sprung; Klick auf aktuelle Folie → nächster Effekt (wie „Weiter“).

**#2d Ghost-Layer:** Ghost-HTML mit Fragment-Struktur; Sichtbarkeit synchron mit Live-Folie (Automatik sichtbar).

**#2g Laser:** `laserPointerEnabled` in Present-Layout; Remote-Tab und Hauptfenster respektieren die Einstellung.

---

*Neue Anpassungen bitte in [AENDERUNGEN.md](AENDERUNGEN.md) Abschnitt A eintragen — nicht hier.*
