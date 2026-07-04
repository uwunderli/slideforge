<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

// Base64-Kodierung von Bildern/Videos für die Einzeldatei-Export braucht deutlich
// mehr Speicher als der PHP-Standard (128 MB) - hier gezielt anheben. @ vor ini_set,
// falls der Server memory_limit per php.ini fest auf PHP_INI_SYSTEM gesetzt hat.
@ini_set('memory_limit', '1024M');
@set_time_limit(180);

$id = $_GET['id'] ?? '';
if (!Presentation::canView($id, $me['id'])) {
    http_response_code(403);
    die(t('export.no_access'));
}
$meta = Presentation::getMeta($id);
$format = $_GET['format'] ?? '';

if ($format === 'html' || $format === 'zip' || $format === 'pptx' || $format === 'odp') {
    $slidesData = Presentation::getSlides($id);
    $filenameBase = Exporter::sanitizeFilename($meta['title']);

    if ($format === 'pptx') {
        $pptxPath = PptxExporter::build($meta, $slidesData, $id);
        header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.pptx"');
        header('Content-Length: ' . filesize($pptxPath));
        readfile($pptxPath);
        unlink($pptxPath);
        exit;
    }

    if ($format === 'odp') {
        $odpPath = OdpExporter::build($meta, $slidesData, $id);
        header('Content-Type: application/vnd.oasis.opendocument.presentation');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.odp"');
        header('Content-Length: ' . filesize($odpPath));
        readfile($odpPath);
        unlink($odpPath);
        exit;
    }

    if ($format === 'zip') {
        $usedFiles = [];
        $resolved = Exporter::resolveAssets($slidesData, $id, 'zip', $usedFiles);
        $html = Exporter::buildStandaloneHtml($meta, $resolved);

        $tmpDir = sys_get_temp_dir() . '/sfexport_' . bin2hex(random_bytes(6));
        mkdir($tmpDir, 0770, true);
        mkdir($tmpDir . '/assets', 0770, true);
        file_put_contents($tmpDir . '/index.html', $html);
        foreach ($usedFiles as $filename => $srcPath) {
            copy($srcPath, $tmpDir . '/assets/' . $filename);
        }

        $zipPath = $tmpDir . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            die(t('export.zip_failed'));
        }
        $zip->addFile($tmpDir . '/index.html', 'index.html');
        foreach (glob($tmpDir . '/assets/*') as $f) {
            $zip->addFile($f, 'assets/' . basename($f));
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);

        // Aufräumen
        unlink($tmpDir . '/index.html');
        foreach (glob($tmpDir . '/assets/*') as $f) unlink($f);
        rmdir($tmpDir . '/assets');
        rmdir($tmpDir);
        unlink($zipPath);
        exit;
    }

    // format === 'html': alles in einer Datei (Bilder/Videos als Base64 eingebettet)
    $resolved = Exporter::resolveAssets($slidesData, $id, 'inline');
    $html = Exporter::buildStandaloneHtml($meta, $resolved);

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.html"');
    header('Content-Length: ' . strlen($html));
    echo $html;
    exit;
}

$pageTitle = t('export.heading') . ' · ' . $meta['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 560px;">
  <a href="editor.php?id=<?= urlencode($id) ?>" class="back-link">&larr; <?= h(t('present.back_to_editor')) ?></a>
  <h1 style="margin-top:14px;"><?= h($meta['title']) ?> <?= h(t('export.heading')) ?></h1>
  <p style="color:var(--text-muted); font-size:0.9rem;">
    <?= h(t('export.intro')) ?>
  </p>

  <div class="section-header" style="margin-top:24px;"><h2><?= h(t('export.single_file')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('export.single_file_desc') ?></p>
  <a href="export.php?id=<?= urlencode($id) ?>&format=html" class="button"><?= h(t('export.download_html')) ?></a>

  <div class="section-header"><h2><?= h(t('export.zip_heading')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('export.zip_desc') ?></p>
  <a href="export.php?id=<?= urlencode($id) ?>&format=zip" class="button button-ghost"><?= h(t('export.download_zip')) ?></a>

  <div class="alert alert-success" style="margin-top:20px;"><?= t('export.reimport_hint') ?></div>

  <div class="section-header"><h2><?= h(t('export.pptx_heading')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('export.pptx_desc') ?></p>
  <a href="export.php?id=<?= urlencode($id) ?>&format=pptx" class="button button-ghost"><?= h(t('export.download_pptx')) ?></a>

  <div class="section-header"><h2><?= h(t('export.odp_heading')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('export.odp_desc') ?></p>
  <a href="export.php?id=<?= urlencode($id) ?>&format=odp" class="button button-ghost"><?= h(t('export.download_odp')) ?></a>

  <div class="section-header"><h2><?= h(t('export.pdf_heading')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('export.pdf_desc') ?></p>
  <a href="pdf_export.php?id=<?= urlencode($id) ?>" target="_blank" class="button button-ghost"><?= h(t('export.open_pdf_view')) ?></a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
