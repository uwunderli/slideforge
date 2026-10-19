# Die Programm-Leiste kommt vom Hub — das Modul entscheidet nichts mehr

**Datum:** 19.10.2026 · **Einordnung: sicherheitsrelevant** (Sichtbarkeit, ausgehender Aufruf
mit Modul-Token, Fremddaten im Markup) · **Stand: gebaut und geprüft, nicht ausgeliefert.**

**Grundlage:** Regel Urs 19.10.2026 — «ACL's werden über die User definiert und stehen nicht
fest im Code. Wer was sehen darf, wird von Menschen! gesagt und nicht von der Maschine.»
Plan `plan-dock-vom-hub-2026-10-19.md` (Commit `0eeb474`), Entscheide des Koordinators vom
19.10. (ein Endpunkt, nur Favoriten, Token von einem Menschen, U67 entfällt).
**Vertrag: Fassung 2**, `_berichte/hub/vertrag-dock-2026-10-19.md`, sha256
`a60de895e9dae9244ac9e85f2ef57586170eb6cb724116153c9676cf3ca69d10` — Prüfwert gegengerechnet,
und die Gegenseite (`hub/public/api/dock.php`) gelesen: GET, nur Modul-Token, Zusatzrecht
nötig, `404` für unbekannte Person, Version als Fingerabdruck der ganzen Antwort, `ttl` 300.
Deckt sich mit dem Vertrag. (Fassung 1 trug `b9035dfe…`; ich habe den Wechsel an der Datei
bemerkt und die drei Änderungen eingebaut, bevor die Meldung des Hubs kam: `isAdmin` ist jetzt
dieselbe Definition wie in `acl.php` — eine Admin-Rolle allein zählt nicht —, `locked` steht
immer in der Antwort, und neu gibt es **503 `lock_unknown`**, zu behandeln wie «Hub nicht
erreichbar».)

## Was vorher falsch war

`src/Launcher.php` hat `modules.json` **selbst ausgelegt** und kannte dabei von den fünf Wegen
des Hubs (`hub/src/ModuleAccess.php`: persönliche App, Administrator, `allowUsers`,
`allowGroups`, `requireAnyGroup`) nur den letzten — plus einen festen Gruppennamen «Admin» und
bis zum 19.10. die Regel «kein Eintrag = für alle sichtbar». Das heisst im Klartext: **was Urs
in der Hub-Oberfläche freigibt, wäre in der Leiste der FolienSchmiede nicht erschienen**, und
was niemand freigegeben hat, wäre für alle sichtbar gewesen.

## Was jetzt gilt

**`src/HubDock.php`** (neu) fragt `GET /api/dock.php?for=<kennung>[&version=]` mit dem
Modul-Token und gibt die Antwort weiter. Die Kennung bildet es wie der Hub
(Personen-Nummer aus dem SharedAuth-Cookie, sonst `u:<Benutzername>`); die Auflösung eines
Zweitkontos macht der Hub.

**`src/Launcher.php`** (von 244 auf 90 Zeilen) zeichnet nur noch: Hub-Zeichen aus der Antwort,
danach die Einträge in der Reihenfolge der Favoriten, mit `data-hub-fav-dock`,
`data-hub-home`, `data-module-id` wie im Hub-Kanon. **Kein Zugriffsentscheid mehr im Modul**,
keine Tags, keine Gruppen, kein `modules.json`.

**`public_html/includes/launcher.php`** ruft `Launcher::render('slideforge')` — ohne Tags.
Unverändert bleiben die zwei Fälle davor: in der Hub-Hülle (`?hub_theme=`) zeichnet der Hub
sein Dock, und ohne Hub-Anmeldung gibt es das Solo-Zeichen.

**`src/HubFavorites.php` ist gelöscht.** Damit liest die FolienSchmiede nicht mehr in den
Dateien einer anderen Anwendung (`user-prefs.json`, `identity-links.json`) — das war die
schwächste Stelle am alten Entwurf. Die Favoriten kommen in derselben Antwort mit.

