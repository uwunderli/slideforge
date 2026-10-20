# Schlichtes Design (cf-fine.css) in SlideForge — Umsetzung 17.10.2026

Vorgabe: `_berichte/hub/vorgabe-schlichtes-design-apps-2026-10-17.md` (Urs, 17.10.:
«Gefällt mir, übertrag es auf die anderen Apps»). Optik-Schnellweg, kein Verhalten.

## Was geändert wurde (zwei Dateien)

**1. `public_html/includes/header.php` — cf-fine.css als letztes Stylesheet.**
Reihenfolge jetzt: `style.css`, `mobile.css`, `hub-kit.css`, eigene Dialog-/Menü-Dateien,
**dann** `hub_asset('shared/cf-fine.css')`. Eingebunden über den Hub, **nicht** mitgeführt
(eine Quelle, DESIGN.md). Der Aufruf steht wie die übrigen Hub-Einbindungen hinter
`function_exists('hub_asset')`: ohne `shared/bootstrap.php` bliebe die Seite sonst mit
einem Fatal Error stehen statt nur ungeschminkt.

**2. `public_html/assets/css/style.css` — vier kleine Eingriffe.**

| Punkt | vorher | nachher |
|---|---|---|
| 4 Fliesstext | nur `html, body { font-size: 16px }` | `.container { font-size: 0.875rem }` |
| 2 Reiter | `.page-tabs { gap: 4px; margin: 20px 0 4px }` | nur noch `display:flex` + Trennlinie |
| 2 Reiter | `.page-tab-btn { padding: 10px 16px; font-size: 0.9rem }` | beides raus (kommt aus cf-fine) |
| 2 Reiter | `.page-tab-btn.active { … font-weight: 600 }` | Gewicht raus (cf-fine setzt 700) |
| 3 Knopfbreite | `.form-card .button { width: 100% }` | Breite raus — Knopf so breit wie sein Text |
| 2 Titel | `.form-card h1 { font-size: 1.4rem }` | raus (Grösse aus cf-fine) |

**Zur Schriftgrösse, dem einzigen Eingriff mit Reichweite — zwei Entscheide dahinter:**

1. **Nicht an der Wurzel.** `html` bleibt bei 16px. Hätte ich dort verkleinert, wäre jedes
   `rem` im ganzen Haus mitgewandert — auch die in cf-fine.css, die auf eine 16px-Wurzel
   gerechnet sind; das Ergebnis wäre zweimal verkleinert gewesen.
2. **Nicht an `body`, sondern am Inhaltsrahmen `.container`.** Den tragen genau die Seiten
   mit Fliesstext (Bühne, Einstellungen, Profil, Vorlagen, An-/Abmeldung, Import, Export).
   **Editor und Präsentationsansichten benutzen ihn nicht** (gemessen: `editor.php` und
   `present.php` 0 Treffer) — deren Werkzeugflächen behalten damit ihr eigenes Mass. Das ist
   Absicht: ein Menüband ist kein Fliesstext, und „nichts am Verhalten ändern" heisst für
   mich auch, die Arbeitsfläche nicht ungefragt zu verkleinern. Soll es doch überall gelten,
   ist es eine Zeile.

## Abnahme: Bildschirmfotos vorher/nachher

Aufgenommen an einer **lokalen Prüfkopie** (Schattenbaum im Scratchpad, eigene Demo-Daten
über `scripts/seed_demo.php`, `php -S` mit `CHURCHFORGE_HUB_URL` auf denselben Server,
`hub-kit.css` und `cf-fine.css` vom Hub-Stand **220**, Chromium ohne Oberfläche).
**Nichts davon lief gegen Produktion**, und beide Serien unterscheiden sich **nur** durch
die zwei geänderten Dateien.

Ablage: `slideforge/_design-2026-10-17/{vorher,nachher}/`.

