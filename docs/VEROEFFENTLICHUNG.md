# SlideForge – Veröffentlichung (Handoff für separaten Dialog)

> **Zweck:** Dieses Dokument bündelt alle Schritte, um SlideForge öffentlich
> bereitzustellen. In einem **neuen Cursor-Chat** kannst du schreiben:
>
> *„Lies `docs/VEROEFFENTLICHUNG.md` und arbeite Phase für Phase ab.“*

Stand: Juli 2026 · Projekt: selbst gehosteter reveal.js-Editor (PHP, dateibasiert, keine DB)

---

## Kurzüberblick für den nächsten Assistenten

| Thema | Aktueller Stand |
|--------|-----------------|
| README | DE + englische Kurzfassung oben, Deployment-Docs vorhanden |
| Lizenz | `LICENSE` (MIT) |
| `.gitignore` | Secrets, `data/`, Uploads, Root-HTML, Logs, OS-Dateien |
| Demo-Instanz | Noch nicht eingerichtet |
| GitHub / Releases | Noch nicht veröffentlicht |
| Secrets | `.deploy/ssh.env` mit Zugangsdaten – **nicht committen** (Beispiel: `ssh.env.example`) |
| Export-HTML im Root | `Medien Test.html` – per `.gitignore` ausgeschlossen |

**Stärken fürs Marketing:** kein Composer, kein Docker-Zwang, Offline-HTML-Export,
Präsentationsmodus, Multiuser, reveal.js-kompatibel.

---

## Offene Entscheidungen (vor Phase 1 klären)

- [x] **Lizenz:** MIT (einfach, maximal offen) ~~oder AGPL-3.0~~
- [ ] **Repo-Name:** `slideforge` / `SlideForge` / anderer Name?
- [x] **Git-Host:** GitHub ~~(empfohlen) oder Codeberg?~~
- [ ] **Demo:** Subdomain ja/nein? Täglicher Reset der Demo-Daten? *(Phase 3)*
- [ ] **Sprache README:** DE beibehalten + englische Kurzfassung oben, oder vollständig zweisprachig?

---

## Phase 1 – Repo vorbereiten (lokal) ✅

Ziel: Ein sauberes, öffentliches Git-Repository ohne Geheimnisse und ohne Nutzerdaten.

- [x] `.gitignore` erweitern, mindestens:
  - `.deploy/ssh.env`
  - `data/` (Präsentationen, User, Cache)
  - `public_html/uploads/` (Logos o. Ä.)
  - `*.html` im Projektroot (Test-Exporte)
  - `.env`, `*.log`, OS-Dateien (`.DS_Store`, `Thumbs.db`)
- [x] Prüfen, ob irgendwo Hardcoded-Secrets, echte Domains oder persönliche Daten stehen
  - Ergebnis: nur `.deploy/ssh.env` (gitignored); Beispiel in `ssh.env.example`
- [x] `seed/` oder Beispiel-Daten für frische Installation prüfen/ergänzen
  - 7 Folienvorlagen unter `seed/templates/`; Anlage beim ersten Admin via `Auth::register()`
- [x] `LICENSE` anlegen (MIT)
- [x] `README.md` ergänzen:
  - [x] Englische Kurzbeschreibung (3–5 Sätze) ganz oben
  - [x] Link zu diesem Dokument entfernt; stattdessen `CONTRIBUTING.md`
  - [x] Badges (Lizenz, PHP-Version)
- [x] Optional: `CONTRIBUTING.md`, `SECURITY.md`
- [x] Optional: `CHANGELOG.md` mit Eintrag **v1.0.0**
- [x] `.deploy/ssh.env.example` als Vorlage ohne Secrets

**Check vor erstem Push:**

```bash
git status
git log --oneline -5
# Keine Dateien unter data/, .deploy/ssh.env, uploads mit echten Inhalten
```

---

## Phase 2 – GitHub (oder Codeberg) anlegen

