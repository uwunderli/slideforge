<?php
/**
 * Demo-Reset per HTTP (SFTP-only Hosting: kein SSH-Exec).
 * Nur aktiv wenn DEMO_MODE=true. Nach Reset: Text mit Zählerstand.
 */
require dirname(__DIR__) . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

if (!Demo::isActive()) {
    http_response_code(404);
    echo "Not found.\n";
    exit;
}

Demo::resetAll();

$adminId = null;
foreach (Storage::read(USERS_FILE, []) as $u) {
    if (($u['role'] ?? '') === 'admin') {
        $adminId = $u['id'];
        break;
    }
}

$tplCount = 0;
$showcase = false;
$tourLinks = [];
if ($adminId !== null) {
    [$myTpl] = Presentation::listTemplatesForUser($adminId);
    $tplCount = count($myTpl);
    foreach (@scandir(PRESENTATIONS_PATH) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $meta = Presentation::getMeta($entry);
        if (!$meta || !empty($meta['is_template'])) {
            continue;
        }
        $title = $meta['title'] ?? '';
        if ($title === 'Element-Showcase') {
            $showcase = true;
        }
        if (str_starts_with($title, 'SlideForge')) {
            $acl = Presentation::getAcl($entry);
            if (!empty($acl['public']['enabled']) && !empty($acl['public']['token'])) {
                $tourLinks[] = $acl['public']['token'];
            }
        }
    }
}
sort($tourLinks);

echo "Demo reset OK\n";
echo "Folienvorlagen (Admin): $tplCount\n";
echo "Element-Showcase: " . ($showcase ? 'ja' : 'nein') . "\n";
echo "Feature-Touren: " . count($tourLinks) . "\n";
foreach ($tourLinks as $token) {
    echo "  https://slideforge.service7.ch/view.php?token={$token}\n";
}
echo 'Nächster Reset: ' . date('c', Demo::nextResetAt()) . "\n";
