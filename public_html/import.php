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

/** @param list<array<string,mixed>> $owned */
function sf_resolve_import_section_id(string $userId, array $owned): string
{
    $sectionId = trim((string)($_POST['dashboard_section_id'] ?? ''));
    $sections = Dashboard::ensureSections($userId, $owned);
    $ids = array_map(fn($s) => (string)$s['id'], $sections);
    if ($sectionId !== '' && in_array($sectionId, $ids, true)) {
        return $sectionId;
    }
    foreach ($sections as $s) {
        if (!empty($s['is_default'])) {
            return (string)$s['id'];
        }
    }
    return $sections[0]['id'] ?? '';
}

function sf_import_assign_section(string $userId, string $presentationId): void
{
    [$owned] = Presentation::listForUser($userId);
    $sectionId = sf_resolve_import_section_id($userId, $owned);
    if ($sectionId === '') {
        return;
    }
    try {
        Dashboard::moveItem($userId, $presentationId, $sectionId, 0);
    } catch (Throwable $e) {
        // Ungültiger Bereich — Präsentation bleibt beim nächsten Dashboard-Besuch im Standard-Bereich.
    }
}

$error = '';
$logosImporterEnabled = Auth::logosImporterEnabled($me);
[$layoutSetsMine, $layoutSetsShared] = LayoutSet::listForUser($me['id']);
$layoutSets = array_merge($layoutSetsMine, $layoutSetsShared);
$defaultLayoutSetId = Presentation::defaultLayoutSetId();
[$ownedPresentations] = Presentation::listForUser($me['id']);
$importSections = Dashboard::ensureSections($me['id'], $ownedPresentations);
$defaultImportSectionId = '';
foreach ($importSections as $importSection) {
    if (!empty($importSection['is_default'])) {
        $defaultImportSectionId = (string)$importSection['id'];
        break;
    }
}
if ($defaultImportSectionId === '' && $importSections !== []) {
    $defaultImportSectionId = (string)$importSections[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['logos_preview'])) {
    csrf_check();
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!$logosImporterEnabled) {
            throw new RuntimeException(t('import.logos_disabled'));
        }
        $layoutSetId = trim((string)($_POST['layout_set_id'] ?? ''));
        if ($layoutSetId === '' || !LayoutSet::isLayoutSet($layoutSetId)) {
            throw new RuntimeException(strip_tags(t('import.layout_set_required')));
        }
        if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(t('import.no_file'));
        }
        $file = $_FILES['import_file'];
        $origName = strtolower($file['name']);
        if (!str_ends_with($origName, '.html') && !str_ends_with($origName, '.htm')) {
            throw new RuntimeException(t('import.unsupported_type'));
        }
        $htmlContent = file_get_contents($file['tmp_name']);
        if ($htmlContent === false || !LogosSermonImporter::isLogosExport($htmlContent)) {
            throw new RuntimeException(t('import.unsupported_type'));
        }
        $plan = LogosSermonImporter::planImport($htmlContent, $layoutSetId);
        echo json_encode(['ok' => true, 'plan' => $plan], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

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
                sf_import_assign_section($me['id'], $newId);
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
                sf_import_assign_section($me['id'], $newId);
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

                sf_import_assign_section($me['id'], $newId);
                redirect('editor.php?id=' . urlencode($newId));
                exit;
            }

            if (str_ends_with($origName, '.chs') || LayoutSet::isLayoutSetArchiveFile($file['tmp_name'])) {
                $newId = LayoutSet::importArchive($me['id'], $file['tmp_name']);
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

            if (LogosSermonImporter::isLogosExport($htmlContent)) {
                if (!$logosImporterEnabled) {
                    throw new RuntimeException(t('import.logos_disabled'));
                }
                $layoutSetId = trim((string)($_POST['layout_set_id'] ?? ''));
                if ($layoutSetId === '' || !LayoutSet::isLayoutSet($layoutSetId)) {
                    throw new RuntimeException(strip_tags(t('import.layout_set_required')));
                }
                $result = LogosSermonImporter::import($htmlContent, $layoutSetId);
                $title = trim($result['title']) . t('import.title_suffix');
                $newId = Presentation::create($me['id'], $title ?: t('import.default_title'), $result['width'], $result['height']);
                if (!empty($result['layout_set_id'])) {
                    Presentation::updateMeta($newId, ['layout_set_id' => $result['layout_set_id']]);
                }
                if (!empty($result['footer_text'])) {
                    Presentation::updateMeta($newId, ['footer_text' => $result['footer_text']]);
                }
                $slides = $result['slides'] ?: [Presentation::defaultSlide()];
                Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $slides]);
                if (!empty($result['warnings'])) {
                    $_SESSION['import_warnings'] = $result['warnings'];
                }
                sf_import_assign_section($me['id'], $newId);
                redirect('editor.php?id=' . urlencode($newId));
                exit;
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
            $restored = Exporter::metaFromReimport($meta, $me['id']);
            if ($restored['fields'] !== []) {
                Presentation::updateMeta($newId, $restored['fields']);
            }
            if ($restored['warnings'] !== []) {
                $_SESSION['import_warnings'] = array_merge($_SESSION['import_warnings'] ?? [], $restored['warnings']);
            }

            $slidesData = ['slides' => $data['slides']];
            $slidesData = Exporter::importAssets($slidesData, $newId, $zipAssetsDir);
            if (empty($slidesData['slides'])) {
                $slidesData['slides'] = [Presentation::defaultSlide()];
            }
            Storage::write(Presentation::dir($newId) . '/slides.json', $slidesData);

            if ($zipTmpDir) {
                sf_cleanup_dir($zipTmpDir);
            }

            sf_import_assign_section($me['id'], $newId);
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

  <form method="post" enctype="multipart/form-data" id="importForm" style="margin-top:20px;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="dashboard_section_id" id="dashboard_section_id" value="">
    <input type="hidden" name="layout_set_id" id="layout_set_id_hidden" value="">
    <input type="hidden" name="logos_confirmed" id="logos_confirmed" value="">
    <input type="hidden" name="import_dialog_confirmed" id="import_dialog_confirmed" value="">
    <label for="import_file"><?= h(t('import.file_label')) ?></label>
    <input type="file" id="import_file" name="import_file" required>
    <?php if ($logosImporterEnabled): ?>
    <div class="props-video-note" style="margin-top:8px;"><?= t('import.logos_hint') ?></div>
    <?php endif; ?>
    <div class="props-video-note" style="margin-top:6px;"><?= t('import.chs_hint') ?></div>
    <div class="props-video-note" style="margin-top:6px;"><?= t('import.pptx_hint') ?></div>
    <div class="props-video-note" style="margin-top:6px;"><?= t('import.odp_hint') ?></div>
    <button type="submit" class="button" style="margin-top:16px;"><?= h(t('import.submit')) ?></button>
  </form>
