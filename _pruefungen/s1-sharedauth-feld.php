<?php
/**
 * S-1 Phase 1 in unserer eigenen SharedAuth-Fassung (`src/SharedAuth.php`):
 * `decode()` versteht `ct_login_token_enc` **und** die alte Klartext-Form; misslingt das
 * Entschluesseln, gibt es kein Token, aber **nie** eine Abmeldung; `go_access_token` wird
 * weder geschrieben noch weitergereicht.
 *
 * Vorlage: `hub/scripts/test-sharedauth-feld.php` (Vorgabe
 * `_berichte/hub/s1-lesestelle-forks-2026-10-20.md`).
 *
 * Der wichtigste Fall laeuft in **zwei Prozessen**: verschluesselt wird mit der geteilten
 * Fassung (`gemeinde/shared/src/SharedAuth.php`), entschluesselt mit unserer. Beide Klassen
 * heissen `SharedAuth`, darum geht das nicht in einem Prozess — und nur so ist belegt, dass
 * die Stelle wirklich byte-gleich rechnet.
 *
 *   php _pruefungen/s1-sharedauth-feld.php
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$modul = dirname(__DIR__);
$geheim = 'feld-pruefung-' . bin2hex(random_bytes(6));

// Unterprozess: verschluesselt mit der GETEILTEN Fassung und gibt das Feld aus.
if (($argv[1] ?? '') === '--shared-enc') {
    putenv('CHURCHFORGE_AUTH_SECRET=' . $argv[2]);
    require dirname($modul) . '/shared/src/SharedAuth.php';
    echo SharedAuth::feldVerschluesseln($argv[3], $argv[4]);
    exit(0);
}

putenv('CHURCHFORGE_AUTH_SECRET=' . $geheim);
define('BASE_PATH', $modul);
require $modul . '/src/SharedAuth.php';

$fehler = 0;
$pruefe = static function (string $was, bool $gut, string $wie = '') use (&$fehler): void {
    echo ($gut ? 'OK   ' : 'FEHL ') . $was . (!$gut && $wie !== '' ? '  — ' . mb_substr($wie, 0, 300) : '') . "\n";
    $fehler += $gut ? 0 : 1;
};

$encode = new ReflectionMethod('SharedAuth', 'encode');
$mach = static fn (array $p): string => (string)$encode->invoke(
    null,
    $p + ['iat' => time(), 'exp' => time() + 600, 'username' => 'urs']
);

$sharedEnc = static function (string $klar, string $sub) use ($modul, $geheim): string {
    $cmd = sprintf(
        'php %s --shared-enc %s %s %s',
        escapeshellarg($modul . '/_pruefungen/s1-sharedauth-feld.php'),
        escapeshellarg($geheim),
        escapeshellarg($klar),
        escapeshellarg($sub)
    );
    return trim((string)shell_exec($cmd));
};

// --- 1. Die alte Klartext-Form gilt weiter -------------------------------
$v = SharedAuth::validateToken($mach(['sub' => '42', 'ct_login_token' => 'tok-42']));
$pruefe('Klartext-Form wird weiter gelesen', ($v['ct_login_token'] ?? '') === 'tok-42');

// --- 2. Eigene Verschluesselung, eigene Entschluesselung -----------------
$v = SharedAuth::validateToken($mach(['sub' => '42', 'ct_login_token_enc' => SharedAuth::feldVerschluesseln('tok-42', '42')]));
$pruefe('eigene verschluesselte Form -> Klartext fuer die Leser, _enc nicht weitergereicht',
    ($v['ct_login_token'] ?? '') === 'tok-42' && !isset($v['ct_login_token_enc']), json_encode($v));

// --- 3. **Der Kern: vom Hub verschluesselt, von uns gelesen** ------------
$vomHub = $sharedEnc('tok-hub', '42');
$pruefe('die geteilte Fassung liefert ein Feld der Form v1.x.y.z',
    preg_match('/^v1\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $vomHub) === 1, $vomHub);
$v = SharedAuth::validateToken($mach(['sub' => '42', 'ct_login_token_enc' => $vomHub]));
$pruefe('vom Hub verschluesselt -> von uns im Klartext gelesen (byte-gleich)',
    ($v['ct_login_token'] ?? '') === 'tok-hub', json_encode($v));

// --- 4. Falsches sub (AAD): Sitzung gueltig, Token leer ------------------
$v = SharedAuth::validateToken($mach(['sub' => '43', 'ct_login_token_enc' => $vomHub]));
$pruefe('anderes sub -> kein Token, Sitzung bleibt gueltig',
    is_array($v) && ($v['ct_login_token'] ?? '') === '', json_encode($v));

// --- 5. Kaputtes Feld: keine Abmeldung ----------------------------------
foreach (['v1.kaputt.kaputt.kaputt', 'v1.a.b', 'v2.a.b.c', '', 'nonsens'] as $mist) {
    $v = SharedAuth::validateToken($mach(['sub' => '42', 'ct_login_token_enc' => $mist]));
    $pruefe('kaputtes Feld (' . ($mist === '' ? 'leer' : $mist) . ') -> kein Token, keine Abmeldung',
        is_array($v) && ($v['ct_login_token'] ?? '') === '');
}

// --- 6. Klartext gewinnt, solange er da ist -----------------------------
$v = SharedAuth::validateToken($mach([
    'sub' => '42',
    'ct_login_token' => 'klartext',
    'ct_login_token_enc' => SharedAuth::feldVerschluesseln('verschluesselt', '42'),
]));
$pruefe('ist beides da, bleibt der Klartext stehen', ($v['ct_login_token'] ?? '') === 'klartext');

// --- 7. go_access_token ------------------------------------------------
$v = SharedAuth::validateToken($mach(['sub' => '42', 'go_access_token' => 'alt-geheim']));
$pruefe('altes go_access_token im Keks wird nicht weitergereicht',
    is_array($v) && !array_key_exists('go_access_token', $v), json_encode(array_keys((array)$v)));

$roh = file_get_contents($modul . '/src/SharedAuth.php');
$pruefe('unsere Fassung schreibt go_access_token nirgends',
    substr_count((string)$roh, 'go_access_token') === 1, 'Treffer: ' . substr_count((string)$roh, 'go_access_token'));

// --- 8. Das Token steht nicht im Klartext im Keks -----------------------
$enc = SharedAuth::feldVerschluesseln('tok-42', '42');
$pruefe('verschluesselte Form enthaelt das Token nicht im Klartext', !str_contains($enc, 'tok-42'));
$pruefe('zweimal verschluesselt gibt zwei verschiedene Felder (eigener Nonce)',
    SharedAuth::feldVerschluesseln('tok-42', '42') !== $enc);

// --- 9. Ohne Geheimnis keine Abmeldung, aber auch kein Token ------------
$keks = $mach(['sub' => '42', 'ct_login_token_enc' => $enc]);
putenv('CHURCHFORGE_AUTH_SECRET=ein-anderes-geheimnis');
$pruefe('mit falschem Geheimnis gilt der Keks gar nicht (Unterschrift)',
    SharedAuth::validateToken($keks) === null);
putenv('CHURCHFORGE_AUTH_SECRET=' . $geheim);

echo $fehler === 0 ? "\nalle Pruefungen bestanden\n" : "\n$fehler Pruefung(en) fehlgeschlagen\n";
exit($fehler === 0 ? 0 : 1);
