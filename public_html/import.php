<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

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

if (($_GET['action'] ?? '') === 'status') {
    header('Content-Type: application/json; charset=utf-8');
    $jobId = trim((string)($_GET['job'] ?? ''));
    $job = BackgroundJob::read($jobId);
    if ($job === null || ($job['type'] ?? '') !== 'import' || ($job['user_id'] ?? '') !== $me['id']) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => t('import.job_not_found')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (($job['status'] ?? '') === 'done') {
        $warnings = $job['result']['warnings'] ?? [];
        if (is_array($warnings) && $warnings !== []) {
            $_SESSION['import_warnings'] = $warnings;
        }
    }
    echo json_encode(['ok' => true, 'job' => BackgroundJob::publicStatus($job)], JSON_UNESCAPED_UNICODE);
    exit;
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
    $wantsJson = !empty($_POST['async'])
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest');
    try {
        if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(t('import.no_file'));
        }
        $job = ImportJob::enqueueFromUpload(
            $me['id'],
            $_FILES['import_file'],
            trim((string)($_POST['dashboard_section_id'] ?? '')),
            trim((string)($_POST['layout_set_id'] ?? ''))
        );
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'job_id' => $job['id'], 'job' => BackgroundJob::publicStatus($job)], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Fallback ohne JS: kurze Poll-Seite
        redirect('import.php?wait=' . urlencode($job['id']));
        exit;
    } catch (Throwable $e) {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $error = $e->getMessage();
    }
}

$waitJobId = trim((string)($_GET['wait'] ?? ''));
if ($waitJobId !== '') {
    $waitJob = BackgroundJob::read($waitJobId);
    if ($waitJob && ($waitJob['user_id'] ?? '') === $me['id'] && ($waitJob['type'] ?? '') === 'import') {
        if (($waitJob['status'] ?? '') === 'done') {
            $warnings = $waitJob['result']['warnings'] ?? [];
            if (is_array($warnings) && $warnings !== []) {
                $_SESSION['import_warnings'] = $warnings;
            }
            redirect((string)($waitJob['result']['redirect'] ?? 'index.php'));
            exit;
        }
        if (($waitJob['status'] ?? '') === 'failed') {
            $error = (string)($waitJob['error'] ?? t('import.job_failed'));
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
    busy: <?= json_encode(t('import.busy')) ?>,
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
    return /sermonpromptrichtextstyle|lighttextstyle|ref\.ly\/|Export(?:iert aus|ed from)\s+<a[^>]+logos\.com/i.test(text);
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
    if (window.SFBusy) {
      SFBusy.show(i18n.busy || 'Import läuft…');
    } else if (window.HubPageBusy) {
      HubPageBusy.show({ title: i18n.busy || 'Import läuft…', soft: true });
    }
    var fd = new FormData(form);
    fd.set('async', '1');
    fetch(window.location.pathname, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok || !data.job_id) {
          throw new Error(data.error || i18n.busy);
        }
        return pollImport(data.job_id);
      })
      .catch(function (err) {
        if (window.SFBusy) SFBusy.hide();
        else if (window.HubPageBusy) HubPageBusy.hide();
        dialogConfirmed.value = '';
        if (window.SFDialog && SFDialog.alert) SFDialog.alert(String(err.message || err));
        else if (window.CfStatus) CfStatus.show('error', String(err.message || err));
      });
  }

  function pollImport(jobId) {
    return fetch('import.php?action=status&job=' + encodeURIComponent(jobId), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || i18n.busy);
        var st = data.job && data.job.status;
        if (st === 'done') {
          var redirect = (data.job.result && data.job.result.redirect) || 'index.php';
          window.location.href = redirect;
          return;
        }
        if (st === 'failed') throw new Error((data.job && data.job.error) || i18n.busy);
        return new Promise(function (resolve) { setTimeout(resolve, 700); })
          .then(function () { return pollImport(jobId); });
      });
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

  var waitJobId = <?= json_encode($waitJobId !== '' && empty($error) ? $waitJobId : '', JSON_UNESCAPED_UNICODE) ?>;
  if (waitJobId) {
    if (window.SFBusy) SFBusy.show(i18n.busy || 'Import läuft…');
    else if (window.HubPageBusy) HubPageBusy.show({ title: i18n.busy || 'Import läuft…', soft: true });
    pollImport(waitJobId).catch(function (err) {
      if (window.SFBusy) SFBusy.hide();
      else if (window.HubPageBusy) HubPageBusy.hide();
      if (window.CfStatus) CfStatus.show('error', String(err.message || err));
    });
  }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
