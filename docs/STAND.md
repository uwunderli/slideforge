# STAND SlideForge (FolienSchmiede)

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
Von Hand, siehe `docs/PRUEFUNGEN.md` («So wird in der FolienSchmiede ausgeliefert»). Kein release.sh.

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