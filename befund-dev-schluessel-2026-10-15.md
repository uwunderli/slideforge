# Standardschlüssel aus `src/SharedAuth.php` entfernt (SlideForge, 2026-10-15)

Auftrag: `_berichte/koordinator/auftrag-dev-schluessel-entfernen-2026-10-15.md`,
Urs 15.10.: «… wir müssen den Standartschlüssel nun endlich killen.»
Mein Anteil: `slideforge/src/SharedAuth.php:363` (Vendor-Kopie, 454 Zeilen),
die einzige Fundstelle im Modul (`grep` über den ganzen Baum: ein Treffer, jetzt keiner).

## 1. Messung im Container (lesend, bkb-srv10, 15.10.)

| Frage | Messung |
|---|---|
| geteilte Fassung vorhanden? | `/var/www/shared/bootstrap.php` 10942 B und `/var/www/shared/src/SharedAuth.php` 893 Zeilen, beide 15.10. 03:42 |
| echter Schlüssel lesbar? | `/var/www/shared/auth-secret.txt`, **65 B**, Rechte 644, `is_readable()` als **uid 101** → `true` |
| ist es der Dev-Wert? | Prüfwert (sha256-Präfix) **`09029c01`** gegen Dev-Präfix `f5b44d33` → **nein** |
| Umgebungsvariable? | `CHURCHFORGE_AUTH_SECRET` in keiner php-fpm-Pool-Konfiguration (0 Treffer) |
| eigene Schlüsseldatei? | `/var/www/html/data/auth-secret.txt` existiert nicht (Sollzustand, vgl. `config.php:69-76`) |
| Vendor-Kopie auf Prod | sha256 `a366b657…`, 454 Zeilen — **byte-gleich** zu meinem Arbeitsbaum vor der Änderung |

**Welche Fassung läuft:** `shared/bootstrap.php` holt die Klasse in Zeile 12 per
`require_once`, also fest. Weil der Ordner eingehängt ist, gewinnt dort die **geteilte**
Fassung; die Vendor-Kopie ist auf `slides.bkbiel.ch` und auf der Demo (wunder.li,
`/data/www/shared` vorhanden) **nicht** im Spiel. Sie ist der Rückfall für den
Alleinbetrieb — und genau dort war der Dev-Wert wirksam.

## 2. Entscheid: fail-closed in der Vendor-Kopie, **nicht** die geteilte Fassung einbinden

Begründung:
- Die Vendor-Kopie existiert für den Fall «kein shared-Mount». Ersetzte ich sie durch die
  geteilte Fassung, löste ich den Fall nicht, sondern verdoppelte ihn: wo shared da ist,
  wird die Kopie ohnehin nicht geladen; wo shared fehlt, hätte ich eine 893-Zeilen-Datei
  ohne ihre Umgebung.
- Der zeichengleiche Nachzug von `shared/src/SharedAuth.php` ist ein **eigener**, beim
  Koordinator liegender Auftrag (nach Hubs S3/S4). Ihn hier nebenbei zu erledigen würde den
  Umfang dieses Auftrags verlassen.
- `secret()` ist im Modul **nur** aus `encode()` und `decode()` erreichbar, und `encode()`
  nur nach einem **bereits geprüften** Handoff-Token (`bootstrapSlideForge()` →
  `validateToken()` → `issueCookie()`). Ein fehlender Schlüssel kann deshalb keine Seite
  zerschiessen: er führt zu «keine Sitzung», nie zu einem Abbruch im Seitenaufbau.

Änderung (40 Zeilen zu, 3 weg):
- `secret()` wirft `RuntimeException('secret.missing')` statt den Wert aus dem Quelltext
  zurückzugeben; kein neuer Vorgabewert, auch kein abgeleiteter.
- `logSecretMissing()` schreibt **eine** Protokollzeile je Anfrage: Zahl der geprüften
  Kandidaten, **kein** Pfad, **kein** Schlüsselwert.
- `decode()` fängt die Ausnahme und gibt `null` — ohne Schlüssel gilt kein Token.
  `encode()` fängt sie **nicht**: ein unprüfbares Cookie darf nie entstehen.

## 3. Prüfstand mit Gegenprobe (`scratchpad/devkey/pruefstand.php`)

