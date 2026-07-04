#!/usr/bin/env php
<?php
/**
 * Demo-Daten zurücksetzen und Standardkonten anlegen (CLI / Cron).
 */
require __DIR__ . '/../config.php';

if (!Demo::isActive()) {
    fwrite(STDERR, "DEMO_MODE ist nicht aktiv.\n");
    exit(1);
}

Demo::resetAll();
echo "Demo zurückgesetzt. Nächster Reset: " . date('c', Demo::nextResetAt()) . "\n";
