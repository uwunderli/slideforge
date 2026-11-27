# STAND SlideForge (FolienSchmiede)

- **27.11. 3.12.17 gebaut** (`entwickler/slide-mehrfach-kopieren` ab 3.12.16 ed31e75): mehrere Objekte kopieren/einfügen, andere Folie gleiche Stelle. Bei Gitwart.
- **27.11. 3.12.16 gebaut** (`entwickler/slide-alle-folien` ab 3.12.15 a7a2ce5): Befehl «Alle Folien wählen» (Ctrl+A in Filmstreifen/Raster). Bei Gitwart.
- **27.11. 3.12.15 gebaut** (gleicher Zweig nach 3.12.14): Markierung Effektliste mit Hub-Akzent. Bei Gitwart.
- **27.11. 3.12.14 gebaut** (`entwickler/slide-zahlfelder` ab 3.12.13 9a0cf79): alle Zahlenfelder/Schieber auf Hub HubNumberStep (braucht Hub 2.37.x). Bei Gitwart.
- **27.11. 3.12.13 gebaut** (`entwickler/slide-pfeil-regler` ab 3.12.12 0c1e8c6): Pfeil-Regler Spitze/Schaft in einer Zeile mit Zahlenfeld. Bei Gitwart.
- **27.11. 3.12.12 gebaut** (`entwickler/slide-abspielen-tasten` ab 3.12.11 8445255): Abspielen-Tasten wie Vorführen (Eingabe, ↓/↑), Zeitsteuerung läuft im Abspielen. Bei Gitwart.
- **27.11. 3.12.11 gebaut** (`entwickler/slide-schritt-knoepfe` ab 3.12.10 b68b794): Schritt-Feld ‹ Nummer › mit grossen Knöpfen. Bei Gitwart.
- **27.11. 3.12.10 gebaut** (`entwickler/slide-pfeilspitze` ab 3.12.9 4ea6fce): Pfeil-Regler Spitze/Schaft (Editor + SlideRenderer). Offen: PPTX-Export ohne Spitze/Schaft. Bei Gitwart.
- **27.11. 3.12.9 gebaut** (`entwickler/slide-fx-klick` ab 3.12.8 58d0bd2): Klick in der Effektliste wählt das Objekt, Doppelklick öffnet Einblenden. Bei Gitwart.
- **27.11. 3.12.8 gebaut** (gleicher Zweig nach 3.12.7): Kürzel «Abspielen» im Katalog, Vorgabe Ctrl+Enter. Bei Gitwart.
- **27.11. 3.12.7 gebaut** (`entwickler/slide-abspielen-oben` ab 3.12.6 66eb8b5): «▶ Abspielen» oben mittig über der Folie statt im Effekte-Bereich. Bei Gitwart.
- **27.11. 3.12.6 gebaut** (`entwickler/slide-fx-auswahl` ab 3.12.5 782435a, Worktree `_claude/entwickler/wt-slide-fx-auswahl`): Effektliste (Reihenfolge) markiert die auf der Folie gewählten Objekte. Bei Gitwart.
- **27.11. 3.12.5 gebaut** (`entwickler/slide-fx-tabs` ab 3.12.4 f8f4963, Worktree `_claude/entwickler/wt-slide-fx-doppelklick`, beim Gitwart): Effekte-Reiter mit Untertabs Einblenden/Ausblenden/Reihenfolge (editor_ui.fxSubtab), Klapp-Abschnitte 3.10.2 entfernt.
- **27.11. 3.12.3 gebaut** (`entwickler/slide-pfeil-abspielen` ab 3.12.2 7ca9131, Worktree `_claude/entwickler/wt-slide-pfeil-abspielen`): ←/→ auf der Folienfläche spielt Effekte + Übergänge über Foliengrenzen (preview.php play = ganze Präsentation ab Start), Abspielen ohne Fenster (Klick/Taste beendet, Esc), Weitere Befehle stage_play_next/prev; Fix ↑↓ im Filmstreifen (SF.keyArea). Beim Gitwart.

