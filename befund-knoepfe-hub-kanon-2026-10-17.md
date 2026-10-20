# Befund: Knöpfe auf den Hub-Kanon (Paket 2 des schlichten Designs)

SlideForge, 17.10.2026 spät. Auftrag über den Koordinator (Urs 17.10., Vorgabe
`_berichte/hub/vorgabe-schlichtes-design-apps-2026-10-17.md`): Paket 2 — Markup additiv auf
`hub-btn` umstellen, eigene `.button*`-Regeln für Grösse/Füllung/Rundung/Rahmen entfernen,
cf-fine.css zuletzt (schon aus Paket 1), Gegenprobe vorher/nachher, ASSET_VERSION anheben.
Menüband und Editor-Raster werden **nicht** umgebaut.

## Was geändert wurde

**1. Markup: ergänzt, nichts ersetzt** (180 Klassenlisten in 21 Dateien)

| vorher | nachher |
|---|---|
| `button` | `button hub-btn hub-btn--primary` |
| `button button-sm` | `… hub-btn hub-btn--primary hub-btn--sm` |
| `button button-ghost` | `… hub-btn hub-btn--ghost` |
| `button button-ghost button-sm` | `… hub-btn hub-btn--ghost hub-btn--sm` |
| `button button-danger …` | `… hub-btn hub-btn--danger` |
| `button button-primary …` | `… hub-btn hub-btn--primary` |

Die alten Klassen bleiben stehen, darum laufen alle JS-Selektoren auf `.button`, `.button-sm`
usw. unverändert weiter. Der satt gefüllte Knopf von heute ist die Hauptaktion, er wird nach
Vorgabe «getönt, nicht satt», also `hub-btn--primary`. Erfasst ist auch der eine per JS gesetzte
Knopf (`editor.js:926`, `className`). Gegenprobe: eine Suche über alle `class`-Attribute in
`public_html/**/*.php|js` findet **0** Knöpfe mit `button`, aber ohne `hub-btn`.

**2. Eigene Regeln zurückgenommen** (`public_html/assets/css/style.css`, 7 Stellen)

- `.button`: Füllung, Schriftfarbe, Gewicht, Rahmen, Polsterung, Rundung, Schriftgrösse und der
  Übergang raus; `display:inline-flex`, `align-items`, `gap`, `cursor` (Layout) bleiben.
- `.button:hover`: der eigene 3px-Ring raus, nur `text-decoration:none` bleibt.
- `.button-ghost` und `.button-ghost:hover`: ganz raus (nur `a.button-ghost:hover` bleibt wegen
  der Unterstreichung).
- `.button-danger`, `.button-sm`: ganz raus.
- `.button.icon-only`: Polsterung raus (die Regel wird im Markup nirgends benutzt).
- `.pixabay-card-actions .button`, `.media-library-actions .button`: Schriftgrösse und Polsterung
  raus, `flex`/`width` (Layout) bleiben.
- `.form-card .button`-Breite war schon in Paket 1 weg; zusätzlich jetzt das letzte
  `style="width:100%"` an einem Knopf auf einer normalen Seite (`templates.php`, Import in einen
  Layoutsatz). Der Löschknopf im Editor-Seitenfuss (`editor.php`, `width:100%`) bleibt, das ist
  die schmale Spalte im Editor, die nicht umgebaut wird.

**Absichtlich stehen geblieben** (höhere Spezifität, Touch-Grössen in `mobile.css`):
`.mobile-pres-item .button`, `.present-remote-nav .button` (64px bzw. 92px auf dem Tablet),
`html.sf-tablet body.present-mode .button-sm` (48px). Auf dem Handy und am Tablet bleiben die
Flächen also gross genug zum Tippen; nur Füllung und Rahmen kommen neu aus dem Kanon.

**3. `config.php`: `ASSET_VERSION` 678 → 679.**

## Gegenprobe (gemessen, nicht geschätzt)

Prüfstand: Kopie des Codes, eigener PHP-Server, Chromium mit frischem Profil je Aufnahme
(ohne das lief der Browser in seinen eigenen Zwischenspeicher und zeigte alte Stile — einmal
beinahe ein falscher Befund). cf-fine.css frisch vom Hub geholt (12593 B,
`9602464ed600951d16e836bae9b6a557…`); die lokale Kopie im Prüfstand war vom Nachmittag und
kannte `hub-btn--sm` noch nicht.

