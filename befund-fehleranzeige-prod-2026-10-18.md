# PHP-Meldungen erscheinen auf Prod in der Seite («Fehleranzeige»)

> **Bezeichnung:** Diese Sache heisst **«Fehleranzeige»**, nicht U53 — unter U53 führt Urs
> PlanForge (Hinweis Koordinator, 18.10.). Frühere Fassungen dieses Befunds und der
> Commit-Titel `3a91b97` nennen noch «U53»; der Commit-Text bleibt stehen, weil der Prüfwert
> beim Koordinator protokolliert ist und nicht nachträglich wandern soll.

**Datum:** 18.10.2026 · **Umfang:** eine Stelle in `config.php` (Zeilen 53-61)
**Herkunft:** Nebenbefund aus dem Review zu U50. Auftrag des Koordinators vom 18.10.:
«als eigenes kleines Paket vorbereiten (Wert 0 bzw. aus dem Prod-Modus, Probe: Fehlerseite
ohne Meldungstext, Fehler weiter im Log; Vorher-Digest, Sicherung, Rückbau-Satz), mit Review,
Auslieferung im Dauer-Go-Ablauf (nur bkbiel-Modulordner; die wunder.li-Demo bleibt
eingefroren)».

## Der Befund

`config.php:57` setzte `ini_set('display_errors', '1')` — auf Produktion. Der Kommentar
daneben sagte es selbst: «Für den Produktivbetrieb display_errors ganz auf 0 setzen.» Der Hub
macht es richtig (`hub/config.php:14` steht auf `'0'`), und mehrere JSON-Endpunkte im Modul
(`api.php`, `user_api.php`, `live.php`, `upload.php`, `webdav.php`, `admin_api.php`,
`clipart.php`, `icons.php`, `pixabay.php`, `spellcheck.php`) setzen sich den Wert einzeln auf
`'0'` — ein deutliches Zeichen, dass die globale `1` ein Entwicklungsrest war.

**Gemessen im Prüfstand** (Kopie des Moduls, `php -S`, eine Seite, die absichtlich eine
Warnung auslöst — genau der Fall «Array to string conversion» aus dem U50-Review):

```
<b>Warning</b>:  Array to string conversion in
<b>/…/sf/public_html/fehlerprobe.php</b> on line <b>15</b>
```

Der **vollständige Dateipfad** steht damit in der Seite. Das ist der eigentliche Schaden: eine
PHP-Meldung verrät Pfade, Klassen- und Funktionsnamen und oft Teile der Daten, mit denen sie
ausgelöst wurde.

**Teilweise schon vorher richtig:** Eine nicht gefangene **Ausnahme** (`Throwable`) ergibt
keine rohe Meldung, sondern die neutrale Seite «Unerwarteter Fehler» mit Status 500 — das
macht der `set_exception_handler` in `config.php:311`. Bei einer `RuntimeException` zeigt er
bewusst den Text (unsere eigenen, für Menschen geschriebenen Meldungen).

**Der Review hat diese Einschätzung in zwei Punkten korrigiert**, und zwar nach oben:

1. Ein **echter** `E_ERROR` (Speicher- oder Zeitlimit) läuft **nicht** durch diesen Handler.
   Vorher stand sein Text **mit vollem Pfad** in der Seite, Status 200 (gemessen). Die
   Umstellung schliesst also mehr als zunächst behauptet.
2. Nachher entsteht in diesem Fall ein leerer Rumpf mit Status 500 — **aber mit Spur**: der
   Protokolleintrag samt Stack-Trace ist da.

## Die Änderung

```php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', getenv('SLIDEFORGE_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');
```

Vorgabe ist **aus**. Zum Entwickeln schaltet `SLIDEFORGE_DEBUG=1` die Anzeige wieder ein; auf
Prod ist die Variable nicht gesetzt, dort gilt also `0`. `log_errors` bleibt an, es geht
nichts verloren. Dieselbe Umgebungsvariablen-Art benutzt das Modul schon
(`SLIDEFORGE_PRESENT_HOST`, `config.php:469`).

Dazu die **Auflage A aus dem Review**, eine Zeile als Erstes im `set_exception_handler`:

```php
error_log('SlideForge: nicht gefangene Ausnahme: ' . $e);
```

Der Grund ist stichhaltig und gemessen: der Handler hat **nie** protokolliert. Eine nicht
gefangene Ausnahme war damit vollkommen spurlos — neutrale Seite für die Besucherin, **leeres
Protokoll** für uns. Das war schon vorher so (die Fehleranzeige spielt für den Handler keine
Rolle), wäre nach der Umstellung aber die einzige Stelle geblieben, an der ein Defekt
unbemerkt bleibt. Jetzt steht der Text samt Stack-Trace im Protokoll — und nur dort.