- **27.11. 3.12.2 gebaut** (`entwickler/slide-fx-zeilen` ab 3.12.0 8ba6ecf, nach 3.12.1, Worktree `_claude/entwickler/wt-slide-ausblenden`): Effekte-Paare je in einer Zeile mit Hub-Symbolen, Schritt-Abzeichen als Pillen «5 → 6».
- **27.11. SF 3.12.1 gebaut (Zweig `entwickler/slide-weitere-befehle`, Worktree `_claude/entwickler/wt-slide-hub-ribbon`, auf 3.12.0 8ba6ecf), braucht Hub 2.36.3:** feste Editor-Tasten als «Weitere Befehle» im Katalog (umstellbar), scope grid/play/stage; fest nur Objekt-Pfeile. Bei Gitwart nach 3.12.0. Details docs/RIBBON_MENU.md.
- **27.11. SF 3.12.0 gebaut (Zweig `entwickler/slide-hub-ribbon`, Worktree `_claude/entwickler/wt-slide-hub-ribbon`, auf 3.11.4 3f8b5b9), braucht Hub 2.36.1:** Menüband Editor+Präsentation auf HubRibbon/HubRibbonCustomize/HubShortcuts; eigener Anpassen-Dialog raus, Renderer nur noch Inhalt; Kürzel über SFShortcuts; Layout-Felder `known`/`shortcuts`, alte Nachrüstungen nur noch einmal. Einzelheiten `docs/RIBBON_MENU.md`, `docs/PRUEFUNGEN.md`. Offen: nicht im Browser gesehen.
- **27.11. 3.11.1 gebaut** (`entwickler/slide-mehrfach-ziehen` auf 3.11.0 225a452, Worktree `_claude/entwickler/wt-slide-mehrfach-ziehen`, beim Gitwart): mehrere markierte Folien als Block ziehen (Raster + Folienleiste, Plakette mit Anzahl), Strg+Umschalt+Pfeil verschiebt die Auswahl. 3.12.0 (Menüband auf Hub) rebased darauf.

- **27.11. 3.11.0 gebaut** (`entwickler/slide-abspielen` ab 3.10.2 fc0124c, Worktree `_claude/entwickler/wt-slide-abspielen`, beim Gitwart): «▶ Abspielen» (Effekte-Reiter, Ansicht › Vorschau) als Überlagerung mit `preview.php?play=1`, ▶ je Effekt auf der Bühne, Mitlaufen folgt Schritt für Schritt (`frag`), Schalter «Animationen beim Folienwechsel abspielen» im Dialog «Auf Gerät» (`editor_ui.mitAnim` → Live `anim`), Geräte-Kacheln gleich gross, Filmstreifen ←/→, Raster-Pfeile. Ungeprüft im Browser.

