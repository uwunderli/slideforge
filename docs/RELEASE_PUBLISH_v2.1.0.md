# Release v2.1.0 veröffentlichen

Stand: 2026-07-07 · Tag: **`v2.1.0`** (Minor — Folien-Sets & Logos-Import)

---

## Versionsstand (lokal geprüft)

| Datei | Wert | Status |
|-------|------|--------|
| `VERSION` | `2.1.0` | ✔️ |
| `CHANGELOG.md` | `[2.1.0]` | ✔️ |
| `README.md` | Link `v2.1.0` | ✔️ |
| `config.php` | `ASSET_VERSION` 356 | ✔️ |
| `docs/RELEASE_v2.1.0.md` | Briefing shipped | ✔️ |
| `.github/RELEASE_v2.1.0.md` | GitHub-Release-Text | ✔️ |
| `scripts/verify_schlicht_seed.py` | OK (7 Folien) | ✔️ |

**Branch:** `cursor/layout-sets-logos-ui` · letzter Commit: `chore(release): v2.0.4`  
**Delta:** alle uncommitteten Änderungen = Nacharbeit + Re-Versionierung 2.0.4 → **2.1.0**

---

## Tag-Strategie

- **Semantic Versioning:** Minor-Bump `2.0.x` → **`2.1.0`** (neues Feature-Paket Sets/Logos, kein Patch)
- **Git-Tag:** `v2.1.0` (annotiert, via pre-push-Hook oder manuell)
- **GitHub Release:** Body aus `.github/RELEASE_v2.1.0.md`
- **Roadmap:** church.tools → **v2.2.0** (siehe `docs/RELEASES.md`)

### Wichtig: pre-push-Hook

Der Hook ruft `.deploy/release-prep.sh` auf, das **nur den Patch** erhöht (`2.1.0` → `2.1.1`).  
Für **v2.1.0** daher:

1. `VERSION`, `CHANGELOG.md`, `README.md` **manuell** auf 2.1.0 setzen (erledigt)
2. Feature-Commit(s) mit allen Änderungen
3. **Abschluss-Commit:** `chore(release): v2.1.0` (nur wenn VERSION/CHANGELOG noch nicht im Feature-Commit)
4. `git push` — Hook erkennt Release-Commit und **überspringt** auto-bump, deployt + taggt `v2.1.0`

Ohne Deploy: `SKIP_RELEASE_DEPLOY=1 git push`

---

## In den Release-Commit aufnehmen

**Code & Assets**

- `src/LayoutSet.php`, `Presentation.php`, `TextTemplate.php`, `LogosSermonImporter.php`
- `public_html/` (api, editor, templates, import, css, js)
- `lang/de.php`, `en.php`, `fr.php`, `it.php`, `rm.php`
- `seed/layout-sets/schlicht/`
- `config.php` (`ASSET_VERSION`)
- `scripts/build_schlicht_seed.py`, `scripts/sync_set_i18n.py`, `scripts/verify_schlicht_seed.py`

**Dokumentation**

- `CHANGELOG.md`, `README.md`, `VERSION`
- `docs/RELEASES.md`, `docs/RELEASE_v2.1.0.md`, `docs/RELEASE_v2.2.0.md`, `docs/RELEASE_v2.3.0.md`
- `docs/RELEASE_v2.4.0.md`, `docs/RELEASE_v3.1.0.md` (neu)
- `.github/RELEASE_v2.1.0.md`
- `docs/VEROEFFENTLICHUNG.md`, `docs/AGENT_TASK_2026-07-07.md`

**Nicht committen** (optional / lokal)

- `.githooks/pre-push` (nur wenn bewusst geändert)
- `docs/ADJUSTMENTS_2026-07-05.md`, `docs/samples/` (Arbeitsnotizen)

---

## Merge & Publish (Reihenfolge)

```bash
# 1. Feature + Doku committen (Beispiel)
git add <Dateien aus Liste oben>
git commit -m "feat: Folien-Sets, Logos-Import und Release-Doku v2.1.0"

# 2. Release-Commit (falls VERSION/CHANGELOG nicht schon im Feature-Commit)
git add VERSION CHANGELOG.md README.md
git commit -m "chore(release): v2.1.0"

# 3. Merge nach main (PR oder lokal)
git checkout main && git merge cursor/layout-sets-logos-ui

# 4. Push + Deploy (Hook taggt v2.1.0)
git push origin main

# 5. GitHub Release (falls Hook nicht gh nutzt)
gh release create v2.1.0 --title "v2.1.0 — Folien-Sets & Logos-Import" \
  --notes-file .github/RELEASE_v2.1.0.md
```

---

## Abnahme-Checkliste

- [x] Release-Dokumentation publizierbar
- [x] Keine Secrets im Diff (`data/`, `.env` ausgeschlossen)
- [x] Schlicht-Seed verifiziert
- [ ] Owner: manuelle Tests (siehe `.github/RELEASE_v2.1.0.md` Test plan)
- [ ] Commit + Merge + Push + Tag `v2.1.0`
- [ ] Prod-Deploy (Hook oder manuell)
- [ ] Optional: `./.deploy/push-layout-set-seed.sh schlicht` (Abschnitt 3)

---

## Bekannte Punkte

- Tag `v2.0.4` existiert auf dem Feature-Branch; **`v2.1.0` ersetzt** die inhaltliche Release-Einordnung (CHANGELOG umgestellt).
- Lokales „Schlicht II“ auf Prod ≠ Seed — Abschnitt 3 klärt Prod-Übernahme.