| Bild | zeigt | was sich ändert |
|---|---|---|
| `01-liste.png` | Bühne mit Präsentationen (**Liste**) | Titel „Deine Bühne" kleiner, Reiter Aktiv/Archiv feiner, Hinweiszeilen kleiner |
| `02-formular.png` | Einstellungen (**Formular**) | Seitentitel kleiner, Reiterreihe enger und feiner, Beschriftungen kleiner |
| `03-anmeldung.png` | Anmeldekarte (**Formular**) | **Knopf „Anmelden" nicht mehr über die ganze Karte**, sondern so breit wie sein Text |
| `04-dialog.png` | „Neue Präsentation" (**Dialog**) | Titelzeile und Beschriftungen feiner; Knöpfe im Fuss unverändert |
| `05-editor.png` | Editor | **unverändert** — er trägt `.container` nicht |

Zum Editor-Bild ehrlich dazu: Die lokale Prüfkopie lädt das Menüband nicht (die Leiste
bleibt in **beiden** Serien leer). Das Bild belegt deshalb nur, dass Kopfleiste und
Seitenrahmen gleich bleiben — **nicht**, dass das Menüband unberührt ist. Letzteres ist
über den Bauweg belegt: die Schriftregel hängt an `.container`, und `editor.php` benutzt
den nicht (0 Treffer).

## Zwei Funde, die nicht in diesem Auftrag stecken

**1. Unsere 144 Knöpfe erreicht cf-fine.css nicht.** Die Datei stylt `.hub-btn`, `.btn` und
die Aliase `.cf-btn`/`.cf-card`/`.cf-field`. SlideForge nennt seinen Knopf aber `.button`
(144 Stellen in 14 Dateien) und hat **kein** `.btn`, kein `hub-btn`, kein `hub-admin-field`.
Darum sehen „Speichern", „Bearbeiten" und „+ Neue Präsentation" weiterhin satt gefüllt aus,
während Titel, Reiter und Fliesstext schon fein sind — gut sichtbar in `01-liste.png` und
`02-formular.png`.

Drei Wege, mein Vorschlag zuerst:
- **a) Eine Zeile im Hub:** `.button` in die Alias-Liste von `cf-fine.css` aufnehmen. Trifft
  jedes Modul, das seinen Knopf so nennt, und wir bekommen **keine** eigene Übersteuerung —
  genau das, was Punkt 2 der Vorgabe will. Entscheid liegt beim Hub, nicht bei mir.
- b) `cf-btn` zusätzlich ins Markup schreiben (144 Stellen, dazu die in JS erzeugten).
- c) Eigene Brücken-CSS — widerspricht Punkt 2, darum nicht.

**Achtung bei a) und b):** Unsere Abwandlungen `.button-ghost`, `.button-danger` und
`.button-small` würden von `.cf-btn` überschrieben; die **rote** Löschen-Farbe ginge dabei
verloren. Das will vorher angeschaut sein — ich habe es bewusst **nicht** im Alleingang
gemacht.

**2. Punkt 3 und das Menüband.** Im Editor sind Dutzende Knöpfe `width:100%` — sie füllen
dort **bewusst** ihre Rasterzelle (Menüband wie in Office). Ich habe sie **nicht** angefasst:
Das wäre keine Optik, sondern ein Umbau des Werkzeugs, und die Vorgabe sagt „nichts am
Verhalten ändern". Die echten Punkt-3-Fälle ausserhalb des Editors waren zwei; der sichtbare
(Anmeldekarte) ist erledigt, der zweite (`.present-local-btn` in der schmalen
Referentenleiste) fällt unter „bewusst im schmalen Fuss" und bleibt.

## Stand

**Nichts ausgeliefert.** Geändert sind nur die zwei Dateien im Arbeitsbaum; Prod läuft
unverändert auf ASSET_VERSION 677. Für eine Auslieferung braucht es die Dauer-Go-Kette
(Review, Vorher-Digest gegen Live, Sicherung, Takt-Probe, Rückbau-Satz) **und** eine
Anhebung von `ASSET_VERSION`, weil `style.css` mit `?v=` ausgeliefert wird.

