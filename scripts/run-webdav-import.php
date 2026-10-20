<?php
/**
 * CLI: WebDAV-Medienimport (gestartet von webdav.php nach Enqueue).
 * Usage: php scripts/run-webdav-import.php <jobId>
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$jobId = $argv[1] ?? '';
if ($jobId === '' || !preg_match('/^[a-f0-9]{32}$/', $jobId)) {
    fwrite(STDERR, "Usage: run-webdav-import.php <jobId>\n");
    exit(1);
}

require dirname(__DIR__) . '/config.php';

try {
    WebdavImportJob::run($jobId);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'WebDAV import failed: ' . $e->getMessage() . "\n");
    exit(1);
}
