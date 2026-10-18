# pre-push-Hook abgeschaltet: keine Auslieferung und kein Tag mehr beim Push (U52)

**Datum:** 18.10.2026 · **Ort:** nur das SlideForge-Repo, `/.githooks/pre-push`
**Go:** Urs, 18.10.2026, Wortlaut «U52 → ja abschalten», weitergegeben durch den Koordinator.
Umfang: nur der Git-Hook; Auslieferung (Prod **und** Demo) und das Setzen des Release-Tags
abschalten; **ausschalten statt löschen**, deaktivierte Kopie mit `.aus-JJJJ-MM-TT` behalten.

## Was der Hook vorher tat

Aktiv über `git config core.hooksPath = .githooks`. Bei **jedem** Branch-Push, **vor** dem
eigentlichen Push:

1. `.deploy/release-prep.sh`, dann ein Commit `chore(release): vX.Y.Z` (ohne Hook-Prüfung),
2. `.deploy/deploy.sh sync-code` → **Produktion** `slides.bkbiel.ch`,
3. `.deploy/deploy-demo.sh sync-demo` → **Demo** `slideforge.service7.ch`,
4. `git tag -a vX.Y.Z` (mit `push.followTags` also mitgepusht).

Darum lag seit 17.10. eine Push-Sperre auf dem Repo: ein Push war in Wahrheit eine
Auslieferung auf zwei Hosts, ohne Review — und die Demo ist seit Urs' Entscheid vom 16.10.
eingefroren.

## Was jetzt gilt

| | sha256 | Zeilen |
|---|---|---|
| vorher (= jetzt `.githooks/pre-push.aus-2026-10-18`) | `5781c6db2363df4af4afa89b547223f7266fe83cd6d58565c57193b0fcff15d5` | 76 |
| nachher (`.githooks/pre-push`) | `a076614d8e570334b70e9132bd0a216855f90ae859f3fa1c8efb1382c136ffbf` | 22 |

Der neue Hook liefert nichts aus, macht keinen Commit, setzt kein Tag. Er schreibt zwei
Zeilen auf die Konsole — dass Auslieferung und Tag abgeschaltet sind und wie man von Hand
ausliefert — und endet mit `exit 0`, blockiert den Push also nicht.

Die vollständige alte Fassung liegt **unverändert** daneben als
`.githooks/pre-push.aus-2026-10-18` (Zeitstempel und Rechte mit `cp -p` erhalten, `0755`).

## Probe

Trocken ausgeführt mit einer nachgebildeten Push-Zeile auf `refs/heads/main`
(`printf 'refs/heads/main <sha> refs/heads/main <sha>' | .githooks/pre-push origin <url>`):
beide Hinweiszeilen erscheinen, Rückgabewert **0**, kein `ssh`, kein `rsync`, kein
`git tag`, kein Commit. `bash -n` ohne Beanstandung. **Es wurde nicht gepusht** — auch nicht
als Test (Auflage aus der Weitergabe).

## Rückbau

> **Rückbau-Satz:** `cp -p .githooks/pre-push.aus-2026-10-18 .githooks/pre-push` im
> Repo-Wurzelverzeichnis stellt den alten Hook Byte für Byte wieder her
> (Prüfwert `5781c6db2363df4a…`); danach liefert ein Push wieder Prod und Demo aus.

## Folge für den Arbeitsablauf

Ausgeliefert wird von Hand und nach Review, wie bei den letzten Paketen:
`.deploy/deploy.sh sync-code` für Prod (mit Sicherung nach `.sicherung/`), die Demo nur,
wenn der Einfrier-Entscheid fällt. **Nicht mehr automatisch** passiert jetzt auch der
Versionssprung in `VERSION`/`CHANGELOG.md` — der gehört ab jetzt zur Lieferung von Hand
(`.deploy/release-prep.sh`). Das war nicht ausdrücklich im Auftrag; wenn der Versionssprung
beim Push bleiben soll, ist es eine Zeile im Hook, dann ohne Auslieferung und ohne Tag.
