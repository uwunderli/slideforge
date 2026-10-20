# Befund SlideForge (FolienSchmiede) — 2026-09-12

Session `SlideForge` auf srv09 · Arbeitsordner `/mnt/storage/Projekte/gemeinde/slideforge`
Zeitbasis: srv09-Uhr, seit 2026-09-11 08:45 UTC korrekt. Alle Prüfungen heute **lesend**
(ssh `cat`/`ls`/`md5sum`, HTTP nur `HEAD`) — mit **einer Ausnahme**, siehe §0.

---

## 0. Selbst verursachte Änderung (Demo, nicht Prod)

Bei den HTTP-Prüfungen habe ich `HEAD https://slideforge.service7.ch/demo-reset.php`
abgesetzt. **PHP führt ein Skript auch bei `HEAD` vollständig aus** — damit ist auf dem
**Demo**-Host `Demo::resetAll()` gelaufen: Demo-Benutzer, Präsentationen, Uploads gelöscht
und frisch geseedet.

- **Prod war nicht betroffen** (`DEMO_MODE=false` → `Demo::isActive()` falsch → 404).
- Wirkung entspricht exakt dem geplanten 12-h-Reset und dem Aufruf, den
  `.deploy/deploy.sh reset-demo` nach jedem Deploy selbst macht. Kein Datenverlust von Belang.
- Nachkontrolle: `login.php` 200, Feature-Tour `view.php?token=slideforge-tour` und
  `…-tour-de` 200 → Demo ist gesund und wieder im Soll-Zustand.
- **Lehre:** „nur HEAD“ schützt nicht vor Ausführung. Bei ausführenden Endpunkten hilft nur
  *nicht aufrufen*. `demo-reset.php` gehört auf die Nicht-Anfassen-Liste.

Sonst: **Nichts verändert.**

---

## 1. Zustand des Moduls

| | |
|---|---|
| Version `VERSION` (lokal, Branch `main`) | 2.1.22 |
| Version auf Prod | 2.1.22 (`VERSION` wird nicht mitdeployt) |
| Prod | `slides.bkbiel.ch` (Unraid BK Biel 10.10, `/data/www/slideforge`), `DEMO_MODE=false` |
| Demo | `slideforge.service7.ch` (Wunderli 15.15), `DEMO_MODE=true`, 12-h-Reset |
| Arbeitsbaum | **53 Dateien geändert** gegen HEAD (`+2226 / −811`), 1 Datei gestaged (`public_html/import.php`) |
| Syntax | `php -l` über alle PHP-Dateien des Arbeitsbaums (PHP 8.5.10): **keine Parse-Fehler** |
| Prod-Erreichbarkeit | `/manifest.php` 200 · `/assets/css/style.css` 200 · `/` `→` 302 Hub-Login · `/view.php` 404 (ohne Token korrekt) |
| Speicherplatz Prod | `/data/www` 3.8 T, 75 % belegt, 972 G frei · `data/` 54 MB |
| PHP-/nginx-Fehlerlogs Prod | im Jail nicht lesbar / nicht vorhanden — keine Auffälligkeit feststellbar |

Prod läuft. Nichts deutet auf einen akuten Ausfall hin.

---

## 2. Repo ↔ Prod (Kern des Problems)

Vollvergleich per `md5sum` über alle vom Deploy erfassten Pfade
(`config.php`, `README.md`, `config/`, `src/`, `lang/`, `seed/`, `docker/`, `public_html/`,
`scripts/`; ohne `uploads/` und `data/`): **Prod 660 Dateien, lokal 641.**

### 2a. Prod ist bei 5 Dateien NEUER als der Arbeitsbaum

| Datei | Prod | Arbeitsbaum (`main`) |
|---|---|---|
| `config.php` | `ASSET_VERSION` **623**, `sf_hub_asset()` vorhanden | **622** |
| `src/LayoutSet.php` | enthält `isPlaceholderText($phText)`-Fix | **fehlt** |
| `src/LogosSermonImporter.php` | `formatListItemsMarkdown` 4× | 3× (ältere Notizen-Logik) |
| `public_html/assets/css/style.css` | abweichend | — |
| `public_html/import.php` | synchroner Import | gestagte Async-Fassung |

Die beiden `src/`-Fixe leben auf dem **lokalen Branch `fix/logos-import-detection`**
(`e3bb287 chore(release): v2.1.23`, 2 Commits über `main`) — **nie gepusht**, kein
Remote-Gegenstück. Sie sind auf Prod deployt, aber im ausgecheckten `main`-Arbeitsbaum
**nicht** enthalten.