---

# Nachtrag 17.10. abends: Paket 1 auf dem Prod-Stand, gemessen statt geschätzt

## Der Diff gegen HEAD war die falsche Messlatte

Das Review las „viel Fremdes" in meinen zwei Dateien. Das kommt **nicht** von mir, sondern
vom bekannten Zustand **Prod ≠ HEAD** (mein Briefing, erster Punkt: der `pre-push`-Hook hat
monatelang den ganzen Arbeitsbaum ausgeliefert, nicht Commits). Gemessen gegen die
**Live-Dateien**, die ich mir dafür geholt habe:

| Datei | live vorher | Umfang meiner Änderung gegen live | Soll nachher |
|---|---|---|---|
| `public_html/includes/header.php` | `a3dc77a7…` | **1 Stelle**, 10 Zeilen — nur der cf-fine-Block | `92dc2aa7…` |
| `public_html/assets/css/style.css` | `507a47d1…` | **5 Stellen**, 26 Zeilen (4 Optik + 1 Abstand, s. u.) | `b34c84d1…` |
| `config.php` | `6cbaa219…` | **1 Stelle**: ASSET_VERSION 677 → **678** | `10b4b855…` |

Nichts Fremdes also, sobald man gegen den Stand misst, der wirklich läuft. Die übrige WIP im
Arbeitsbaum bleibt unberührt und geht nicht mit.

## Editor und Präsentation geprüft — und zwar gemessen

