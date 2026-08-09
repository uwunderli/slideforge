# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [2.1.21] - 2026-08-09

### Changed

- fix: Ton bei Medien-Steuerung auf der Presenter-Konsole hörbar


## [2.1.19] - 2026-08-09

### Changed

- feat: Media-Leave-Confirm und Seek im Präsentationsmodus


## [2.1.18] - 2026-08-02

### Changed

- fix: User-Menü im Hub-Embed nicht doppelt anzeigen


## [2.1.17] - 2026-08-02

### Changed

- fix: Dashboard-Crash durch \$shared in user_menu behoben


## [2.1.16] - 2026-08-02

### Changed

- fix: Hub-Dock trotz sticky auth_via=local wieder anzeigen


## [2.1.15] - 2026-08-02

### Fixed

- Launcher: Hub-Login zeigt wieder den vollen Programm-Dock (shouldUseHubLogin)
- Embed-Erkennung nur noch via hub_theme (kein generisches iframe-Hide)

## [2.1.14] - 2026-08-02

### Fixed

- Launcher: Hub-Session erkennen; lokales Logo ohne Dock-Label
- Dialog-Bridge: lazy Upgrade, kein Host-Umhängen

## [2.1.13] - 2026-08-02

### Added

- HubFloatDialog (globale Klassen): schwebende Dialoge mit Min/Max, 8-Resize, fitContent
- Launcher: voller Dock bei Hub-Login, ein CF-Logo bei lokalem Login

### Changed

- FolienSchmiede im Hub wie SendeSchmiede (`external` + `hubAuth`); Embed blendet SF-Dock aus

## [2.1.12] - 2026-08-02

### Fixed

- Present-Notizen: Kontrast zur Folienfarbe (helles UI-Theme lesbar)

## [2.1.11] - 2026-08-02

### Added

- Present: Notizen-Einstellungen im Ribbon (offen / geschlossen / übernehmen)

## [2.1.10] - 2026-08-02

### Changed

- Topbar: HubUserMenu-Standard (Anzeigename-Chip; Sprache/Darstellung im Menü)

## [2.1.9] - 2026-08-02

### Changed

- Notizen-Overlay nach unten einklappen (Register bleibt); Deploy-Regel im Repo


## [2.1.8] - 2026-08-02

### Changed

- chore: Regel — nach Umsetzung sofort Release/Deploy
- fix: Notizen-Overlay nach unten einklappen


## [2.1.7] - 2026-08-02

### Changed

- **Notizen-Overlay:** Ausblenden nach unten; horizontales Register bleibt zum Einblenden

## [2.1.6] - 2026-08-02

### Added

- **Notizen-Overlay einklappbar** — Tippen auf die Fläche schiebt Notizen zur Seite; Register rechts blendet sie wieder ein (touch-tauglich)

## [2.1.5] - 2026-07-29

### Changed

- chore: snapshot current module state


## [2.1.4] - 2026-07-28

### Added

- **Present-Ribbon** — konfigurierbar wie im Editor (Anpassen, Tabs/Gruppen, `present_ribbon_layout`)
- Tab **Ansicht:** Steuerungsleiste (Panel-Toggles), Timer/Zeitleiste/Uhr/Laser als Dialoge
- Fortschritt & Navigation als eigene Ribbon-Toggle-Befehle (Editor + Present)

### Changed

- Editor-Anzeige: nur Fortschritt/Navigation; Link und Bildschirm nur noch im Präsentationsmodus
- Present-Tab «Einstellungen» → **Ansicht**; dezente Spalten-Trenner; Abstände in Zuschauer-/Steuerungs-Gruppen

## [2.1.3] - 2026-07-27

### Added

- **Konfigurierbares Ribbon** — Tabs/Gruppen/Befehle anpassen, Peek, Standard-Layout inkl. Ansicht→Vorschau/Fenster/Lokal
- **Theme** Tag / Nacht / System (Prefs + `cf_theme`-Cookie)
- Dashboard Phase 1; einheitliche Dialoge (`.sf-dialog-*`); Zoom-Stepper; Einstellungen als Ribbon-Widgets

### Fixed

