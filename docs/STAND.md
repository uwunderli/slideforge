# STAND SlideForge (FolienSchmiede)

- **27.11. 3.6.1 live Biel** (Farben bläulich, Urs «Halbwegs repariert»); **3.6.2 gebaut** (`entwickler/slide-farben`, auf 84502bf), beim Gitwart: Medienliste füllt die Seitenleiste (Flex-Kette, keine feste max-height).
- **27.11. 3.6.1 gebaut** (`entwickler/slide-farben`, Worktree `_claude/entwickler/wt-slide-farben`, auf 3.6.0), beim Gitwart, nach 3.6.0: Hub-Farben griffen nicht, weil header.php die veraltete eigene cf-theme.js lud (Hub-Nachricht mit Akzent ignoriert) → jetzt hub_asset wie FF; Beigereste weg; Medienliste (Einträge zusammengedrückt) neu. sf_hub_asset() nimmt jetzt grundsätzlich die Hub-Datei, lokale Kopie nur Rückfall.
- **27.11. 3.5.0 gebaut** (`entwickler/slide-ff-medien`, Worktree `_claude/entwickler/wt-slide-ff-medien`): Bild/Audio/Video/Hintergrund/Ersetzen über FF-Picker (HubFiles), upload.php nur `ref` via AppCall; beim Gitwart.

- **27.11.: 3.3.2 gebaut** (Zweig `entwickler/slide-statusleiste`, auf 3.3.1): Statusleiste unten (links Aktionen mit Uhrzeit, Mitte Datei, rechts Version), schwebender Speicher-Knopf weg. Beim Gitwart, nach 3.3.1, nur Biel.
- **27.11. 3.3.0 «Auf Gerät zeigen»** (Zweig `entwickler/slide-auf-geraet`, Worktree `_claude/entwickler/wt-slide-geraet`, auf 3.2.6; braucht Hub 2.33.0): Menüband Präsentieren › Auf Gerät zeigen, `player.php` (Einmal-Ticket des Hubs → Gerätesitzung nur für diese Präsentation → Zuschauer-Ansicht folgt Presenter/Tablet), `geraet_api.php`, `src/Geraete.php`. Nicht gelaufen.

- **27.11. in Arbeit: Umbau zum FF-Editor im Hub** (Go Urs direkt). Plan + Entscheide: `_berichte/slideforge/plan-hub-editor-2026-11-27.md`. H1/H2 (FileForge) und H3 (Hub-Pfad) laufen parallel als Unter-Agenten; H4–H6 danach.
  - **H3 gebaut (3.0.0, Zweig `entwickler/slide-hubpfad`; Hub 2.32.1 `entwickler/hub-slide-platform`), beim Gitwart:** `public/` statt `public_html/`, Anmeldung nur über den Hub (ID = Personenschlüssel), Eigenbetrieb (Login, Konto, Dock, PWA, .deploy) entfernt. Offen: NPM-Location `/slideforge/` → `/slideforge/public/` je Hub (Server-Agenten), wunder.li hat Alt-Ordner `/data/www/slideforge` (Aug., public_html) im Zielordner, PHP-CLI für Export-/Importjobs nur in Biel belegt (clipforge/runtime), App-Schlüssel/AppCall kommt mit H4. slides.bkbiel.ch läuft weiter bis H6 (Altdaten lesbar unter `/data/slideforge` im Web-Container Biel).
  - **H4/H5 gebaut (3.1.0, Zweig `entwickler/slide-cfs` ab 3.0.0; Hub 2.32.2 `entwickler/hub-slide-takt`), nicht beim Gitwart:** `.cfs` (`src/CfsFile.php`), Arbeitskopie je Datei (`src/Workspace.php`, `data/workspaces/`, `data/recent/`), AppCall `src/HubApp.php`/`src/FfFile.php`, `public/hooks/open.php` (FF «Öffnen mit»), `public/hooks/takt.php` (Hub-Job `slideforge.aufraeumen`), `public/ff_api.php`, Startseite `public/index.php` + `assets/js/sf-ff.js`. Rechte nur aus FileForge. Offen: H6 Bestand, Vorlagen-Liste im Neu-Dialog, thumb.png, nichts gegen echtes FF geprüft.