### Was passiert, wenn keine Auskunft kommt

Die Tabelle steht im Quelltext von `HubDock`, damit sie nicht auseinanderläuft:

| Lage | Anzeige |
|---|---|
| 200 mit Liste | diese Liste, diese Reihenfolge |
| 200, Liste leer | nur das Hub-Zeichen (die Person hat keine Favoriten) |
| 200 `unchanged` | der Zwischenspeicher, Frist neu gesetzt |
| `404 unknown_subject` | **unbekannt** → nur Hub-Zeichen; ausdrücklich **nicht** «darf nichts» |
| 401 / 403 | nur Hub-Zeichen, dazu ein Hinweis ins Protokoll (ohne Token-Wert) |
| Hub stumm, Speicher frisch | letzte bekannte Liste, bis `ttl` × 4 |
| Hub stumm, Speicher zu alt oder leer | nur Hub-Zeichen |
| kein Token im Modul | gar kein Aufruf, nur Hub-Zeichen |

**Nie «vorsichtshalber alles».** Das ist der Punkt, an dem die alte Fassung falsch lag.

### Verfügbarkeit

Zeitlimit **2 s** (Verbindung und gesamt), `CURLOPT_FOLLOWLOCATION` aus. Zwischenspeicher je
Person in `data/cache/hub-dock-<sha1>.json`, geschrieben über eine temporäre Datei mit
`rename`, damit nie ein halber Stand gelesen wird. Innerhalb der Frist (`ttl`, 300 s) geht
**kein** Aufruf hinaus — gemessen. Ein toter Hub kostet im schlechtesten Fall einmal 2 s pro
Person und Frist, danach greift die Gnadenfrist bzw. das Hub-Zeichen.

### Zum Modul-Token

Quellen: Umgebungsvariable `SLIDEFORGE_HUB_TOKEN`, sonst `data/.hub-token` (so legt der Hub es
laut Vertrag beim Modul ab; `data/` steht in `.gitignore` und wird nicht mit ausgeliefert).
**Ausgestellt wird es von einem Menschen** im Hub (Einstellungen › Modul-Zugänge, Zeile
FolienSchmiede) — ich erzeuge und rotiere keine Schlüssel. Der Wert steht in keiner Meldung,
keinem Befund, keinem Protokoll: die Hinweiszeile bei 401/403 nennt nur den Status.

## Prüfung: 57 Fälle, alle grün

`php _pruefungen/dock-vom-hub.php` — startet eine Attrappe des Endpunkts
(`_pruefungen/dock-attrappe/`) mit `php -S` und prüft gegen sie, ohne echten Hub:

- **Normalfall:** Liste und Reihenfolge, `shortLabel`, `newTab` → `target="_blank"` mit
  `rel="noopener noreferrer"`, ohne `newTab` kein Ziel, aktive App hervorgehoben, die drei
  Kennzeichen des Hub-Kanons, Hub-Zeichen genau einmal.
- **Zwischenspeicher:** innerhalb der Frist kein zweiter Aufruf; nach Ablauf wird gefragt;
  `unchanged` behält die Liste; eine neue Version wird übernommen.
- **Randfälle:** leere Liste bleibt eine Liste (nicht «unbekannt»); gesperrte Person
  (`locked: true`, keine Einträge); `404` → unbekannt; `401`/`403` → nichts, Hinweis ohne
  Token; kein Token → gar kein Aufruf; nicht angemeldet → kein Aufruf; kaputtes JSON → nichts.
- **Hub stumm** (Attrappe antwortet erst nach 5 s): mit frischem Speicher die letzte Liste und
  Wartezeit unter 3.5 s; mit zu altem Speicher nichts. Dasselbe bei **503 `lock_unknown`** —
  die letzte Liste gilt weiter, es wird nichts herabgesetzt.
