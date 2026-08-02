# SlideForge v2.1.16

**Basis:** v2.1.15 · `ASSET_VERSION` 595

## Changes

- Launcher: sticky `auth_via=local` blockiert auf Hub-Hosts nicht mehr den Programm-Dock
- SharedAuth-Bootstrap zieht Hub-Sessions nach (alte PHP-Sessions)
- Admin-Tag aus Hub-Cookie sieht alle Module im Dock

## Deploy

Prod + Demo via Pre-Push auf `main`. SharedAuth zusätzlich unter `/data/www/shared`.