</div>
<div id="importDialog" class="modal-backdrop import-dialog-backdrop">
  <div class="modal modal-import-dialog" id="importDialogPanel">
    <div class="sf-dialog-header import-dialog-header">
      <h2 class="sf-dialog-title"><?= h(t('import.dialog_heading')) ?></h2>
    </div>
    <p class="sf-dialog-hint import-dialog-hint"><?= h(t('import.dialog_intro')) ?></p>

    <div class="import-dialog-body">
      <label for="import_section_id"><?= h(t('import.section_label')) ?></label>
      <select id="import_section_id">
        <?php foreach ($importSections as $importSection): ?>
          <option value="<?= h($importSection['id']) ?>"<?= $importSection['id'] === $defaultImportSectionId ? ' selected' : '' ?>><?= h(Dashboard::sectionTitle($importSection)) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="props-video-note" style="margin-top:6px; margin-bottom:16px;"><?= h(t('import.section_hint')) ?></div>

      <div id="importLogosOptions" hidden>
        <?php if ($logosImporterEnabled && $layoutSets): ?>
        <label for="import_layout_set_id"><?= h(t('import.layout_set_label')) ?></label>
        <select id="import_layout_set_id">
          <?php foreach ($layoutSets as $set): ?>
            <option value="<?= h($set['id']) ?>"<?= ($defaultLayoutSetId !== null && $set['id'] === $defaultLayoutSetId) ? ' selected' : '' ?>><?= h($set['title'] ?? $set['id']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="props-video-note" style="margin-top:6px;"><?= t('import.layout_set_hint') ?></div>
        <?php elseif ($logosImporterEnabled): ?>
        <div class="alert alert-error" style="margin-top:0;"><?= t('import.layout_set_none_available') ?></div>
        <?php else: ?>
        <div class="alert alert-error" style="margin-top:0;"><?= h(t('import.logos_disabled')) ?></div>
        <?php endif; ?>
      </div>

      <div id="importPreviewBlock" hidden>
        <h3 style="margin:16px 0 8px; font-size:1rem;"><?= h(t('import.preview_heading')) ?></h3>
        <p style="color:var(--text-muted); font-size:0.9rem; margin:0 0 12px;"><?= h(t('import.preview_intro')) ?></p>
        <div id="importPreviewSummary" class="import-preview-summary"></div>
        <div class="import-preview-scroll">
          <div id="importPreviewSlides" class="import-preview-grid"></div>
        </div>
        <div id="importPreviewWarnings" class="alert alert-error" hidden style="margin-top:10px;"></div>
      </div>
    </div>

    <div class="modal-actions import-dialog-actions">
      <button type="button" class="button button-ghost" id="importDialogCancel"><?= h(t('common.cancel')) ?></button>
      <button type="button" class="button button-ghost" id="importPreviewReload" hidden><?= h(t('import.preview_reload')) ?></button>
      <button type="button" class="button" id="importDialogConfirm"><?= h(t('import.submit')) ?></button>
    </div>
  </div>
</div>
<script>
(function () {
  var form = document.getElementById('importForm');
  var input = document.getElementById('import_file');
  var modal = document.getElementById('importDialog');
  var panel = document.getElementById('importDialogPanel');
  var sectionSel = document.getElementById('import_section_id');
  var hiddenSection = document.getElementById('dashboard_section_id');
  var logosBlock = document.getElementById('importLogosOptions');
  var layoutSel = document.getElementById('import_layout_set_id');
  var hiddenLayout = document.getElementById('layout_set_id_hidden');
  var logosConfirmed = document.getElementById('logos_confirmed');
  var dialogConfirmed = document.getElementById('import_dialog_confirmed');
  var previewBlock = document.getElementById('importPreviewBlock');
  var previewSummary = document.getElementById('importPreviewSummary');
  var previewSlides = document.getElementById('importPreviewSlides');
  var previewWarnings = document.getElementById('importPreviewWarnings');
  var btnCancel = document.getElementById('importDialogCancel');
  var btnConfirm = document.getElementById('importDialogConfirm');
  var btnReload = document.getElementById('importPreviewReload');
  if (!form || !input || !modal || !sectionSel) return;

  var logosEnabled = <?= $logosImporterEnabled ? 'true' : 'false' ?>;
  var hasLayoutSets = <?= ($logosImporterEnabled && $layoutSets) ? 'true' : 'false' ?>;
  var isLogosHtml = false;
  var previewShown = false;

  if (window.SFModalBackdrop) {
    SFModalBackdrop.bindDismiss(modal, function () {
      modal.classList.remove('open');
      panel.classList.remove('modal-import-preview');
    });
  }

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  var i18n = {
    previewSlides: <?= json_encode(t('import.preview_slides')) ?>,
    previewSection: <?= json_encode(t('import.preview_section')) ?>,
    previewPreamble: <?= json_encode(t('import.preview_preamble')) ?>,
    previewFailed: <?= json_encode(t('import.preview_failed')) ?>,
    previewShow: <?= json_encode(t('import.preview_show')) ?>,
    submit: <?= json_encode(t('import.submit')) ?>,
    logosDisabled: <?= json_encode(t('import.logos_disabled')) ?>,
    layoutSetRequired: <?= json_encode(strip_tags(t('import.layout_set_required'))) ?>
  };

  function detectLogosFromName(name) {
    return name.endsWith('.html') || name.endsWith('.htm');
  }

  function isSlideForgeBackupText(text) {
    return text.indexOf('id="slideforge-source-data"') !== -1
      || text.indexOf("id='slideforge-source-data'") !== -1;
  }

  function isLogosExportText(text) {
    return /sermonpromptrichtextstyle|ref\.ly\/|Exportiert aus\s+<a[^>]+logos\.com/i.test(text);
  }

  function setPreviewLoading(loading) {
    btnConfirm.disabled = loading;
    if (btnReload) btnReload.disabled = loading;
  }

  function updateLogosOptions() {
    var show = isLogosHtml;
    logosBlock.hidden = !show;
    if (!show) {
      previewBlock.hidden = true;
      previewShown = false;
      if (btnReload) btnReload.hidden = true;
      panel.classList.remove('modal-import-preview');
      btnConfirm.textContent = i18n.submit;
      return;
    }
    btnConfirm.textContent = previewShown ? i18n.submit : i18n.previewShow;
    if (btnReload) btnReload.hidden = !previewShown;
    if (!logosEnabled || !hasLayoutSets) {
      btnConfirm.disabled = true;
      if (btnReload) btnReload.disabled = true;
    } else if (!previewShown) {
      btnConfirm.disabled = false;
    }
  }

  function readFileHead(file, cb) {
    var reader = new FileReader();
    reader.onload = function () { cb(String(reader.result || '')); };
    reader.onerror = function () { cb(''); };
    reader.readAsText(file.slice(0, Math.min(file.size, 131072)));
  }

    function prepareDialog(file, cb) {
    var name = file.name.toLowerCase();
    isLogosHtml = false;
    previewShown = false;
    previewBlock.hidden = true;
    panel.classList.remove('modal-import-preview');
    if (logosConfirmed) logosConfirmed.value = '';

    if (!detectLogosFromName(name)) {
      updateLogosOptions();
      cb();
      return;
    }
    readFileHead(file, function (text) {
      isLogosHtml = !isSlideForgeBackupText(text) && isLogosExportText(text);
      updateLogosOptions();
      cb();
    });
  }

  function renderPreviewTile(slide, num, w, h) {
    var thumb = slide.thumbHtml || '';
    var layout = slide.layoutKey || '—';
    return '<div class="import-preview-grid-item">'
      + '<div class="import-preview-grid-thumb">'
      + '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="xMidYMid slice" width="100%" height="100%">'
      + '<foreignObject width="' + w + '" height="' + h + '">'
      + '<div xmlns="http://www.w3.org/1999/xhtml" style="width:100%;height:100%;">' + thumb + '</div>'
      + '</foreignObject></svg></div>'
      + '<div class="import-preview-grid-meta">'
      + '<span class="import-preview-grid-num">' + num + '</span>'
      + '<span class="import-preview-grid-layout">' + esc(layout) + '</span>'
      + '</div></div>';
  }

  function showPreview(plan) {
    previewSummary.textContent = (plan.title || '') + ' — ' + i18n.previewSlides.replace('{count}', String(plan.slide_count || 0));
    var w = plan.width || 1920;
    var h = plan.height || 1080;
    var slides = plan.slides || [];
    var gridHtml = '';
    var slideIdx = 0;
    var preamble = plan.preamble_slide_count || 0;
    var globalNum = 0;

    if (preamble > 0) {
      gridHtml += '<div class="import-preview-section-head">' + esc(i18n.previewPreamble) + '</div>';
      for (var p = 0; p < preamble && slideIdx < slides.length; p++) {
        globalNum++;
        gridHtml += renderPreviewTile(slides[slideIdx], globalNum, w, h);
        slideIdx++;
      }
    }
    (plan.sections || []).forEach(function (sec, idx) {
      var title = sec.title || (i18n.previewSection + ' ' + (idx + 1));
      var count = sec.slide_count || 0;
      if (count > 0) {
        gridHtml += '<div class="import-preview-section-head">' + esc(title) + '</div>';
        for (var j = 0; j < count && slideIdx < slides.length; j++) {
          globalNum++;
          gridHtml += renderPreviewTile(slides[slideIdx], globalNum, w, h);
          slideIdx++;
        }
      }
    });
    while (slideIdx < slides.length) {
      globalNum++;
      gridHtml += renderPreviewTile(slides[slideIdx], globalNum, w, h);
      slideIdx++;
    }
    previewSlides.innerHTML = gridHtml;
    if (plan.warnings && plan.warnings.length) {
      previewWarnings.hidden = false;
      previewWarnings.textContent = plan.warnings.join('\n');
    } else {
      previewWarnings.hidden = true;
      previewWarnings.textContent = '';
    }
    previewBlock.hidden = false;
    previewShown = true;
    panel.classList.add('modal-import-preview');
    btnConfirm.textContent = i18n.submit;
    if (btnReload) btnReload.hidden = false;
    setPreviewLoading(false);
  }

  function syncHiddenFields() {
    hiddenSection.value = sectionSel.value || '';
    if (layoutSel && hiddenLayout) {
      hiddenLayout.value = layoutSel.value || '';
    }
  }

  function submitImport() {
    syncHiddenFields();
    if (isLogosHtml) {
      logosConfirmed.value = '1';
    }
    dialogConfirmed.value = '1';
    modal.classList.remove('open');
    form.requestSubmit();
  }

  function fetchLogosPreview() {
    syncHiddenFields();
    if (!hasLayoutSets || !layoutSel || !layoutSel.value) {
      SFDialog.alert(i18n.layoutSetRequired);
      return;
    }
    var fd = new FormData(form);
    fd.set('logos_preview', '1');
    setPreviewLoading(true);
    fetch(window.location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        setPreviewLoading(false);
        if (!data.ok) {
          SFDialog.alert(i18n.previewFailed.replace('{error}', data.error || ''));
          return;
        }
        showPreview(data.plan || {});
      })
      .catch(function (err) {
        setPreviewLoading(false);
        SFDialog.alert(i18n.previewFailed.replace('{error}', String(err)));
      });
  }

  form.addEventListener('submit', function (ev) {
    if (dialogConfirmed.value === '1') {
      dialogConfirmed.value = '';
      return;
    }
    ev.preventDefault();
    var file = input.files && input.files[0];
    if (!file) return;
    prepareDialog(file, function () {
      modal.classList.add('open');
    });
  });

  btnCancel.addEventListener('click', function () {
    modal.classList.remove('open');
    panel.classList.remove('modal-import-preview');
  });

  btnConfirm.addEventListener('click', function () {
    if (isLogosHtml) {
      if (!logosEnabled) {
        SFDialog.alert(i18n.logosDisabled);
        return;
      }
      if (!hasLayoutSets || !layoutSel || !layoutSel.value) {
        SFDialog.alert(i18n.layoutSetRequired);
        return;
      }
      if (!previewShown) {
        fetchLogosPreview();
        return;
      }
    }
    submitImport();
  });

  if (btnReload) {
    btnReload.addEventListener('click', function () {
      if (!isLogosHtml || !previewShown) return;
      fetchLogosPreview();
    });
  }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
