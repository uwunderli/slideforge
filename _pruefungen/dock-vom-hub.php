<?php
/**
 * Prueffaelle: die Programm-Leiste kommt vom Hub (`HubDock` + `Launcher`).
 *
 * Laeuft ohne echten Hub: eine Attrappe des Endpunkts wird mit `php -S` gestartet
 * (`dock-attrappe/api/dock.php`), der Fall steht in `dock-attrappe/fall.json`.
 *
 *   php _pruefungen/dock-vom-hub.php
 */
$modul = dirname(__DIR__);
$sand = __DIR__ . '/sand-dock';
$attrappe = __DIR__ . '/dock-attrappe';
$port = (int)(getenv('DOCK_PORT') ?: 8151);

@mkdir($sand . '/data/cache', 0770, true);

define('BASE_PATH', $sand);
define('DATA_PATH', $sand . '/data');
define('EXPORT_CACHE_PATH', DATA_PATH . '/cache');

// --- Attrappen -----------------------------------------------------------
class SharedAuth
{
    /** @var array<string,mixed> */
    public static array $user = ['sub' => '4711', 'username' => 'eva'];
    public static function read(): ?array
    {
        return self::$user === [] ? null : self::$user;
    }
}
class ModuleIcon
{
    public static function hubUrl(): string
    {
        return 'https://hub.test/icons/hub-fallback.svg';
    }
}
$hubUrl = 'http://127.0.0.1:' . $port;
function churchforge_hub_url(): string
{
    return 'http://127.0.0.1:' . (int)(getenv('DOCK_PORT') ?: 8151);
}

require $modul . '/src/HubDock.php';
require $modul . '/src/Launcher.php';

// --- Attrappe starten ----------------------------------------------------
@unlink($attrappe . '/aufrufe.log');
$log = $sand . '/server.log';
$pid = null;
// Mehrere Arbeiter, sonst blockiert ein absichtlich langsamer Fall den ganzen
// Pruefstand: `php -S` nimmt sonst erst nach dem Schlafen die naechste Verbindung an.
$cmd = sprintf('PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:%d -t %s > %s 2>&1 & echo $!', $port, escapeshellarg($attrappe), escapeshellarg($log));
$pid = (int)trim((string)shell_exec($cmd));
register_shutdown_function(static function () use ($pid) {
    if ($pid > 0) {
        @shell_exec('kill ' . $pid . ' 2>/dev/null');
    }
});
usleep(700000);

// --- Hilfen --------------------------------------------------------------
$pruefungen = 0;
$fehler = 0;
function pruefe(string $was, $ist, $soll): void
{
    global $pruefungen, $fehler;
    $pruefungen++;
    if ($ist !== $soll) {
        $fehler++;
        printf("FEHL %s\n   ist : %s\n   soll: %s\n", $was,
            json_encode($ist, JSON_UNESCAPED_UNICODE), json_encode($soll, JSON_UNESCAPED_UNICODE));
        return;
    }
    printf("OK   %s\n", $was);
}

function setzeFall(string $fall, string $version = 'v1'): void
{
    global $attrappe;
    file_put_contents($attrappe . '/fall.json', json_encode(['fall' => $fall, 'version' => $version]));
    HubDock::reset();
}

function leereSpeicher(): void
{
    global $sand;
    foreach (glob($sand . '/data/cache/hub-dock-*.json') ?: [] as $f) {
        unlink($f);
    }
    HubDock::reset();
}

function aufrufe(): int
{
    global $attrappe;
    $d = @file_get_contents($attrappe . '/aufrufe.log');
    return is_string($d) && $d !== '' ? count(array_filter(explode("\n", $d))) : 0;
}

function markup(string $aktiv = 'slideforge'): string
{
    ob_start();
    Launcher::render($aktiv);
    return (string)ob_get_clean();
}

function ids(?array $dock): array
{
    return array_map(static fn($i) => (string)$i['id'], $dock['items'] ?? []);
}

putenv('SLIDEFORGE_HUB_TOKEN=cfm_pruefstand');
// Die Adresse fuer den Aufruf kommt nur aus der Umgebung, nie aus dem Anfragekopf.
putenv('CHURCHFORGE_HUB_URL=http://127.0.0.1:' . $port);

// --- 1. Normalfall -------------------------------------------------------
leereSpeicher();
setzeFall('liste');
$dock = HubDock::current();
pruefe('Liste kommt vom Hub, in dieser Reihenfolge', ids($dock), ['planforge', 'go']);
pruefe('isAdmin aus der Antwort', $dock['isAdmin'] ?? null, false);
pruefe('Hub-Zeichen aus der Antwort', $dock['hub']['href'] ?? '', 'https://hub.test/');