- **27.11. 3.10.2 gebaut** (`entwickler/slide-effekte-klappen` ab 3.10.1 f448749, Worktree `_claude/entwickler/wt-slide-ausblenden`, beim Gitwart): Abschnitte Einblenden/Ausblenden im Effekte-Reiter ein-/ausklappbar, Zusammenfassung in der Kopfzeile, Zustand je Person in editor_ui.collapsed.
- **27.11. 3.10.0 gebaut** (`entwickler/slide-ausblenden`, Worktree `_claude/entwickler/wt-slide-ausblenden`, nach 3.9.10, beim Gitwart): Ein- und Ausblenden je Objekt (`animExit`, äusseres Fragment `sf-exit`), gleiche Klick-Nummer = gleichzeitig (Renderer zählte vorher jedes Objekt einzeln), Effekte-Reiter Einblenden/Ausblenden mit «Mit/Nach vorherigem», Plakette «1→2». Offen: PPTX/ODP-Export ohne Animationen, en/fr/it/rm-Texte fallen auf Deutsch zurück.
- **27.11. 3.9.10 gebaut** (`entwickler/slide-schrift-suche`, Worktree `_claude/entwickler/wt-slide-schrift-suche`, nach 3.9.9, braucht Hub 2.34.4): durchsuchbare Schriftwahl `public/assets/js/sf-font-select.js` (Zuletzt benutzt · Hub-Schriften · Weitere, max. 200 Einträge, Vorschau lazy) im Menüband «Start» und in den Textvorlagen; ASSET_VERSION 737. Offen: `ensureFontsLoaded` (editor.js) lädt alle Familien vor – bei ~1000 Hub-Schriften auf benutzte beschränken.
- **27.11. 3.9.9 gebaut** (`entwickler/slide-geraet-aendern`, Worktree `_claude/entwickler/wt-slide-geraet-aendern`, nach 3.9.8, braucht Hub 2.34.3 `entwickler/hub-geraet-aendern`): Geräte-Kachel mit Stift → Name/Icon ändern (Hub-AppCall umbenennen/icon), Dialoghöhe passt sich an, Meldung «Gerät 456 registriert». Beim Gitwart.
- **27.11. SF 3.9.5 gebaut** (`entwickler/slide-deckbusy`, Worktree `_claude/entwickler/wt-slide-deckbusy`, auf 3.9.4): «Neue Folie» ohne «Bitte warten» — Menüband löste add_slide doppelt aus (ribbon.js-Trigger + editor.js-Dispatcher); Folien-Schritte jetzt in Warteschlange (deckRun/deckUi), deckBegin/deckStep passen dazu.
- **27.11. SF 3.9.4 gebaut** (`entwickler/slide-einklappen`, Worktree `_claude/entwickler/wt-slide-einklappen`, nach 3.9.3): Folien-/Seitenleiste ein-/ausklappbar wie PD (Griff, HubSplit), Stand in `editor_ui.collapsed`. Braucht Hub 2.34.2 (`entwickler/hub-split-einklappen`) zuerst. Presenter nicht umgestellt.
- **27.11. 3.9.3 gebaut** (Zweig `entwickler/slide-unterpunkte` auf 3.9.2, Worktree `_claude/entwickler/wt-slide-unterpunkte`), beim Gitwart, braucht Hub 2.34.1: Unterpunkte der Einstellungen meldet SF selbst an den Hub (`src/SettingsNav.php`, eine Quelle), Hub-Seitenleiste zeigt sie; im Rahmen eigene Reiterleiste aus.

- **27.11. 3.9.2 gebaut** (`entwickler/slide-farbwahl`, nach 3.9.1): Farbwahl überall HubColorPicker mit Hub-Palette (`SfPalette`, `sf-color.js`), eigene Markenfarben-Liste und Schnellwahlen entfernt; Reiter Farben nur Anzeige + Link Hub › Branding. Offen: im Browser ansehen (Menüband-Farbfelder).

