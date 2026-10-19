# Plan: das Dock kommt vom Hub, die FolienSchmiede deutet nichts mehr

**Datum:** 19.10.2026 · **Art:** Plan, **nichts gebaut, nichts ausgeliefert** ·
**sicherheitsrelevant**
**Anlass:** Hub-Antwort vom 19.10.: das Gruppenfeld heisst heute `allowGroups`/`allowUsers`
(Oberfläche: Apps & Dienste › App › Rechte), `requireAnyGroup` ist die alte Form, die der Hub
weiter prüft, aber nicht mehr anzeigt. Auftrag des Koordinators: U50 und Fund 1 so planen,
dass die FolienSchmiede `modules.json` **nicht selbst deutet**.

## Warum das stimmt, und zwar deutlicher als gedacht

`hub/src/ModuleAccess.php` entscheidet heute über **fünf** Wege: persönliche App → nur
Besitzer; Administrator → alles; `allowUsers`; `allowGroups` (Hub-Gruppen, Mitglieder stehen in
der Gruppe, **nicht** in der App); `requireAnyGroup` (alte Form); und sonst **nur
Administratoren**. Dazu kommt die Personensperre (`PersonLocks`).

Mein Stand in `src/Launcher.php` kennt davon **einen** Weg: `requireAnyGroup`. Die Folge ist
genau die, die der Hub beschreibt: **Was Urs in der Oberfläche freigibt, würde im Dock der
FolienSchmiede nicht erscheinen.** Mein fail-closed (Fund 1, Commit `b5b17fe`) macht das noch
sichtbarer — es würde Apps verstecken, die freigegeben sind. Die Zeile bleibt richtig, aber sie
ist am falschen Ort: entscheiden soll hier gar nicht die FolienSchmiede.

**Zwei weitere Messungen, die den Plan bestimmen:**

1. `hub/public/api/acl.php` (nur lesend, GET, Modul-Token) liefert **je Person**: `groups`,
   `isAdmin`, `locked`, `caps`, `themes`, `version`, `ttl: 300` und bei gleicher Version
   `unchanged: true`. Es sagt **nicht**, welche Apps jemand sehen darf.
2. Im Kopf von `acl.php` steht ausdrücklich: solange `enforcement` false ist, **darf ein Modul
   nichts einschränken** («sonst sähe jemand plötzlich weniger als gestern»). Und auf bkbiel
   ist es heute false: in `hub/data/gemeinde/` liegt **keine `acl.json`**, es gibt also noch
   **keine Hub-Gruppen** und `enforcement` steht auf der Vorgabe `false`. Heute nutzbar sind
   darum nur `allowUsers` und die alte `requireAnyGroup`.

Mit `acl.php` allein käme ich also nicht weiter: ich müsste die fünf Wege im Modul
nachbauen — genau das, was nicht sein soll.

## Der Vorschlag: der Hub liefert die fertige Liste

**Ein Endpunkt, der je Person die Apps zurückgibt, die sie sehen darf — fertig gefiltert und
in der Reihenfolge ihrer Favoriten.** Die FolienSchmiede rendert sie und deutet nichts.

- **Es gibt den Präzedenzfall schon:** `HubPeer::snapshot()` filtert den App-Katalog
  serverseitig für eine ausdrücklich genannte Person (`ModuleAccess::userMaySeeAs()`), damit
  der andere Hub «nicht raten muss und nicht mit seinen eigenen Rollen nachfiltert»
  (`hub/src/HubPeer.php:784-820`). Dasselbe braucht ein Modul — nur über den Modul-Token
  statt den Peer-Token, denn `acl.php` verweigert Peers die Personenauskunft zu Recht.
- **Gebraucht pro Eintrag:** Kennung, Beschriftung, Adresse, Zeichen (Icon-URL), ob neuer
  Reiter — also genau das, was `Launcher::render()` ausgibt. Dazu die Favoritenreihenfolge und
  `version`/`ttl` wie bei `acl.php`.
- **Wirkung auf die Regel:** Wer was sieht, entscheidet der Hub aus dem, was Menschen dort
  eingetragen haben. Fail-closed, `enforcement`, Sperren, persönliche Apps — alles bleibt an
  einer Stelle. Im Modul bleibt kein Zugriffsentscheid übrig.

### Was damit wegfällt

- **`src/HubFavorites.php` in seiner heutigen Form** (liest `user-prefs.json` und
  `identity-links.json` aus dem Hub-Datenordner) wird **nicht gebraucht**. Die Favoriten kommen
  aus derselben Antwort. Dass ein Modul in den Dateien einer anderen Anwendung liest, war
  immer die schwächste Stelle an U50 — sie verschwindet.
- **U67 (Hub-Datenordner lesend einhängen) wird für das Dock nicht mehr gebraucht.** Kein
  Mount, keine Dateirechte, keine zweite Leseart. Das ist der grösste Gewinn des Plans; ob U67
  für etwas anderes noch gewollt ist, entscheidet Urs.
- Aus U50 **bleibt** der Teil, der unbestritten ist: das Markup auf dem Hub-Kanon
  (`data-hub-fav-dock`, `data-hub-home`, `data-module-id`), der Rückfall, wenn keine Auskunft
  da ist, und die angeglichene Typenliste.