`display_startup_errors` ist aus der Änderung **wieder herausgenommen**: an dieser Stelle ist
es wirkungslos, weil die Startphase vorbei ist, wenn die Zeile läuft. Statt einer Zeile, die
nichts tut, steht dort jetzt ein Hinweis, dass das in die Server-ini gehört.

## Probe im Prüfstand

Kopie des Moduls, `php -S` mit eigenem Protokoll, dieselbe Prüfseite:

| Fall | vorher | nachher |
|---|---|---|
| Warnung, Text in der Seite | **ja, mit vollem Pfad** | **nein** |
| Warnung, Seite rendert weiter | ja | ja |
| Warnung im Protokoll | ja | **ja** |
| Nicht gefangene Ausnahme: Antwort | neutrale Fehlerseite, 500 | neutrale Fehlerseite, 500 |
| Nicht gefangene Ausnahme: Meldungstext in der Seite | nein | nein |
| Nicht gefangene Ausnahme **im Protokoll** | **nein — spurlos** | **ja, mit Stack-Trace** |
| Echter `E_ERROR` (Speicher/Zeit): Text in der Seite | **ja, mit vollem Pfad**, Status 200 | nein, leerer Rumpf mit 500 — Protokolleintrag vorhanden |
| mit `SLIDEFORGE_DEBUG=1` | — | Text wieder sichtbar |
| `login.php` / `index.php` / `sw.php` | — | 200 / 302 / 200 |

Der Review hat zusätzlich **25 Endpunkte unangemeldet abgeklappert** und mit eingeschalteter
Anzeige geprüft, ob irgendwo eine Warnung steht, die wir jetzt verdecken würden: **keine**. Es
wird also kein bestehender Defekt unsichtbar gemacht (angemeldete Editor-Pfade liessen sich
ohne Sitzung nicht prüfen). Die JSON-Endpunkte antworten unverändert sauber (401/404 als JSON,
keine Meldungszeile).

**Was ich auf Prod selbst nicht zeigen kann:** Im Modulordner liegt keine Protokolldatei, und
`error_log` ist nicht gesetzt — PHP schreibt also in die Standardfehlerausgabe, und die landet
im Protokoll des Containers. Das kann ich von hier nicht lesen (kein Docker-Zugang). Dass
nichts verloren geht, steht darum auf zwei Beinen: `log_errors` bleibt unverändert auf `1`
(die Zeile wird nicht angefasst), und im Prüfstand ist die Warnung nach der Umstellung
nachweislich im Protokoll. Wer den Container-Blick hat (bkb-srv10), kann es dort in einem Satz
gegenprüfen. Einen Fehler auf Prod absichtlich auslösen werde ich nicht.

## Offen geblieben (eigene Pendenzen, nicht in diesem Paket)

Alles aus der Liste B des Reviews, nachgeprüft und bewusst nicht mitgeliefert:

1. **Pfade in unseren eigenen Meldungen.** `src/Storage.php:37` und `:70` schreiben den
   **vollen Pfad** in eine `RuntimeException` («Kann nicht in "/…/data/users.json"
   schreiben»), und `config.php:316` zeigt `RuntimeException`-Texte bewusst an. Das Ziel «keine
   Pfade in der Seite» ist auf diesem Pfad also noch nicht erreicht. Die Meldung ist für
   Menschen gedacht und hat Urs schon geholfen — sie zu kürzen ist ein eigener Entscheid.
2. **Echte Fatals** (Speicher-/Zeitlimit) ergeben eine leere Seite. Eine
   `register_shutdown_function` mit `error_get_last()` könnte daraus eine neutrale Fehlerseite
   machen. PHP selbst protokolliert diese Fälle, die Spur ist also da.
3. **`SLIDEFORGE_DEBUG` unter PHP-FPM:** Pools haben vorgabemässig `clear_env = yes`, dann
   sieht `getenv()` die Container-Umgebung nicht. Der Entwicklerschalter braucht dort
   `env[SLIDEFORGE_DEBUG]` im Pool oder `clear_env=no`. **Für die Richtung ist das
   unkritisch:** im Zweifel schaltet die Anzeige **aus**. Im README steht der Hinweis.
