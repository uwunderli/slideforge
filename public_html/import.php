<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

@ini_set('memory_limit', '1024M');
@set_time_limit(180);

function sf_cleanup_dir(string $dir): void
{
    if (!is_dir($dir)) return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $error = t('import.no_file');
    } else {
        $file = $_FILES['import_file'];
        $origName = strtolower($file['name']);
        $htmlContent = null;
        $zipAssetsDir = null;
        $zipTmpDir = null;

        try {
            if (str_ends_with($origName, '.pptx')) {
                $newId = Presentation::create($me['id'], trim((string)pathinfo($origName, PATHINFO_FILENAME)) . t('import.title_suffix'), DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT);
                try {
                    $result = PptxImporter::import($file['tmp_name'], $newId);
                } catch (Throwable $e) {
                    Presentation::delete($newId);
                    throw new RuntimeException(t('import.pptx_failed', ['error' => $e->getMessage()]));
                }
                Presentation::updateMeta($newId, ['width' => $result['width'], 'height' => $result['height']]);
                $slides = $result['slides'] ?: [Presentation::defaultSlide()];
                Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $slides]);

                if (!empty($result['warnings'])) {
                    $_SESSION['import_warnings'] = $result['warnings'];
                }
                redirect('editor.php?id=' . urlencode($newId));
                exit;
            }

            if (str_ends_with($origName, '.odp')) {
                $newId = Presentation::create($me['id'], trim((string)pathinfo($origName, PATHINFO_FILENAME)) . t('import.title_suffix'), DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT);
                try {
                    $result = OdpImporter::import($file['tmp_name'], $newId);
                } catch (Throwable $e) {
                    Presentation::delete($newId);
                    throw new RuntimeException(t('import.odp_failed', ['error' => $e->getMessage()]));
                }
                Presentation::updateMeta($newId, ['width' => $result['width'], 'height' => $result['height']]);
                $slides = $result['slides'] ?: [Presentation::defaultSlide()];
                Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $slides]);

                if (!empty($result['warnings'])) {
                    $_SESSION['import_warnings'] = $result['warnings'];
                }
                redirect('editor.php?id=' . urlencode($newId));
                exit;
            }

            if (str_ends_with($origName, '.pdf')) {
                if (!class_exists('Imagick')) {
                    throw new RuntimeException(t('import.pdf_no_imagick'));
                }
                $pdfTmpDir = sys_get_temp_dir() . '/sfimportpdf_' . bin2hex(random_bytes(6));
                mkdir($pdfTmpDir, 0770, true);

                try {
                    $probe = new Imagick();
                    $probe->pingImage($file['tmp_name']);
                    $pageCount = max(1, $probe->getNumberImages());
                    $probe->clear();
                } catch (Throwable $e) {
                    sf_cleanup_dir($pdfTmpDir);
                    throw new RuntimeException(t('import.pdf_read_failed', ['error' => $e->getMessage()]));
                }
                if ($pageCount > 200) {
                    sf_cleanup_dir($pdfTmpDir);
                    throw new RuntimeException(t('import.pdf_too_many_pages'));
                }

                $pageImages = [];
                $pageWidth = DEFAULT_SLIDE_WIDTH;
                $pageHeight = DEFAULT_SLIDE_HEIGHT;
                for ($p = 0; $p < $pageCount; $p++) {
                    $im = new Imagick();
                    $im->setResolution(150, 150); // vor readImage setzen, wirkt sich auf die Rasterisierung von Vektor-PDFs aus
                    $im->readImage($file['tmp_name'] . '[' . $p . ']');
                    $im->setImageFormat('png');
                    $im->setImageBackgroundColor('white');
                    $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                    if ($p === 0) {
                        $pageWidth = $im->getImageWidth();
                        $pageHeight = $im->getImageHeight();
                    }
                    $pagePath = $pdfTmpDir . '/page' . ($p + 1) . '.png';
                    $im->writeImage($pagePath);
                    $im->clear();
                    $pageImages[] = $pagePath;
                }

                $title = trim((string)pathinfo($origName, PATHINFO_FILENAME)) . t('import.title_suffix');
                $newId = Presentation::create($me['id'], $title ?: t('import.default_title'), $pageWidth, $pageHeight);
                $assetsDir = Presentation::dir($newId) . '/assets';

                $slides = [];
                foreach ($pageImages as $i => $srcPath) {
                    $filename = 'pdfpage' . ($i + 1) . '_' . bin2hex(random_bytes(4)) . '.png';
                    copy($srcPath, $assetsDir . '/' . $filename);
                    $slides[] = [
                        'id' => 'slide' . bin2hex(random_bytes(4)),
                        'background' => ['type' => 'color', 'value' => '#ffffff'],
                        'objects' => [[
                            'id' => 'o' . bin2hex(random_bytes(4)),
                            'type' => 'image', 'x' => 0, 'y' => 0, 'w' => $pageWidth, 'h' => $pageHeight,
                            'rotation' => 0, 'opacity' => 1,
                            'src' => 'asset.php?id=' . urlencode($newId) . '&file=' . urlencode($filename),
                        ]],
                        'notes' => '', 'transition' => 'slide', 'autoAdvance' => 0,
                    ];
                }
                Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $slides]);
                sf_cleanup_dir($pdfTmpDir);

                redirect('editor.php?id=' . urlencode($newId));
                exit;
            }

            if (str_ends_with($origName, '.zip')) {
                $zip = new ZipArchive();
                if ($zip->open($file['tmp_name']) !== true) {
                    throw new RuntimeException(t('import.zip_open_failed'));
                }
                $zipTmpDir = sys_get_temp_dir() . '/sfimport_' . bin2hex(random_bytes(6));
                mkdir($zipTmpDir, 0770, true);
                $zip->extractTo($zipTmpDir);
                $zip->close();
                $indexPath = $zipTmpDir . '/index.html';
                if (!is_file($indexPath)) {
                    throw new RuntimeException(t('import.no_index_html'));
                }
                $htmlContent = file_get_contents($indexPath);
                if (is_dir($zipTmpDir . '/assets')) {
                    $zipAssetsDir = $zipTmpDir . '/assets';
                }
            } elseif (str_ends_with($origName, '.html') || str_ends_with($origName, '.htm')) {
                $htmlContent = file_get_contents($file['tmp_name']);
            } else {
                throw new RuntimeException(t('import.unsupported_type'));
            }

            if (!preg_match('/<script type="application\/json" id="slideforge-source-data">(.*?)<\/script>/s', $htmlContent, $m)) {
                throw new RuntimeException(t('import.no_source_data'));
            }
            $data = json_decode($m[1], true);
            if (!is_array($data) || empty($data['meta']) || !isset($data['slides'])) {
                throw new RuntimeException(t('import.corrupt_data'));
            }

            $meta = $data['meta'];
            $width = max(100, (int)($meta['width'] ?? DEFAULT_SLIDE_WIDTH));
            $height = max(100, (int)($meta['height'] ?? DEFAULT_SLIDE_HEIGHT));
            $title = trim((string)($meta['title'] ?? t('import.default_title'))) . t('import.title_suffix');

            $newId = Presentation::create($me['id'], $title, $width, $height);
            Presentation::updateMeta($newId, [
                'show_progress' => $meta['show_progress'] ?? true,
            ]);

            $slidesData = ['slides' => $data['slides']];
            $slidesData = Exporter::importAssets($slidesData, $newId, $zipAssetsDir);
            if (empty($slidesData['slides'])) {
                $slidesData['slides'] = [Presentation::defaultSlide()];
            }
            Storage::write(Presentation::dir($newId) . '/slides.json', $slidesData);

            if ($zipTmpDir) {
                sf_cleanup_dir($zipTmpDir);
            }

            redirect('editor.php?id=' . urlencode($newId));
            exit;
        } catch (Throwable $e) {
            if ($zipTmpDir && is_dir($zipTmpDir)) {
                sf_cleanup_dir($zipTmpDir);
            }
            $error = $e->getMessage();
        }
    }
}

$pageTitle = t('import.heading');
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 560px;">
  <a href="index.php" class="back-link">&larr; <?= h(t('profile.back_to_dashboard')) ?></a>
  <h1 style="margin-top:14px;"><?= h(t('import.page_heading')) ?></h1>
  <p style="color:var(--text-muted); font-size:0.9rem;">
    <?= h(t('import.intro')) ?>
  </p>

  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="margin-top:20px;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <label for="import_file"><?= h(t('import.file_label')) ?></label>
    <input type="file" id="import_file" name="import_file" accept=".html,.htm,.zip,.pdf,.pptx,.odp" required>
    <div class="props-video-note" style="margin-top:8px;"><?= t('import.pdf_hint') ?></div>
    <div class="props-video-note" style="margin-top:6px;"><?= t('import.pptx_hint') ?></div>
    <div class="props-video-note" style="margin-top:6px;"><?= t('import.odp_hint') ?></div>
    <button type="submit" class="button" style="margin-top:16px;"><?= h(t('import.submit')) ?></button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