### Was neu gebraucht wird, und von wem

| Was | Wer |
|---|---|
| Der Endpunkt im Hub (Modul-Token, nur lesend, `version`/`ttl`) | **Hub** |
| Ein **Modul-Token** für die FolienSchmiede (`cfm_…`) | **Hub-Administrator** (ein Mensch) |
| Ablage des Tokens auf Prod — **nicht im Repo** | ich, nach Anweisung |
| Lesen, Zwischenspeichern, Rendern im Modul | ich |

Zum Token, damit es keine Verwechslung gibt: das ist ein **neues Geheimnis**. Ich erzeuge
keines, rotiere keines und schreibe seinen Wert nie in eine Meldung, einen Befund oder ein
Protokoll. Ablage auf Prod am besten als Umgebungsvariable des Containers oder als Datei
**neben** der Installation; `data/` wäre technisch möglich (steht in `.gitignore`, wird nicht
mit ausgeliefert), aber eine Datei, die nur der Dienst lesen kann, ist sauberer. Das legt Urs
oder bkb-srv10 ab, nicht ich im Vorbeigehen.

### Wie das Modul sich verhält

| Lage | Verhalten |
|---|---|
| Antwort da | Dock = genau diese Liste, in dieser Reihenfolge |
| Antwort da, Liste leer | Dock = nur das Hub-Zeichen (die Person hat keine App freigegeben) |
| `unchanged` bei gleicher `version` | Zwischenspeicher weiterverwenden, kein Neuaufbau |
| Hub nicht erreichbar, Zwischenspeicher vorhanden | letzte bekannte Liste, bis `ttl` × 4 abgelaufen ist |
| Hub nicht erreichbar, nichts im Speicher | nur das Hub-Zeichen — **nicht** raten, aber auch nichts «vorsichtshalber öffnen» |
| `404 unknown_subject` | als **unbekannt** behandeln (nur Hub-Zeichen), **nicht** als «darf nichts»: der Hub sagt selbst, ein Tippfehler soll nicht wie Rechtlosigkeit aussehen |
| Keine Anmeldung über den Hub | wie heute das Solo-Zeichen (`includes/launcher.php`) |
| In der Hub-Hülle (`?hub_theme=`) | wie heute: der Hub rendert sein Dock, wir gar nichts |

Zwischenspeicher: je Person, Schlüssel = Benutzerkennung, Inhalt = Liste + `version`, Dauer
`ttl` (300 s) — in `data/cache/`, das es schon gibt. Ein Seitenaufbau darf **nicht** auf jeden
Hub-Aufruf warten; bei abgelaufener Frist wird die alte Liste gezeigt und im Hintergrund
erneuert, oder der Aufruf bekommt ein kurzes Zeitlimit (2 s) und fällt sonst auf den
Zwischenspeicher zurück.

## Reihenfolge

1. **Hub baut den Endpunkt** und sagt die Form zu (Felder, `version`, `ttl`, Fehlerfälle).
2. Ein Mensch legt das **Modul-Token** an und hinterlegt es auf Prod.
3. Ich baue im Modul: Abfrage, Zwischenspeicher, Rendern; `HubFavorites` wird zum reinen
   Leser dieser Antwort (oder fällt weg). Dazu die Prüffälle — die 28 bestehenden bleiben
   gültig, die Fixtures werden von «modules.json» auf «Antwort des Hubs» umgestellt.
4. **Unabhängiger Review** (sicherheitsrelevant), dann Lieferung im üblichen Ablauf.
5. Erst danach entscheidet sich, ob U67 noch jemand braucht.

## Bis dahin

**Nichts ausliefern.** Das Dock bleibt, wie es heute ist: im Alleinbetrieb nur das Hub-Zeichen
(es gibt auf Prod keinen Hub-Datenordner), in der Hub-Hülle das Dock des Hubs. Die Commits
`f90fad8` (U50) und `b5b17fe` (fail-closed) bleiben liegen; der file-lesende Teil wird nach
diesem Plan ersetzt, nicht ausgeliefert. Das ist ehrlicher, als etwas zu liefern, das wir
anschliessend sofort wieder ersetzen.

## Was ich an diesem Plan für offen halte

- **Ein Endpunkt oder zwei?** Apps und Favoriten in einer Antwort ist weniger Verkehr und
  immer in sich stimmig. Getrennt wäre flexibler. Mein Vorschlag: eine Antwort.
- **Darf das Dock in der Reihenfolge der Favoriten *nur* Favoriten zeigen** (so wie der Hub
  heute) oder alles Erlaubte mit den Favoriten vorne? Das hat Urs am 18.10. für den Hub
  entschieden («Launcher ja» = nur Favoriten); dasselbe für das Modul anzunehmen ist
  naheliegend, aber es ist seine Sache, nicht meine.
- **Soll die Oberfläche der FolienSchmiede eigene Rechte kennen?** Heute macht der Hub beim
  Anmelden aus der Gruppe «Admin» einen lokalen Administrator (Fund 2, eigenes Paket). Wenn der
  Hub künftig `isAdmin` je Person liefert, wäre das die bessere Quelle als ein Gruppenname im
  Code — passt zusammen, gehört aber in Fund 2 und braucht Urs' Wort.