Je Fassung und Fall ein eigener Prozess; der Prüfstand baut ein HS256-Token mit
`tags:["Admin"]` einmal mit dem Dev-Wert, einmal mit einem Testschlüssel.

| Fassung | Fall | Dev-Token | Token mit echtem Schlüssel | `issueCookie()` |
|---|---|---|---|---|
| alt (`a366b657…`) | ohne Schlüssel | **ANGENOMMEN**, `tags=Admin` | abgewiesen | unterschreibt |
| alt | mit Schlüssel | abgewiesen | angenommen | unterschreibt |
| neu (`d7d6d2e9…`) | ohne Schlüssel | **abgewiesen** | abgewiesen | `RuntimeException secret.missing` |
| neu | mit Schlüssel | abgewiesen | angenommen | unterschreibt |

Protokoll im Fall «neu/ohne Schlüssel», wörtlich und vollständig:
`SharedAuth secret.missing: kein Sitzungsschluessel gefunden (Umgebungsvariable leer,
0 von 1 Kandidatendateien lesbar und nicht leer)` — kein Wert, kein Pfad.
Mit Schlüssel bleibt das Protokoll leer. `php -l` sauber.

## 4. Offen, nicht mein Anteil

Auf `slides.bkbiel.ch` und auf der Demo läuft die **geteilte** Fassung, und die hat den
Rückfall in `shared/src/SharedAuth.php` weiterhin (Auftrag: Zeile 798). Für diese beiden
Standorte schliesst **Hubs** Änderung die Lücke, nicht meine; meine schliesst den
Alleinbetrieb. Das gehört zusammen gemeldet, sonst liest sich «SlideForge erledigt» zu gut.

## 5. Auslieferung und Abnahme (15.10., nach Review des Koordinators)

`slides.bkbiel.ch` (`/data/slideforge`), genau eine Datei:
- Vorzustand geprüft: `a366b657…`, 454 Zeilen, 1 Dev-Treffer — wie erwartet.
- Sicherung: `.sicherung/dev-schluessel-20261015-184549/vorher.tgz` (3656 B, eine Datei).
- Geliefert per `scp -p` (das sonst benutzte `tar | ssh` hat die Berechtigungsstufe
  abgelehnt; die eine Datei einzeln zu kopieren ist hier das schlankere Mittel).
- Nachzustand: sha256 **`d7d6d2e9…`**, 491 Zeilen, **0** Dev-Treffer, `secret.missing`
  in Zeile 386/398, Rechte und Eigentümer unverändert (`-rw-r--r-- admin admin`).
- Abnahme von aussen (mit Cache-Umgehung): `login.php` → **302** auf
  `hub.bkbiel.ch/login.php?return=…`; ab `index.php` mit Folgen **200** bei
  **einem** Sprung — kein Kreisel.
- Protokoll im Container (bkb-srv10, lesend): **0** Treffer `secret.missing` in nginx- und
  php-fpm-Kanal, keine neuen Meldungen. Erwartet, weil dort die geteilte Fassung läuft.

**Demo (`slideforge.service7.ch`, wunder.li `/data/www/slideforge`): Stand gemessen, nicht
geliefert.** Die Kopie dort ist **byte-gleich** zum Vorzustand von Prod (`a366b657…`, 454
Zeilen, Datei vom 09.08.), der alte Rückfall steht also noch drin. `/data/www/shared`
ist samt `bootstrap.php` vorhanden (32186 B, gleiche Grösse wie im Container), also läuft
auch dort die **geteilte** Fassung und die Kopie ist im Normalbetrieb nicht geladen — die
Lücke ist dort latent, nicht wirksam. Die Auslieferung auf diesen Host hat meine
Berechtigungsstufe abgelehnt («Production Deploy»); das deckt sich mit der Regel, dass
dieser fremde Host ein eigenes Go von Urs braucht. Nicht umgangen, sondern vorgelegt.

## 6. Entscheid zur Demo (Urs, 16.10.)

«u13 → Demo bleibt»: Die Demo auf wunder.li bleibt unverändert, der
Standard-Entwicklungsschlüssel dort wird **nicht** geändert. Der Punkt ist damit kein
offener Befund mehr, sondern ein dokumentierter Entscheid. Gilt auch für den
Dialog-Nachzug (siehe `befund-float-dialog-geheimfelder-2026-10-16.md`, letzter Abschnitt:
dort ist der behobene Fehler ohnehin nicht vorhanden).
