# Prod-Übernahme Folien-Set „Schlicht“ (2026-07-07)

## Ziel-Set

| Feld | Wert |
|------|------|
| **Titel** | Schlicht |
| **Quelle** | `seed/layout-sets/schlicht/` |
| **Weg** | `.deploy/push-layout-set-seed.sh schlicht` |
| **Prod-ID** | `1cb754c60ed55f3b` |
| **Server** | `ftp.bkbiel.ch` (BK Biel Prod) |

## Durchgeführt (2026-07-07, Nachziehen)

1. **Vollständiger Code-Deploy:** `./.deploy/deploy.sh sync-code` — 584 Dateien auf `ftp.bkbiel.ch`
2. **Admin-Rolle wiederhergestellt:** `./.deploy/fix-prod-admin.sh urs` — `role: admin` in `data/users.json`
3. **Set erneut synchronisiert:** `push-layout-set-seed.sh schlicht` → `1cb754c60ed55f3b`

**Hinweis:** Nach Admin-Änderung einmal **abmelden und neu anmelden**, damit die Session die Rolle übernimmt.

## Durchgeführt (erster Lauf)

```bash
./.deploy/push-layout-set-seed.sh schlicht
```

**Ausgabe:**

```
Admin auf dem Server ermitteln …
Suche vorhandenes Set „Schlicht“ …
Aktualisiere vorhandenes Set: 1cb754c60ed55f3b
Fertig: Folien-Set „Schlicht“ auf ftp.bkbiel.ch (1cb754c60ed55f3b), freigegeben für alle.
```

Das Skript setzt automatisch:

- `is_layout_set: true`
- `template_shared: true`
- `default_layout_set: true` (aus Seed-`meta.json`)
- `elementZones`, `logosNotesOrder`, `safe_margin` aus Seed

**Inhalt:** 7 Layout-Folien (Titel, Abschnitt, Überschrift, … — Stand Seed nach `build_schlicht_seed.py`).

## Sichttest (Owner)

1. **Vorlagen → Folienvorlagen → Folien-Sets** — „Schlicht“ sichtbar, Badge „Freigegeben“
2. **Importieren** — Folien-Set „Schlicht“ in der Auswahl
3. Logos-HTML importieren — Layouts aus Schlicht werden angewendet

## Hinweis: „Schlicht II“ / Folie 14

Das Prod-Set ist der **Seed-Stand „Schlicht“** (7 Folien), nicht ein lokal bearbeitetes „Schlicht II“ mit zusätzlichen Layouts.

Eigenes Set auf Prod bringen:

- **Option A:** Im Set-Editor exportieren (`.chs`) → auf Prod unter Vorlagen importieren
- **Option B:** `scripts/export_layout_set_to_seed.php` → Seed pflegen → erneut `push-layout-set-seed.sh`
