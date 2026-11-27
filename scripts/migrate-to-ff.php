<?php
declare(strict_types=1);

/**
 * Altbestand der FolienSchmiede nach FileForge übernehmen (H6). Handaktion, kein Takt; sichtbar im Task Manager des Hubs.
 *
 *   php scripts/migrate-to-ff.php --source=/data/slideforge/data          Probelauf: nur Protokoll, nichts abgelegt
 *   php scripts/migrate-to-ff.php --source=/data/slideforge/data --run    übernehmen (zweiter Lauf überspringt Übernommenes)
 *   … --ablage="BK - Office"   statt «Meine Ablage» in diese Ablage (per Name, als die Besitzerin; ohne Schreibrecht = Fehler)
 *
 * Protokoll: data/migration/protokoll-<zeit>.md. Die Quelle wird nur gelesen.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config.php';

$opts = getopt('', ['source:', 'run', 'ablage:']);
$ablage = is_string($opts['ablage'] ?? null) ? trim((string)$opts['ablage']) : '';
$source = is_string($opts['source'] ?? null) ? (string)$opts['source'] : '';
if ($source === '' || !is_dir($source)) {
    fwrite(STDERR, "Aufruf: php scripts/migrate-to-ff.php --source=<alter Datenordner> [--run] [--ablage=\"<Name der Ablage>\"]\n");
    exit(2);
}
@set_time_limit(0);
$r = Migration::run($source, !isset($opts['run']), static function (string $line): void {
    echo $line, "\n";
}, $ablage);
if (empty($r['ok']) && ($r['error'] ?? '') !== 'cancelled') {
    fwrite(STDERR, 'Fehler: ' . (string)($r['error'] ?? '?') . "\n");
    exit(1);
}
$c = $r['counts'];
printf("Fertig%s: %d Präsentationen, %d übernommen, %d schon übernommen, %d nicht zuordenbar, %d Fehler. Protokoll: %s\n",
    ($r['error'] ?? '') === 'cancelled' ? ' (abgebrochen)' : '', $c['total'], $c['done'], $c['skipped'], $c['unassigned'], $c['failed'], $r['protocol']);