- **Die drei Fälle werden auseinandergehalten** (das ist der Kern des Vertrags): `404`
  wirft einen alten Stand **weg** und zeigt nur das Hub-Zeichen; `401`/`403` benutzen den
  Zwischenspeicher **nicht** (das Recht ist weg, nicht die Verbindung), lassen ihn aber liegen,
  damit sich die Leiste nach einem Einrichtungsfehler von selbst erholt — geprüft; `503` und
  Netzfehler behalten ihn.
- **Untergeschobener Zwischenspeicher:** eine von Hand eingefügte `javascript:`-Adresse in der
  abgelegten Datei erscheint **nicht** in der Leiste und nicht im Markup.
- **Böse Antwort:** `javascript:`- und `data:`-Adressen fliegen raus, Einträge ohne Kennung
  und Unsinn im Feld ebenso; Spitzklammern und `&` sind im Markup maskiert; fällt die
  Hub-Adresse aus, greift unsere eigene.

Dass nur `https:`/`http:`-Adressen übernommen werden, ist **keine** Rechtefrage, sondern eine
harte Grenze — die darf im Code stehen.

## Auflagen aus dem Review des Koordinators (19.10.), eingebaut

| Auflage | Umsetzung |
|---|---|
| Frist (`ttl`) deckeln | auf 30–600 s begrenzt, beim Ablegen **und** beim Lesen; eine masslose Zahl im Zwischenspeicher wird nicht geglaubt |
| Adressen an den Hub-Host binden | **für die Zeichen** umgesetzt (`zeichenAdresse()`: Schema, Host und Port müssen der Hub-Adresse entsprechen, sonst bleibt das Zeichen leer) |
| HTTPS erzwingen | der Aufruf geht nur über `https` hinaus; `http` allein für Loopback (Prüfstand), sonst gar kein Aufruf und ein Hinweis ins Protokoll |
| Negativ-Marke bei Hub-Ausfall | nach einer stummen Antwort wird 60 s nicht erneut gewartet — sonst kostet ein toter Hub jeden Seitenaufbau 2 s |
| Vertragsfassung 2 im Kopf | Fassung und Prüfwert stehen im Kopf von `HubDock.php` |
| `data/cache` härten | **Prod-Schritt, nicht Code** — Voraussetzung für die Lieferung, siehe unten |

**Eine Auflage habe ich bewusst nur zur Hälfte umgesetzt, mit Begründung:** die **Ziele der
Einträge** (`href`) bleiben an jeden `http(s)`-Host erlaubt. Die Apps liegen auf eigenen Hosts
— Group-Office, Nextcloud, die FolienSchmiede selbst. Eine Bindung an den Hub-Host würde die
Leiste leerräumen, also genau das Gegenteil von «zeigen, was Menschen freigegeben haben».
Beschränkt ist, was dorthin gehört: die **Zeichen** liefert laut Vertrag der Hub, und ein
fremder Bildverweis würde bei jeder Person einen Abruf auf einem fremden Host auslösen. Prüffall
dazu: fremdes Zeichen wird verworfen, der Eintrag bleibt.

**Zur Zahl der Prüffälle:** sie ist von 47 auf 53 gewachsen. Vier Fälle sind beim ersten Lauf
nach dem Umbau **rot** gewesen — Ursache war der Prüfstand selbst, nicht der Code: `php -S`
nimmt einstrangig keine neue Verbindung an, solange der absichtlich langsame Fall schläft, und
die folgenden Fälle liefen dadurch in einen leeren Hub. Mit `PHP_CLI_SERVER_WORKERS=4` und
3 s Schlafzeit ist es stabil; zweimal hintereinander 53 grün.

## Zwei Dinge, die beim Ablegen des Tokens zählen (gemessen 19.10.)

Der Hub warnt in Fassung 2 des Vertrags für wunder.li: dort gehört `/data/www/slideforge/data`
dem Benutzer 99 mit **0777**, ein Token darin wäre ersetzbar oder löschbar. **Dasselbe gilt
auf bkbiel.** Gemessen auf Prod:

```
/data/slideforge/data        99:100  777
/data/slideforge/data/cache  99:100  777
```

U51 hat am 18.10. nur die Präsentationsordner und `data/jobs` korrigiert, **nicht `data/`
selbst** (ausdrücklich ausserhalb des damaligen Umfangs, Entscheid des Koordinators: nicht
anfassen, Urs wird gesondert gefragt). Daraus folgen zwei Punkte:

1. **Das Token gehört auf bkbiel nicht in diesen Ordner.** Eine Datei mit `0600` hilft nichts,
   wenn der Ordner darüber weltschreibbar ist — ersetzen und löschen gehen über das
   Ordnerrecht. **Mein Vorschlag: auf bkbiel die Umgebungsvariable `SLIDEFORGE_HUB_TOKEN`
   verwenden** (der Leser kann beides). Dann braucht es die Datei gar nicht. Alternativ zuerst
   `data/` auf `101:101 2770` bringen — das ist ein eigener Entscheid in der Linie von U51.
2. **Der Zwischenspeicher liegt im selben weltschreibbaren Ordner.** Darum säubert `HubDock`
   die Werte nicht nur, wenn sie vom Hub kommen, sondern **auch beim Lesen aus dem
   Zwischenspeicher** — sonst könnte eine untergeschobene Datei eine Adresse ins Markup
   bringen. Das ist Tiefenstaffelung, keine Rechtefrage.

## Auflagen aus dem eigenen unabhängigen Review (19.10.), eingebaut

Der Review fand fünf echte Punkte — drei davon hatte ich während seines Laufs schon aus den
Auflagen des Koordinators behoben (Säuberung beim Lesen, `ttl`-Deckel, Negativ-Marke,
https-Pflicht); er hat sie unabhängig nachgemessen und bestätigt. Neu eingebaut:

1. **`locked: true` wird jetzt durchgesetzt, nicht nur gemerkt.** Vorher hätte eine Antwort,
   die sich selbst widerspricht (gesperrt **und** mit Eintrag), den Eintrag gezeigt — die
   Sperre hing allein an der Gegenseite. Jetzt setzt `sauber()` bei einer Sperre Liste **und**
   `isAdmin` auf leer. Prüffall dazu.
2. **Der Token hängt nicht mehr am Anfragekopf.** Das war der schwerste Fund:
   `churchforge_hub_url()` leitet den Hub notfalls aus `HTTP_HOST` ab, und damit hätte ein
   angemeldeter Mensch mit `Host: hub.wunder.li` unser bkbiel-Token an einen **anderen** Hub
   ausleiten können (im Review gemessen). Für den Leisten-Aufruf gilt jetzt **nur** die
   ausdrücklich gesetzte Umgebungsvariable `CHURCHFORGE_HUB_URL`; fehlt sie, geht **kein**
   Aufruf hinaus. Dieselbe Adresse prüft auch die Herkunft der Zeichen.
3. **Token-Form wird geprüft** (`[A-Za-z0-9_-.]{8,512}`). Eine Token-Datei mit einem
   Zeilenumbruch hätte sich sonst in die Kopfzeilen der Anfrage geschrieben. Prüffall mit
   eingebautem `\nX-Boese: 1`.
4. **Keine dauerhaft leere Leiste mehr.** Lag im Speicher eine `version` ohne gültigen Stand,
   antwortete der Hub immer `unchanged` und die Leiste blieb leer, ohne sich zu erholen. Jetzt
   wird eine Version nur mitgesendet, wenn auch ein brauchbarer Stand dazu liegt.
5. **Die Tabelle im Kopf von `HubDock.php`** nennt jetzt auch 400, 405 und 503 und sagt, dass
   `404` einen alten Stand löscht.

