#!/usr/bin/env php
<?php
/**
 * Baut seed/layout-sets/schlicht/ aus den Standard-Folienvorlagen in seed/templates/.
 * Reihenfolge und Inhalte entsprechen dem Folien-Set „Schlicht“.
 *
 * Usage: php scripts/build_schlicht_seed.php
 */
require __DIR__ . '/../config.php';

$seedName = 'schlicht';
$target = BASE_PATH . '/seed/layout-sets/' . $seedName;
$templateOrder = [
    '77da9b45b679c85b', // Titel
    '6f46aa3644fc25d5', // Abschnitt
    '60c1046a44ddcd8f', // Überschrift
    '4a38cb88d0722919', // Überschrift und Inhalt
    '1040b5e23d947b36', // Überschrift und zwei Inhalte
    '0691915389a265ac', // Text
    'fc35b144beff7c31', // Vergleich
];

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

$slides = [];
foreach ($templateOrder as $templateSeedId) {
    $src = BASE_PATH . '/seed/templates/' . $templateSeedId;
    $metaPath = $src . '/meta.json';
    $slidesPath = $src . '/slides.json';
    if (!is_file($metaPath) || !is_file($slidesPath)) {
        fwrite(STDERR, "Fehlende Vorlage: {$templateSeedId}\n");
        exit(1);
    }
    $tplMeta = json_decode((string)file_get_contents($metaPath), true);
    $tplSlides = json_decode((string)file_get_contents($slidesPath), true);
    $slide = $tplSlides['slides'][0] ?? null;
    if (!is_array($slide)) {
        fwrite(STDERR, "Leere Vorlage: {$templateSeedId}\n");
        exit(1);
    }

    $title = trim((string)($tplMeta['title'] ?? ''));
    $slide['id'] = Storage::generateId(4);
    $slide['layoutKey'] = LayoutSet::layoutKeyFromTitle($title);
    $slide['label'] = $title !== '' ? $title : LayoutSet::slideLabel($slide);
    $slide = LayoutSet::prepareLayoutSlide($slide);

    $json = json_encode($slide, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $json = str_replace(
        'asset.php?id=' . urlencode($templateSeedId) . '&',
        'asset.php?id=' . urlencode($seedName) . '&',
        $json
    );
    $slide = json_decode($json, true) ?? $slide;
    $slides[] = $slide;

    $srcAssets = $src . '/assets';
    if (is_dir($srcAssets)) {
        foreach (glob($srcAssets . '/*') ?: [] as $file) {
            if (is_file($file)) {
                copy($file, $target . '/assets/' . basename($file));
            }
        }
    }
}

$seedMeta = [
    'title' => 'Schlicht',
    'width' => DEFAULT_SLIDE_WIDTH,
    'height' => DEFAULT_SLIDE_HEIGHT,
    'template_order' => 0,
    'is_layout_set' => true,
    'template_shared' => true,
    'default_layout_set' => true,
    'logosNotesOrder' => LayoutSet::DEFAULT_NOTES_ORDER,
    'elementZones' => LayoutSet::DEFAULT_ELEMENT_ZONES,
    'safe_margin' => 100,
];

file_put_contents(
    $target . '/meta.json',
    json_encode($seedMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);
file_put_contents(
    $target . '/slides.json',
    json_encode(['slides' => $slides], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "Erstellt: seed/layout-sets/{$seedName}/ mit " . count($slides) . " Layout-Folien.\n";
