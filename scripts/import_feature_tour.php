#!/usr/bin/env php
<?php
/**
 * Importiert alle seed/feature-tour/{lang}/ inkl. öffentlicher Links.
 * Aufruf: php scripts/import_feature_tour.php
 */
require __DIR__ . '/../config.php';

$users = Storage::read(USERS_FILE, []);
$adminId = null;
foreach ($users as $u) {
    if (($u['role'] ?? '') === 'admin') {
        $adminId = $u['id'];
        break;
    }
}
if ($adminId === null) {
    fwrite(STDERR, "Kein Admin-Benutzer gefunden.\n");
    exit(1);
}

$base = BASE_PATH . '/seed/feature-tour';
$scheme = 'https';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$imported = 0;

foreach (['de', 'en', 'fr', 'it', 'rm'] as $lang) {
    $seedDir = $base . '/' . $lang;
    if (!is_file($seedDir . '/meta.json')) {
        continue;
    }
    $presId = Presentation::importSeedPresentation($adminId, $seedDir);
    if ($presId === null) {
        fwrite(STDERR, "Import fehlgeschlagen: $lang\n");
        continue;
    }
    $imported++;
    $acl = Presentation::getAcl($presId);
    $token = $acl['public']['token'] ?? '';
    echo "[$lang] $presId";
    if (!empty($acl['public']['enabled']) && $token !== '') {
        echo " → {$scheme}://{$host}/view.php?token={$token}";
    }
    echo "\n";
}

if ($imported === 0) {
    fwrite(STDERR, "Keine Touren importiert (php scripts/build_feature_tour.php ausführen?).\n");
    exit(1);
}

echo "Importiert: $imported Tour(en).\n";