- Hub→SlideForge: fehlende Shared-Helpers im Docker-Container (500 nach Login)
- Diverse Ribbon-/Editor-/Raster-Fixes (siehe AENDERUNGEN C)

## [2.1.2] - 2026-07-08

### Changed

- **Raster-Ansicht:** Meta-Zeile pro Kachel — Folien-ID, Übergangs-Icon, Zeit links; Effektname (z. B. „Schieben“) rechtsbündig

## [2.1.1] - 2026-07-08

### Added

- **Raster-Ansicht:** Folien per Drag & Drop sortieren (wie Filmstreifen)
- **Raster-Ansicht:** Steuerung pro Kachel — Präsentieren ein/aus, Duplizieren, Löschen
- **Raster-Ansicht:** Miniatur-Grösse per Schieberegler (Statusleiste), gespeichert pro Benutzer
- **Dokumentation:** `docs/AENDERUNGEN.md` (Bugs & Features A→B→C), `docs/BACKLOG.md`

### Fixed

- Vorlageelemente: Speichern schliesst den Dialog nach erfolgreichem Speichern
- Editor-Ebenen: keine „Ghost-Ebenen“ mehr beim Sortieren (Layer-Reorder korrigiert)
- Editor: leere Zustände im Objekte-/Elemente-Bereich zentriert dargestellt

## [2.1.0] - 2026-07-07

### Added

- **Folien-Sets (Layout-Sets):** Mehrere Layout-Folien pro Set, Set-Editor, Element-Zonen, Platzhalter-Rollen
- **Logos-Import:** HTML-Export aus Logos Sermon Builder → Folien mit Layouts + Sprechernotizen
- **Logos-Zuordnung** pro Set (Folien vs. Notizen vs. nicht verwendet)
- **Vorlageelemente:** Modal zur Textvorlagen-Verknüpfung; Standard-Fallback-Textvorlage
- **Set-Import/Export** als `.chs` (ZIP-Archiv)
- **Standard-Set „Schlicht“** in `seed/layout-sets/schlicht/` für Neuinstallationen
- **Vorlage anwenden** in Präsentationen mit verknüpftem Folien-Set
- **Editor:** Vorlagen-Accordion (Ebenen → Objekte → Vorlagen), Raster-Ansicht im Hauptfenster (Toggle)
- **Editor:** Raster-Ansicht und Masterfolie in linker Menüleiste
- i18n für Set-/Logos-Funktionen in DE, EN, FR, IT, RM

### Changed

- Editor: Folien-Raster ersetzt separates Modal — Ansicht wechselt im Hauptbereich
- Editor: Rechte Sidebar mit einheitlichem Scroll-Verhalten

### Fixed

- Layout-Folien ohne `layoutKey` erscheinen in der Vorlagen-Auswahl (eindeutige `slide.id`)
- Editor: Doppelte Scrollbalken in der rechten Sidebar behoben

## [2.0.3] - 2026-07-06

### Changed

- feat: Plan B — Folien-Raster, Live-Sync, Release-Deploy-Hooks
- chore: Abschluss v2.0.x — Docs sync, Patch-Skript entfernt
- docs: README Mobile-Abschnitt und Release v2.0.2

## [2.0.2] - 2026-07-04

### Added

- **PWA (Progressive Web App):** `manifest.php`, Service Worker (`sw.php`), App-Icons — installierbar auf Android/iOS („Zum Home-Bildschirm“ / „App installieren“), `display: standalone`, Start auf dem Dashboard

## [2.0.1] - 2026-07-04

### Added

- **Feature-Tour (Demo):** Folien „Mobile Fernsteuerung“ mit Screenshot in DE / EN / FR / IT / RM
- Screenshot-Asset `ui-remote.png` und erweitertes Capture-Skript (`capture_feature_tour_screenshots.py`)

### Fixed

- **QR-Code** im Present-Menü: PHP-Warnungen aus phpqrcode zerstörten die PNG-Ausgabe — Konstanten-Guards und sauberer Output-Buffer in `RemoteQr.php`

## [2.0.0] - 2026-07-04

### Added

