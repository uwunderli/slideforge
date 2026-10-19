<?php
/**
 * Prueffall fuer das neue Dock (U50): Favoriten bekannt / leer / unbekannt /
 * Zweitkonto-Verweis. Laeuft ohne Hub und ohne Anmeldung, mit Attrappen.
 */
$modul = dirname(__DIR__);
$sand = __DIR__ . '/sand';

define('BASE_PATH', $sand . '/app');
define('CHURCHFORGE_SHARED_PATH', $sand . '/shared');
$hubData = $sand . '/hub/data/gemeinde';

// --- Attrappen -----------------------------------------------------------
class SharedAuth
{
    /** @var array<string,mixed> */
    public static array $user = [];
    public static function read(): ?array
    {
        return self::$user === [] ? null : self::$user;
    }
}

function churchforge_hub_url(): string
{
    return 'https://hub.beispiel.test';
}
function churchforge_modules_file(): string
{
    return dirname(CHURCHFORGE_SHARED_PATH) . '/hub/data/gemeinde/modules.json';
}

require $modul . '/src/HubFavorites.php';
require $modul . '/src/Launcher.php';

// --- Sandkasten anlegen --------------------------------------------------
@mkdir($hubData, 0700, true);
// Hinweis: seit 19.10.2026 gilt fail-closed — ein Modul OHNE requireAnyGroup ist
// fuer niemanden sichtbar. Die drei normalen Module tragen darum die Gruppe, die
// unsere Pruefperson hat ('Mitarbeiter'); 'ohnegruppe' prueft genau den Rueckfall.
file_put_contents($hubData . '/modules.json', json_encode(['modules' => [
    ['id' => 'slideforge', 'labels' => ['de' => 'FolienSchmiede'], 'url' => 'https://slides.test/', 'requireAnyGroup' => ['Mitarbeiter'], 'sort' => '20'],
    ['id' => 'planforge', 'labels' => ['de' => 'PlanSchmiede'], 'url' => 'https://plan.test/', 'requireAnyGroup' => ['Mitarbeiter'], 'sort' => '10'],
    ['id' => 'officeforge', 'labels' => ['de' => 'BueroSchmiede'], 'url' => 'https://buero.test/', 'requireAnyGroup' => ['Mitarbeiter'], 'sort' => '30'],
    ['id' => 'geheim', 'labels' => ['de' => 'Nur Admin'], 'url' => 'https://x.test/', 'requireAnyGroup' => ['Vorstand'], 'sort' => '40'],
    ['id' => 'ohnegruppe', 'labels' => ['de' => 'Ohne Gruppeneintrag'], 'url' => 'https://o.test/', 'sort' => '45'],
    ['id' => 'versteckt', 'labels' => ['de' => 'Versteckt'], 'hidden' => true, 'sort' => '50'],
]]));

$pruefungen = 0;
$fehler = 0;
function pruefe(string $was, $ist, $soll): void
{
    global $pruefungen, $fehler;
    $pruefungen++;
    $gleich = $ist === $soll;
    if (!$gleich) {
        $fehler++;
    }
    printf("%s %s\n   ist : %s\n   soll: %s\n", $gleich ? 'OK  ' : 'FEHL', $was,
        json_encode($ist, JSON_UNESCAPED_UNICODE), json_encode($soll, JSON_UNESCAPED_UNICODE));
}

function ids(array $module): array
{
    return array_map(static fn($m) => (string)$m['id'], $module);
}

function setzePrefs(string $hubData, array $prefs): void
{
    file_put_contents($hubData . '/user-prefs.json', json_encode($prefs));
    HubFavorites::reset();
}

SharedAuth::$user = ['sub' => '4711', 'username' => 'urs', 'tags' => [], 'groups' => []];

// 1) Favoriten bekannt: nur diese, in dieser Reihenfolge
setzePrefs($hubData, ['4711' => ['favorites' => ['officeforge', 'planforge']]]);
pruefe('Favoriten bekannt -> nur Favoriten in Hub-Reihenfolge',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['officeforge', 'planforge']);

