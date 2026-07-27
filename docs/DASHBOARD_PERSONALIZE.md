# Dashboard personalisieren — Briefing (AENDERUNGEN #14)

**Status:** Phase 1 ✔️ · Phase 2 geplant · **AENDERUNGEN:** [B #14b](AENDERUNGEN.md) · Prio **8** · siehe [RELEASES.md](RELEASES.md)

## Problem

Das Dashboard (`index.php`) zeigt Präsentationen in festen Bereichen (Aktiv / Archiv). Nutzer können Bereiche weder benennen, sortieren noch frei zusammenstellen.

## Ziel

Personalisiertes Dashboard:

- **Eigene Bereiche** anlegen (z. B. «Sonntag», «Jugend», «Vorlagen»)
- **Drag & Drop** zum Sortieren von Bereichen und Präsentationen
- **Freigegebene Folien** in Bereiche verschieben (nicht nur «Meine»)
- Bereiche **auf-/zuklappbar**
- **Kachel- und Listenansicht** pro Bereich (wie Raster im Editor)

## Datenmodell (Entwurf)

```sql
-- dashboard_sections: pro User
id, user_id, title, sort_order, collapsed, view_mode ('grid'|'list'), created_at

-- dashboard_section_items: Zuordnung Präsentation → Bereich
id, section_id, presentation_id, sort_order
```

- Standard-Bereiche «Aktiv» / «Archiv» als Seed beim ersten Login migrieren
- Archiv-Flag der Präsentation bleibt; Bereich ist zusätzliche Gruppierung

## UI (Entwurf)

```
┌─ Dashboard ─────────────────────────────────────┐
│ [+ Bereich]  [Ansicht: Kacheln ▾]              │
├─ ▼ Sonntag (3) ─────────────── [≡] [✎] [🗑] ─┤
│  [Kachel] [Kachel] [Kachel]                    │
├─ ▶ Archiv (12) ────────────────────────────────┤
└────────────────────────────────────────────────┘
```

- Drag-Handle am Bereichskopf
- Präsentationen zwischen Bereichen ziehen
- Kontextmenü: Umbenennen, Löschen (nur leere Bereiche)

## API (Entwurf)

| Endpoint | Aktion |
|----------|--------|
| `dashboard.php?action=sections` | Liste Bereiche + Items |
| `POST section_create` | Neuer Bereich |
| `POST section_reorder` | `section_ids[]` |
| `POST item_move` | `presentation_id`, `section_id`, `sort_order` |
| `POST section_prefs` | `collapsed`, `view_mode` |

## Abgrenzung

**Phase 1 (MVP):** ✔️ umgesetzt (2026-07-09)

- Eigene Bereiche, DnD Sortierung, Kachel/Liste pro Bereich
- Nur eigene Präsentationen
- `Dashboard.php`, `dashboard.php`, `dashboard.js`

**Phase 2:**

- Geteilte Präsentationen in Bereiche
- Bereich als Link teilen (read-only)

**Out of Scope:**

- Team-weite Standard-Dashboards
- Widgets (Kalender, RSS)

## Risiken

- Konflikt mit bestehendem «Archiv»-Konzept — klare UX nötig
- Performance bei vielen Präsentationen (Lazy Load pro Bereich)
- Mobile: DnD schwierig → «Verschieben nach…»-Dialog

## Nächste Schritte

1. Entscheid: Bereich vs. Archiv-Flag
2. DB-Migration + Seed
3. Prototyp nur «Meine Präsentationen»
4. DnD mit SortableJS o. ä. (bereits im Editor für Raster)
