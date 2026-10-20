# `hub-float-dialog.js` neu vendort: Geheimfelder nicht mehr in den Browser-Speicher (16.10.)

Auftrag vom Koordinator: Hub-Sicherheitsfix (Asset **213**) byte-gleich nachziehen.
Soll-sha256 **`76a5846f726afb5b17a713cab35203ec54b4455ed6af80320a7648ff91d38837`**.

Diese Datei wird in SlideForge über `sf_hub_asset('js/hub-float-dialog.js')` eingebunden,
und `sf_hub_asset()` nimmt **die mitgeführte Kopie, wenn sie da ist** — anders als beim
CSS-Kit ist die Kopie hier also die Fassung, die wirklich ausgeliefert wird. Darum trifft
der Fix SlideForge nur über diesen Nachzug.

## Vorprüfung: geht durch den Nachzug etwas Eigenes verloren?

Nein. Die Hub-Fassung (1202 Zeilen) ist gegenüber meiner (1001) ein **echter Obermenge**:

- Funktionsbestand verglichen: **jede** Funktion meiner Kopie existiert dort; neu sind
  `isSecretField`, `htmlWithoutSecrets`, `clampDragPosition`, `applyClampedPosition`,
  `beginDragUi`, `endDrag`, `leavePage`, `cssDim`.
- Meine eigene Korrektur vom 14.10. (Sperre in `removeDlg()`, damit per `bind()`
  angebundene Dialoge beim Schliessen nur versteckt und nicht aus dem Dokument genommen
  werden) ist **wörtlich** enthalten, mit Herkunftsvermerk auf die SlideForge-Kopie.
- Die Alt-Klassen unseres Markups (`.sf-dialog-header`, `.ribbon-customize-header`,
  `.sf-dialog-title`, `.sf-dialog-close`, `.ribbon-customize-close`) sind in der
  Hub-Fassung unverändert vorhanden (Zeilen 894/901/925).
- Eigene Haken sonst: keine.

## Prüfstand mit Gegenprobe (`scratchpad/devkey/pruefstand-dialog.mjs`)

Die Datei ist ein IIFE für den Browser; der Prüfstand schneidet die reinen Funktionen
(`SECRET_NAME_RE`, `persistableValue`, `fieldKey`, `isSecretField`, `captureValues`) aus
dem Quelltext und ruft sie mit Stellvertreter-Feldern auf. Je Fassung ein Lauf.

| Feld | alt (`b16524ae…`) | neu (`76a5846f…`) |
|---|---|---|
| `type=password`, Name `smtp_pass` | **IM SPEICHER** | nicht gespeichert |
| `autocomplete=new-password` | **IM SPEICHER** | nicht gespeichert |
| Textfeld `pixabay_api_key` | **IM SPEICHER** | nicht gespeichert |
| Textfeld mit `id="view_token"` | **IM SPEICHER** | nicht gespeichert |
| Feld in `[data-no-persist]` | **IM SPEICHER** | nicht gespeichert |
| normales Feld `titel` | im Speicher | im Speicher (richtig) |

Dazu nachgelesen, dass es **keinen** zweiten Schreibweg gibt: die beiden
`sessionStorage.setItem`-Stellen (Zeile 485/492) speisen sich allein aus `snapshotDlg()`,
und dort läuft der Rumpf durch `htmlWithoutSecrets()` und die Werte durch
`captureValues()`. `node --check`: sauber.

## Nebenwirkung, die man kennen muss: Persistenz ist jetzt Opt-in

Meine Kopie sicherte **jeden** Dialog (`persist: dlg.dataset.hubNoPersist !== '1'`), die
Hub-Fassung nur noch solche mit `data-hub-persist="1"` **und** verdrahtetem Binder
(Zeile 451). SlideForge setzt das **nirgends** — also wird ab jetzt **kein**
Dialoginhalt mehr im `sessionStorage` abgelegt. Sichtbar ist das nur so: Nach einem
Neuladen kommt ein offener Dialog (Import, Pixabay) nicht mehr mit altem Inhalt zurück.
Grösse und Lage der Dialoge bleiben, die hängen am `localStorage` und sind nicht betroffen.

## Vorher-Digest und Umfang der Auslieferung

| Datei | auf Prod vorher | Soll nachher |
|---|---|---|
| `public_html/assets/js/hub-float-dialog.js` | `b16524ae…` | `76a5846f…` |
| `config.php` | `b9aef049…` (ASSET_VERSION 676) | `6cbaa219…` (**677**) |

Die Versionsnummer muss mit, sonst holt der Browser die alte Datei weiter aus dem Zwischen-
speicher (openresty hält Statisches rund 11 h); `sf_hub_asset()` hängt `?v=ASSET_VERSION`
an. In `config.php` ändert sich **nur** diese eine Zeile.