// 2) Favorit, den die Person nicht sehen darf, fliegt raus
setzePrefs($hubData, ['4711' => ['favorites' => ['geheim', 'planforge']]]);
pruefe('Favorit ohne Berechtigung wird weggelassen',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['planforge']);
pruefe('mit Gruppe Vorstand ist er dabei',
    ids(Launcher::modulesForUser(['Mitarbeiter', 'Vorstand'], 'de')), ['geheim', 'planforge']);

// 3) Leere Favoritenliste: leeres Dock (wie im Hub)
setzePrefs($hubData, ['4711' => ['favorites' => []]]);
pruefe('keine Favoriten -> leeres Dock', ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), []);

// 4) Person im Hub-Stand nicht gefuehrt -> wie im Hub: keine Favoriten -> leeres Dock
//    (Auflage aus dem Review: nicht "unbekannt", sonst kippt das Dock spaeter von
//     "alles" auf "leer", sobald im Hub irgendeine Einstellung einen Eintrag anlegt)
setzePrefs($hubData, ['9999' => ['favorites' => ['planforge']]]);
pruefe('Person nicht im Hub-Stand -> leeres Dock (wie im Hub)',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), []);
setzePrefs($hubData, ['4711' => ['theme' => 'dark']]);
pruefe('Eintrag ohne favorites -> leeres Dock',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), []);

// 5) Zweitkonto zeigt auf Hauptkonto
setzePrefs($hubData, ['4711' => ['favorites' => ['officeforge']], '5000' => ['favorites' => ['planforge']]]);
file_put_contents($hubData . '/identity-links.json', json_encode(['links' => ['4711' => ['to' => '5000']]]));
HubFavorites::reset();
pruefe('Zweitkonto -> Favoriten des Hauptkontos',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['planforge']);
unlink($hubData . '/identity-links.json');

// 6) Datei nicht lesbar -> unbekannt -> alte Anzeige
unlink($hubData . '/user-prefs.json');
HubFavorites::reset();
pruefe('kein Hub-Datenordner -> alles Sichtbare',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['planforge', 'slideforge', 'officeforge']);

// 7) Nicht angemeldet -> unbekannt
SharedAuth::$user = [];
HubFavorites::reset();
pruefe('ohne Hub-Anmeldung -> Favoriten unbekannt', HubFavorites::favorites(), null);

// 8) Markup: Dock-Kennzeichen fuer Ziehen/Ablegen und Hub-Zeichen
SharedAuth::$user = ['sub' => '4711', 'username' => 'urs'];
setzePrefs($hubData, ['4711' => ['favorites' => ['planforge']]]);
ob_start();
Launcher::render('slideforge', ['Mitarbeiter'], 'de');
$markup = (string)ob_get_clean();
pruefe('Dock traegt data-hub-fav-dock', str_contains($markup, 'data-hub-fav-dock'), true);
pruefe('Hub-Zeichen traegt data-hub-home', str_contains($markup, 'data-hub-home'), true);
pruefe('Eintrag traegt data-module-id', str_contains($markup, 'data-module-id="planforge"'), true);
pruefe('kein zweites Hub-Zeichen', substr_count($markup, 'data-hub-home'), 1);

// 9) Kaputtes JSON und unerwartete Typen
file_put_contents($hubData . '/user-prefs.json', '{das ist kein json');
HubFavorites::reset();
pruefe('kaputtes user-prefs.json -> unbekannt (alte Anzeige)',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['planforge', 'slideforge', 'officeforge']);

setzePrefs($hubData, ['4711' => ['favorites' => 'planforge']]);
pruefe('favorites ist kein Feld -> leeres Dock', ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), []);

setzePrefs($hubData, ['4711' => ['favorites' => ['planforge', '', 'planforge', 'gibtsnicht', 42]]]);
pruefe('leere/doppelte/unbekannte Eintraege werden gefiltert',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['planforge']);