**Folge:** Ein `git push` auf `main` würde über `.githooks/pre-push` → `deploy.sh sync-code`
den **ganzen Arbeitsbaum** per rsync auf Prod **und** Demo schieben und damit
**Prod zurückrollen**:

1. `«Untertitel»`-Platzhalter erscheinen wieder sichtbar auf importierten Predigtfolien
   (genau der Fehler, den der `LayoutSet`-Fix behebt),
2. Notizen-Aufzählungen bekommen wieder eine Leerzeile pro Punkt,
3. `ASSET_VERSION` **sinkt** 623 → 622 → Browser liefern zwischengespeicherte alte
   CSS/JS-Stände zu neuen Dateien (klassischer Stale-Cache, schwer zu diagnostizieren).

Der rsync läuft **ohne** `--delete`, daher werden Prod-only-Dateien nicht gelöscht — das ist
der einzige Grund, warum frühere Pushes nicht mehr Schaden angerichtet haben.

### 2b. 19 Dateien existieren nur auf Prod

| Datei(en) | in Git je vorhanden? | Bewertung |
|---|---|---|
| `public_html/_fix_admin_1093447.php`, `_install_layout_set_1199879.php`, `_role_check_1089615.php`, `_role_check_1090768.php` | nie | Einmal-Skripte vom **2026-07-07**, die `.deploy/fix-prod-admin.sh` / `check-prod-role.sh` / `push-layout-set-seed.sh` per `trap … EXIT` hätten löschen sollen. Jedes hat einen `hash_equals()`-Token-Guard (128-Bit-Einmal-Token) → **nicht brute-forcebar**, aber ausführender Code (`Auth::setRole(…, 'admin')`, `unlink`) im Web-Root, ausserhalb jeder Historie. |
| `public_html/assets/js/qrcodegen.js`, `present-remote-qr.js` | nie | im aktuellen Code **nicht referenziert** (nur CSS-Klassen `present-remote-qr-*`) → vermutlich Altlast |
| `public_html/includes/editor_ribbon_collab_menu.php`, `editor_ribbon_present_menu.php` | nur in einem Stash-Commit (`504f953`, v2.1.2) | nicht mehr referenziert → Altlast |
| `seed/feature-tour/meta.json`, `slides.json` | in `8f35e7e` | Altformat; lokal liegt die Tour als `seed/feature-tour/{de,en,fr,it,rm}/` |
| `scripts/patch_feature_tour_remote.py` | nie | Altlast |
| `src/phpqrcode/{index.php,bindings,temp,tools/*}` | nie | Vendor-Reste, lokal nicht im Repo |

**Kein Fall von „nur lokal, nie deployt“** — alles Lokale liegt auf Prod.

### 2c. Layout-Set «Schlicht – Logos»

`data/presentations/a452ba0246f40ca3` auf Prod **vorhanden** (mtime 2026-07-07). Layout-Sets
sind Präsentationen, kein eigener Ordner. Lokal existiert nur `seed/layout-sets/schlicht` —
das Logos-Set hat **keinen Seed**. Bestätigt: nicht überschreiben, nicht „wiederherstellen“.

### 2d. Async-Umbau — Briefing/Memory korrigieren

Das Briefing sagt, `ImportJob`/`BackgroundJob` seien „uncommitted **und undeployt**“. Der
Vergleich zeigt: **teilweise deployt**.

| | Prod |
|---|---|
| `src/{BackgroundJob,ImportJob,ExportJob,WebdavImportJob}.php` | **vorhanden**, md5-identisch mit lokal |
| `scripts/run-{import,export,webdav-import}.php` | **vorhanden**, md5-identisch, alle drei mit CLI-Guard |
| `public_html/export.php` | **async verdrahtet** (3× `ExportJob`) |
| `public_html/webdav.php` | **async verdrahtet** (1× `WebdavImportJob`) |
| `public_html/import.php` | **0× `ImportJob`** → Import läuft weiter **synchron im FPM** |

Also: Infrastruktur und Export/WebDAV sind live, nur der **Import**-Umschalter ist noch nicht
umgelegt (liegt gestaged im Arbeitsbaum). Alles davon ist **uncommitted** — in Git steht
keine dieser Dateien.
`data/jobs/` existiert auf Prod, ist für den SSH-Benutzer aber nicht lesbar (php-fpm-Owner) —
ein etwaiger Job-Rückstau war nicht prüfbar.

