# SlideForge → hub-kit.css: Plan und Abweichungsbefund

Stand 2026-10-14. Gemessen gegen den **Live-Stand** (slides.bkbiel.ch), nicht gegen HEAD.
Vorbereitet, **nicht ausgeliefert**.

## 0. Messgrundlage

- Kit: `https://hub.bkbiel.ch/assets/css/hub-kit.css` → HTTP 200,
  sha256 `b5f208dab215ebf8ee5dc45b261365a5a2c0741db3046f11576c7fcb097ce9e8`,
  2692 Zeilen, 72 790 Bytes, **16 Teile**.
- Hub-Asset-Nummer: `https://hub.bkbiel.ch/assets/ui-version.txt` → `198`.
- Arbeitsbaum == Live für **alle sieben** Kopien (sha256 je Datei gleich). Der
  Memory-Hinweis „Prod weicht von HEAD ab" trifft hier also nicht zu.

## 1. Dateiliste: die sieben mitgeführten Kopien

Alle unter `public_html/assets/css/`. Vergleich über den sha256-Präfix, den der
Kit-Kopf je Teil mitführt.

| Datei | Bytes | unser sha256 | Kit-Präfix | Befund |
|---|---|---|---|---|
| hub-confirm.css | 3735 | `0477425de962` | `0477425de962` | **identisch** |
| hub-page-busy.css | 2980 | `11fd53136240` | `11fd53136240` | **identisch** |
| hub-scroll.css | 1961 | `4eb6f633bf44` | `4eb6f633bf44` | **identisch** |
| hub-secure-delete.css | 3697 | `9382cb894437` | `9382cb894437` | **identisch** |
| hub-float-dialog.css | 5065 | `072cb442583b` | `c9ddebaadbf9` | weicht ab |
| hub-status.css | 3404 | `26813ccc7ef1` | `b72cad21cde9` | weicht ab |
| hub-user-menu.css | 4465 | `ee17c6c508d2` | `69f0a4d5ffdd` | weicht ab |

`style.css` (288 KB) ist nicht im Kit und bleibt unberührt; ebenso unsere eigenen
`mobile.css` und `import-dialog.css`.

Eingebunden werden alle sieben an genau **einer** Stelle:
`public_html/includes/header.php` (Zeilen 50-55 und, nur angemeldet, 60).
Die fünf Seiten mit eigenem `<head>` (`view.php`, `preview.php`, `present_frame.php`,
`present_audience.php`, `pdf_export.php`) verwenden **keine** Hub-Bausteine — geprüft.

## 2. Abweichungsbefund: ist es Absicht?