- **27.11. 3.9.1 gebaut** (`entwickler/slide-split`, Worktree `_claude/entwickler/wt-slide-split`, nach 3.9.0): Editor-Spalten links/rechts per Hub-Trenner (HubSplit) verstellbar, Breite je Person in `editor_ui.splits`; Notiz-Trenner mit Pfeiltasten; Filmstreifen/Raster behalten Bildlauf beim Neuaufbau. Presenter unverändert (eigene Trenner).
- **27.11. 3.9.0 gebaut** (`entwickler/slide-hub-schriften` auf 3.8.3, Worktree `_claude/entwickler/wt-slide-hub-schriften`): Schriften aus dem Hub (braucht Hub 2.34.0 + FF 1.18.3), Upload-Verwaltung weg, Übergang alte `custom_fonts` (Biel: 1 Schrift, Myriad Pro Regular). Nach Auslieferung und Ordnerwahl durch Urs: `php scripts/schriften-in-hub.php --person=<Urs> [--run]` auf Biel (stösst Entwickler über ha-bkb an).
- **27.11. 3.8.3 gebaut** (`entwickler/slide-einstellungen`, nach 3.8.2): Einstellungen vollständig (Reiter Folienvorlagen/Textvorlagen/Vorlageelemente/Schriftarten/Farben + Admin Allgemein/Rechtschreibung/Medien; Modul-Einstellungen + Startseite «Einstellungen»), Autosave, SMTP raus, `migrate-to-ff.php --settings` (Biel: ha-bkb nach Lieferung). Offen: Farben-Reiter → Hub-Palette (Entscheid), Schriftarten → Hub (Entscheid Urs).
- **27.11. 3.8.2 gebaut** (`entwickler/slide-schrift`, nach 3.8.1): Editor-Bühne zeigte Serif, weil die Übernahme die hochgeladenen Schriften (Biel: Myriad Pro) nicht mitbrachte; `Migration::fonts` holt sie nach — in Biel nach Lieferung und Go: `migrate-to-ff.php --source=/data/slideforge/data --ablage="BK - Office" --run` (holt nur Fehlendes). Bühne fällt bei fehlender Schrift auf sans-serif.
- **27.11. 3.8.1 gebaut** (`entwickler/slide-erstes-bild` auf 3.8.0 d190725, Worktree `_claude/entwickler/wt-slide-erstes-bild`, beim Gitwart nach 3.8.0): kein Sand-Flackern beim Laden — Hub-Look je Person in users.json `hub_look`, serverseitig im ersten Bild (sf_look_pair/sf_theme_after). Offen: einmal je Akzentwechsel im Hub kann es noch flackern; Hub unverändert.

- **27.11. 3.8.0 gebaut** (`entwickler/slide-mitlaufen` auf 3.7.0 ce1894b, Worktree `_claude/entwickler/wt-slide-mitlaufen`, beim Gitwart nach 3.7.0): Ansicht › Vorschau «Auf Gerät» (Mitlaufen: Gerät/Web-Fenster folgt dem Editor über live.php Kanal «editor», tauscht geänderte Folien ohne Neuladen); «Fenster»/«Lokal» weg (Lokal = Zuschauerfenster auf 2. Bildschirm, bleibt im Presenter); «Folien löschen» einmalig in angepasste Menübänder nachgerüstet. ASSET 723.

- **27.11. 3.7.0 gebaut** (`entwickler/slide-undo` auf 3.6.6 551bcbe, beim Gitwart): Rückgängig/Wiederholen für Folien-Aktionen (`restore_slides`, 30 Schritte) + Mehrfachauswahl/Mehrfachlöschen in der Folienleiste, Menüband «Folien löschen». Offen: Umschalt+Pfeil-Auswahl; ungeprüft im Browser.

- **27.11. Geräte 3.6.0–3.6.6** (Zweige `entwickler/slide-geraete-gruppe` → `-dialog` → `slide-geraet-neuladen`, Worktree `_claude/entwickler/wt-slide-geraete-dialog`): Presenter-Gruppe «Geräte» (Registrieren per QR/Code, Geräte inkl. «Web»; 3.6.0 live Biel); 3.6.5 Dialoge nach Lab + Icon-Raster (braucht Hub 2.33.10), 3.6.6 «Neu laden» je Gerät (braucht Hub 2.33.11) — beide beim Gitwart. QR-Leser jsQR lokal `public/assets/js/jsqr-1.4.0.js`. Offen: Feldstile in `sf-ff.css` sind aus Hub admin.css gespiegelt (SF lädt admin.css nicht).
- **27.11. 3.6.4 gebaut** (`entwickler/slide-ff-import`, auf 3.6.3, nach 3.6.3 einspielen): FF-Picker für «Präsentation einfügen», Import, Traktanden, Folien-Set, Logo, Medien-Seitenleiste «Aus Dateien einfügen» (`FfFile::fetchTemp`/`fieldHtml`). Offen: Schriften-Upload (Mehrfachwahl) noch lokal.

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