- **14.11. live (VERSION 2.1.33, ASSET_VERSION 698):** Editor/Präsentation ohne Kopfleiste; Vollbild (Ribbon-Icon, F, F5; im Hub-Rahmen braucht es Hub ≥ 2.6.16); Presenter/Pfeiltasten auch aus dem Beamer-Fenster und an der Tablet-Fernsteuerung. Urs: «jetzt gehts»; Stick am Tablet ungeprüft.
- **Erledigt (alles auf Prod, 04.11., ASSET_VERSION 690):** Traktandenlisten-Import (`agenda.php`), Ribbon «Präsentation einfügen» (pptx/odp/xlsx/chs/zip/html/cfs), Excel-Import (nur erstes Blatt). Letzter Commit `7276df7` auf main (lokal, Gitwart gemeldet 05.11.).
- **Offen:** Urs schaut Agenda-Seite und Excel-Import an, Korrekturen kommen von ihm. Excel: Pfeilspitzen/Strichelung bei Linien nicht unterstützt, Text in Ellipsen nur grob mittig. PDF-Parser mit echter Datei ungeprüft.
- **Nächster Schritt:** Rückmeldung von Urs abwarten.
- **Pfade:** `src/AgendaImporter.php`, `src/PresentationInsert.php`, `src/XlsxImporter.php`, `public_html/agenda.php`, Texte `lang/de.php`.
- **Pfade:** `src/AgendaImporter.php`, `public_html/agenda.php`, Texte `lang/de.php` (`agenda.*`), Link in `import.php`.

- **Gebaut, nicht geliefert (5.11.):** Zweig `helferlein/kennung-slide` = VERSION 2.1.24 (Kennung `ct:`/`u:` in HubDock). Tag-Vorschlag `v2.1.24`.

## Live-Stand
- Prod `slides.bkbiel.ch` (bkb-srv10, `/data/slideforge`): Agenda-Import mit Layout-Wahl und Vorschau ausgeliefert 04.11. (`1675929`). Sicherung `.sicherung/agenda2-20261104-155115`.

## Liefer-Weg
Ab 3.0.0 über Hub-Installer/Gitwart (Web-Share). Altbestand slides.bkbiel.ch: von Hand, siehe `docs/PRUEFUNGEN.md` («So wird in der FolienSchmiede ausgeliefert»). Kein release.sh.

## Zuletzt gemacht
- 04.11.: Ribbon «Präsentation einfügen» (PresentationInsert, ASSET_VERSION 688).
- 04.11.: Agenda-Import (Layout wählbar, Traktanden auf Spalten verteilt, Vorschau-Kacheln). Eingabe docx/md/pdf → Gliederung (Markdown, korrigierbar) → Titelfolie + je Traktandum eine Übersicht mit Hervorhebung (Farbe/fett/beides, über `[color=]`/`**`) + optional Unterpunkt-Folie + Zusatz-PPTX hinter gewähltem Traktandum.

## Fallen
- Importer läuft über eigene Seite `agenda.php` (nicht über den Logos-Dialog); PDF-Upload im Hauptdialog bleibt Bild-Import.
- Neue Folien-Set-Rollen: Titel `document_title`/`subtitle`, Übersicht `heading1` + `normal`.

## Wo mehr steht
`docs/PRUEFUNGEN.md`, `_berichte/slideforge/uebergabe-2026-10-20.md`.

## Installer (5.11., Helferlein)
- VERSION 2.1.23 (Zweig `helferlein/install-gruppe1-slide`), Tag `v2.1.23` durch den Gitwart. Katalog hat kein `install`; Prod ist ein eigener Docker-Host (`slides.bkbiel.ch`, `/data/slideforge`), nicht der Hub-webroot: ohne Entscheid (Umzug unter `hubPath` oder Installer-Ziel je App) kein Installer.