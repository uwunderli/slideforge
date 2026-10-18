# Programm-Dock auf den Hub-Kanon: Favoriten statt aller Module (U50)

**Datum:** 18.10.2026 · **Stand:** gebaut, geprüft, **nicht ausgeliefert**
**Go:** Urs, 18.10.2026, Wortlaut «U50 → passt» (neues schlichtes Design) und
«U50 → Launcher ja» (Launcher mit Favoriten, Ziehen/Ablegen, übernehmen), weitergegeben durch
den Koordinator. Umfang: Launcher bauen/übernehmen im SlideForge-Modul nach Entwurf.
**`src/SharedAuth.php` bleibt gesperrt** (dafür kein Go) — nicht angefasst.

## Vorlage

Der Entwurf ist die Fassung des Hubs, `hub/src/Launcher.php`: das Dock zeigt das Hub-Zeichen
und danach **nur die Favoriten** der Person, in der Reihenfolge des Hubs; das Gefäss trägt
`data-hub-fav-dock`, das Hub-Zeichen `data-hub-home`, jeder Eintrag `data-module-id`.
Unsere Fassung zeigte dagegen **alle** sichtbaren Module und benutzte die Favoriten nur zum
Sortieren.

## Was geändert ist

**`src/Launcher.php`** (3 Stellen Markup, 1 Stelle Logik, 1 Abgleich):

- Dock: `<div class="cf-launcher-dock" role="list" data-hub-fav-dock>`
- Hub-Zeichen: zusätzlich `data-hub-home draggable="false"`
- Einträge: zusätzlich `data-module-id="<kennung>"`
- `modulesForUser()`: gibt die Favoriten in der Reihenfolge des Hubs zurück. Sind die
  Favoriten **unbekannt**, bleibt die alte Anzeige (alles Sichtbare, nach `sort`) — damit das
  Dock nie leer dasteht, nur weil die Quelle fehlt.
- `opensInNewTab()` und `moduleHref()`: `paperless` zur Typenliste ergänzt, wie im Hub
  (`groupoffice`, `nextcloud`, `paperless`). Vorher wären Paperless-Module bei uns eingebettet
  und im Hub in einem neuen Reiter geöffnet worden.

- `userMaySee()`: die Kennung für eigene (`managed`) Programme kommt jetzt aus
  `HubFavorites::userKey()` statt aus `UserPrefs::userKey()` — die Klasse gibt es in der
  FolienSchmiede nicht, die Kennung war also immer leer und eigene Programme hier **nie**
  sichtbar. Mit dem Favoriten-Dock wäre das aufgefallen: ein eigenes Programm als Favorit
  wäre verschwunden (Befund 3 des Reviews).

**`src/HubFavorites.php`** (neu, 167 Zeilen): holt die Favoriten **nur lesend** aus dem Stand
des Hubs.

- Kennung wie `UserPrefs::userKey()` im Hub: Personen-Nummer aus dem SharedAuth-Cookie
  (`sub`, sonst `ct_person_id`/`person_id`), sonst `u:<Benutzername>`.
- Zweitanmeldung derselben Person über `identity-links.json` auf das Hauptkonto, wie
  `IdentityLinks::canonical()`.
- Favoriten aus `user-prefs.json`, **nur der eigene Eintrag**, Werte gefiltert (leer, doppelt,
  verschachtelt, nicht-Text fliegen raus).
- Rückgabe `null` = unbekannt: nicht angemeldet, Datei fehlt, nicht lesbar, kaputtes JSON,
  Datei grösser als 4 MB → alte Anzeige. Rückgabe `[]` = keine Favoriten **oder** Person im
  Hub-Stand noch nicht geführt → Dock mit nur dem Hub-Zeichen, genau wie im Hub.
- Der Datenordner ist `dirname(churchforge_modules_file())` — derselbe Ordner, in den der Hub
  schreibt. Keine eigene Herleitung, damit `CHURCHFORGE_MODULES_JSON`, `CHURCHFORGE_HUB_DATA`
  und der Standort (gemeinde / feuerwehr / homeoffice) gelten wie im Rest der Kette.
- **Schreibt nie.** `user-prefs.json` hält die Einstellungen aller Personen; der Hub schreibt
  sie mit Sperre über `HubJsonStore`. Ein zweiter Schreiber ohne diese Sperre könnte fremde
  Einstellungen verlieren.

## Unabhängiger Review und die zwei Auflagen

Urteil: **lieferbar mit Auflagen**. Beide Auflagen sind umgesetzt, dazu alle vier
Empfehlungen:

1. **Auflage: `null` nur bei «Datei nicht lesbar/kaputt».** Vorher gab ein fehlender Eintrag
   der Person `null` → alle Module, während der Hub für dieselbe Person nur das Hub-Zeichen
   zeigt. Das war unstetig: wer nie etwas gestellt hat, hätte alles gesehen — und sobald im
   Hub irgendeine **andere** Einstellung (Thema, Sprache) einen Eintrag anlegt, wäre das Dock
   ohne Zutun auf leer gekippt. Jetzt: fehlender Eintrag = keine Favoriten, wie im Hub.
2. **Auflage: Datenordner aus `dirname(churchforge_modules_file())`** statt eigener Herleitung
   mit festgeschriebenem «gemeinde» (sonst greift die Umstellung auf anders eingerichteten
   Installationen still nicht).
3. Empfehlung umgesetzt: Kennung für `managed`-Module (oben), der tote `UserPrefs`-Rückfall
   ist weg (er hätte, wenn er lebte, genau den Rückfall ausgehebelt), Typprüfung gegen
   verschachtelte Werte, 4-MB-Grenze beim Lesen, Kommentar zur Kennung präzisiert.

Bestätigt hat der Review: die Kennungsbildung stimmt für jede realistische Cookie-Form mit
dem Hub überein (SharedAuth normalisiert die Nummer schon beim Ausstellen; lokale Konten haben
`u_<hex>` und landen bei beiden auf `u:<Benutzername>`), es gibt keinen Weg, einen fremden
Schlüssel zu lesen, keinen Schreibpfad, **keinen XSS-Pfad** (kein Wert aus `user-prefs.json`
erreicht das Markup; die Kennungen dienen nur zum Nachschlagen gegen `modules.json`, ausgegeben
wird über `h()`), und der Zwischenspeicher ist richtig (`launcher.php` wird genau einmal
eingebunden).

## Prüfung: 25 Fälle, alle grün

Prüfskript mit Attrappen für `SharedAuth` und einen eigenen Sandkasten als Hub-Datenordner
(kein Hub, keine Anmeldung nötig):

Das Prüfskript liegt als `_pruefungen/u50-launcher-favoriten.php` im Repo (ausserhalb der
Positivliste der Auslieferung, geht also nicht mit auf Prod) und läuft ohne Hub und ohne
Anmeldung, mit Attrappen und einem eigenen Sandkasten als Hub-Datenordner:
`php _pruefungen/u50-launcher-favoriten.php`.

1. Favoriten bekannt → nur die Favoriten, in der Reihenfolge des Hubs
2. Favorit, den die Person nicht sehen darf → fällt weg; mit der Gruppe → wieder dabei
3. Leere Favoritenliste → leeres Dock (wie im Hub)
4. Person im Hub-Stand nicht geführt → leeres Dock; Eintrag ohne `favorites` → leeres Dock
5. Zweitkonto → Favoriten des Hauptkontos
6. Kein Hub-Datenordner → alte Anzeige
7. Nicht angemeldet → unbekannt
8. Markup: `data-hub-fav-dock`, `data-hub-home` (genau einmal), `data-module-id`
9. Kaputtes JSON → alte Anzeige; `favorites` kein Feld → leeres Dock; leere/doppelte/
   unbekannte Einträge gefiltert; verschachtelter Wert → **keine** Warnung und nur die
   brauchbaren Einträge; Datei über 4 MB → alte Anzeige
10. Eigenes (`managed`) Programm mit `owner` = Hub-Kennung ist dabei; das fremde bleibt weg
11. **Gar keine Hub-Daten (der heutige Prod-Fall):** keine Module, genau **ein** Eintrag im
    Dock (das Hub-Zeichen), kein `data-module-id`, Gefäss und Hub-Zeichen stehen trotzdem —
    keine Meldung, kein Fehler.

`php -l` auf beide Dateien ohne Beanstandung.

## Zwei Nebenbefunde, die nicht zu diesem Paket gehören

- **`ini_set('display_errors', '1')` in `config.php:57`** gilt auch auf Prod; der Kommentar
  daneben sagt selbst «Für den Produktivbetrieb display_errors ganz auf 0 setzen». Darum hätte
  ein verschachtelter Wert in den Favoriten eine sichtbare PHP-Warnung im Seitenkopf erzeugt
  (reproduziert im Review). Die Warnung ist jetzt abgefangen, die Einstellung bleibt — sie zu
  ändern ist ein eigener Entscheid, betrifft jede Seite und gehört nicht in dieses Paket.
