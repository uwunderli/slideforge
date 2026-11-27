<?php
declare(strict_types=1);

/**
 * Früher in der FolienSchmiede hochgeladene Schriften (`custom_fonts`, public/uploads/fonts/) in den Schriften-Ordner des Hubs kopieren
 * (3.9.0; Hub ≥ 2.34.0, Einstellungen › Hub › Palette › Schriften). Handaktion, kein Takt.
 *
 *   php scripts/schriften-in-hub.php --person=u:<name>          Probelauf: zeigt, was kopiert würde
 *   php scripts/schriften-in-hub.php --person=u:<name> --run    kopieren (FileForge `create` als diese Person, ersetzt nie)
 *
 * Nur wenn im Hub ein Ordner gewählt ist (`api/fonts.php?liste=1` → ordner.ref). Idempotent: eine Datei mit gleichem Namen im Ordner
 * wird übersprungen. Dateiname: «<Familie>.<Endung>». Das Recht prüft FileForge (Seafile als die Person). Danach den Ordner im Hub neu
 * einlesen lassen («Neu einlesen» oder Job «Schriften einlesen»). Die Dateien in der FolienSchmiede bleiben (Übergang).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config.php';

$opts = getopt('', ['person:', 'run']);
$person = is_string($opts['person'] ?? null) ? trim((string)$opts['person']) : '';
$run = isset($opts['run']);
if (preg_match('/^(u|ct):[A-Za-z0-9._@:+-]{1,120}\z/', $person) !== 1) {
    fwrite(STDERR, "Aufruf: php scripts/schriften-in-hub.php --person=u:<name> [--run]\n");
    exit(2);
}

$fonts = FontLibrary::customFonts();
echo 'Schriften der FolienSchmiede: ' . count($fonts) . "\n";
foreach ($fonts as $f) {
    echo '  - ' . (string)($f['family'] ?? '?') . ' (' . strtoupper((string)($f['format'] ?? '')) . ', ' . (string)($f['file'] ?? '') . ")\n";
}
if ($fonts === []) {
    exit(0);
}

$l = HubApp::hubGet('/api/fonts.php?liste=1', 10);
$ref = (string)($l['json']['ordner']['ref'] ?? '');
if (empty($l['ok']) || $ref === '' || ($p = FfFile::parseRef($ref)) === null || !$p['dir']) {
    fwrite(STDERR, 'Kein Schriften-Ordner im Hub gewählt oder Hub nicht erreichbar (' . ($l['error'] ?: 'ohne Ordner') . "). Nichts getan.\n");
    exit(1);
}
$liste = FfFile::listing($person, $ref, ['woff2', 'woff', 'ttf', 'otf']);
if (!$liste['ok']) {
    fwrite(STDERR, 'Ordner nicht lesbar als ' . $person . ' (' . $liste['error'] . "). Nichts getan.\n");
    exit(1);
}
$da = [];
foreach ($liste['items'] as $it) {
    if (!$it['isDir']) {
        $da[mb_strtolower($it['name'])] = true;
    }
}

$ok = 0;
$skip = 0;
$fail = 0;
foreach ($fonts as $f) {
    $file = (string)($f['file'] ?? '');
    $ext = strtolower((string)($f['format'] ?? ''));
    $path = FontLibrary::filePath($f);
    if ($path === null || !in_array($ext, ['woff2', 'woff', 'ttf', 'otf'], true)) {
        echo "  übersprungen (Datei fehlt): {$file}\n";
        $skip++;
        continue;
    }
    $name = trim((string)preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]/', '', (string)($f['family'] ?? ''))) ?: pathinfo($file, PATHINFO_FILENAME);
    $name .= '.' . $ext;
    if (isset($da[mb_strtolower($name)])) {
        echo "  schon da: {$name}\n";
        $skip++;
        continue;
    }
    if (!$run) {
        echo "  würde kopieren: {$name}\n";
        continue;
    }
    $r = FfFile::create($person, $p['repo'], $p['path'], $name, $path);
    if ($r['ok']) {
        echo "  kopiert: {$r['name']}\n";
        $da[mb_strtolower($name)] = true;
        $ok++;
    } else {
        echo "  Fehler: {$name} ({$r['error']})\n";
        $fail++;
    }
}
echo $run ? "Kopiert {$ok}, übersprungen {$skip}, Fehler {$fail}. Danach im Hub «Neu einlesen».\n" : "Probelauf. Mit --run kopieren.\n";
exit($fail > 0 ? 1 : 0);
