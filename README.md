# SlideForge

[License: MIT](LICENSE)
[PHP](https://www.php.net/)

**SlideForge** is a self-hosted, file-based multi-user editor for [reveal.js](https://revealjs.com) presentations. It runs on any nginx + PHP host with **no database** and no Composer dependency. You get a full canvas editor, presentation mode with live sync, **mobile remote control** (installable as PWA), offline HTML export, templates, and PPTX/ODP/PDF export. Data is stored as JSON files under `data/`, so backup and migration stay simple.

Contributions welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).

**Repository:** [github.com/uwunderli/slideforge](https://github.com/uwunderli/slideforge) · **Release:** [v2.0.4](https://github.com/uwunderli/slideforge/releases/tag/v2.0.4)

**Live demo:** [slideforge.service7.ch](https://slideforge.service7.ch/) *(resets every 12h — credentials on login page)*

**Feature tour** *(no login)* — [EN](https://slideforge.service7.ch/view.php?token=slideforge-tour) · [DE](https://slideforge.service7.ch/view.php?token=slideforge-tour-de) · [FR](https://slideforge.service7.ch/view.php?token=slideforge-tour-fr) · [IT](https://slideforge.service7.ch/view.php?token=slideforge-tour-it) · [RM](https://slideforge.service7.ch/view.php?token=slideforge-tour-rm)

---

Ein selbst-gehosteter, dateibasierter Multiuser-Editor für [reveal.js](https://revealjs.com)
Präsentationen. Läuft auf jedem PHP-Webspace, **keine Datenbank** nötig.

## SlideForge – Funktionen



### Dashboard

- Login und Registrierung (Sessions, `password_hash`, optional „Angemeldet bleiben“)
- Übersicht eigener und geteilter Präsentationen
- Neue Präsentation mit frei wählbarer Foliengrösse (Standard 1920×1080)
- Tabs **Aktiv** und **Archiv** – archivierte Präsentationen bleiben bearbeitbar, verschwinden aus der Hauptliste
- Teilen mit einzelnen Nutzern (**Nur ansehen** / **Bearbeiten**)
- Öffentlicher, login-freier Link pro Präsentation (an-/abschaltbar, Token neu generierbar)
- Kompakte Icon-Aktionen auf den Karten: Bearbeiten, Ansehen, Präsentieren, Export, Teilen, Archivieren, Löschen
- **Import:** HTML/ZIP-Backup, PowerPoint (`.pptx`), PDF (jede Seite als Bild – benötigt Imagick/Ghostscript)
- **Export:** offline-fähiges HTML (Einzeldatei oder ZIP), PowerPoint (`.pptx`), OpenDocument (`.odp`), PDF (über Browser-Druckdialog)
- Alle Daten als JSON-Dateien unter `data/` – keine Datenbank



### Editor

- **Canvas-Editor (Konva.js):** Folien als verschiebbare Tabs (Drag & Drop), Zoomen (+/−/Einpassen), Karo-Hintergrund exakt foliengross
- **Objekte:** Text, Formen (Linie, Rechteck, Dreieck, Ellipse, Pfeil, Stern, Sprechblase, Klammer), Bild, Video, Audio – verschieben, skalieren, drehen, Ebenen-Reihenfolge
- **Eigenschaften-Panel** in Tabs **Format**, **Form**, **Position**, **Effekt** – inkl. Füllfarbe/Verlauf, Rahmen, Schrift, Teilformatierung, Animationen
- **Hintergründe:** Volltonfarbe (Marken-Palette), Farbverlauf, Bild-/Video-Upload – Medien liegen ausserhalb des Web-Roots und werden über `asset.php` ausgeliefert
- **Medien-Tab:** Stock-Medien und eigene Quellen direkt im Editor einbinden
- **Pixabay-Suche** (optional, Admin): lizenzfreie Bilder und Videos
- **Iconify-Icon-Suche:** 150+ SVG-Icon-Sets durchsuchen, Farbe wählbar (Markenpalette), ohne API-Key
- **Openclipart-Clipart-Suche:** SVG-Illustrationen (Public Domain), ohne API-Key
- **WebDAV-Ordner:** im Profil konfigurierte Cloud-/NAS-Laufwerke erscheinen als Buttons — Ordner durchsuchen, Lightbox-Vorschau, Import von Bildern, SVG, Audio und Video auf Folie oder als Hintergrund
- **Hintergrund entfernen** bei Bildobjekten (helle Flächen bei SVG und Fotos)
- **Markdown** in Textobjekten (`**fett**`, Listen, Links usw.) – in Vorschau und Export gerendert
- **Objekt-Animationen** (reveal.js-Fragments) mit Reihenfolge, automatischem Weitergehen und Folienübergängen
- **Vorlagen:** editierbare Markenfarben, Textvorlagen, Folienvorlagen (privat oder für alle freigegeben)
- **Rechtschreibung & Grammatik** über LanguageTool (Admin konfiguriert den Server), optional automatische Prüfung vor dem Präsentieren
- Undo/Redo, Kopieren/Ausschneiden/Einfügen/Duplizieren, Ausrichtungshilfen, Sicherheitsabstand, Pfeiltasten-Verschiebung
- Auto-Speichern, Notizen pro Folie (Markdown)
- Eigene **Ansehen-Ansicht** für Nutzer ohne Bearbeitungsrecht (kein fehleranfälliger Editor-Code)
- Einstellungen für Fortschrittsbalken, Navigationspfeile, Präsentationsdauer, Zeitleiste und Uhr-Anzeigen



### Präsentation

- **Präsentationsmodus** mit grosser aktueller Folie, Vorschau der nächsten Folie, Filmstreifen und Notizen
- **Live-Sync:** Vorschau-Link und öffentlicher Link folgen der Steuerung aus dem Präsentationsmodus (Polling über `live.php`)
- **Laserpointer** mit Farbe, Grösse und Schweif – sichtbar im Hauptfenster und in der Live-Ansicht
- Uhr (Digital/Analog), Timer und farbige Zeitleiste mit konfigurierbaren Stufen
- Touch-taugliche Vor-/Zurück-Steuerung, responsives Layout für Tablets
- Echte **reveal.js**-Ausgabe in Vorschau (`preview.php`), öffentlichem Link (`view.php`) und Export
- Audio-/Video-Wiedergabe: manuell, bei Klick oder automatisch nach Verzögerung; optional Dauerschleife



### Mobile (Smartphone)

- **Reduziertes Dashboard** auf Smartphones (ab v2.0.0): Präsentation auswählen und **Fernsteuern** — der Editor ist auf schmalen Handys gesperrt; Tablets/iPads (≥768 px) behalten die Desktop-Oberfläche
- **`present_remote.php`:** Folien vor/zurück, Folien-Vorschau, Uhr (analog), Timer (Studiouhr), Laser und Fortschrittsbalken am Handy — spiegelt den Desktop-Präsentationsmodus über **HTTPS** (kein gemeinsames WLAN nötig)
- **QR-Code** im Present-Menü („Präsentieren“) und kopierbarer Remote-Link für schnellen Zugang vom Handy
- **Laser vom Handy:** Touch-Fläche steuert den Laserpointer am Beamer/Laptop
- Anzeige **„Handy verbunden“** im Präsentationsmodus, wenn eine Remote-Session aktiv ist
- **PWA (v2.0.2):** Als App auf den Home-Bildschirm installierbar (Android/iOS) — `manifest.php`, Service Worker, Vollbild-Modus (`standalone`); Start auf dem Dashboard



### Admin

- Zwei Rollen: **Administrator** und **Editor**; erster registrierter Nutzer wird automatisch Admin
- Einstellungen in Tabs: **Allgemein**, **SMTP**, **Rechtschreibung**, **Medien**, **Benutzer**
- **Allgemein:** Seitentitel, Logo, offene Registrierung an-/abschaltbar
- **SMTP:** Konfiguration und Test-Mail (reiner PHP-SMTP-Client, kein Composer) – für Einladungs-Mails
- **Rechtschreibung:** LanguageTool-Server (eigene Instanz oder Cloud-API)
- **Medien:** Pixabay-API-Schlüssel (optional); Iconify- und Openclipart-Suche an-/abschaltbar; Vorlagen für Markenfarben und Textstile
- **Benutzer:** Rollen ändern, Nutzer löschen (optional inkl. Präsentationen), **Einladungslinks** mit optionalem E-Mail-Versand
- Mehrsprachige Oberfläche: Deutsch, Englisch, Französisch, Italienisch, Rumantsch
- Hell/Dunkel-Theme pro Benutzer

### Profil

- **WebDAV-Laufwerke:** bis zu 10 HTTPS-WebDAV-Verbindungen pro Benutzer (URL, Benutzername, Passwort verschlüsselt gespeichert), optionaler Startordner — im Editor unter **Medien** als Laufwerks-Buttons verfügbar



## Installation

SlideForge braucht **PHP 8.2+** (ohne Composer). `config.php`, `src/` und `data/` müssen **ausserhalb** des öffentlichen Web-Roots liegen – nur `public_html/` ist per URL erreichbar. Die Ordner `data/` und `public_html/uploads/` müssen für den PHP-Prozess beschreibbar sein.

### Docker-Installation

Für das Image `tangramor/nginx-php8-fpm` gibt es zwei gleichwertige Wege. In **beiden** Fällen wird `/var/www/html` mit dem gesamten Projektordner gemountet (inkl. `config.php`, `src/`, `data/`, `public_html/`).

**Option A – ohne eigenen Config-Mount (einfachster Weg)**

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

**Option B – mit eigener nginx-Konfiguration**

Fertige Datei: `docker/nginx-conf/default.conf` – als einzelne Datei mounten (nicht den ganzen Ordner):

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

Nach dem Start Schreibrechte setzen (User/Group im Container ggf. mit `id` prüfen, meist `nginx` oder `www-data`):

```bash
chown -R nginx:nginx /var/www/html/data /var/www/html/public_html/uploads
chmod -R 770 /var/www/html/data /var/www/html/public_html/uploads
```

Upload-Limits beim Docker-Image optional über `PHP_UPLOAD_MAX_FILESIZE` und `PHP_POST_MAX_SIZE` (Default 100 MB).

### nginx-Webspace

1. Gesamten Ordner auf den Server hochladen, z. B. nach `/var/www/slideforge/`.
2. Schreibrechte für `data/` und `public_html/uploads/`:
  ```bash
   chown -R www-data:www-data /var/www/slideforge/data /var/www/slideforge/public_html/uploads
   chmod -R 770 /var/www/slideforge/data /var/www/slideforge/public_html/uploads
  ```
   (User an eure PHP-FPM-Konfiguration anpassen.)
3. nginx-Serverblock mit `root` auf `public_html`:
  ```nginx
   server {
       listen 80;
       server_name praesentationen.eure-domain.tld;
       root /var/www/slideforge/public_html;
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
4. Aufrufen: `https://praesentationen.eure-domain.tld/register.php`

Kein `.htaccess` nötig – die Absicherung erfolgt dadurch, dass `data/`, `src/` und `config.php` ausserhalb von `root` liegen. Optional enthält `docker/nginx-conf/default.conf` zusätzliche `deny`-Regeln als zweite Sicherheitsebene.

### Installation auf einem Hostingserver

Für klassisches Shared Hosting (cPanel, Plesk, Hostpoint, Metanet usw.), wo ihr nginx/Apache nicht selbst konfiguriert:

1. **PHP-Version prüfen:** mindestens **8.2** im Hosting-Panel aktivieren.
2. **Dateien hochladen** (FTP/SFTP): den **kompletten** Projektordner auf den Server legen, z. B. nach `~/slideforge/` oder `~/domains/example.com/slideforge/`.
3. **Document Root setzen:** im Hosting-Panel den Document Root (Webroot) der Domain/Unterdomain auf den Unterordner `public_html` innerhalb des Projektordners zeigen lassen – **nicht** auf das Projektverzeichnis selbst.
  - Beispiel: Dateien liegen in `/home/user/slideforge/` → Document Root = `/home/user/slideforge/public_html`
  - Liegen `config.php`, `src/` und `data/` damit eine Ebene **über** dem Web-Root, sind Nutzdaten nicht per URL erreichbar.
4. **Schreibrechte:** `data/` und `public_html/uploads/` müssen für den Webserver beschreibbar sein (im Panel oft „Rechte 775“ oder „Schreibbar für Gruppe“; bei Problemen kurz testweise 777, danach wieder einschränken).
5. **PHP-Limits:** `upload_max_filesize` und `post_max_size` mindestens auf die gewünschte Maximalgrösse setzen (Bilder standardmässig 15 MB, Videos 60 MB – änderbar in `config.php`). PDF-Import braucht die PHP-Erweiterung **Imagick** mit Ghostscript.
6. **HTTPS aktivieren** (Let’s Encrypt im Panel) – wichtig für Session-Cookies.
7. **Erster Aufruf:** `https://eure-domain.tld/register.php` – der erste Account wird Administrator (siehe unten).

Falls euer Hoster **keinen** Document Root auf einen Unterordner erlaubt, müsst ihr die Struktur anpassen oder einen VPS/nginx-Webspace verwenden – SlideForge setzt voraus, dass nur `public_html/` öffentlich erreichbar ist.

## Weiteres



### Erster Start

Der **allererste registrierte Nutzer wird automatisch Administrator** – danach sind alle weiteren Registrierungen standardmässig **Editor**. Nach dem Deployment also zuerst selbst unter `register.php` ein Konto anlegen, bevor andere sich registrieren oder die offene Registrierung deaktiviert wird.

Beim ersten Admin werden zudem sieben Standard-Folienvorlagen aus `seed/templates/` angelegt.

### Sicherheitshinweise

- HTTPS im Produktivbetrieb verwenden (Session-Cookies, Passwörter).
- `error_reporting` / `display_errors` in `config.php` für den Live-Betrieb auf `0` stellen.
- Präsentations-IDs sind zufällige Hex-Strings – dienen **nicht** als alleiniger Schutz; Zugriff wird immer über Berechtigungsprüfung gesteuert.
- Öffentliche Links sind immer **nur Ansehen** – Bearbeitungsrechte per öffentlichem Link gibt es bewusst nicht.
- Bild-Uploads: max. 15 MB, Videos: max. 60 MB (in `config.php` über `MAX_IMAGE_SIZE` / `MAX_VIDEO_SIZE` anpassbar).



### KI-Hinweis

Der Quellcode dieses Projekts wurde **vollständig mit Unterstützung durch KI** (Large Language Models) erstellt und weiterentwickelt. Trotz sorgfältiger Prüfung kann **keine Garantie** für Fehlerfreiheit, Vollständigkeit oder Eignung für einen bestimmten Zweck übernommen werden. Bitte testet kritische Funktionen (Authentifizierung, Freigaben, Export, Mailversand) in eurer Umgebung, bevor ihr SlideForge produktiv einsetzt.