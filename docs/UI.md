# UI / Design

**Keine modul-eigene Design-Doku.** Alle grafischen Vorgaben liegen nur im Hub und sind **verbindlich**:

- [hub/docs/DESIGN.md](../../hub/docs/DESIGN.md) — kanonischer Text (Abschnitt **Verbindlich** + Details)
- Hub-**Lab** `/lab/` — grafische Abnahme

Bei UI-Änderungen diese Vorgaben einhalten. **Neue Design-Entscheidungen** immer in `hub/docs/DESIGN.md` (und Lab) speichern — nie hier und nie in anderen Modul-Dateien.

In diesem Modul nur Screens und Workflows beschreiben.

## Hub-Primitives (V2.0a)

| Kanal | API | Hinweis |
|--|--|--|
| Float-Dialog | `HubFloatDialog` | Vendor; `sf-float-bridge.js` |
| Confirm / Alert / Prompt | `HubConfirm` | `SFDialog` = dünne Alias (`ui-dialog.js`) |
| Busy | `HubPageBusy` | Formulare `data-hub-page-busy`; Alias `SFBusy` |
| Flash | `CfStatus` | `#cf-status-host` |
| Sicheres Löschen | `HubSecureDelete` | Präsentation löschen (Dashboard) |
| Theme | `cf-theme.js` | Vendor |

Laden: `sf_hub_asset()` in `includes/header.php`. Modul-CSS nur Domäne (Canvas, Ribbon, Present).