$m = markup();
pruefe('Dock traegt data-hub-fav-dock', str_contains($m, 'data-hub-fav-dock'), true);
pruefe('Hub-Zeichen traegt data-hub-home (genau einmal)', substr_count($m, 'data-hub-home'), 1);
pruefe('Eintrag traegt data-module-id', str_contains($m, 'data-module-id="planforge"'), true);
pruefe('newTab wird zu target=_blank mit noopener',
    str_contains($m, 'href="https://go.test/" role="listitem" title="Group-Office" data-module-id="go" target="_blank" rel="noopener noreferrer"'), true);
pruefe('ohne newTab kein target', str_contains($m, 'href="https://plan.test/" role="listitem" title="PlanSchmiede" data-module-id="planforge">'), true);
pruefe('shortLabel wird angezeigt', str_contains($m, '<span class="cf-launcher-label">Plan</span>'), true);
pruefe('keine eigene Modulliste mehr gelesen', str_contains($m, 'modules.json'), false);

// --- 2. Zwischenspeicher -------------------------------------------------
$vorher = aufrufe();
HubDock::reset();
$dock = HubDock::current();
pruefe('innerhalb der Frist wird nicht erneut gefragt', aufrufe(), $vorher);
pruefe('und die Liste ist dieselbe', ids($dock), ['planforge', 'go']);

// --- 3. unchanged --------------------------------------------------------
// Frist kuenstlich ablaufen lassen: geholt-Zeitstempel zuruecksetzen.
function altern(int $sekunden): void
{
    global $sand;
    foreach (glob($sand . '/data/cache/hub-dock-*.json') ?: [] as $f) {
        $d = json_decode((string)file_get_contents($f), true);
        $d['geholt'] = time() - $sekunden;
        file_put_contents($f, json_encode($d));
    }
    HubDock::reset();
}
altern(400);
$vorher = aufrufe();
$dock = HubDock::current();
pruefe('nach Ablauf wird gefragt', aufrufe() > $vorher, true);
pruefe('unchanged behaelt die Liste', ids($dock), ['planforge', 'go']);

// --- 4. Neue Version ----------------------------------------------------
altern(400);
setzeFall('liste', 'v2');
$dock = HubDock::current();
pruefe('neue Version wird uebernommen', $dock['version'] ?? '', 'v2');

// --- 5. Leere Liste ist nicht "unbekannt" --------------------------------
leereSpeicher();
setzeFall('leer');
$dock = HubDock::current();
pruefe('leere Liste bleibt eine Liste (nicht null)', is_array($dock) ? ids($dock) : null, []);
$m = markup();
pruefe('leere Liste -> genau ein Eintrag (Hub-Zeichen)', substr_count($m, 'class="cf-launcher-item'), 1);

// --- 6. Gesperrte Person -------------------------------------------------
leereSpeicher();
setzeFall('gesperrt');
$dock = HubDock::current();
pruefe('gesperrt -> locked true und keine Eintraege',
    [$dock['locked'] ?? null, ids($dock)], [true, []]);

// --- 7. Person unbekannt (404) ------------------------------------------
leereSpeicher();
setzeFall('unknown');
pruefe('404 unknown_subject -> unbekannt (null), nicht leere Liste', HubDock::current(), null);
$m = markup();
pruefe('unbekannt -> nur das Hub-Zeichen', substr_count($m, 'class="cf-launcher-item'), 1);
pruefe('unbekannt -> Hub-Zeichen faellt auf unsere Adresse zurueck',
    str_contains($m, 'hub-fallback.svg'), true);

// --- 8. Token/Recht fehlt ------------------------------------------------
leereSpeicher();
setzeFall('unauthorized');
pruefe('401 -> null', HubDock::current(), null);
leereSpeicher();
setzeFall('forbidden');
pruefe('403 -> null', HubDock::current(), null);

// --- 9. Kein Token im Modul ---------------------------------------------
leereSpeicher();
setzeFall('liste');
putenv('SLIDEFORGE_HUB_TOKEN=');
$vorher = aufrufe();
pruefe('ohne Token wird der Hub nicht gefragt', HubDock::current(), null);
pruefe('und es geht kein Aufruf hinaus', aufrufe(), $vorher);
putenv('SLIDEFORGE_HUB_TOKEN=cfm_pruefstand');

