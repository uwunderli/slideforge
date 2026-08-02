# SlideForge v2.1.17

**Basis:** v2.1.16 · `ASSET_VERSION` 596

## Changes

- Dashboard-Crash im Hub-Embed: `user_menu.php` überschrieb `$shared` (Präsentationen) mit dem Hub-Session-Payload → leere Seite
- FolienSchmiede-Kachel-Icon (Hub): Builtin `slides.svg` statt kaputtem Favicon-Cache

## Deploy

Prod + Demo via Pre-Push auf `main`. Hub-`ModuleIcon.php` parallel auf Prod.
