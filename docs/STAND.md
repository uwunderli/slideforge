# STAND SlideForge (FolienSchmiede)

- **Erledigt:** Traktandenlisten-Import gebaut (04.11.), ausgeliefert.
- **Offen:** Folienbau mit dem Set «Schlicht - Mitgliederversammlung» und Finanz-PPTX-Einfügung an Urs' Set prüfen (Urs legt das Set an); PDF-Parser mit echter Datei; Auslieferung nach Urs' Go.
- **Nächster Schritt:** Urs probiert `agenda.php` aus, danach Anpassungen und Auslieferung.
- **Pfade:** `src/AgendaImporter.php`, `public_html/agenda.php`, Texte `lang/de.php` (`agenda.*`), Link in `import.php`.

## Live-Stand
- Prod `slides.bkbiel.ch` (bkb-srv10, `/data/slideforge`): Agenda-Import mit Layout-Wahl und Vorschau ausgeliefert 04.11. (`1675929`). Sicherung `.sicherung/agenda2-20261104-155115`.

## Liefer-Weg
Von Hand, siehe `docs/PRUEFUNGEN.md` («So wird in der FolienSchmiede ausgeliefert»). Kein release.sh.

## Zuletzt gemacht
- 04.11.: Agenda-Import (Layout wählbar, Traktanden auf Spalten verteilt, Vorschau-Kacheln). Eingabe docx/md/pdf → Gliederung (Markdown, korrigierbar) → Titelfolie + je Traktandum eine Übersicht mit Hervorhebung (Farbe/fett/beides, über `[color=]`/`**`) + optional Unterpunkt-Folie + Zusatz-PPTX hinter gewähltem Traktandum.

## Fallen
- Importer läuft über eigene Seite `agenda.php` (nicht über den Logos-Dialog); PDF-Upload im Hauptdialog bleibt Bild-Import.
- Neue Folien-Set-Rollen: Titel `document_title`/`subtitle`, Übersicht `heading1` + `normal`.

## Wo mehr steht
`docs/PRUEFUNGEN.md`, `_berichte/slideforge/uebergabe-2026-10-20.md`.