// --- 10. Nicht angemeldet -----------------------------------------------
leereSpeicher();
SharedAuth::$user = [];
HubDock::reset();
pruefe('ohne Anmeldung keine Abfrage', HubDock::current(), null);
SharedAuth::$user = ['sub' => '4711', 'username' => 'eva'];
HubDock::reset();

// --- 11. Kaputte Antwort ------------------------------------------------
leereSpeicher();
setzeFall('kaputt');
pruefe('kaputtes JSON -> null', HubDock::current(), null);

// --- 12. Hub stumm: Gnadenfrist -----------------------------------------
leereSpeicher();
setzeFall('liste');
HubDock::current();                      // Speicher fuellen
setzeFall('langsam');                    // Antwort kommt zu spaet (5 s > 2 s Zeitlimit)
altern(400);                             // Frist abgelaufen
$t0 = microtime(true);
$dock = HubDock::current();
$dauer = microtime(true) - $t0;
pruefe('Hub stumm, Speicher frisch -> letzte Liste', ids($dock), ['planforge', 'go']);
pruefe('und der Seitenaufbau wartet maximal ~2 s', $dauer < 3.5, true);
altern(2000);                            // ueber TTL x 4
$dock = HubDock::current();
pruefe('Hub stumm, Speicher zu alt -> null', $dock, null);

// --- 13. Unsinn und boese Adressen in der Antwort -----------------------
leereSpeicher();
setzeFall('boese');
$dock = HubDock::current();
pruefe('javascript:-Verweis fliegt raus, Eintrag ohne Kennung auch, Unsinn ebenso',
    ids($dock), ['ok']);
$m = markup();
pruefe('kein javascript: im Markup', stripos($m, 'javascript:'), false);
pruefe('kein data: im Markup', stripos($m, 'data:text'), false);
pruefe('Hub-Adresse faellt auf unsere zurueck', str_contains($m, 'hub-fallback.svg'), true);
pruefe('Spitzklammern sind maskiert', str_contains($m, '&lt;b&gt;Kurz&lt;/b&gt;'), true);
pruefe('kaufmaennisches Und ist maskiert', str_contains($m, 'https://ok.test/?a=1&amp;b=2'), true);

// --- 14. 503 lock_unknown: wie "Hub nicht erreichbar" (Vertrag Fassung 2) ---
leereSpeicher();
setzeFall('liste');
HubDock::current();                      // Speicher fuellen
setzeFall('lock_unknown');
altern(400);                             // Frist abgelaufen
$dock = HubDock::current();
pruefe('503 lock_unknown -> letzte Liste gilt weiter (nichts herabsetzen)',
    ids($dock), ['planforge', 'go']);
altern(2000);
pruefe('503 und Speicher zu alt -> null', HubDock::current(), null);

// --- 15. 404 wirft einen alten Stand weg --------------------------------
leereSpeicher();
setzeFall('liste');
HubDock::current();
pruefe('Speicherdatei ist da', count(glob($sand . '/data/cache/hub-dock-*.json') ?: []), 1);
setzeFall('unknown');
altern(400);
pruefe('404 -> null, auch wenn ein Stand im Speicher lag', HubDock::current(), null);
pruefe('und der alte Stand ist weg', count(glob($sand . '/data/cache/hub-dock-*.json') ?: []), 0);

// --- 16. 401 benutzt den Zwischenspeicher NICHT -------------------------
leereSpeicher();
setzeFall('liste');
HubDock::current();
setzeFall('unauthorized');
altern(400);
pruefe('401 -> keine Liste aus dem Speicher (Recht weg, nicht Ausfall)', HubDock::current(), null);
altern(0);
pruefe('der Stand liegt aber noch da (fuer die Erholung nach dem Fehler)',
    count(glob($sand . '/data/cache/hub-dock-*.json') ?: []), 1);
setzeFall('liste');
altern(400);
pruefe('nach dem Einrichtungsfehler kommt die Liste wieder', ids(HubDock::current()), ['planforge', 'go']);

// --- 17. Untergeschobener Zwischenspeicher wird gesaeubert --------------
leereSpeicher();
setzeFall('liste');
HubDock::current();
$datei = (glob($sand . '/data/cache/hub-dock-*.json') ?: [])[0];
$d = json_decode((string)file_get_contents($datei), true);
$d['stand']['items'][] = ['id' => 'boese', 'label' => 'Boese', 'href' => 'javascript:alert(1)', 'newTab' => false];
$d['stand']['hub']['href'] = 'javascript:alert(2)';
file_put_contents($datei, json_encode($d));
HubDock::reset();
$dock = HubDock::current();
pruefe('aus dem Speicher kommt kein javascript:-Eintrag', ids($dock), ['planforge', 'go']);
$m = markup();
pruefe('und kein javascript: im Markup', stripos($m, 'javascript:'), false);

