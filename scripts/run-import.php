<?php
/**
 * CLI: Datei-Import (gestartet von import.php nach Enqueue).
 * Usage: php scripts/run-import.php <jobId>
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$jobId = $argv[1] ?? '';
if ($jobId === '' || !preg_match('/^[a-f0-9]{32}$/', $jobId)) {
    fwrite(STDERR, "Usage: run-import.php <jobId>\n");
    exit(1);
}

require dirname(__DIR__) . '/config.php';

try {
    ImportJob::run($jobId);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
    exit(1);
}