---

## 3. Sicherheit / Sperre seit 11.09.

**Frage aus dem Briefing — braucht das Modul einen HTTP-Pfad ausserhalb `public_html/`?
Antwort: nein.**

- Grep über `public_html/**` (`*.php`, `*.js`, `*.css`) nach `href|src|action|url` mit `../`
  sowie nach URL-artigen `/data/`, `/src/`, `/scripts/`, `/config/`-Referenzen: **kein Treffer**.
- Auf Prod existiert **kein** `public_html/data` — `/data/config.json` und
  `/data/auth-secret.txt` können über den Web-Root gar nicht auflösen.
- HTTP-Kontrolle (HEAD): beide Pfade geben **302 → `https://bkbiel.ch/login.php?return=…`**,
  nicht 404. Das ist **nicht** die nginx-Sperre, sondern SlideForges eigene
  SharedAuth-Umleitung, die jeden unbekannten Pfad abfängt; der Default-Deny greift hier also
  nicht sichtbar. Ergebnis ist dasselbe: kein Zugriff. **Der Briefing-Satz „jetzt 404“ trifft
  auf `slides.bkbiel.ch` nicht zu — es ist 302.**
- `/scripts/seed_demo.php` → **404** auf Prod **und** Demo. `scripts/` ist über HTTP auf
  beiden Hosts **nicht erreichbar**. Die fehlenden CLI-Guards (7 von 10 Skripten, siehe unten)
  sind damit Tiefenverteidigung, nicht ein offenes Loch.
- Secrets auf Prod: `data/auth-secret.txt` `-rw-r--r--` (Owner `admin`),
  `data/config.json` **`-rwxrwxrwx`**. Weltlesbar im Dateisystem des Hosts. Die Rotation nach
  der LAN-Offenlegung steht laut Briefing bei Urs noch aus — Befund hier unverändert.

### CLI-Guard-Inventar `scripts/*.php`

| ohne Guard (7) | mit Guard (3) |
|---|---|
| `build_demo_showcase.php`, `build_feature_tour.php`, `build_schlicht_seed.php`, `export_layout_set_to_seed.php`, `export_seed_templates.php`, `import_feature_tour.php`, `seed_demo.php` | `run-export.php`, `run-import.php`, `run-webdav-import.php` |

Fehlendes Muster: `if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }`.
Offene Aufgabe, nur mit Urs' Go.

### `public_html/demo-reset.php` — ungeschützter destruktiver Endpunkt

Liegt **im Web-Root**, hat **keinen** Token-, Auth- oder Herkunftsschutz und **keine**
Wiederholsperre — einzige Bedingung ist `Demo::isActive()`. Jeder Aufruf aus dem Internet
(GET **oder** HEAD) löscht auf dem Demo-Host alle Benutzer, Präsentationen und Uploads und
seedet neu. Belegt: §0, heute selbst ausgelöst.

- Prod ist durch `DEMO_MODE=false` geschützt — der Schutz hängt aber an **einer** Konstante
  in `config.php`. `deploy.sh` setzt `DEMO_MODE=true` beim Demo-Upload per `sed`; ein
  Deploy mit falsch gesetztem `DEPLOY_DEMO_MODE` gegen Prod wäre dort sofort scharf.
- Wer die öffentliche Demo vorführt, kann sie von Dritten jederzeit mitten in der Vorführung
  zurückgesetzt bekommen.

### `display_errors = 1` auf Prod

`config.php:52` — auf Prod aktiv (`ini_set('display_errors', '1')`), obwohl der Kommentar
zwei Zeilen darüber („Für den Produktivbetrieb display_errors ganz auf 0 setzen“) das
Gegenteil verlangt. Jede PHP-Warnung landet im HTML — mit Dateipfaden. Im Gottesdienst wäre
das auf der **Beamer-Ausgabe** sichtbar.

---

## 4. Sonntag — Lagebeurteilung

**Nichts gefunden, was den Gottesdienst morgen akut gefährdet.** Prod antwortet, Assets 200,
kein Fehlerlog-Befund, Platz reichlich, das Logos-Set ist intakt.

Zwei Punkte mit Sonntagsbezug:

1. **Die jüngste Präsentation auf Prod wurde am 2026-08-30 geändert** (`data/presentations`,
   7 Einträge; danach nichts). Die Folien für morgen sind also noch **nicht** angelegt —
   sie entstehen vermutlich heute Abend. Damit läuft der Predigt-Import heute Abend über den
   **synchron im FPM** laufenden Importpfad (§2d) und über die **nur auf Prod** vorhandenen
   Logos-Fixe (§2a). Beides funktioniert heute — solange **niemand pusht**.
2. **Der `pre-push`-Hook ist die eigentliche Gefahr.** Ein `git push` auf `main` heute oder
   morgen früh — auch ein gut gemeinter Aufräum-Commit — deployt sofort Prod und Demo und
   rollt die Logos-Fixe sowie `ASSET_VERSION` zurück (§2a). Kein Commit, kein Push bis
   Sonntag nachmittag. Wer trotzdem pushen muss: `SKIP_RELEASE_DEPLOY=1 git push`.

Die Präsentation selbst konnte ich nicht inhaltlich prüfen — das erforderte einen Login und
damit `GET` auf geschützte Endpunkte. Unterlassen.

---

## 5. Drei wichtigste Pendenzen (meine Sicht)

1. **Prod-WIP nach Git zurückholen, in der richtigen Richtung.** Der Arbeitsbaum ist an
   5 Dateien **älter** als Prod; der Branch `fix/logos-import-detection` (v2.1.23) hängt
   ungepusht daneben. Erster Schritt ist **nicht** Commit und **nicht** Deploy, sondern:
   Prod-Stand der 5 Dateien holen (`.deploy/remote.sh "cat …"`), gegen Branch und
   Arbeitsbaum zusammenführen, `ASSET_VERSION` auf ≥ 623 setzen, dann committen — und der
   erste Push erst, wenn der Baum **inhaltlich ≥ Prod** ist. Solange das offen ist, ist jeder
   Push eine Regression. Braucht Urs' Go und darf nicht am Wochenende passieren.
2. **`demo-reset.php` absichern** (Token oder `X-Deploy`-Header + Wiederholsperre) und die
   7 fehlenden CLI-Guards in `scripts/` nachziehen. Der Endpunkt ist der einzige heute
   nachweislich von aussen auslösbare destruktive Pfad des Moduls — §0 ist der Beleg.
   Dazu `display_errors` auf Prod auf `0`.
3. **Prod-Web-Root aufräumen:** die 4 Einmal-Skripte von 2026-07-07 löschen und die übrigen
   15 Prod-only-Dateien (§2b) einsortieren — entweder ins Repo aufnehmen (falls gebraucht)
   oder entfernen. Sonst bleibt der Prod-Stand dauerhaft nicht reproduzierbar.

Danach (nicht jetzt): Import-Async-Umschalter fertigstellen (§2d), Import-Dialog auf
`HubFloatDialog` (DESIGN.md §10/§14), Erkennungs-Regex PHP↔JS entdoppeln,
`buildSlidesFromLayoutSet` (~490 Zeilen) zerlegen, erste Tests auf Basis von
`docs/samples/*.html`. Ausserdem Kanon-Defekt **R1**: `slideforge/.cursor/rules/status-ux.mdc`
ist ein Symlink auf den absoluten Pfad `/home/dev/Projekte/gemeinde/.cursor/rules/status-ux.mdc`
und **hier kaputt** (`cat` → „No such file or directory“) — die Status-UX-Regel ist im Modul
faktisch nicht lesbar.

---

## 6. Geprüfte Quellen

Regeln: `/config/koordinator/briefing-{slideforge,srv-gemeinsam}.md`,
`gemeinde/_notes/{CANON,PRINCIPLES}.md`, `hub/docs/{WORKFLOW,DEV_WORKFLOW,DESIGN}.md` (§Verbindlich),
`gemeinde/.cursor/rules/*.mdc` (10), `slideforge/.cursor/rules/*.mdc` (6, davon 1 kaputter Symlink).
Projekt: `README.md`, `VERSION`, `config.php`, `docs/{WORKFLOW,UI,BACKLOG,RELEASES}.md`,
`.deploy/{deploy.sh,ssh-common.sh,remote.sh,fix-prod-admin.sh,check-prod-role.sh}`,
`.githooks/pre-push`, `src/`, `public_html/`, `scripts/`, `seed/`.
Gedächtnis: `slideforge-prod-divergence.md`, `slideforge-logos-importer.md`.
Prod/Demo: read-only `ssh` (`cat`/`ls`/`md5sum`/`grep -c`/`df`/`du`) + HTTP `HEAD`.