### hub-user-menu.css — **Absicht, darf nicht einfach weg**
10 Selektoren stehen nur bei uns, und alle sind **Doppelselektoren auf unsere
Alt-Klassen**: `.hub-user-menu__dropdown, .hub-user-menu .user-menu-dropdown`,
dazu `user-menu-name`, `user-menu-pref`, `user-menu-sep`. Unser Markup benutzt diese
Alt-Klassen tatsächlich (9 Treffer in `*.php`/`*.js`). Das ist eine bewusste Brücke.
Kopie löschen ⇒ Benutzermenü verliert seine Gestaltung.
Empfehlung: Brücke als kleine eigene Datei behalten (nicht als „Hub-Kopie" führen) —
oder in einem separaten Auftrag das Markup auf `hub-user-menu__*` umstellen.

### hub-float-dialog.css — **teils Absicht, teils veraltet**
- **Absicht:** Vorgabegrösse. Uns `width: min(920px, 96vw)`, `height: min(680px, 90vh)`;
  Kit `420px × 280px`. SlideForge-Dialoge (Import, Pixabay, Suchen und Ersetzen) brauchen
  die Fläche. Kopie löschen ⇒ alle Schwebedialoge schrumpfen auf 420×280. Sichtbarer Rückschritt.
- **KORREKTUR 14.10.:** Die zwei `__title`-Regeln sind **keine** Abweichung. Mein
  erster Vergleich hat Selektorgruppen als Ganzes verglichen; der Kit fasst
  `.hub-float-dialog__titlebar, .hub-float-dialog__title` zusammen (Zeile 89/90) und
  hat beide Regeln **zeichengleich**. Nach Selektoren einzeln verglichen bleibt von
  unserer Kopie **nur** `.lab-float-dialog` exklusiv — und das ist toter Code.
  Einzige echte Abweichung ist damit die Vorgabegrösse.
- **Veraltet, Kit ist besser:** die acht Griffzonen. Uns innen am Rand (`top:0`, 6 px),
  Kit halb überstehend (`-5px`, 10 px) — der Kit-Kommentar nennt den Grund (breiter zu
  treffen, nicht über der Scrollleiste). Übernehmen.
- **Leiche:** `.lab-float-dialog` — 0 Treffer im ganzen Baum, kann weg.
- **Kein Risiko bei den Knöpfen:** der Kit nutzt `var(--hub-dialog-accent)` statt
  `var(--accent)`, definiert die Variable aber selbst (`hub-kit.css:1045`:
  `--hub-dialog-accent: var(--accent)`). Unsere Akzentfarbe bleibt, zusätzlich kommen
  die `data-dialog-accent`-Varianten.

### hub-status.css — **keine Absicht erkennbar, nur älter**
Kein Selektor steht nur bei uns; der Kit ist ein gehärtetes Obermenge (Rückfallwerte
`var(--accent, var(--of-accent, #428bbe))`, `background` → `background-color`).
**Eine verhaltensrelevante Differenz:** `.cf-status-host` hat bei uns `z-index: 100`,
im Kit `13050`. Unsere `.modal-backdrop` liegt auf `1300`, die höchste eigene Lage auf
`2500` — Statusmeldungen liegen heute also **hinter** offenen Dialogen. Der Kit behebt
das. Ich halte es für eine Verbesserung, nenne es aber als einzige sichtbare
Verhaltensänderung.

## 3. Die neun Teile, die wir heute nicht laden

Das Kit bringt `hub-alpha-index`, `hub-avatar-crop`, `hub-color-picker`,
`hub-date-picker`, `hub-dialog-actions`, `hub-emoji-palette`, `hub-help`,
`hub-ribbon-customize`, `hub-theme-tag` mit — rund 48 KB zusätzlich.
Kollisionsprüfung gegen unser Markup und `style.css`:

- **Unkritisch:** die Treffer sind fast nur Zustandsklassen (`is-active`, `is-on`,
  `is-open`, `is-dragging`, `is-selected`). Im Kit gibt es **keine** freistehende
  `.is-*`-Regel und keine nackte Element-Regel — geprüft; sie greifen nur in
  Verbindung mit dem jeweiligen Hub-Baustein.
- **Anzusehen:** drei echte Bausteinklassen stehen in unserem Markup, ohne dass wir
  deren CSS heute laden: `hub-dialog-actions`, `hub-dialog-actions__start` und
  `hub-admin-field` (aus `hub-help.css`), ausserdem die Klassen aus
  `hub-ribbon-customize.css`, wo SlideForge eine **eigene** Ribbon-Anpassung hat
  (`ribbon-customize.js`, Regeln in `style.css`). Diese Stellen bekommen mit dem Kit
  erstmals Hub-Gestaltung. Da das Kit **nach** `style.css` geladen wird (wie heute die
  Kopien), gewinnt bei gleichem Gewicht der Hub. Das ist genau die Stelle, die am
  laufenden Bildschirm angesehen werden muss.

## 4. Was lokal vorbereitet ist

`public_html/includes/header.php`: die sechs festen Kopie-Links ersetzt durch **einen**
Kit-Link, an derselben Stelle (nach `style.css`, damit sich die Rangfolge nicht ändert).
`sf_hub_asset()` braucht es dafür nicht — der Weg ist `hub_asset('css/hub-kit.css')`,
das ergibt `https://hub.bkbiel.ch/assets/css/hub-kit.css?v=198`.
`php -l` sauber.

## 5. Warum ich die Kopien NOCH NICHT gelöscht habe

Zwei Gründe, beide belegt:

1. **Die drei abweichenden Kopien tragen echte SlideForge-Unterschiede** (Abschnitt 2).
   Sie bleiben darum vorerst **nach** dem Kit geladen und wirken als Nachtrag; das
   Aussehen bleibt damit exakt wie heute.
2. **Im Alleinbetrieb bricht der Kit-Link.** `hub_asset()` liefert die Hub-Adresse nur,
   wenn `hub_ui_integrated()` wahr ist, und das hängt an
   `SharedAuth::shouldUseHubLogin()` (`shared/bootstrap.php:100-106`). Auf
   slides.bkbiel.ch ist das wahr — belegt: `/` und `/login.php` antworten mit 302 auf
   `hub.bkbiel.ch/login.php`. **Auf unserer Demo slideforge.service7.ch ist es falsch**,
   weil `DEMO_MODE` in `shouldUseHubLogin()` sofort `false` zurückgibt. Dort würde
   `hub_asset()` auf einen lokalen Pfad zurückfallen, und ohne Einzelkopien stünde die
   Demo ohne Hub-CSS da. Der Einbau trägt darum eine `if`-Verzweigung: Kit wenn
   eingebunden, sonst die vier identischen Einzelkopien. Deren Löschung setzt voraus,
   dass stattdessen **eine** Kopie `hub-kit.css` mitgeführt wird — das ist eine
   Architekturfrage für Hub, nicht für mich.

## 6. Offen

- Entscheid Hub/Urs zu Abschnitt 5.2 (Alleinbetrieb: ein vendoriertes `hub-kit.css`?).
- Sichtprüfung am laufenden Bildschirm für Abschnitt 3 (Ribbon-Anpassung,
  Dialog-Aktionszeile) — nur nach Auslieferung möglich.
- `hub-user-menu.css`: Brücke behalten oder Markup umstellen.
- Auslieferung erst auf Wort des Koordinators mit Urs' Freigabe.


---

# Nachtrag 2026-10-14: Umsetzung nach den Entscheiden

Entscheide des Koordinators (1)-(4) und Hubs Architekturentscheid (eine byte-gleiche
Kopie unter `assets/vendor/hub/css/hub-kit.css`) sind **lokal umgesetzt**. Nicht
ausgeliefert.

## Neue und entfernte Dateien

Entfernt, alle sieben: `public_html/assets/css/hub-{confirm,float-dialog,page-busy,
scroll,secure-delete,status,user-menu}.css`. Keine Verweise mehr im Code (geprüft über
den ganzen Baum ohne `.git`); was bleibt, sind Kommentare in `style.css`, ein Beispiel
im Docblock von `config.php:343` und ein alter Eintrag in
`.github/RELEASE_v2.1.10.md`. Der Export zieht nur reveal-CSS, kein Hub-CSS.

Neu:
- `public_html/assets/vendor/hub/css/hub-kit.css` — byte-gleiche Kopie,
  sha256 `b5f208dab215ebf8ee5dc45b261365a5a2c0741db3046f11576c7fcb097ce9e8`, geprüft.
- `public_html/assets/css/slideforge-float-dialog.css` (836 B) — **nur** die
  Vorgabegrösse `left/top/width/height`. Griffzonen, Titelleiste und Knöpfe kommen
  jetzt vom Kit; `.lab-float-dialog` ist weg.
- `public_html/assets/css/slideforge-user-menu-bridge.css` (2241 B, 10 Regeln) — nur
  die Selektoren auf unsere Alt-Klassen `.user-menu*`. Die sieben `--hub-um-*`-Variablen,
  die die Brücke braucht, definiert der Kit zeichengleich selbst — geprüft, sonst wäre
  die Brücke farblos aufgegangen.

`hub-status.css` ist vollständig abgelöst, inklusive `z-index` 100 → 13050.

## Eine Verzweigung bleibt in header.php — Hubs Annahme trifft für uns nicht zu

Hub schreibt, `hub_asset('css/hub-kit.css')` finde die Vendor-Kopie, wenn
`hub_ui_integrated()` falsch ist. Für SlideForge **nicht**: der Alleinbetrieb-Zweig in
`shared/bootstrap.php` (Zeilen 218-239) sucht die Datei unter einem Modul-Wurzelpfad und
kennt dafür nur `HUB_PUBLIC_PATH`, `OFFICEFORGE_ROOT`, `PLANFORGE_ROOT`,
`CLIPFORGE_ROOT`, `ARCHIVEFORGE_ROOT`. Für SlideForge bleibt `$root` leer, `is_file()`
greift nie, und zurück kommt `$candidates[1]`, also `/assets/css/hub-kit.css` — mit
führendem Schrägstrich und an einer Stelle, wo nach dem Aufräumen nichts liegt.

Darum steht in `header.php` weiterhin: mit Anbindung `hub_asset('css/hub-kit.css')`,
sonst fest `assets/vendor/hub/css/hub-kit.css?v=ASSET_VERSION`. Die Begründung steht als
Kommentar daneben. **Sobald `hub_asset()` einen SlideForge-Zweig hat, fällt die
Verzweigung weg** — das ist eine Zeile in Hubs Datei, nicht in meiner.

## Was noch nicht geprüft werden kann

`php -l` ist sauber, die Pfade stimmen, der Kit antwortet mit 200 und
`ui-version.txt` mit 198. Alles Weitere — Ribbon-Anpassung, Dialog-Aktionszeile,
Statusmeldung über offenem Dialog, Benutzermenü — braucht die Sichtprüfung an
slides.bkbiel.ch und, nach dem Nachziehen, an der Demo. Vorher liefere ich nicht aus.

---

## Nachtrag 17./18.10.2026 — Drift-Runde, und wofür die mitgeführte Kopie überhaupt da ist

**Die Umstellung ist längst live.** Auf Prod liegt unter `public_html/assets/css/` keine
einzige `hub-*.css` mehr, und der Kopf lädt `https://hub.bkbiel.ch/assets/css/hub-kit.css`
(derzeit `v=223`), danach als letztes `https://hub.bkbiel.ch/shared/cf-fine.css`. Der
Arbeitsbaum bildet das ab (die drei Löschungen in `git status` sind genau das).

**Was die mitgeführte Kopie noch tut.** `public_html/assets/vendor/hub/css/hub-kit.css` wird
in Prod **nicht geladen**. Sie ist allein der Rückfall des `else`-Zweigs in `header.php`:
ohne `CHURCHFORGE_HUB_URL` (Alleinbetrieb, Demo) gibt es keinen Hub, von dem man das Kit
holen könnte. Deshalb bleibt sie — und deshalb ist sie auch kein Teil einer Auslieferung,
solange sich an der Anbindung nichts ändert.

**Drift behoben:** Die Kopie war vom 16.10. (82 338 B, `821a122c…`, Hub-Asset 213). Jetzt
zeichengleich mit der Hub-Fassung: 92 197 B, `add6ffd7d8e8a8001ca1…`. Geprüft mit
`hub/scripts/check-vendored-css-drift.sh` — der Eintrag ist weg.

**Was die Drift-Prüfung für SlideForge noch meldet (drei Punkte, alle bewusst offen):**

1. `public_html/assets/js/hub-user-menu.js` — unsere Fassung (3139 B) sucht zusätzlich
   `.user-menu-dropdown` und `.user-menu-trigger`, die Hub-Fassung (3098 B) nur die
   `data-hub-user-menu-*`-Merkmale und `.hub-user-chip`. Das ist **Absicht**: unser Menü
   trägt die alten Klassennamen, dafür gibt es sogar eine eigene Brückendatei
   (`slideforge-user-menu-bridge.css`). Byte-gleich ziehen geht erst, wenn das Markup die
   Merkmale bekommt — und das Markup kommt aus dem Launcher.
2. `src/Launcher.php` — 226 Zeilen bei uns, 229 in `shared/`. Der Unterschied ist sichtbar:
   die gemeinsame Fassung zeigt in der Leiste **nur Favoriten** (mit Ziehen und Ablegen,
   `data-module-id`, `data-hub-fav-dock`), unsere **alle sichtbaren Module**, Favoriten
   zuerst. Geladen wird unsere Datei — der Autolader in `config.php` sucht in
   `BASE_PATH/src`, der Ordner-Mount spielt hier keine Rolle. Das ist ein Entscheid für Urs,
   keine stille Angleichung.
3. `src/SharedAuth.php` — unsere Fassung ohne Dev-Schlüssel (15.10.); in Prod gewinnt über
   den Ordner-Mount ohnehin die gemeinsame. Nachziehen bleibt gesperrt bis Go.
