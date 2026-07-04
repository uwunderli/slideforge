# Demo-Instanz einrichten

Öffentliche Testinstanz für SlideForge (Phase 3).

## 1. Subdomain / Deployment

- Eigene Instanz oder bestehendes Deployment nutzen (nginx `root` → `public_html/`).
- HTTPS empfohlen.

## 2. Demo-Banner aktivieren

In `config.php` auf der Demo-Instanz:

```php
define('DEMO_MODE', true);
```

Der gelbe Hinweis erscheint auf Login, Dashboard, Editor und allen Seiten mit `header.php`.

## 3. Regelmässiger Reset (optional)

```bash
# Alle 12 Stunden (zusätzlich prüft die App bei jedem Request das Intervall)
0 */12 * * * /pfad/zu/slideforge/scripts/reset-demo-data.sh >> /var/log/slideforge-demo-reset.log 2>&1
```

Standardkonten nach jedem Reset:

| Benutzername | E-Mail | Passwort | Rolle |
|--------------|--------|----------|--------|
| admin | admin@service7.ch | admin | Administrator |
| editor | edit@service7.ch | editor | Editor |

Das Skript löscht Benutzer, Präsentationen, Uploads und Cache, legt danach die Standardkonten, **7 Folienvorlagen**, **Textvorlagen**, die **Element-Showcase**- und die **Feature-Tour**-Präsentation neu an. `data/config.json` (Titel, SMTP, …) bleibt erhalten.

**Feature-Touren (Marketing, öffentlich)** — Seed in `seed/feature-tour/{de,en,fr,it,rm}/`, gemeinsame UI-Bilder in `seed/feature-tour/assets/` (inkl. Mobile-Fernsteuerung). Neu generieren: `php scripts/build_feature_tour.php`; Screenshots: `python3 scripts/capture_feature_tour_screenshots.py`.

**PWA:** Auf Smartphones installierbar (Manifest + Service Worker) — „Zum Home-Bildschirm“ / „App installieren“.

| Sprache | Link |
|---------|------|
| EN | https://slideforge.service7.ch/view.php?token=slideforge-tour |
| DE | https://slideforge.service7.ch/view.php?token=slideforge-tour-de |
| FR | https://slideforge.service7.ch/view.php?token=slideforge-tour-fr |
| IT | https://slideforge.service7.ch/view.php?token=slideforge-tour-it |
| RM | https://slideforge.service7.ch/view.php?token=slideforge-tour-rm |

Neu erzeugen: `php scripts/build_feature_tour.php` · Import: `php scripts/import_feature_tour.php`

Manueller Reset nach Deploy (SFTP-only Hosting, kein SSH-Exec):

```bash
./.deploy/deploy-demo.sh reset-demo
```

Ruft `https://slideforge.service7.ch/demo-reset.php` auf (nur aktiv bei `DEMO_MODE=true`).

## 4. Verzeichnisrechte

SlideForge schreibt Laufzeitdaten in:

| Pfad | Inhalt |
|------|--------|
| `data/` | Benutzer, Konfiguration, Cache |
| `data/presentations/` | Präsentationen, Vorlagen, Assets |
| `public_html/uploads/` | Logos, Avatare, Schriftarten |

Der **Webserver-Benutzer** (z. B. `www-data`, `nginx`) muss diese Verzeichnisse lesen und schreiben können. Läuft der Reset per **Cron** im Container oder auf dem Host, braucht auch dieser Prozess Schreibzugriff — sonst bleiben alte Präsentationsordner liegen oder der Reset bricht mit *Permission denied* ab.

### Demo-Instanz

Für eine öffentliche Demo mit einfachem Setup (Web + Cron unterschiedliche User) sind **777** auf den obigen Verzeichnissen und deren Unterordnern ein pragmatischer Kompromiss:

```bash
chmod -R 777 data public_html/uploads
```

Nach einer Rechte-Anpassung einmal `./.deploy/deploy-demo.sh reset-demo` ausführen und prüfen, dass die Ausgabe **ohne PHP-Warnungen** endet.

### Produktiv-Instanz

Dort **kein 777** — besser gemeinsame Gruppe und enge Rechte:

```bash
chown -R www-data:www-data data public_html/uploads
chmod -R 770 data public_html/uploads
```

Cron-Job und PHP-FPM/nginx sollten dieselbe Gruppe nutzen (z. B. beide als `www-data` oder beide in Gruppe `www-data`).

## 5. Empfehlungen für die Demo

- Registrierung in der Demo deaktiviert (`DEMO_MODE` setzt das automatisch)
- In Admin: Seitentitel z. B. „SlideForge Demo“
- Link im README und auf GitHub unter **About** → Website

## 6. README aktualisieren

Sobald die Demo-URL feststeht, in `README.md` eintragen:

```markdown
**Live demo:** https://demo.example.com/
```
