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

Das Skript löscht Benutzer, Präsentationen, Uploads und Cache. `data/config.json` (Titel, SMTP, …) bleibt erhalten.

## 4. Empfehlungen für die Demo

- Registrierung anlassen oder festen Demo-Account + Einladungslink
- In Admin: Seitentitel z. B. „SlideForge Demo“
- Link im README und auf GitHub unter **About** → Website

## 5. README aktualisieren

Sobald die Demo-URL feststeht, in `README.md` eintragen:

```markdown
**Live demo:** https://demo.example.com/
```
