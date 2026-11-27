<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$id = $_GET['id'] ?? '';
$format = $_GET['format'] ?? '';
$action = $_GET['action'] ?? '';
$jobId = trim((string)($_GET['job'] ?? ''));

function sf_export_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'status' || ($jobId !== '' && $format === '' && $action !== 'download')) {
    $job = BackgroundJob::read($jobId);
    if ($job === null || ($job['type'] ?? '') !== 'export' || ($job['user_id'] ?? '') !== $me['id']) {
        sf_export_json(['ok' => false, 'error' => t('export.job_not_found')], 404);
    }
    sf_export_json(['ok' => true, 'job' => BackgroundJob::publicStatus($job)]);
}

if ($action === 'download') {
    $job = BackgroundJob::read($jobId);
    if ($job === null || ($job['type'] ?? '') !== 'export' || ($job['user_id'] ?? '') !== $me['id']) {
        http_response_code(404);
        die(t('export.job_not_found'));
    }
    if (($job['status'] ?? '') !== 'done') {
        http_response_code(409);
        die(t('export.job_not_ready'));
    }
    $path = ExportJob::outputPath($jobId);
    if ($path === null) {
        http_response_code(404);
        die(t('export.job_not_found'));
    }
    $filename = (string)($job['result']['filename'] ?? basename($path));
    $mime = (string)($job['result']['mime'] ?? 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if (ExportJob::isFormat($format)) {
    if ($id === '' || !Presentation::canView($id, $me['id'])) {
        if (!empty($_GET['async']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            sf_export_json(['ok' => false, 'error' => t('export.no_access')], 403);
        }
        http_response_code(403);
        die(t('export.no_access'));
    }
    try {
        $job = ExportJob::enqueue($me['id'], $id, $format);
        sf_export_json(['ok' => true, 'job_id' => $job['id'], 'job' => BackgroundJob::publicStatus($job)]);
    } catch (Throwable $e) {
        sf_export_json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($id !== '' && !Presentation::canView($id, $me['id'])) {
    http_response_code(403);
    die(t('export.no_access'));
}
$meta = $id !== '' ? Presentation::getMeta($id) : null;
if (!$meta) {
    http_response_code(404);
    die(t('export.no_access'));
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
  <a href="export.php?id=<?= urlencode($id) ?>&format=html" class="button hub-btn hub-btn--primary" data-export-download><?= h(t('export.download_html')) ?></a>

  <div class="section-header"><h2><?= h(t('export.zip_heading')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('export.zip_desc') ?></p>
  <a href="export.php?id=<?= urlencode($id) ?>&format=zip" class="button button-ghost hub-btn hub-btn--ghost" data-export-download><?= h(t('export.download_zip')) ?></a>

  <div class="alert alert-success" style="margin-top:20px;"><?= t('export.reimport_hint') ?></div>

  <div class="section-header"><h2><?= h(t('export.pptx_heading')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('export.pptx_desc') ?></p>
  <a href="export.php?id=<?= urlencode($id) ?>&format=pptx" class="button button-ghost hub-btn hub-btn--ghost" data-export-download><?= h(t('export.download_pptx')) ?></a>

  <div class="section-header"><h2><?= h(t('export.odp_heading')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('export.odp_desc') ?></p>
  <a href="export.php?id=<?= urlencode($id) ?>&format=odp" class="button button-ghost hub-btn hub-btn--ghost" data-export-download><?= h(t('export.download_odp')) ?></a>

  <div class="section-header"><h2><?= h(t('export.pdf_heading')) ?></h2></div>
  <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('export.pdf_desc') ?></p>
  <a href="pdf_export.php?id=<?= urlencode($id) ?>" target="_blank" class="button button-ghost hub-btn hub-btn--ghost"><?= h(t('export.open_pdf_view')) ?></a>
</div>
<script>
(function () {
  var busyTitle = <?= json_encode(t('export.preparing'), JSON_UNESCAPED_UNICODE) ?>;
  var failMsg = <?= json_encode(t('export.job_failed'), JSON_UNESCAPED_UNICODE) ?>;

  function showBusy(title) {
    if (window.SFBusy) SFBusy.show(title || busyTitle);
    else if (window.HubPageBusy) HubPageBusy.show({ title: title || busyTitle, soft: true });
  }
  function hideBusy() {
    if (window.SFBusy) SFBusy.hide();
    else if (window.HubPageBusy) HubPageBusy.hide();
  }
  function pollExport(jobId) {
    return fetch('export.php?action=status&job=' + encodeURIComponent(jobId), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) throw new Error(data.error || failMsg);
        var st = data.job && data.job.status;
        if (st === 'done') {
          hideBusy();
          window.location.href = 'export.php?action=download&job=' + encodeURIComponent(jobId);
          return;
        }
        if (st === 'failed') throw new Error((data.job && data.job.error) || failMsg);
        return new Promise(function (resolve) { setTimeout(resolve, 700); })
          .then(function () { return pollExport(jobId); });
      });
  }
  document.querySelectorAll('[data-export-download]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var url = link.getAttribute('href');
      if (!url) return;
      showBusy(busyTitle);
      fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'async=1', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok || !data.job_id) throw new Error(data.error || failMsg);
          return pollExport(data.job_id);
        })
        .catch(function (err) {
          hideBusy();
          var msg = err.message || failMsg;
          if (window.SFDialog && SFDialog.alert) SFDialog.alert(msg);
          else if (window.CfStatus) CfStatus.show('error', msg);
        });
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