- **`shared/src/Launcher.php` ist eine dritte Fassung, die niemand lädt.** Der Review nahm an,
  jedes andere Modul zeige damit ein leeres Dock (sie hat die Favoriten-Logik, aber keinen
  Leser: `class_exists('UserPrefs')` ist dort falsch → `[]` → `return []`). Nachgemessen
  stimmt das nicht: `shared/bootstrap.php` lädt den Launcher ausdrücklich **nicht**
  («Launcher: bewusst nicht hier»), kein Modul verweist auf `shared/src/Launcher.php`, und
  ausser Hub und FolienSchmiede hat **kein** Modul einen eigenen Launcher oder benutzt
  `cf-launcher` überhaupt. Die Datei liegt also nur herum und läuft Gefahr, beim nächsten Mal
  für den Stand gehalten zu werden — aufräumen oder nachziehen ist Hub/shared-Arbeit, nicht
  meine; dem Koordinator gemeldet.

## Der Haken: auf Prod liegen die Hub-Daten nicht

Von bkb-srv10 (nur gelesen, 18.10.): im Container `nginx-Php8-slideforge` sind **nur**
`/var/www/html` und `/var/www/shared` eingehängt; `/var/www/hub` gibt es nicht, und
`CHURCHFORGE_HUB_DATA`, `CHURCHFORGE_MODULES_JSON`, `CHURCHFORGE_SHARED_PATH` sind nicht
gesetzt. Beide Wege zu den Hub-Daten laufen über `dirname('/var/www/shared') . '/hub/data/gemeinde'`.

Folge, und sie betrifft nicht nur diese Änderung: **das Modul liest heute schon keine
`modules.json`.** Im Alleinbetrieb auf `slides.bkbiel.ch` zeigt das Dock also auch jetzt nur
das Hub-Zeichen. In der Hub-Hülle (`?hub_theme=`) rendert ohnehin der Hub sein eigenes Dock
(`public_html/includes/launcher.php` hört dort auf).

Darum: **U50 ist gebaut und geprüft, wirkt aber erst, wenn der Hub-Datenordner lesend
eingehängt wird** (beim Koordinator als U67, braucht Urs' Go — neue Infrastruktur).
Die Änderung ist genau darauf vorbereitet: ohne Hub-Daten verhält sie sich wie bisher
(Fall 10 oben), mit Hub-Daten zeigt sie die Favoriten.

Auf der Demo (`wunder.li`, eingefroren, nur gelesen): `/data/www/hub/data/gemeinde/modules.json`
liegt dort, **`user-prefs.json` nicht**. Dort greift also der Rückfall «Favoriten unbekannt →
alles Sichtbare» — die Demo sieht aus wie heute.

## Was ich nicht gebaut habe, und warum

**Ziehen/Ablegen im Modul-Dock.** Der Hub speichert die Reihenfolge über
`/api/user-prefs.php` — Sitzung, CSRF, gleiche Herkunft. Von `slides.bkbiel.ch` ist das nicht
erreichbar; das Modul hat kein Modul-Token, und von aussen in `user-prefs.json` zu schreiben
lehne ich ab (siehe oben). Ein Ziehen, das nichts speichert, wäre eine Lüge an der
Oberfläche. Das Dock zeigt darum die Favoriten in der Reihenfolge des Hubs; gepflegt werden
sie dort (Stern an der Kachel, Ziehen/Ablegen). Für Ziehen/Ablegen auch im Modul bräuchte es
einen Hub-Endpunkt für Module — als Wunsch beim Koordinator aufgenommen, Entscheid 18.10.:
nicht bauen, Favoriten in Hub-Reihenfolge reichen vorerst.

## Auslieferung

Noch nicht geschehen. Vorbereitet:

- Lieferumfang: `src/Launcher.php` (geändert) und `src/HubFavorites.php` (neu). Zwei Dateien,
  kein CSS, kein JS — **kein `ASSET_VERSION`-Sprung nötig**, weil keine statische Datei
  dazukommt oder sich ändert; Markup hängt nicht an `?v=`.
- Vorher-Digest auf Prod geprüft und **wie erwartet**:
  `src/Launcher.php` = `be04edb37cbce632bf6e46a3863877f8d1dfed68b7fff4bdf7d6f50ab0c7f1d1`
  (gleich dem Arbeitsbaum vor der Änderung), `src/HubFavorites.php` existiert dort nicht.
- Sicherung vor der Lieferung nach `.sicherung/launcher-favoriten-<stempel>/vorher.tgz`.
- Nicht in `main` gepusht (Push-Sperre bzw. Lieferbasis, nie der `main`-Kopf).

> **Rückbau-Satz:** `src/Launcher.php` aus `.sicherung/launcher-favoriten-<stempel>/vorher.tgz`
> zurücklegen (Prüfwert `be04edb37cbce632…`) und `src/HubFavorites.php` auf Prod entfernen —
> damit ist der Stand von vor der Lieferung wiederhergestellt; es gibt keine Daten, die
> mitgewandert wären.