- [x] Neues **öffentliches** Repository erstellen (ohne README, wenn lokal schon vorhanden)
- [x] Ersten Push durchführen → https://github.com/uwunderli/slideforge
- [x] Repository-Beschreibung gesetzt
- [x] Repository-Topics gesetzt:
  `reveal-js`, `presentation`, `php`, `self-hosted`, `no-database`, `editor`
- [x] Default-Branch `main` bestätigen
- [ ] Branch-Schutz optional später (PRs für Contributions)

**Release v1.0.0:**

- [x] Git-Tag `v1.0.0` setzen und gepusht
- [x] GitHub Release mit Kurztext (`.github/RELEASE_v1.0.0.md`)
- [x] Source-ZIP anhängen (automatisch von GitHub sobald Release existiert)

---

## Phase 3 – Demo & Sichtbarkeit

- [ ] Demo auf Subdomain (bestehendes Deployment nutzen oder separate Instanz) — siehe [docs/DEMO.md](DEMO.md)
- [x] Hinweis auf Demo-Seite: `DEMO_MODE` in `config.php` + Banner (DE/EN/FR)
- [x] Reset-Skript: `scripts/reset-demo-data.sh` (für Cron)
- [ ] **Screenshots** ins Repo (`docs/screenshots/`):
  - Editor
  - Präsentationsmodus
  - Export / Vorschau
- [ ] Optional: 2-Minuten-Screencast (YouTube/Vimeo, Link im README)

---

## Phase 4 – Bekanntmachen (Reihenfolge empfohlen)

1. [ ] **awesome-selfhosted** – PR/Issue: SlideForge eintragen (Kategorie Presentation / Groupware o. Ä.)
2. [ ] **Reddit** `r/selfhosted` – Post mit Demo-Link + GitHub (Regeln lesen)
3. [ ] **Show HN** – nur wenn Demo stabil läuft
4. [ ] **reveal.js**-Community (Discussions/Issues) – als Editor-Ergänzung, nicht als Fork
5. [ ] Optional: Product Hunt, EDU-Foren (DACH), Schul-IT-Kreise

**Textbausteine** (anpassen vor Veröffentlichung):

> SlideForge is a self-hosted, file-based multi-user editor for reveal.js presentations.
> No database, runs on any nginx+PHP host. Offline HTML export, presentation mode with live sync, templates, PPTX export, and more.

---

## Phase 5 – Nach der Veröffentlichung

- [ ] Issues aktiv beantworten (Installation, Feature-Wünsche)
- [ ] Kleine Releases (`v1.0.1`, …) bei Bugfixes
- [ ] README „Known limitations“ pflegen (z. B. PPTX-Import fehlt, Konva-Teilformatierung im Canvas)
- [ ] Optional: GitHub Discussions aktivieren
- [ ] Optional: Roadmap-Issue oder `docs/ROADMAP.md`

---

## Checkliste „Nicht vergessen“

| Datei / Ort | Aktion |
|-------------|--------|
| `.deploy/ssh.env` | Nie committen |
| `data/` | Nie committen (nur Struktur/Seed) |
| `config.php` | Keine produktiven Secrets; Defaults für Open Source ok |
| Export-HTML im Root | Löschen oder in `.gitignore` |
| Admin-Default nach Install | README: ersten User anlegen → Admin |

---

## Nützliche Befehle (Referenz)

```bash
# Repo-Status vor Veröffentlichung
git status
git diff

# Tag für Release
git tag -a v1.0.0 -m "First public release"
git push origin v1.0.0

# Dateien prüfen, die nicht ins Repo sollten
find data -type f 2>/dev/null | head
```

---

## Erfolgskriterien

Veröffentlichung ist „fertig“, wenn:

1. Öffentliches Repo mit Lizenz und bereinigter `.gitignore` existiert
2. Release **v1.0.0** mit Install-Hinweis online ist
3. Mindestens eine Demo oder klare Screenshots vorhanden sind
4. SlideForge an **einem** Discovery-Kanal (z. B. awesome-selfhosted) gelistet ist

---

*Dieses Dokument kann nach Abschluss archiviert oder in kürzere `docs/RELEASE.md` überführt werden.*