4. Mehrere JSON-Endpunkte (`user_api.php`, `live.php`, `clipart.php`, `icons.php`,
   `pixabay.php`, `spellcheck.php`) liefern im Fehlerfall HTML in eine
   `application/json`-Antwort — vorbestehend, von dieser Änderung nicht berührt.

## Auslieferung

- **Vorher-Digest, geprüft und gleich:** Arbeitsbaum = Prod =
  `2a77f134b19eaeb5fade745e7cf7ed6b35194834270c0ccfe0343f5bdf4a5387`
- **Nachher (Arbeitsbaum), nach Umsetzung der Auflage:**
  `config.php` = `f48d27839fc7b6843e21afc7a350fb3f1d47f07593d07850b787a1affe4c854a`
  (die Zwischenfassung ohne Auflage war `764e861db75b7ed2…` und wird nicht ausgeliefert)
- Mitgeliefert wird ausserdem `README.md` (Sicherheitshinweis nachgezogen,
  `4bf2768192b933155b5ed50152fc8c6393660c8be0fa3955a5236a8889383c5a`); `src/HubFavorites.php`
  ist nur ein Kommentar und reist mit U50, nicht hier.
- Kein CSS, kein JS → **kein `ASSET_VERSION`-Sprung** nötig; die Version bleibt 679.
- Nur `slides.bkbiel.ch`. Die Demo auf wunder.li bleibt eingefroren.

### Lieferprotokoll (18.10.2026, erledigt)

1. Sicherung auf Prod: `/data/slideforge/.sicherung/fehleranzeige-20261018-1452/` mit
   `config.php` (`2a77f134b19eaeb5…`) und `README.md` (`5f544b3b307279f3…`) — beide gegen die
   laufenden Dateien geprüft, Byte für Byte gleich.
2. Übertragen mit `scp -p`, zwei Dateien.
3. Nachher auf Prod gemessen: `config.php` = `f48d27839fc7b684…`, `README.md` =
   `4bf2768192b93315…` — genau die Werte aus dem Arbeitsbaum. Besitzer und Rechte unverändert
   (`99:100`, `644`). `display_errors`-Zeile und die Protokollzeile im Handler sind an Ort.
4. Takt-Probe, jede Abfrage mit Cache-Buster: `login.php` **302** zur Hub-Anmeldung (0.07 s),
   `index.php` **302**, `assets/css/style.css` **200**, `sw.php` liefert die Zeichenkette
   `slideforge-679` (PHP läuft, Version unverändert). `register.php` **200** (3347 Bytes) und
   `resend_verification.php` **200** (3806 Bytes), beide mit **0** Meldungszeilen im Rumpf
   (gesucht nach `<b>Warning</b>`, `<b>Notice</b>`, `<b>Fatal error</b>`, `on line <b>`).
5. Nur `slides.bkbiel.ch`. Die Demo auf wunder.li wurde nicht angefasst.

**Eine Zeile Abweichung Arbeitsbaum ↔ Prod, bewusst so gelassen.** Nach der Lieferung kam der
Hinweis, dass «U53» bei Urs PlanForge ist. Der Kommentar in `config.php` nennt darum im
Arbeitsbaum keine Nummer mehr, sondern diesen Befund. Damit gilt:

| | sha256 |
|---|---|
| Prod (ausgeliefert, **in Betrieb**) | `f48d27839fc7b6843e21afc7a350fb3f1d47f07593d07850b787a1affe4c854a` |
| Arbeitsbaum (nur der Kommentar anders) | `b9cb2463aeefc0964a7728efb581cce5f29a908ded64dd707fad920d5c6fc554` |

Der Unterschied ist **ein Kommentar**, kein Verhalten. Für eine eigene Lieferung ist das zu
wenig — die Korrektur reist mit der nächsten Lieferung von `config.php` mit. Wer vorher
vergleicht, soll sich nicht erschrecken: **nicht** HEAD oder den Arbeitsbaum als Prod-Stand
annehmen, sondern die laufende Datei lesen.

> **Rückbau-Satz:** `config.php` und `README.md` aus
> `.sicherung/fehleranzeige-20261018-1452/`
> zurücklegen (Prüfwerte `2a77f134b19eaeb5…` und `5f544b3b307279f3…`) — damit stehen
> Fehleranzeige, Protokollzeile und alles andere wieder auf dem Stand von vor der Lieferung.
> (Der naheliegende Kurzweg «auf Prod `SLIDEFORGE_DEBUG=1` setzen» ist **kein verlässlicher
> Rückbau**: PHP-FPM räumt die Umgebung vorgabemässig aus, dann sieht `getenv()` die Variable
> nicht. Der Dateitausch ist der Weg.)
