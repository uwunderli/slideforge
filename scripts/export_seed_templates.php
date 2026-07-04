#!/usr/bin/env php
<?php
/**
 * Exportiert alle öffentlich freigegebenen Folienvorlagen nach seed/templates/.
 * Auf dem Server ausführen (wo data/presentations/ liegt), danach sync-code deployen.
 *
 * Usage: php scripts/export_seed_templates.php
 */
require __DIR__ . '/../config.php';

$count = Presentation::exportSharedTemplatesToSeed();
echo "Exportiert: {$count} Folienvorlage(n) nach seed/templates/\n";
