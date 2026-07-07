#!/usr/bin/env php
<?php
/**
 * Exportiert ein Folien-Set aus data/presentations/ nach seed/layout-sets/<name>/.
 *
 * Usage: php scripts/export_layout_set_to_seed.php <presentation-id> [seed-name]
 */
require __DIR__ . '/../config.php';

$setId = $argv[1] ?? '';
$seedName = $argv[2] ?? '';
if ($setId === '') {
    fwrite(STDERR, "Usage: php scripts/export_layout_set_to_seed.php <presentation-id> [seed-name]\n");
    exit(1);
}
if (!LayoutSet::isLayoutSet($setId)) {
    fwrite(STDERR, "Kein Folien-Set: {$setId}\n");
    exit(1);
}

$meta = Presentation::getMeta($setId);
if (!$meta) {
    fwrite(STDERR, "Meta nicht gefunden.\n");
    exit(1);
}

if ($seedName === '') {
    $seedName = strtolower(trim((string)($meta['title'] ?? 'layout-set')));
    $seedName = preg_replace('/[^a-z0-9]+/', '-', $seedName) ?? 'layout-set';
    $seedName = trim($seedName, '-') ?: 'layout-set';
}

$target = BASE_PATH . '/seed/layout-sets/' . $seedName;
if (is_dir($target)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($target);
}
mkdir($target, 0770, true);
mkdir($target . '/assets', 0770, true);

$seedMeta = [
    'title' => $meta['title'] ?? 'Folien-Set',
    'width' => (int)($meta['width'] ?? DEFAULT_SLIDE_WIDTH),
    'height' => (int)($meta['height'] ?? DEFAULT_SLIDE_HEIGHT),
    'template_order' => (int)($meta['template_order'] ?? 0),
    'is_layout_set' => true,
    'template_shared' => !empty($meta['template_shared']),
    'default_layout_set' => !empty($meta['default_layout_set']),
    'safe_margin' => (int)($meta['safe_margin'] ?? 100),
];
foreach (['logosLayoutMap', 'logosLayoutSlideIds', 'logosNotesOrder', 'elementZones', 'elementTextLinks'] as $key) {
    if (array_key_exists($key, $meta)) {
        $seedMeta[$key] = $meta[$key];
    }
}

$slidesPath = Presentation::dir($setId) . '/slides.json';
$slidesData = json_decode((string)file_get_contents($slidesPath), true);
$json = json_encode($slidesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$json = str_replace('asset.php?id=' . urlencode($setId) . '&', 'asset.php?id=' . urlencode($seedName) . '&', $json);

file_put_contents(
    $target . '/meta.json',
    json_encode($seedMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
file_put_contents($target . '/slides.json', $json . "\n");

$srcAssets = Presentation::dir($setId) . '/assets';
if (is_dir($srcAssets)) {
    foreach (glob($srcAssets . '/*') ?: [] as $file) {
        if (is_file($file)) {
            copy($file, $target . '/assets/' . basename($file));
        }
    }
}

echo "Exportiert nach seed/layout-sets/{$seedName}/\n";