Gemessene Werte nachher (`getComputedStyle`):

| Knopf | Höhe | Schrift | Fläche | Rahmen/Rundung |
|---|---|---|---|---|
| Hauptaktion (`--primary`) | 32px | 13.44px / 600 | Akzent 16% getönt | 1px / 9px |
| Abbrechen (`--ghost`) | 32px | 13.44px / 600 | durchsichtig | 1px / 9px |
| Klein (`--sm`) | **26px** | **12.48px** | Akzent 16% getönt | 1px / 8px |
| Löschen (`--danger`) | 32px | 13.44px | Rot 12% getönt, rote Schrift | 1px / 9px |

Damit ist erfüllt, was der Koordinator als Gegenprobe verlangt hat: Löschknopf rot getönt,
Hauptknopf getönt, kleine Knöpfe kleiner.

Bilder (Prüfstand, je vorher/nachher): Liste, Formular, Anmeldung, Dialog, Editor, Stilprobe in
`_design-2026-10-17/knoepfe-{vorher,nachher}/`. Der Editor sieht vorher wie nachher gleich aus —
im Prüfstand zeichnet das Menüband nicht, und an seinem Raster wurde nichts geändert.

## Auflagen aus dem Review (17.10., Koordinator) — beide erfüllt

**Auflage 1: Rückfall, wenn der Hub ausfällt.** In `style.css` steht jetzt ein kleines, neutrales
Ersatz-Aussehen für `.hub-btn`, `--primary`, `--ghost`, `--danger`, `--sm` (Rahmen, Rundung,
Fläche, Schriftgrösse, Gewicht, Höhe, Polsterung, Cursor). Es steht in derselben Spezifität und
wird **vor** Kit und cf-fine geladen, der Hub-Kanon überstimmt es also immer, sobald er da ist.
Probe: `15-stilprobe-ohne-hub.png` zeigt die Seite mit abgeschaltetem cf-fine — alle Knöpfe haben
Rahmen und Tönung, der «Weiter»-Knopf der Fernsteuerung ist in voller Grösse klar erkennbar.

**Auflage 2: Touch-Grössen am Handy.** In `mobile.css` neu:
`@media (max-width: 600px) { .hub-btn.hub-btn--primary, .hub-btn.hub-btn--danger { min-height: 44px } }`
— zwei Klassen, damit die Regel den Kanon (eine Klasse, später geladen) überstimmt. Gemessen bei
390px Breite: Hauptaktion 44px, Hauptaktion klein 44px, Löschen klein 44px, Fernsteuerung 64px;
bei 1400px unverändert 32/26/26/64px. Die Zweit-Knöpfe (`--ghost`) bleiben bei 32/26px, das sind
keine Hauptaktionen. In der Handy-Liste bleibt «Steuern» bei 44px wie bisher
(`.mobile-pres-item .button`), nur die Füllung ist jetzt getönt statt satt.

Zusätzliche Bilder dazu: `11-handy-liste`, `12-fernsteuerung`, `13-praesentation`,
`14-stilprobe-echt` (echtes Markup), `15-stilprobe-ohne-hub`, `16-stilprobe-handy`.
Einschränkung bei `12-fernsteuerung`: im Prüfstand zeichnet die Fernsteuerung nur den oberen
Teil, die Vorschau darin meldet sich nicht an. Die Knopfzeile ist darum **gemessen** statt
fotografiert — im DOM stehen die richtigen Klassen
(`button button-ghost hub-btn hub-btn--ghost` / `button hub-btn hub-btn--primary`), und
`14/15-stilprobe` zeigen dieselbe Zeile mit `mobile.css` in beiden Zuständen (64px).

## Was das für den Betrieb heisst