**Rückbau:** `tar xzf .sicherung/<stempel>/vorher.tgz` im Wurzelverzeichnis von
`/data/slideforge` stellt beide Dateien wieder her; die Vorfassung liegt zusätzlich unter
`scratchpad/devkey/hub-float-dialog.js.vorher`.

## Auslieferung und Abnahme (16.10., nach Review des Koordinators)

Bedingung des Reviews zuerst erfüllt: `config.php` von Prod geholt (`b9aef049…`
bestätigt) und gegen meine Fassung gediffed — **genau eine** geänderte Zeile,
`define('ASSET_VERSION', '676')` → `'677'`. Kein anderer Unterschied, also kein Anhalten.

Sicherung: `.sicherung/float-dialog-20261016-111914/vorher.tgz` (drei Dateien,
`config.php` 21195 B, `hub-float-dialog.js` 35306 B, `hub-kit.css` 72790 B).
Geliefert mit `scp -p`, je Datei einzeln.

| Datei | Soll | auf Prod nachher |
|---|---|---|
| `public_html/assets/js/hub-float-dialog.js` | `76a5846f…` | **`76a5846f…`** |
| `public_html/assets/vendor/hub/css/hub-kit.css` | `821a122c…` | **`821a122c…`** |
| `config.php` | `6cbaa219…` | **`6cbaa219…`** |

Rechte und Eigentümer unverändert (`-rw-r--r-- admin admin`).

Takt-Probe von aussen, jeweils mit Cache-Umgehung:
- `sw.php` → `const CACHE = 'slideforge-677'`.
- `assets/js/hub-float-dialog.js?v=677` → **200**, 45308 B, sha256 **`76a5846f…`** —
  die ausgelieferten Bytes, nicht nur die Datei auf der Platte.
- **Prüfstand gegen genau diese heruntergeladenen Bytes** nochmals gelaufen: Passwort-,
  `api_key`-, `token`- und `[data-no-persist]`-Felder werden nicht gespeichert, das
  normale Feld schon.
- `register.php` → bindet `hub-float-dialog.js?v=677` ein, holt das Kit weiterhin vom Hub
  (`hub-kit.css?v=213`), keine PHP-Meldung in der Ausgabe.

Das mitgeführte Kit ist im selben Lauf byte-gleich nachgezogen (`821a122c…`, neuer Teil
`hub-list.css`); es ist der Rückfall für den Alleinbetrieb und auf Prod nicht im Spiel.
`hub-user-menu.js` bleibt offen (JS-Welle).

## Demo auf wunder.li: **nicht** betroffen, darum nicht geliefert

Freigabe des Koordinators lag vor (Urs' Dauer-Go für reviewte Auslieferungen an
wunder.li). Beim Vorher-Digest gegen Live fand ich aber etwas anderes vor als
beschrieben — und der Fall ist damit erledigt, nicht offen:

- Die Kopie dort ist **nicht** eine abweichende Fassung derselben Linie, sondern eine
  **ältere andere**: `1d5caa62…`, **479** Zeilen, vom 09.08. (Prod hatte 1001, der Hub
  jetzt 1202).
- Diese Fassung hat **keinerlei** `sessionStorage`-Benutzung: die einzige Schreibstelle im
  ganzen Datei (`setItem`, Zeile 52) legt **Grösse und Lage** im `localStorage` ab.
  `captureValues`, `values`, `innerHTML` kommen nicht vor.
- **Der behobene Fehler existiert dort also nicht.** Geheimnisse können nicht in den
  Browser-Speicher geraten, weil Dialoginhalte dort überhaupt nicht gespeichert werden.
- Die Passwortfelder der Demo (`admin_settings.php`: `smtp_password`, `lt_api_key`,
  `px_api_key`) stehen in einem gewöhnlichen Seitenformular, nicht in einem Schwebedialog.

Dazu käme ein Risiko ohne Gegenwert: `public_html/includes/header.php` bindet dort
`assets/js/hub-float-dialog.js?v=ASSET_VERSION` **direkt** ein (kein `sf_hub_asset()`) und
lädt noch `assets/css/hub-float-dialog.css` — eine der sieben Dateien, die auf Prod am
14.10. im Kit aufgegangen sind. Die 1202-Zeilen-Fassung in diesen zwei Monate alten Baum zu
legen, wäre ein Sprung über die ganze Zwischenzeit, ohne dass ich dort etwas am Bildschirm
prüfen kann. Gehört darum zum eigenen Auftrag «Demo auf den aktuellen Stand nachziehen»,
nicht in dieses Sicherheitspaket.