- **Mobile Fernsteuerung:** Smartphone-Dashboard (Listenansicht), `present_remote.php` für Foliensteuerung über Internet (HTTPS + `live.php`)
- **Remote-Oberfläche:** Tabs Folie, Vorschau, Uhr (analog mit Zahlen), Timer (Studiouhr), Laser; Fortschrittsbalken rechts am Handy (Timer-Sync)
- **QR-Code** im Präsentationsmodus (Menü „Präsentieren“) für schnellen Remote-Zugang — serverseitig via `qr.php` / phpqrcode
- **Laser vom Handy:** Touch-Fläche auf der Remote-Oberfläche steuert den Laserpointer am Desktop-Present
- Mobile Erkennung (`Mobile.php`, `mobile-detect.js`, max-width 767px; `?mobile=1` / `?desktop=1` zum Testen)
- Editor auf Smartphones gesperrt mit klarer Meldung; iPad/Tablet ≥768px = Desktop-UI
- Anzeige „Handy verbunden“ im Präsentationsmodus; Remote-Link kopieren
- `remote_slide.php` für Folien-Vorschau auf der Remote-Oberfläche

### Changed

- `live.php` erweitert: Remote-Schritte, Laser-Sync, Session-Heartbeats, Present-Config-Sync; View-Recht für Remote-Aktionen
- Present-Modus: schnellerer Laser-/Remote-Poll, Menüs schliessen bei Klick ausserhalb

### Fixed

- Mobile-Remote: Panel-Sichtbarkeit (`hidden`-Attribut vs. CSS), Laser-Bewegung (Throttle statt Debounce)
- QR-Code: zuverlässige serverseitige PNG-Generierung (kein fehleranfälliger Client-Generator)

## [1.0.4] - 2026-07-04

### Added

- **WebDAV integration:** per-user cloud/NAS drives in profile (up to 10, encrypted credentials); browse folders in the editor media tab and import images, SVG, audio, and video
- WebDAV lightbox preview for images, SVG, audio, and video before import
- Admin settings split into tabs: General, SMTP, Spellcheck, Media, Users

### Changed

- Media import buttons: **On slide** (primary) before **As background** (Pixabay, WebDAV)
- SVG images preserve aspect ratio when inserted on the canvas
- Demo showcase and feature tour updated for WebDAV

### Fixed

- WebDAV import JSON errors for large media files (size validation and error handling)

## [1.0.3] - 2026-07-04

### Added

- **Openclipart integration:** search and import free SVG cliparts in the editor (no API key)
- **Remove background** on image objects (light backgrounds for SVG and raster images)
- Meaningful layer names for imported media (icons, cliparts, Pixabay)
- Compact SVG/icon preview in the properties panel

### Changed

- Media tab buttons for Pixabay, Iconify, and Openclipart now show icons
- Demo showcase and feature tour updated for new media features

## [1.0.2] - 2026-07-04

### Added

- **Iconify integration:** search SVG icons from 150+ sets in the editor (no API key)
- Icon color picker in the search dialog and on the slide (brand palette supported)
- Server-side SVG tinting for preview, presentation, and export

## [1.0.1] - 2026-07-04

### Added

- Optional spellcheck before entering presentation mode (user setting in editor)
- Feature tour v2: navigation slide, UI screenshots, multilingual tours (DE/EN/FR/IT/RM)
- Improved LanguageTool integration: loading UI, re-check button, markdown list handling, name-variant hints
- Invite management moved to admin user-management tab with optional email delivery

### Changed

- README restructured: feature overview by area, installation guide, AI disclaimer
- Animation and transition pickers use icon grids instead of dropdowns
- Public view links can show navigation controls when enabled on the presentation

## [1.0.0] - 2026-07-03

### Added

- Self-hosted, file-based multi-user editor for [reveal.js](https://revealjs.com) presentations
- Canvas editor (Konva.js): shapes, text, images, video, animations, templates
- Presentation mode with live sync, offline HTML/ZIP export, PDF, PPTX, and ODP export
- PPTX import, user roles, SMTP mail, invite links, DE/EN/FR UI
- Seven default slide templates seeded on first admin registration

[1.0.3]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.3
[1.0.2]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.2
[1.0.1]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.1
[1.0.0]: https://github.com/uwunderli/slideforge/releases/tag/v1.0.0