// 8b) **Fail-closed**: ein Modul ohne requireAnyGroup ist fuer niemanden sichtbar —
//     auch nicht, wenn es als Favorit eingetragen ist (Regel Urs 19.10.2026).
setzePrefs($hubData, ['4711' => ['favorites' => ['ohnegruppe', 'planforge']]]);
pruefe('Modul ohne Gruppeneintrag ist nicht sichtbar, auch als Favorit nicht',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['planforge']);
unlink($hubData . '/user-prefs.json');
HubFavorites::reset();
pruefe('auch im Rueckfall (Favoriten unbekannt) bleibt es weg',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['planforge', 'slideforge', 'officeforge']);
pruefe('Gruppe "Admin" sieht es weiterhin (unveraendert, eigener Entscheid offen)',
    in_array('ohnegruppe', ids(Launcher::modulesForUser(['Admin'], 'de')), true), true);

// 9b) Verschachtelter Wert darf keine Warnung ausloesen (display_errors = 1)
setzePrefs($hubData, ['4711' => ['favorites' => ['planforge', ['officeforge'], null, true]]]);
$warnung = '';
set_error_handler(static function ($nr, $text) use (&$warnung) {
    $warnung = $text;
    return true;
});
$ids = ids(Launcher::modulesForUser(['Mitarbeiter'], 'de'));
restore_error_handler();
pruefe('verschachtelter Wert -> keine Warnung', $warnung, '');
pruefe('verschachtelter Wert -> nur die brauchbaren Eintraege', $ids, ['planforge']);

// 9c) Riesige Datei -> unbekannt (statt Seitenaufbau abbrechen)
file_put_contents($hubData . '/user-prefs.json',
    '{"4711":{"favorites":["planforge"],"fuell":"' . str_repeat('x', 4200000) . '"}}');
HubFavorites::reset();
pruefe('Datei ueber der Grenze -> unbekannt (alte Anzeige)',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['planforge', 'slideforge', 'officeforge']);

// 9d) Eigenes (managed) Programm wird jetzt ueber die Hub-Kennung sichtbar
$module = json_decode((string)file_get_contents($hubData . '/modules.json'), true);
$module['modules'][] = ['id' => 'meins', 'labels' => ['de' => 'Mein Programm'],
    'url' => 'https://meins.test/', 'managed' => true, 'owner' => '4711', 'sort' => '05'];
file_put_contents($hubData . '/modules.json', json_encode($module));
setzePrefs($hubData, ['4711' => ['favorites' => ['meins', 'planforge']]]);
pruefe('eigenes Programm (managed, owner = Hub-Kennung) ist dabei',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), ['meins', 'planforge']);
setzePrefs($hubData, ['4711' => ['favorites' => ['meins', 'planforge']], '0' => []]);
SharedAuth::$user = ['sub' => '5001', 'username' => 'andere'];
HubFavorites::reset();
pruefe('fremdes managed-Programm bleibt fuer andere weg',
    ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), []);
SharedAuth::$user = ['sub' => '4711', 'username' => 'urs'];
HubFavorites::reset();

// 10) **Der Fall Prod heute: gar kein Hub-Datenordner.** Dock = nur das Hub-Zeichen,
//     keine Meldung, kein Fehler.
unlink($hubData . '/user-prefs.json');
unlink($hubData . '/modules.json');
HubFavorites::reset();
pruefe('ohne Hub-Daten -> keine Module', ids(Launcher::modulesForUser(['Mitarbeiter'], 'de')), []);
ob_start();
Launcher::render('slideforge', ['Mitarbeiter'], 'de');
$ohne = (string)ob_get_clean();
pruefe('ohne Hub-Daten -> genau ein Eintrag (das Hub-Zeichen)',
    substr_count($ohne, 'class="cf-launcher-item'), 1);
pruefe('ohne Hub-Daten -> kein data-module-id', str_contains($ohne, 'data-module-id'), false);
pruefe('ohne Hub-Daten -> Dock-Gefaess steht trotzdem',
    str_contains($ohne, 'data-hub-fav-dock') && str_contains($ohne, 'data-hub-home'), true);

printf("\n%d Pruefungen, %d Fehler\n", $pruefungen, $fehler);
exit($fehler === 0 ? 0 : 1);
