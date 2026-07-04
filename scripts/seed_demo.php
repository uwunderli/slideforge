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

$adminId = null;
foreach (Storage::read(USERS_FILE, []) as $u) {
    if (($u['role'] ?? '') === 'admin') {
        $adminId = $u['id'];
        break;
    }
}

$tplCount = 0;
$showcase = false;
$featureTour = false;
if ($adminId !== null) {
    [$myTpl] = Presentation::listTemplatesForUser($adminId);
    $tplCount = count($myTpl);
    foreach (scandir(PRESENTATIONS_PATH) ?: [] as $entry) {
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
        if ($title === 'SlideForge Feature Tour') {
            $featureTour = true;
        }
    }
}

echo "Demo zurückgesetzt.\n";
echo "  Folienvorlagen (Admin): $tplCount\n";
echo "  Element-Showcase: " . ($showcase ? 'ja' : 'nein') . "\n";
echo "  Feature-Tour: " . ($featureTour ? 'ja' : 'nein') . "\n";
if ($featureTour) {
    echo "  Tour: https://slideforge.service7.ch/view.php?token=" . Demo::FEATURE_TOUR_TOKEN . "\n";
}
echo "  Nächster Reset: " . date('c', Demo::nextResetAt()) . "\n";
