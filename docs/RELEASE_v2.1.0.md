# SlideForge – Release v2.1.0 (Briefing)

> **Handoff:** *„Lies `docs/RELEASE_v2.1.0.md` und setze die Release um — ausser „Nicht in dieser Release“.“*

Stand: Juli 2026 · Basis: **v2.0.0** (main) · **Integration:** [church.tools](https://church.tools)

---

## Ziel

SlideForge verbindet sich mit **church.tools** und kann **Präsentationsfolien automatisch mit Live-Daten** befüllen — Kalender, Termine, Gruppen, Event-Details. Beim **Start einer Präsentation** (Present / öffentlicher View) werden die Daten **neu geladen**. Admins/Editoren definieren **church.tools-Templates** mit **Platzhaltern**; im Editor wählt man Ansichten, Filter und Layout — ohne Termine manuell abzutippen.

---

## Referenz: church.tools API

- REST-API pro Instanz: `https://{gemeinde}.church.tools/api` (OpenAPI/Swagger)
- Relevante Bereiche (Auszug): **Kalender** (`/calendars`, `/calendars/appointments`), **Events**, **Gruppen**, **Tags**
- Authentifizierung: Session/Login als **church.tools-Benutzer** (API-Token/User — Details bei Implementierung aus OpenAPI der Zielinstanz)
- **Öffentlicher Benutzer:** Wenn in SlideForge **kein** dedizierter User konfiguriert ist → Anbindung über **öffentlichen church.tools-User** (nur Daten, die dieser sehen darf)

> **Hinweis:** Endpunkte und Auth je nach church.tools-Version prüfen (`openapi.json` der Gemeinde-Instanz). Kein Composer — PHP-Client wie `WebdavClient` / `PixabayClient`.

---

## Features (priorisiert)

### Konfiguration & Anbindung

1. **[MUSS] church.tools-Verbindung (Admin, global)** — Basis-URL der Gemeinde, optional **dedizierter API-User** (Login/Token); **Fallback: öffentlicher User**
2. **[MUSS] Verbindung testen** — Button „Verbindung prüfen“ (Kalenderliste abrufen)
3. **[MUSS] Credentials sicher speichern** — Passwort/Token verschlüsselt (`CryptoHelper`, wie WebDAV)

### Daten in Folien

4. **[MUSS] Kalender-Datenquelle** — Folien-Objekt oder Folien-Typ **„church.tools Kalender“** mit Ansichten:
   - **Aktuelle Woche**
   - **Kommende Woche(n)** (konfigurierbar: +1, +2, … Wochen)
   - **Zeitraum** (von/bis)
   - **Einzelner Termin** (Appointment-ID / Auswahl)
5. **[MUSS] Filter** — **Kalender** (multi-select) und **Tags** einschränken
6. **[MUSS] Gruppen anzeigen** — Gruppenliste oder gruppenbezogene Termine (API-abhängig)
7. **[MUSS] Event-Details** — zu Terminen: Titel, Datum/Uhrzeit, Ort, Beschreibung, Ressourcen/Buchungen (soweit API liefert)
8. **[MUSS] Reload bei Präsentation** — beim Öffnen von **Present** / **view.php** (öffentlicher Link) → **frischer API-Abruf**; Editor-Vorschau: manuell „Aktualisieren“ + optional Auto-Refresh

### Templates & Platzhalter

9. **[MUSS] church.tools-Templates** — eigene **Folien-/Layout-Vorlagen** mit Platzhaltern, z. B.:
   - `{{ct.event.title}}`, `{{ct.event.start}}`, `{{ct.event.location}}`
   - `{{ct.appointment.list}}` … (Liste/Wiederholung)
   - `{{ct.group.name}}`, `{{ct.calendar.weekRange}}`
10. **[MUSS] Template-Editor** — Vorlagen anlegen/bearbeiten (Admin oder Editor): Text/Layout + Platzhalter einfügen (Picker)
11. **[SOLL] Wiederholungs-Layouts** — eine Vorlage pro Termin in Liste (Loop über `appointments[]`)

### Editor-UX

12. **[MUSS] Datenquelle konfigurieren** — im Editor: church.tools-Panel (Ansicht, Kalender, Tags, Template wählen)
13. **[SOLL] Live-Vorschau im Editor** — Beispieldaten oder letzter API-Abruf
14. **[SOLL] Platzhalter-Vorschau** — unaufgelöste Platzhalter sichtbar markieren

---

## Feature-Details

### Feature: Konfiguration church.tools

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | **Admin → Integration** (neuer Tab) — **eine globale Konfiguration** für die ganze SlideForge-Instanz |
| **Verhalten** | Felder: **Basis-URL** (`https://gemeinde.church.tools`), **Benutzername** (optional), **Passwort/Token** (optional). Hinweis: *Leer = öffentlicher church.tools-Benutzer.* Button **Verbindung testen**. Alle Editoren nutzen dieselbe Anbindung. |
| **Grenzen** | Eine Gemeinde-URL pro SlideForge-Installation |
| **Technik** | `config.php` / `data/settings.json`; Secrets verschlüsselt; `src/ChurchToolsClient.php` |

---

### Feature: API-Client (PHP)

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Verhalten** | Methoden (Auszug): `testConnection()`, `listCalendars()`, `getAppointments($filters)`, `getAppointment($id)`, `listGroups()`, `getEvent($id)`. Filter: `calendar_ids[]`, `from`, `to`, `tags[]`. Session-Handling / Token-Refresh. |
| **Technik** | cURL, kein Composer; User-Agent `SlideForge/{version}`; Fehler → JSON für Editor |

---

### Feature: Kalender-Ansichten

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Editor — church.tools-Datenquelle pro Folie oder Objekt |
| **Verhalten** | **Ansichtstyp** wählen: `current_week`, `next_week`, `next_n_weeks` (n=2…4), `date_range`, `single_appointment`. Rendering gemäss gewähltem **Template**. |
| **Akzeptanz** | „Aktuelle Woche“ zeigt Mo–So Termine der gefilterten Kalender |

---

### Feature: Filter Kalender & Tags

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Verhalten** | Multi-Select **Kalender** (aus API geladen). Optional **Tags** filtern (API-Feld je nach church.tools-Version). Gespeichert in Folien-Metadaten `churchtools: { calendars: [], tags: [], view: … }`. |

---

### Feature: Gruppen & Event-Details

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Verhalten** | **Gruppen:** Liste oder Einzelgruppe auf Folie (Name, Beschreibung, Mitgliederzahl — API-Felder). **Event:** Combined Appointment/Event inkl. Buchungen/Ressourcen wo verfügbar. |
| **Akzeptanz** | Einzeltermin-Folie zeigt Titel, Zeit, Ort, Info-Text |

---

### Feature: Reload & Export-Snapshot

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | `present.php`, `view.php`, **Offline-HTML/PDF-Export** |
| **Verhalten** | **Present / öffentlicher View:** beim **Start** frischer API-Abruf, Platzhalter ersetzen. **Offline-Export (HTML/PDF):** church.tools-Daten beim Export **einfrieren** (Snapshot mit Zeitstempel im Export, z. B. „Kalenderstand: 04.07.2026 20:15“). Export funktioniert **ohne Internet**. Bei API-Fehler live: Fallback-Text + optional letzter Cache (SOLL). |
| **Technik** | `churchtools.php?action=resolve`; Export-Pipeline ruft Resolver einmal auf und schreibt aufgelöstes HTML in die Export-Datei |

---

### Feature: church.tools-Templates (Platzhalter)

| Feld | Inhalt |
|------|--------|
| **Priorität** | MUSS |
| **Wo** | Admin **Vorlagen** oder `seed/churchtools-templates/` |
| **Verhalten** | Vorlage = Folienlayout (Text, Formen) mit **Platzhalter-Syntax** `{{ct.…}}`. Beispiele: |
| | • **Terminliste:** Loop-Block `{{#each appointments}} … {{/each}}` oder vereinfachte SlideForge-Syntax |
| | • **Einzeltermin:** `{{appointment.title}}`, `{{appointment.startDate}}` |
| | • **Gruppe:** `{{group.name}}` |
| **Editor** | Platzhalter-Picker (Kategorien: Termin, Kalender, Gruppe, Event). |
| **Speicherung** | `data/churchtools_templates/` oder unter `seed/` + Admin-CRUD |

---

## Datenmodell (Entwurf)

```json
{
  "churchtools": {
    "enabled": true,
    "source": {
      "view": "current_week",
      "calendarIds": [1, 3],
      "tags": ["gottesdienst"],
      "weeksAhead": 2,
      "appointmentId": null,
      "groupId": null
    },
    "templateId": "ct_week_list",
    "refreshOnPresent": true
  }
}
```

Folie oder dediziertes **church.tools-Objekt** auf der Folie referenziert diese Config.

---

## Nicht in dieser Release

- Marketing / Bekanntmachen
- **Schreiben** in church.tools (Termine anlegen/ändern) — nur **Lesen**
- church.tools **Personen/Adressbuch** vollständig (nur soweit für Gruppen/Events nötig)
- Multi-Gemeinde (mehrere church.tools-Instanzen pro SlideForge)
- Echtzeit-Sync während laufender Present (nur Reload beim **Start**; manueller Refresh SOLL)
- **Live-** church.tools-Abruf im Offline-Export (dort nur **Snapshot**)

---

## Technische Rahmenbedingungen

- PHP 8.2+, cURL, kein Composer
- Auth: dedizierter User **oder** öffentlicher User (User-Entscheid)
- Rate-Limiting / Timeout (Kalender kann gross sein)
- DSGVO: nur Daten anzeigen, die der konfigurierte User sehen darf
- Mehrsprachigkeit UI: DE / EN / FR / IT / RM

---

## Dokumentation & Demo

- [ ] README — Abschnitt **church.tools-Integration**
- [ ] CHANGELOG.md → `[2.1.0]`
- [ ] `.github/RELEASE_v2.1.0.md`
- [ ] Demo: Folie „Gottesdienst diese Woche“ (Mock oder Demo-church.tools)
- [ ] `docs/CHURCHTOOLS.md` — Setup, Platzhalter-Referenz, API-Hinweise

---

## Release-Checkliste (am Ende)

- [ ] Config + verschlüsselte Credentials + Verbindungstest
- [ ] Kalender-Ansichten, Filter, Gruppen, Event-Details
- [ ] Templates + Platzhalter + Present-Reload + **Export-Snapshot**
- [ ] i18n · PR → main · Tag **`v2.1.0`** · Deploy

---

## Getroffene Entscheidungen

| # | Frage | Entscheidung | Begründung |
|---|--------|--------------|------------|
| 1 | API-User | **Optional**; leer = **öffentlicher User** | User-Vorgabe. |
| 2 | Reload | **Bei Präsentationsstart** neu laden | User: „Bei einer Präsentation werden die Daten neu geladen“. |
| 3 | Templates | **Eigene CT-Templates mit Platzhaltern** | User-Vorgabe. |
| 4 | Version | **v2.1.0** (Integration) | church.tools in v2.x-Reihe, vor Editor-Major v3.0. |
| 5 | Config-Ort | **Global im Admin** | Eine Gemeinde-Instanz, ein API-User — einfacher für Team/Gemeinde; keine pro-User-Secrets. |
| 6 | Offline-Export | **Kalender einfrieren** (Snapshot + Zeitstempel) | Export offline nutzbar; Present bleibt live. |
| 7 | Entwicklung | **Produktive church.tools-URL** | Keine separate Test-Instanz; vorsichtig entwickeln (Read-only, wenig Last). |

---

## Offene Fragen (optional klären)

1. **Platzhalter-Syntax:** Mustache `{{#each}}` vs. einfache `{{feld}}`?

---

## Entwicklung & Betrieb

- **Keine Test-Instanz** — Entwicklung gegen **produktive church.tools-URL** (vom Betreiber freigegeben).
- Empfehlung: dedizierter **Read-only-API-User** oder **öffentlicher User**; keine Schreib-Requests; API-Calls in Dev drosseln/cachen.
- Produktions-URL **nicht** in Git — nur in Admin-Config auf dem Server.

---

## Ursprüngliche Idee (Rohtext)

> Wir arbeiten mit church.tools — Verbindung zum Tool (API vorhanden). Slides sollen automatisch Daten importieren; bei Präsentation **neu laden**:
> - Kalender (Ansichten: aktuelle Woche, kommende Wochen …)
> - Filter Kalender & Tags
> - Einzelne Termine
> - Gruppen
> - Event-Daten auslesen und anzeigen
> - **Eigene church.tools-Templates** mit Platzhaltern
> - Config: church.tools-User; wenn keiner → **öffentlicher User**