**Einen Punkt habe ich bewusst nicht umgesetzt — `subject` gegen den angefragten Schlüssel
prüfen.** Der Review hat recht, dass die Antwort ungeprüft übernommen wird; eine Gleichheit zu
verlangen wäre aber **falsch**: der Vertrag sagt ausdrücklich, dass eine verknüpfte Kennung als
Hauptkonto zählt und `subject` dann **absichtlich** abweicht (`for=216` → `subject=u:urs`). Eine
Prüfung auf Gleichheit würde also genau die Zweitkonten brechen, für die das Feld gemacht ist,
und die Zuordnung selbst kann ich nicht nachrechnen — `identity-links.json` liegt beim Hub, und
dort hineinzulesen haben wir gerade abgeschafft. Der Hub ist für die Leiste die Autorität: wer
ihn kontrolliert, bestimmt die Liste ohnehin. Abgelegt wird weiterhin unter **unserem**
Schlüssel, eine Vermischung zwischen Personen ist darum nicht möglich.

**Kleinigkeiten aus dem Review, abgehandelt:** `_pruefungen/` wird **nicht** ausgeliefert — die
Positivliste in `.deploy/deploy.sh:90` überträgt nur `README.md config src lang seed docker
public_html scripts` und `config.php`; die Prüfdateien schreiben ihren Sandkasten in einen
Ordner, der in `.gitignore` steht. Die Vorgabe-Portnummer ist von 8121 auf **8151** geändert.
`isAdmin` hat im Modul weiterhin **keinen Verbraucher** — das ist Fund 2 der ACL-Prüfung und
wartet auf Urs' Wort; bis dahin wird das Feld nur durchgereicht.

## Noch offen, bevor das ausgeliefert werden kann

0. **`CHURCHFORGE_HUB_URL` muss in der Prod-Umgebung des Containers gesetzt sein** — sonst
   fragt das Modul die Leiste bewusst nicht ab (siehe Auflage 2). **Achtung, das wirkt weiter:**
   dieselbe Variable schaltet in `shared/bootstrap.php:113` auch `hub_ui_integrated()` auf
   «live». Auf Prod kommen Kit und `cf-fine.css` schon vom Hub, es wäre also keine Änderung —
   aber ob die Variable heute gesetzt ist, weiss ich nicht; das muss bkb-srv10 sagen, und nach
   dem Setzen gehört eine Sichtprobe der Seite dazu.
1. **Der Hub-Endpunkt ist gebaut, aber noch nicht ausgeliefert.**
2. **Das Modul-Token muss ein Mensch ausstellen** und bkb-srv10 beim Modul ablegen
   (`data/.hub-token`, 0600, lesbar für den Dienst — uid 101).
3. **Unabhängiger Review** (sicherheitsrelevant) — läuft.
4. Danach: Vorher-Digest = erwartet, Sicherung, Nachher-Manifest, Takt-Probe mit
   Cache-Buster, Rückbau-Satz. Nur `slides.bkbiel.ch`; die Demo bleibt eingefroren.

**Reihenfolge wichtig:** Wird das Modul **vor** dem Endpunkt ausgeliefert, zeigt die Leiste nur
noch das Hub-Zeichen (401/404 oder kein Token). Das ist kein Schaden, aber ein sichtbarer
Rückschritt — darum erst liefern, wenn Endpunkt **und** Token stehen. Heute zeigt die Leiste
auf Prod ohnehin nur das Hub-Zeichen, weil kein Hub-Datenordner eingehängt ist.

> **Rückbau-Satz:** `src/Launcher.php` aus der Sicherung zurücklegen, `src/HubDock.php` auf
> Prod entfernen und `public_html/includes/launcher.php` zurücklegen — dann ist der Stand von
> vor der Lieferung wieder da. Es wandern keine Daten mit; der Zwischenspeicher unter
> `data/cache/hub-dock-*.json` kann gelöscht werden, er wird sonst einfach nicht mehr gelesen.
