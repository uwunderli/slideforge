#!/usr/bin/env php
<?php
/**
 * Importiert seed/feature-tour/ inkl. öffentlichem Link (einmalig oder nach manuellem Löschen).
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

$seedDir = BASE_PATH . '/seed/feature-tour';
$presId = Presentation::importSeedPresentation($adminId, $seedDir);
if ($presId === null) {
    fwrite(STDERR, "Import fehlgeschlagen (seed/feature-tour/ prüfen).\n");
    exit(1);
}

$acl = Presentation::getAcl($presId);
$token = $acl['public']['token'] ?? '';
$scheme = 'https';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
echo "Feature-Tour importiert: $presId\n";
if (!empty($acl['public']['enabled']) && $token !== '') {
    echo "Öffentlicher Link: {$scheme}://{$host}/view.php?token={$token}\n";
} else {
    echo "Hinweis: öffentlicher Link in seed/feature-tour/meta.json aktivieren.\n";
}
