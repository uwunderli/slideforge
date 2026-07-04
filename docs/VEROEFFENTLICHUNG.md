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
| Demo-Instanz | [slideforge.service7.ch](https://slideforge.service7.ch/) — Deploy: `.deploy/deploy-demo.sh` |
| GitHub / Releases | [github.com/uwunderli/slideforge](https://github.com/uwunderli/slideforge) · aktuell **v2.0.2** |
| Secrets | `.deploy/ssh.env` mit Zugangsdaten – **nicht committen** (Beispiel: `ssh.env.example`) |
| Export-HTML im Root | `Medien Test.html` – per `.gitignore` ausgeschlossen |

---

## Offene Entscheidungen (vor Phase 1 klären)

- [x] **Lizenz:** MIT (einfach, maximal offen) ~~oder AGPL-3.0~~
- [x] **Repo-Name:** `slideforge` auf GitHub
- [x] **Git-Host:** GitHub ~~(empfohlen) oder Codeberg?~~
- [x] **Demo:** [slideforge.service7.ch](https://slideforge.service7.ch/) — Reset alle 12h via Cron
- [x] **Sprache README:** DE + englische Kurzfassung oben

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

- [x] Demo auf Subdomain → [slideforge.service7.ch](https://slideforge.service7.ch/) (Deploy: `.deploy/deploy-demo.sh`)
- [x] Hinweis auf Demo-Seite: `DEMO_MODE` in `config.php` + Banner (DE/EN/FR)
- [x] Reset-Skript: `scripts/reset-demo-data.sh` (für Cron)
- [x] **Feature-Tour** statt Screenshots — öffentlicher Link (festes Token `slideforge-tour`):
  - https://slideforge.service7.ch/view.php?token=slideforge-tour
  - Seed: `seed/feature-tour/` · wird bei jedem Demo-Reset neu angelegt
- [ ] Optional: 2-Minuten-Screencast (YouTube/Vimeo, Link im README)

---

## Phase 5 – Nach der Veröffentlichung

- [ ] Issues aktiv beantworten (Installation, Feature-Wünsche)
- [x] Releases bis **v2.0.2** (Mobile Fernsteuerung, PWA, Feature-Tour)
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

Veröffentlichung ist abgeschlossen, wenn:

1. Öffentliches Repo mit Lizenz und bereinigter `.gitignore` existiert
2. Release **v1.0.0** (und Folge-Releases) mit Install-Hinweis online ist
3. Demo-Instanz und Feature-Tour öffentlich erreichbar sind

---

*Dieses Dokument kann nach Abschluss archiviert oder in kürzere `docs/RELEASE.md` überführt werden.*