// --- 18. Auflagen aus dem Review 19.10. --------------------------------
leereSpeicher();
setzeFall('liste');
$dock = HubDock::current();
pruefe('Zeichen vom Hub-Host wird uebernommen',
    str_contains((string)($dock['items'][0]['iconUrl'] ?? ''), '127.0.0.1'), true);
$m = markup();
pruefe('fremdes Zeichen (hub.test) landet nicht im Markup', str_contains($m, 'hub.test/i.svg'), false);

// Zeichen von fremder Herkunft wird verworfen, der Eintrag bleibt
leereSpeicher();
setzeFall('boese');
$dock = HubDock::current();
pruefe('fremdes Zeichen wird verworfen, der Eintrag bleibt',
    [(string)($dock['items'][0]['id'] ?? ''), (string)($dock['items'][0]['iconUrl'] ?? '')], ['ok', '']);

// Frist aus der Antwort wird gedeckelt
leereSpeicher();
setzeFall('liste');
HubDock::current();
$datei = (glob($sand . '/data/cache/hub-dock-*.json') ?: [])[0];
$d = json_decode((string)file_get_contents($datei), true);
pruefe('Frist wird abgelegt wie geliefert (300)', (int)$d['ttl'], 300);
$d['ttl'] = 999999;
file_put_contents($datei, json_encode($d));
$d['geholt'] = time() - 700;
file_put_contents($datei, json_encode($d));
HubDock::reset();
setzeFall('langsam');
$t0 = microtime(true);
HubDock::current();
pruefe('eine masslose Frist im Speicher wird nicht geglaubt (es wird gefragt)',
    microtime(true) - $t0 > 1.5, true);

// Negativ-Marke: der zweite Aufbau wartet nicht erneut
$t0 = microtime(true);
HubDock::reset();
HubDock::current();
pruefe('nach einem stummen Hub wartet der naechste Aufbau nicht erneut',
    microtime(true) - $t0 < 0.5, true);

// --- 19. Auflagen aus meinem eigenen Review ----------------------------
leereSpeicher();
setzeFall('gesperrt-mit-eintrag');
$dock = HubDock::current();
pruefe('locked true setzt die Liste leer, auch wenn Eintraege kommen',
    [$dock['locked'] ?? null, ids($dock), $dock['isAdmin'] ?? null], [true, [], false]);

leereSpeicher();
setzeFall('liste');
putenv('CHURCHFORGE_HUB_URL=');
HubDock::reset();
$vorher = aufrufe();
pruefe('ohne CHURCHFORGE_HUB_URL kein Aufruf', [HubDock::current(), aufrufe()], [null, $vorher]);
putenv('CHURCHFORGE_HUB_URL=http://127.0.0.1:' . $port);

leereSpeicher();
putenv('SLIDEFORGE_HUB_TOKEN=cfm_gut' . "\n" . 'X-Boese: 1');
HubDock::reset();
$vorher = aufrufe();
pruefe('Token mit Zeilenumbruch wird verworfen, kein Aufruf',
    [HubDock::current(), aufrufe()], [null, $vorher]);
putenv('SLIDEFORGE_HUB_TOKEN=cfm_pruefstand');
HubDock::reset();

leereSpeicher();
setzeFall('liste');
HubDock::current();
$datei = (glob($sand . '/data/cache/hub-dock-*.json') ?: [])[0];
$d = json_decode((string)file_get_contents($datei), true);
unset($d['stand']);
$d['geholt'] = time() - 400;
file_put_contents($datei, json_encode($d));
HubDock::reset();
pruefe('Version ohne Stand: es wird voll gefragt, die Liste kommt wieder',
    ids(HubDock::current()), ['planforge', 'go']);

// --- 20. Aktive App ----------------------------------------------------
leereSpeicher();
setzeFall('liste');
$m = markup('planforge');
pruefe('aktive App wird hervorgehoben', str_contains($m, 'class="cf-launcher-item is-active" href="https://plan.test/"'), true);

printf("\n%d Pruefungen, %d Fehler\n", $pruefungen, $fehler);
exit($fehler === 0 ? 0 : 1);