Das Aussehen der Knöpfe kommt im Normalfall allein von `cf-fine.css` vom Hub; fällt der Hub aus,
greift seit der Auflage unser eigener, schlichter Rückfall (siehe oben) — nackt sind die Knöpfe
nicht mehr. Für Urs bleibt ein Punkt: der «Weiter»-Knopf der Fernsteuerung ist jetzt getönt statt
satt blau — gross bleibt er, aber leiser. Wenn das im dunklen Saal zu wenig ist, sagen, dann
bekommt genau dieser Knopf eine Ausnahme.

## Stand und Auslieferung

Vor der Auslieferung gemessen: **alle 24 betroffenen Dateien auf Prod sind Zeichen für Zeichen
der Stand, auf dem diese Änderung aufsetzt** (`ssh web-bkb10`, sha256 je Datei gegen die lokale
Ausgangskopie — 24 von 24 gleich). Geliefert werden 24 Dateien.

Mit den beiden Auflagen ändert sich auch `mobile.css`, geliefert werden also **24 Dateien**.

Ausgangs-Digests (gekürzt): style.css `b34c84d1…`, mobile.css `e721cd0d…`, config.php `10b4b855…`,
index.php `0a14e9cf…`, editor.php `cc78ff4c…`.
Ziel-Digests (gekürzt): style.css `e3217f70…`, mobile.css `a96a4888…`, config.php `2a77f134…`,
index.php `f10537b0…`, editor.php `6f8a1f45…`. Vollständige Listen:
`scratchpad/knoepfe/{vorher,ziel}-digests.txt`.

**Rückbau:** Sicherung `.sicherung/knoepfe-<Zeitstempel>/vorher.tgz` auf dem Server auspacken
**und** `ASSET_VERSION` auf 680 setzen (nicht zurück auf 678 — sonst liefert openresty bis zu elf
Stunden lang die neue Datei unter der alten Nummer aus).

## Ausgeliefert

17.10.2026, nach Freigabe des Koordinators (Review ohne Blocker, Nachreview zu den zwei Auflagen
in Ordnung). Dauer-Go-Ablauf:

1. Ausgangsstand unmittelbar vor der Übertragung nochmals gemessen: 24 von 24 Dateien auf Prod
   zeichengleich mit meinem Ausgangsstand.
2. Sicherung auf dem Server: `.sicherung/knoepfe-20261017-233755/vorher.tgz` (216 628 B, 24 Dateien).
3. Übertragen in fünf Schritten nach Zielordner (13 Seiten, 7 Skripte, 2 Stile, present_ribbon.php,
   config.php) — je ein `scp -p`, keine Ketten.
4. Nachher-Stand gemessen: 24 von 24 gleich den Ziel-Digests.
5. Takt-Probe mit Cache-Brecher: `sw.php` → `slideforge-679`; im Kopf `style.css?v=679`,
   `mobile.css?v=679`, danach das Kit und als letztes `cf-fine.css` vom Hub (v=223);
   `style.css?v=679` → 200, 290 194 B, sha256 `e3217f70…`; `mobile.css?v=679` → 200, 20 139 B,
   sha256 `a96a4888…`.

**Rückbau (ein Satz):** `cd /data/slideforge && tar xzf .sicherung/knoepfe-20261017-233755/vorher.tgz`
und danach `ASSET_VERSION` auf **680** setzen (nicht zurück auf 678).

Was ich **nicht** prüfen konnte: eine angemeldete Seite live im Bild. Ich habe keine Zugangsdaten
für slides.bkbiel.ch und soll auch keine haben; `login.php` führt zum Hub-Anmeldefenster. Die
Abnahme am Bild steht also bei Urs — die Prüfstand-Bilder zeigen denselben Code, dieselben Stile
und dasselbe cf-fine vom Hub.

Hinweis des Koordinators zur Touch-Regel: sie trifft auch kleine Primär-/Danger-Knöpfe (--sm) am
Handy (44 statt 26 px). In unseren dichten Listen sind das fast nur Geister-Knöpfe, die bleiben
klein; betroffen sind «Bearbeiten» in der Liste (dort waren es ohnehin schon 44 px) und die
Speichern-Knöpfe in Formularen. Wenn es irgendwo zu wuchtig wirkt, wird die Regel auf
`:not(.hub-btn--sm)` eingeschränkt — kleiner Folgecommit, keine neue Runde.
