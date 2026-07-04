# SlideForge

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)

**SlideForge** is a self-hosted, file-based multi-user editor for [reveal.js](https://revealjs.com) presentations. It runs on any nginx + PHP host with **no database** and no Composer dependency. You get a full canvas editor, presentation mode with live sync, offline HTML export, templates, and PPTX/ODP/PDF export. Data is stored as JSON files under `data/`, so backup and migration stay simple.

Contributions welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).

---

Ein selbst-gehosteter, dateibasierter Multiuser-Editor für [reveal.js](https://revealjs.com)
Präsentationen. Läuft auf jedem nginx+PHP-Webspace, **keine Datenbank** nötig.

## Was bereits funktioniert (Phase 1–5, vollständig)

- Registrierung / Login (Sessions, `password_hash`)
- Dashboard mit eigenen & geteilten Präsentationen
- Präsentation anlegen mit frei wählbarer Fenstergrösse (Standard 1920×1080)
- Teilen mit einzelnen Usern, Rechte **„Nur ansehen“** oder **„Bearbeiten“**
- Öffentlicher, login-freier Link pro Präsentation (an-/abschaltbar, Token neu generierbar)
- Alle Daten liegen als JSON-Dateien unter `data/` – keine DB
- **Vollständiger Canvas-Editor (Konva.js):** Folien als verschiebbare Tabs
  (Drag & Drop zum Umsortieren), Rechtecke/Ellipsen/Text/**Bilder/Videos** auf
  die Folie setzen, per Maus verschieben/skalieren/drehen, Eigenschaften-Panel
  (Füllfarbe **oder Farbverlauf**, Rahmen, Schriftart/-grösse/-schnitt/-ausrichtung,
  Position, Grösse, Deckkraft, Ebenen-Reihenfolge), Auto-Speichern. Bild-Objekte
  werden im Editor live angezeigt; Video-Objekte als Platzhalter-Box (im
  fertigen Foliensatz spielt das Video echt ab).
- **Hintergründe:** Volltonfarbe (inkl. Marken-Farbpalette als Schnellauswahl),
  Farbverlauf (2 Farben + Winkel), Bild-Upload, Video-Upload – Dateien landen
  sicher ausserhalb des Web-Roots in `data/presentations/<id>/assets/` und
  werden über den geschützten Endpoint `asset.php` ausgeliefert (funktioniert
  sowohl im Editor als auch im öffentlichen Link)
- **Objekt-Animationen:** jedes Objekt (Form, Text, Bild, Video) kann einen
  Ein-/Ausblende-Effekt bekommen (Einblenden, Ausblenden, aus einer Richtung,
  Wachsen, Schrumpfen, Durchstreichen) inkl. Reihenfolge-Nummer, die die
  Klick-Reihenfolge auf der Folie bestimmt (reveal.js-Fragments). Im Editor
  zeigt ein kleines nummeriertes Badge an, welche Objekte animiert sind; die
  Animation selbst ist in **Vorschau** und öffentlichem Link sichtbar.
- Folienübergang (fade/slide/convex/concave/zoom) pro Folie
- Live-Vorschau (angemeldet: `preview.php`, öffentlich: `view.php`) als echtes reveal.js
- **Benutzerverwaltung & Rollen:** eigenes Profil bearbeiten (`profile.php`:
  Benutzername/E-Mail/Passwort), zwei Rollen (Administrator/Editor), Admin-Bereich
  (`admin_settings.php`) für Seitentitel/Logo, SMTP-Konfiguration mit Test-Mail-Versand
  (reiner PHP-SMTP-Client, kein Composer nötig), Registrierung an-/abschaltbar,
  Einladungslinks als Alternative zur offenen Registrierung, Benutzerverwaltung
  (`admin_users.php`: Rolle ändern, User löschen, optional inkl. dessen Präsentationen)
- **Hell/Dunkel-Theme** pro Benutzer (Umschalter oben rechts, wird in `users.json` gespeichert)
- **Editor-Komfort:** echtes Zoomen der Folie (+/−/Einpassen), Karo-Hintergrund nur noch exakt
  folien-gross statt den ganzen Canvas-Bereich zu füllen, Objekte links in Tabs **Texte** (Text,
  Titel-/Untertitel-Vorlagen), **Objekte** (Rechteck, Ellipse, Dreieck, Pfeil dünn/dick, Stern,
  Sprechblase, Wellenbanner, Bild, Video) und **Hintergrund** (Farbe/Verlauf/Bild/Video/Übergang)
  gruppiert, linke Spalte mit Icons statt Textbeschriftung. Die neuen Formen werden im exportierten
  Foliensatz als SVG gerendert (inkl. Verlauf und Rahmen) und sehen dort identisch zum Editor aus.
  Rechtes Eigenschaften-Panel in Tabs **Format** (nur Text: Schrift, Farbe, Ausrichtung),
  **Form** (Formen/Bild/Video: Füllfarbe/Verlauf inkl. Marken-Farbpalette, Rahmen, Deckkraft),
  **Position** (X/Y/Breite/Höhe mit sperrbarem Seitenverhältnis, Drehung, Ausrichtung an der
  Folienkante, Ebenen-Reihenfolge) und **Effekt** (Animationen).

- **Markdown in Textobjekten:** `**fett**`, `*kursiv*`, `` `Code` ``, `[Link](https://...)`,
  Listen (`- Punkt`) werden in Vorschau und Export korrekt gerendert (eigener, bewusst simpler
  und XSS-sicherer Konverter in `src/Markdown.php`). Im Editor-Canvas selbst wird der rohe
  Markdown-Text angezeigt, da Konva.js (die Canvas-Bibliothek des Editors) keine gemischte
  Textformatierung innerhalb eines Textfelds unterstützt.

- **Vorlagen** (neuer Menüpunkt oben rechts, `templates.php`):
  - **Farbvorlagen:** die Marken-Farbpalette ist jetzt vollständig editierbar (Name + Hex,
    hinzufügen/ändern/löschen) – standardmässig mit euren bisherigen Farben vorbelegt.
    Bearbeitbar nur durch Administratoren, für alle sichtbar/nutzbar im Editor.
  - **Textvorlagen:** frei definierbare benannte Textstile (Schriftart, Grösse, Fett, Farbe,
    Ausrichtung, Standardgrösse), erscheinen im Editor-Tab „Texte“ als Schnellauswahl-Buttons
    (ersetzt die früher fest einprogrammierten „Titel“/„Untertitel“-Buttons). Ebenfalls nur
    durch Administratoren editierbar.
  - **Folienvorlagen:** eine komplette Folie (Hintergrund + alle Objekte) als Vorlage speichern.
    Bearbeitung im selben Editor, aber auf eine einzelne Folie beschränkt (kein Hinzufügen
    weiterer Folien). Jede Person kann eigene Folienvorlagen erstellen und wählt pro Vorlage,
    ob sie **privat** bleibt oder **für alle freigegeben** wird. Im normalen Editor gibt es
    einen „Vorlage anwenden“-Button, der die aktuelle Folie mit einer gewählten Vorlage
    überschreibt (Hintergrund + Objekte).

- **Erweiterte Textformate:** Fett, Kursiv, Unterstrichen, Durchgestrichen live im Editor;
  GROSSBUCHSTABEN und Kapitälchen wirken in Vorschau/Export (technische Grenze von Konva.js
  im Editor-Canvas selbst nicht sichtbar). Auch bei den Textvorlagen verfügbar.
- **Fortschrittsbalken** pro Präsentation an-/abschaltbar (im „Foliengrösse"-Dialog).
- **Automatisches Weiterschalten** einer Folie nach n Sekunden (0 = aus), einstellbar im
  Hintergrund-Tab neben dem Folienübergang.

- **Export/Import:** Präsentationen als eigenständige, offline-fähige Datei exportieren
  (`export.php`) – wahlweise als **Einzeldatei** (HTML, reveal.js + alle Bilder/Videos als
  Base64 eingebettet, läuft ohne Internet und ohne Server in jedem Browser) oder als **ZIP**
  (HTML + Medien als separate Dateien, kompakter bei vielen Medien). Beide Formate lassen sich
  über **Importieren** auf dem Dashboard (`import.php`) wieder 1:1 als neue, bearbeitbare
  Präsentation einlesen (ideal als Backup) – die kompletten Folien-Rohdaten stecken dafür
  unsichtbar in der Export-Datei.
- **Archiv:** eigene Präsentationen lassen sich archivieren (eigener Tab „Archiv“ auf dem
  Dashboard neben „Aktiv“), bleiben dabei voll bearbeitbar, verschwinden aber aus der
  Hauptübersicht.

- **Navigationspfeile** zusätzlich zum Fortschrittsbalken an-/abschaltbar (im „Foliengrösse"-Dialog).
- **Objekte aneinander ausrichten:** beim Ziehen schnappt ein Objekt ein, sobald es auf gleicher
  Höhe/Breite wie ein anderes Objekt (oder der Folienrand/-mitte) liegt, mit gestrichelter
  Hilfslinie zur Orientierung (wie in Figma/PowerPoint).
- **Automatisches Weitergehen bei Animationen:** zusätzlich zum Klick-basierten Ablauf kann pro
  Objekt eine Zeit in Millisekunden angegeben werden, nach der reveal.js selbstständig zum
  nächsten Animationsschritt springt.
- Dashboard-Kartenaktionen (Export/Teilen/Archivieren/Löschen) sind jetzt kompakte Icon-Buttons
  statt Text, damit sie auf der Karte nicht mehr abgeschnitten werden.
- Bugfix: Editor-Canvas und tatsächliche Browser-Ansicht (Vorschau/öffentlicher Link/Export)
  stimmen jetzt exakt in der Grösse überein (reveal.js' Standard-Rand wurde auf 0 gesetzt).
- Bugfix: eine freigegebene Folienvorlage liess sich nicht mehr zurück auf „Privat“ stellen.

- **Präsentationsmodus** (`present.php`, Link im Editor neben „Vorschau"): grosses
  Hauptfenster mit der aktuellen Folie, kleine Vorschau der nächsten Folie daneben,
  touch-taugliche Vor-/Zurück-Buttons, Notizen-Feld (Markdown, wird im Editor unter
  dem Canvas gepflegt) und ein Filmstreifen aller Folien ganz unten zum direkten
  Anspringen. Der **öffentliche Link** und der **angemeldete Vorschau-Link** folgen
  automatisch der Steuerung aus dem Präsentationsmodus (Polling alle 1,5 Sek. über
  `live.php`), sobald dort aktiv navigiert wird – ohne aktive Sitzung funktionieren
  beide Links weiterhin ganz normal eigenständig. Der Vorschau-Link lässt sich über
  das ⧉-Symbol wahlweise auch in einem echten separaten Fenster statt einem Tab öffnen.

- **Medien-Tab im Editor:** eigener, vierter Tab links (jetzt vertikal als Register angeordnet,
  linke Spalte etwas breiter) für Bild, Audio und Video. Audio ist neu: Objekte lassen sich
  hochladen (MP3/WAV/OGG/M4A) und wie Video auf die Folie platzieren.
- **Wiedergabe-Steuerung für Audio/Video-Objekte:** Manuell (Steuerelemente), bei Klick, oder
  automatisch nach einer Verzögerung in ms (sobald die Folie erscheint) – wirkt in Vorschau,
  öffentlichem Link, Export und Präsentationsmodus.
- **Teilformatierung von Text:** im Format-Tab markierten Text gezielt formatieren (fett, kursiv,
  unterstrichen, durchgestrichen, Grossbuchstaben, Kapitälchen, Textmarker mit Farbwahl,
  Textfarbe mit Farbwahl inkl. Markenfarben) statt nur den ganzen Textblock einheitlich.
- Wellenbanner aus der Formen-Auswahl entfernt (bereits bestehende Wellenbanner-Objekte in alten
  Präsentationen werden weiterhin korrekt dargestellt).
- Vorlagen und Hell/Dunkel-Umschalter sind jetzt im Profil-Menü statt einzelner Buttons in der
  Kopfzeile; stattdessen zeigt die Kopfzeile eine kleine Uhr (Digital/Analog per Klick, wie im
  Präsentationsmodus).
- Präsentationsmodus für Tablets/kleinere Fenster deutlich kompakter (kleinere Vorschau der
  nächsten Folie, Scroll-Fallback, zusätzliche Zwischenstufe im responsiven Layout).

- **Formen-Bibliothek überarbeitet:** neue Reihenfolge (Linie, Rechteck, Dreieck, Ellipse,
  Klammer, Pfeil, Stern, Sprechblase). Linie neu (offene, ungefüllte Form). Pfeil-Varianten
  (rechts/links/oben/unten/doppelt) und Klammer-Varianten (eckig/rund/geschweift, links/rechts)
  sind jetzt EIN Werkzeug mit wählbarem Stil statt mehrerer Buttons. Stern hat eine einstellbare
  Zackenzahl (3-20). Sprechblase hat wählbare Varianten (eckig links/rechts, oval). „Pfeil dick"
  wurde entfernt (im neuen „Pfeil"-Werkzeug über den Stil abgedeckt). Bestehende Präsentationen
  mit alten Pfeil-/Wellenbanner-Objekten werden weiterhin korrekt dargestellt.

- **Mehrsprachigkeit komplett:** Deutsch/Englisch/Französisch (`src/I18n.php`, `lang/`) jetzt
  auf der gesamten Seite, inklusive Admin-Einstellungen.
- **Ansehen-Rechte im Editor überarbeitet:** Nutzer mit Nur-Ansehen-Zugriff sahen bisher eine
  kaputte, fast leere Editoroberfläche (der komplexe Editor-JS-Code griff auf Elemente zu, die
  für Ansehen-Rechte gar nicht gerendert wurden, und brach mit einem Fehler ab, bevor die Folie
  überhaupt angezeigt wurde). Jetzt gibt es für Ansehen-Rechte eine eigene, einfache Ansicht:
  Folie gross und zentriert, Notizen-Panel daneben, Folien-Reiter zum Wechseln oben – ganz ohne
  den fehleranfälligen Bearbeitungs-Code.
- **Einladungs-Mail per SMTP:** Beim Erstellen einer Einladung mit E-Mail-Adresse wird jetzt
  tatsächlich eine Mail mit dem Einladungslink verschickt (vorher wurde die Adresse nur als
  Notiz gespeichert, nie versendet).

- **Undo/Redo:** Buttons oben in der Toolbar (Ctrl+Z / Ctrl+Y bzw. Ctrl+Shift+Z), History pro
  Folie, geht bis zu 60 Schritte zurück. Merkt sich "abgeschlossene" Änderungen (nach dem
  700ms-Speicher-Debounce), nicht jeden einzelnen Zwischenschritt eines Drags.
- **Kopieren/Ausschneiden/Einfügen/Duplizieren** für Objekte: Buttons im Eigenschaften-Panel
  sowie Tastenkürzel Ctrl+C/X/V/D. Eingefügte/duplizierte Objekte werden leicht versetzt platziert.
- **Textvorlagen-Stil nachträglich anwenden:** im Format-Tab eines bereits platzierten Textfelds
  erscheinen jetzt Buttons für alle Textvorlagen – wendet Schriftart/-grösse/-farbe/Formatierung
  der Vorlage auf das bestehende Textfeld an, ohne Inhalt/Position/Grösse zu verändern.
- **Dauerschleife für Audio/Video:** neue Checkbox im Format-Tab, startet die Wiedergabe
  automatisch neu, sobald sie durchgelaufen ist.
- **Präsentationsmodus-Layout:** vier Spalten (Folie doppelt so breit wie Notizen/Steuerung
  einzeln, dazu die Zeitleiste) statt der bisherigen, bei kleineren Fenstern zu engen Aufteilung.

- **PowerPoint-Export (.pptx):** Neu unter „Exportieren". Baut die OOXML-Struktur von Hand
  zusammen (kein Composer/externe Bibliothek). Öffnet in PowerPoint und LibreOffice Impress.
  Bewusste Einschränkungen: keine Animationen/Übergänge, Video/Audio als beschrifteter
  Platzhalter statt funktionierendem Medium, Teilformatierung innerhalb eines Textfelds
  (Markdown-Spans wie `[color=...]`) geht verloren - exportiert wird die Objekt-Formatierung.
  Formen (Stern/Pfeil/Klammer/Sprechblase) werden auf die nächstliegenden PowerPoint-eigenen
  Formen gemappt (z.B. runde Klammer → eckige Klammer, da PowerPoint keine passende Form hat).
- **PDF-Export:** neue Druckansicht (`pdf_export.php`) über reveal.js' eingebauten
  Print-PDF-Modus (alle Fragmente sofort sichtbar, keine Animationen) - Nutzer speichert per
  Browser-Druckdialog (Strg+P) selbst als PDF, kein serverseitiges PDF-Rendering nötig.
- **PPTX-Import:** noch nicht umgesetzt, siehe Hinweis im Chat - deutlich komplexer als der
  Export (beliebige PowerPoint-Dateien aus der freien Wildbahn sind sehr unterschiedlich
  aufgebaut), als eigenes, separates Vorhaben zurückgestellt.

- **PDF-Import:** neu unter „Importieren" – jede PDF-Seite wird als Bild in eine eigene Folie
  umgewandelt (Text/Formen aus dem PDF sind danach nicht mehr einzeln editierbar, nur das
  Bild als Ganzes). Braucht die PHP-Erweiterung Imagick mit Ghostscript auf dem Server -
  fehlt die, erscheint eine klare Fehlermeldung statt eines stillen Absturzes. Das ist die
  einzige Stelle im Projekt, die von einer Server-Erweiterung statt reinem PHP abhängt.
- **Folien-Eigenschaften-Dialog als Akkordeon:** drei aufklappbare Gruppen (Einstellungen /
  Präsentation / Navigation), gleiches Verhalten wie beim Textformatierungs-Panel. Die
  Akkordeon-Umschaltung ist jetzt sauber pro Container gescoped (mehrere Akkordeons auf der
  Seite beeinflussen sich nicht mehr gegenseitig).

- **PPTX-Import:** neu unter „Importieren". Deckt die häufigsten Fälle ab (Text, Rechteck/
  Ellipse/Formen, Bilder, Linien, gruppierte Objekte inkl. korrekter Koordinatentransformation)
  - Tabellen/Diagramme/SmartArt werden übersprungen, Theme-Farben auf eine Standardpalette
  abgebildet, nur die Formatierung des ersten Text-Runs übernommen. Bricht bei Problemen mit
  einzelnen Folien/Objekten NICHT komplett ab, sondern sammelt Warnungen und zeigt sie nach
  dem Import im Editor an (Session-Flash-Mechanismus).
- **ODP-Export** (natives LibreOffice-/OpenOffice-Impress-Format) unter „Exportieren" neu
  dazu, analog zum PPTX-Export von Hand als OpenDocument-XML gebaut. Etwas grössere
  Einschränkungen als PPTX: eigene Formen (Stern/Pfeil/Klammer/Sprechblase) werden nur als
  Rechteck angenähert, da ODF kein so reichhaltiges Formen-Set wie PowerPoint hat.
- **ODP-Import:** noch nicht umgesetzt - wäre ein dritter, eigenständiger XML-Parser (anderes
  Schema als OOXML), als separates Vorhaben zurückgestellt.

- **Präsentationsmodus:** Uhr/Timer/Navigation sitzen jetzt an der unteren Kante der
  Steuerungs-Spalte (statt direkt unter "Nächste Folie", was bei ausgeblendeter
  Nächste-Folie-Vorschau zu viel Leerraum liess), Zeitleisten-Balken etwas schmaler.
- **Sicherheitsabstand im Editor:** einstellbar in Folien Eigenschaften → Einstellungen
  (Standard 100px), wird als feine gestrichelte Linie im Editor angezeigt, Objekte richten
  sich beim Verschieben automatisch daran aus (wie bei den bestehenden Ausrichtungshilfen).
- **Pfeiltasten-Verschiebung:** ausgewähltes Objekt lässt sich mit den Pfeiltasten bewegen
  (1px pro Druck, mit Shift 10px).

- **"Angemeldet bleiben" beim Login:** neue Checkbox, verlängert das Session-Cookie auf
  30 Tage statt es beim Schliessen des Browsers ablaufen zu lassen (`extend_session_cookie()`
  in `config.php`). Kein separates Token-System, nutzt einfach eine längere Cookie-Lebensdauer
  für dieselbe Session. Ehrlicher Hinweis: hängt von den Session-Garbage-Collection-
  Einstellungen des Servers ab - auf den meisten Standard-PHP-Setups funktioniert das
  zuverlässig, bei sehr aggressiven Server-weiten Session-Aufräum-Einstellungen theoretisch
  nicht hundertprozentig garantiert.

- **"Keine" Füllfarbe für Formen:** dritte Option neben Voll/Verlauf bei Rechteck, Ellipse
  und allen Formen (Dreieck/Stern/Pfeil/Sprechblase) – nur der Rahmen ist sichtbar, keine
  Fläche. Konsistent umgesetzt in Editor-Canvas, Export (HTML/ZIP/PDF), PPTX- und
  ODP-Export sowie beim PPTX-Import (statt einer erfundenen Ersatzfarbe).

- **Standard-Textvorlagen und -Folienvorlagen angepasst** (nutzerdefiniert): Textvorlagen in
  `config.php` durch die neue 6er-Liste ersetzt (Titel, Untertitel, Überschrift 1-3, Text).
  Sieben öffentlich freigegebene Folienvorlagen liegen jetzt als Seed-Daten unter
  `seed/templates/<id>/` (meta.json + slides.json) und werden automatisch angelegt, sobald der
  allererste Benutzer (= Admin) registriert wird (`Presentation::seedDefaultTemplates()`,
  aufgerufen aus `Auth::register()`). Private Vorlagen wurden bewusst nicht übernommen.
  „T Text“/„Markdown-Text“-Schnellerstellung im Editor nutzt jetzt dieselbe Formatierung wie
  die „Text“-Textvorlage (Open Sans, 65px) statt der alten, kleineren Standardwerte.

Damit sind alle ursprünglich geplanten Phasen umgesetzt.

## Verzeichnisstruktur

```
revealeditor/
├── config.php              ← zentrale Konfiguration (NICHT im Web-Root!)
├── src/                     ← PHP-Klassen (NICHT im Web-Root!)
│   ├── Storage.php           JSON lesen/schreiben mit Locking
│   ├── Auth.php               Login/Registrierung
│   ├── Presentation.php      Präsentationen, Rechte (ACL), Public-Links
│   └── SlideRenderer.php     slides.json → reveal.js HTML
├── data/                    ← alle Nutzdaten (NICHT im Web-Root!)
│   ├── users.json
│   ├── config.json           Seitentitel, Logo-Dateiname, SMTP, Registrierung an/aus
│   ├── invites.json          Einladungs-Tokens
│   └── presentations/<id>/
│       ├── meta.json          Titel, Owner, Breite/Höhe
│       ├── acl.json           Freigaben + öffentlicher Link
│       ├── slides.json        Folieninhalte
│       └── assets/            hochgeladene Bilder/Videos (später)
└── public_html/             ← DAS ist der nginx document root
    ├── index.php              Dashboard
    ├── login.php / register.php / logout.php
    ├── profile.php            eigenes Profil (Username/E-Mail/Passwort)
    ├── admin_settings.php     Admin: Titel/Logo/SMTP/Registrierung/Einladungen
    ├── admin_users.php        Admin: Benutzerverwaltung
    ├── admin_api.php          Admin: Test-Mail-Versand (AJAX)
    ├── theme_toggle.php       Hell/Dunkel umschalten
    ├── editor.php             Editor (Phase 1: Vorschau + Grösse)
    ├── presentation_share.php Rechteverwaltung
    ├── view.php               öffentliche Ansicht (?token=...)
    ├── uploads/               öffentlich (Logo-Datei, NICHT geschützt)
    ├── includes/              Header/Footer
    └── assets/css/style.css
```

**Wichtig:** `config.php`, `src/` und `data/` liegen bewusst **ausserhalb** von
`public_html`. Nur `public_html/` darf per nginx `root` erreichbar sein. Damit sind
eure Nutzdaten (Präsentationsinhalte, Passwort-Hashes) grundsätzlich nicht per URL
abrufbar – auch ohne zusätzliche `deny`-Regeln.

## Deployment mit dem Docker-Image `tangramor/nginx-php8-fpm`

Für dieses Image gibt es zwei gleichwertige Wege. Wichtig in **beiden** Fällen:
Es wird weiterhin nur `www` (also `/var/www/html`) mit dem gesamten
`revealeditor/`-Inhalt (also inkl. `config.php`, `src/`, `data/`, `public_html/`)
gemountet – die Ordnerstruktur bleibt exakt wie oben beschrieben.

### Option A – ohne eigenen Config-Mount (einfachster Weg)

Das Image unterstützt bereits eine Env-Variable, um den Web-Root auf einen
Unterordner zu legen:

```yaml
services:
  slideforge:
    image: tangramor/nginx-php8-fpm
    environment:
      WEBROOT: /var/www/html/public_html
    volumes:
      - /mnt/user/appdata/nginxphp8-slides/www:/var/www/html
    ports:
      - "8080:80"
```

Kein Config-Mount nötig – `start.sh` im Container setzt beim Start selbst den
nginx `root` auf `public_html/`.

### Option B – mit eigenem nginx-Config-Mount

Falls du volle Kontrolle über die nginx-Konfiguration willst (z.B. für eigene
`deny`-Regeln, Upload-Limits, Gzip etc.): im ZIP liegt fertig
`docker/nginx-conf/default.conf`. Diese Datei **als einzelne Datei** (nicht den
ganzen Ordner) mounten, damit die übrigen Standard-Configs des Images
(SSL-Vorlage etc.) erhalten bleiben:

```yaml
services:
  slideforge:
    image: tangramor/nginx-php8-fpm
    volumes:
      - /mnt/user/appdata/nginxphp8-slides/www:/var/www/html
      - /mnt/user/appdata/nginxphp8-slides/nginx-conf/default.conf:/etc/nginx/conf.d/default.conf:ro
    ports:
      - "8080:80"
```

Dazu die Datei `docker/nginx-conf/default.conf` aus dem ZIP nach
`/mnt/user/appdata/nginxphp8-slides/nginx-conf/default.conf` auf den Host legen.
Sie setzt `root` explizit auf `/var/www/html/public_html` und enthält zusätzlich
`deny`-Regeln für `config.php`, `src/` und `data/` als zweite Sicherheitsebene.

Nach dem Start: Container-Konsole öffnen und Schreibrechte für `data/` setzen
(User/Group des Containers ggf. mit `id` im Container prüfen, meist `nginx`
oder `www-data`):
```bash
chown -R nginx:nginx /var/www/html/data /var/www/html/public_html/uploads
chmod -R 770 /var/www/html/data /var/www/html/public_html/uploads
```

## Deployment auf klassischem nginx-Webspace (ohne Docker)

1. Gesamten Ordner `revealeditor/` auf den Server hochladen, z.B. nach
   `/var/www/revealeditor/`.
2. `data/` und alle Unterordner müssen für den PHP-Prozess **beschreibbar** sein:
   ```bash
   chown -R www-data:www-data /var/www/revealeditor/data /var/www/revealeditor/public_html/uploads
   chmod -R 770 /var/www/revealeditor/data /var/www/revealeditor/public_html/uploads
   ```
   (User ggf. an eure PHP-FPM-Konfiguration anpassen.)
3. nginx-Serverblock, `root` zeigt auf `public_html`:

   ```nginx
   server {
       listen 80;
       server_name praesentationen.eure-domain.tld;
       root /var/www/revealeditor/public_html;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           include fastcgi_params;
           fastcgi_pass unix:/run/php/php8.2-fpm.sock;  # ggf. anpassen
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }

       location ~ /\.(?!well-known) {
           deny all;
       }
   }
   ```
4. Aufrufen, registrieren, loslegen: `https://praesentationen.eure-domain.tld/register.php`

Kein `.htaccess` nötig (nginx nutzt das nicht) – die Absicherung passiert allein
dadurch, dass `data/`, `src/` und `config.php` ausserhalb von `root` liegen.

## Sicherheitshinweise

- Setzt unbedingt HTTPS ein (Session-Cookies, Passwörter).
- `error_reporting`/`display_errors` in `config.php` für den Produktivbetrieb auf
  `0` stellen.
- IDs von Präsentationen sind zufällige Hex-Strings (16 Zeichen) – praktisch nicht
  erratbar, dienen aber **nicht** als alleiniger Schutz: Zugriff wird zusätzlich
  immer über `Presentation::checkPermission()` geprüft.
- Öffentliche Links sind aktuell immer **„nur ansehen“** – es gibt bewusst keine
  Möglichkeit, per öffentlichem Link Bearbeitungsrechte zu vergeben.

## Hinweis zu Datei-Uploads (Hintergrund-Bilder/Videos)

Bilder sind auf 15 MB, Videos auf 60 MB begrenzt (in `config.php` änderbar über
`MAX_IMAGE_SIZE` / `MAX_VIDEO_SIZE`). Damit grosse Uploads nicht am PHP-Server
scheitern, sollten `upload_max_filesize` und `post_max_size` in der PHP-Konfiguration
mindestens so hoch sein. Beim `tangramor/nginx-php8-fpm`-Image lässt sich das über
die Env-Variablen `PHP_UPLOAD_MAX_FILESIZE` und `PHP_POST_MAX_SIZE` setzen (Default
liegt dort bereits bei 100 MB, also i.d.R. kein Handlungsbedarf).

## Corporate Design

Farben und Schriften aus `Farbvariationen.pdf`/`.svg` sind bereits eingearbeitet:

- **Akzentfarben:** Brilliant Blau `#3A6C8D` (Owner/Bearbeiten), Blümchen Grün `#87B42B`
  (Ansehen). Die restliche Palette (`#D1B633`, `#85612D`, `#94C2DC`, `#6A8AA1`,
  `#D67D5D`, `#862B6E`, `#917158`, `#61A8E0`, `#97C764`, `#252E1B`, `#64180B`,
  `#B7DE45`, `#4449A5`) liegt als CSS-Variablen (`--brand-*`) in `style.css` bereit
  für die Hintergrund-Presets in Phase 3.
- **Schriften:** Open Sans (Überschriften, Bold/Ultra-Bold) + PT Sans als freier
  Ersatz für Myriad Pro (Fliesstext) – Myriad Pro selbst ist eine kostenpflichtige
  Adobe-Schrift und lässt sich nur mit gültiger Lizenz per Adobe Fonts einbetten.
  Bei Bedarf einfach `--font-body` in `style.css` anpassen.

## Roadmap – Status

- ~~**Phase 2 – Canvas-Editor**~~ ✅ erledigt
- ~~**Phase 3 – Hintergründe**~~ ✅ erledigt: Farbverlauf, Bild-/Video-Upload, Marken-Palette
- ~~**Phase 4 – Objekt-Animationen**~~ ✅ erledigt: Fragment-Animationen mit Reihenfolge, ausserdem als Bonus Bild-/Video-**Objekte** und Farbverlauf bei Formen
- ~~**Phase 5 – Benutzerverwaltung**~~ ✅ erledigt: Profil, Rollen, Admin-Einstellungen (Logo/Titel/SMTP/Registrierung/Einladungen/User), Hell/Dunkel-Theme

## Wichtig beim allerersten Start

Der **allererste registrierte User wird automatisch Administrator** (sonst
käme niemand an die Einstellungen). Alle danach registrierten User werden
standardmässig **Editor**. Also: nach dem Deployment als erstes selbst unter
`register.php` ein Konto anlegen, bevor andere sich registrieren oder die
Registrierung deaktiviert wird.

Mögliche weitere Ideen für später (nicht ursprünglich angefragt): Text-Objekte
mit Bewegungspfaden statt nur Ein-/Ausblenden, PDF-Export, Versionshistorie,
Kommentarfunktion.