`cf-fine.css` wird über `includes/header.php` eingebunden, den auch `editor.php` und
`present.php` laden. Geprüft, welche ihrer Haken dort überhaupt vorkommen (echte
Klassen-Token, nicht per Textsuche — `ribbon-grid-btn` enthält „btn", ist aber keines):

- **`.btn` kommt in SlideForge nirgends vor.** Die Menüband-Knöpfe heissen `.ribbon-*` und
  werden von cf-fine **nicht** berührt. (Meine erste, zu grobe Suche hatte hier Treffer
  gemeldet — das war der Bindestrich, nicht die Klasse.)
- Erreicht werden genau: die Knöpfe und Felder der drei **Hub-Bibliotheken**, die wir
  mitführen (`hub-confirm.js`, `hub-secure-delete.js`, `hub-float-dialog.js` → `hub-btn`,
  `hub-btn--danger/--ghost/--primary`, `hub-admin-field`) und die **Reiterreihe, die
  `editor.js` baut** (`page-tabs`/`page-tab-btn`). Beides soll fein werden — das ist der Zweck.
- Überschriften: gemessen mit `getComputedStyle` an der Stilprobe, je einmal mit und ohne
  cf-fine.

| Element | ohne cf-fine | mit cf-fine |
|---|---|---|
| `h1` (Seitentitel) | 32px | **20.8px** |
| schlichtes `h2` | 24px, Abstand oben 0 | 16.32px, Abstand oben **25.6px** |
| schlichtes `h3` | 18.72px, Abstand oben 0 | 14.4px, Abstand oben **17.6px** |
| `h2.props-side-panel-title` (Editor-Seitenleiste) | 14.72px, 0 | **unverändert** 14.72px, 0 |
| `h3.present-config-section-title` (Werkzeugfenster) | 11.52px, 0 | 11.52px, **0 nach der Korrektur** |

**Die eine Korrektur, die daraus entstand:** `h3.present-config-section-title` setzte nur
`margin-bottom`. Der Abstand **oben** kam damit neu aus cf-fine (17.6px) und hätte die
Titelzeile in den Werkzeugfenstern vom Block darüber weggerissen. Jetzt steht dort
ausdrücklich `margin-top: 0`. Die Titel der Editor-Seitenleiste brauchten das nicht, sie
setzen bereits `margin: 0` — darum sind sie unverändert.

**Gegenprobe, damit die Nullmessung etwas wert ist:** Dieselbe Messung am schlichten `h2`
und `h3` schlägt an (0 → 25.6px bzw. 17.6px). Die Null bei unseren beiden Titelklassen ist
also ein Ergebnis, kein stummer Fehler.

## Stilprobe

`_design-2026-10-17/stilprobe-{ohne,mit}-cf-fine.png` zeigt alle betroffenen Bauteile
nebeneinander — Überschriften, Seitenleisten-Titel, Werkzeugtitel, Dialogtitel, Reiter,
unsere Knöpfe heute und dieselben Knöpfe mit Hub-Klassen (Vorschau auf Paket 2: Rahmen statt
Füllung, Löschen **rot getönt** statt satt rot).

## Auslieferung Paket 1 (17.10., nach Freigabe des Koordinators)

Vorher-Digest auf Prod geprüft und **getroffen**: `a3dc77a7…` / `507a47d1…` / `6cbaa219…`,
ASSET_VERSION 677. Sicherung: `.sicherung/design-20261017-215317/vorher.tgz` (drei Dateien).
Geliefert mit `scp -p`, je Datei einzeln.

| Datei | Soll | auf Prod nachher |
|---|---|---|
| `public_html/includes/header.php` | `92dc2aa7…` | **`92dc2aa7…`** |
| `public_html/assets/css/style.css` | `b34c84d1…` | **`b34c84d1…`** |
| `config.php` | `10b4b855…` | **`10b4b855…`** (ASSET_VERSION **678**) |

Takt-Probe von aussen, mit Cache-Umgehung:
- `sw.php` → `const CACHE = 'slideforge-678'`.
- `register.php` → die **letzte** eingebundene Stilvorlage ist
  `https://hub.bkbiel.ch/shared/cf-fine.css?v=222`, davor Kit und eigene Dateien; keine
  PHP-Meldung in der Ausgabe.
- `assets/css/style.css?v=678` → 200, 289541 B, sha256 **`b34c84d1…`** — die ausgelieferten
  Bytes, nicht nur die Datei auf der Platte; `font-size: 0.875rem` darin enthalten.
- `https://hub.bkbiel.ch/shared/cf-fine.css` → 200, 12193 B, `text/css`.

**Rückbau in einem Satz:** `tar xzf .sicherung/design-20261017-215317/vorher.tgz` im
Wurzelverzeichnis von `/data/slideforge` **und** `ASSET_VERSION` danach auf **679** setzen
(nicht zurück auf 677 — sonst liefern die Browser die neue Datei aus dem Zwischenspeicher
weiter aus).

## Nachgeprüft: die per JavaScript erzeugten Überschriften

Verlangt war eine Sicht auf Editor-Seitenleiste und Werkzeugfenster. Vier Überschriften
entstehen erst im Browser; alle vier sind gegen die neue `h2`/`h3`-Luft abgesichert, und
zwar durch eine **eigene Regel mit höherer Spezifität**:

| erzeugt in | Element | Regel, die gewinnt |
|---|---|---|
| `editor.js` | `h3.element-links-modal-col-title` | unsere, `margin: 0 0 10px` |
| `ribbon-customize.js` | `h2.sf-dialog-title` | unsere, `margin: 0` |
| `hub-secure-delete.js` | `h3.hub-secure-delete__title` | Hub-Kit, `margin: 0 0 0.65rem` |
| `hub-confirm.js` | `h2` **ohne Klasse** | Hub-Kit, `.hub-confirm-dialog h2 { margin: 0 … }` |

Der letzte Fall ist der einzige ohne eigene Klasse — er hängt an der Regel des Kits. Sie ist
spezifischer als das schlichte `h2` aus cf-fine, also trägt sie; und weil der Hub dieselbe
Reihenfolge fährt, sieht dieser Dialog bei uns aus wie dort.
