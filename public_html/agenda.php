<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$error = '';
$plan = null;
$outline = '';
[$layoutSetsMine, $layoutSetsShared] = LayoutSet::listForUser($me['id']);
$layoutSets = array_merge($layoutSetsMine, $layoutSetsShared);
$layoutSetId = trim((string)($_POST['layout_set_id'] ?? ''));
if ($layoutSetId === '') {
    $layoutSetId = (string)(Presentation::defaultLayoutSetId() ?? '');
    foreach ($layoutSets as $set) {
        if (mb_stripos((string)($set['title'] ?? ''), 'Mitgliederversammlung') !== false) {
            $layoutSetId = (string)$set['id'];
            break;
        }
    }
}
$opt = AgendaImporter::normalizeOptions(is_array($_POST['opt'] ?? null) ? $_POST['opt'] : []);
$step = (string)($_POST['step'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if ($step === 'outline') {
            if (empty($_FILES['agenda_file']) || $_FILES['agenda_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException(t('import.no_file'));
            }
            $outline = AgendaImporter::extractOutline($_FILES['agenda_file']['tmp_name'], (string)$_FILES['agenda_file']['name']);
        } else {
            $outline = (string)($_POST['outline'] ?? '');
            if ($layoutSetId === '' || !LayoutSet::isLayoutSet($layoutSetId)) {
                throw new RuntimeException(strip_tags(t('import.layout_set_required')));
            }
            if ($step === 'create') {
                $extra = null;
                $f = $_FILES['extra_pptx'] ?? null;
                if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    if (!str_ends_with(strtolower((string)$f['name']), '.pptx')) {
                        throw new RuntimeException(t('agenda.extra_not_pptx'));
                    }
                    $extra = (string)$f['tmp_name'];
                }
                @ini_set('memory_limit', '1024M');
                @set_time_limit(300);
                $res = AgendaImporter::createPresentation($me['id'], $outline, $layoutSetId, $opt, trim((string)($_POST['dashboard_section_id'] ?? '')), $extra);
                if ($res['warnings'] !== []) {
                    $_SESSION['import_warnings'] = $res['warnings'];
                }
                redirect($res['redirect']);
                exit;
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if ($step === 'create' || $step === 'preview') {
            $outline = (string)($_POST['outline'] ?? '');
        }
    }
}

$parsed = $outline !== '' ? AgendaImporter::parseOutline($outline) : null;
if ($outline !== '' && $layoutSetId !== '' && LayoutSet::isLayoutSet($layoutSetId)) {
    try {
        $plan = AgendaImporter::planImport($outline, $layoutSetId, $opt);
    } catch (Throwable $e) {
        $error = $error !== '' ? $error : $e->getMessage();
    }
}
$layoutChoices = $layoutSetId !== '' && LayoutSet::isLayoutSet($layoutSetId) ? AgendaImporter::layoutChoices($layoutSetId) : [];
$pageTitle = t('agenda.heading');
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/import-dialog.css?v=<?= ASSET_VERSION ?>">
<div class="container" style="max-width: <?= $outline === '' ? '680px' : '1500px' ?>;">
  <a href="import.php" class="back-link">&larr; <?= h(t('import.heading')) ?></a>
  <h1 style="margin-top:14px;"><?= h(t('agenda.heading')) ?></h1>
  <p style="color:var(--text-muted); font-size:0.9rem;"><?= h(t('agenda.intro')) ?></p>
  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

<?php if ($outline === ''): ?>
  <form method="post" enctype="multipart/form-data" style="margin-top:20px;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="step" value="outline">
    <label for="agenda_file"><?= h(t('agenda.file_label')) ?></label>
    <input type="file" id="agenda_file" name="agenda_file" accept=".docx,.md,.markdown,.pdf" required>
    <label for="layout_set_id" style="margin-top:14px;"><?= h(t('import.layout_set_label')) ?></label>
    <select id="layout_set_id" name="layout_set_id">
      <?php foreach ($layoutSets as $set): ?>
        <option value="<?= h($set['id']) ?>"<?= $set['id'] === $layoutSetId ? ' selected' : '' ?>><?= h($set['title'] ?? $set['id']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="button hub-btn hub-btn--primary" style="margin-top:16px;"><?= h(t('agenda.read')) ?></button>
  </form>
<?php else: ?>
  <style>
    .agenda-cols { display:grid; grid-template-columns: 360px 1fr; gap:28px; align-items:start; margin-top:20px; }
    .agenda-cols label { display:block; margin-top:14px; }
    .agenda-cols textarea, .agenda-cols select, .agenda-cols input[type=text] { width:100%; }
    .agenda-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:14px; margin-top:10px; }
    .agenda-tile .import-preview-grid-thumb { aspect-ratio: <?= (int)($plan['width'] ?? 1920) ?> / <?= (int)($plan['height'] ?? 1080) ?>; background:#000; border-radius:6px; overflow:hidden; }
    .agenda-tile .import-preview-grid-meta { display:flex; justify-content:space-between; gap:8px; padding:4px 2px; font-size:0.85rem; }
    .agenda-actions { display:flex; gap:10px; margin-top:20px; flex-wrap:wrap; }
    @media (max-width: 900px) { .agenda-cols { grid-template-columns: 1fr; } }
  </style>
  <form method="post" enctype="multipart/form-data" id="agendaForm">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="step" id="agendaStep" value="preview">
    <div class="agenda-cols">
      <div>
        <label for="outline" style="margin-top:0;"><?= h(t('agenda.outline_label')) ?></label>
        <textarea id="outline" name="outline" rows="12" style="font-family:monospace;"><?= h($outline) ?></textarea>
        <div class="props-video-note" style="margin-top:6px;"><?= h(t('agenda.outline_hint')) ?></div>

        <label for="layout_set_id"><?= h(t('import.layout_set_label')) ?></label>
        <select id="layout_set_id" name="layout_set_id" data-auto>
          <?php foreach ($layoutSets as $set): ?>
            <option value="<?= h($set['id']) ?>"<?= $set['id'] === $layoutSetId ? ' selected' : '' ?>><?= h($set['title'] ?? $set['id']) ?></option>
          <?php endforeach; ?>
        </select>

        <label for="opt_agenda_layout"><?= h(t('agenda.agenda_layout_label')) ?></label>
        <select id="opt_agenda_layout" name="opt[agendaLayout]" data-auto>
          <option value=""><?= h(t('agenda.layout_auto')) ?></option>
          <?php foreach ($layoutChoices as $c): ?>
            <option value="<?= h($c['id']) ?>"<?= $opt['agendaLayout'] === $c['id'] ? ' selected' : '' ?>><?= h($c['label']) ?> (<?= (int)$c['bodies'] ?>)</option>
          <?php endforeach; ?>
        </select>
        <div class="props-video-note" style="margin-top:6px;"><?= h(t('agenda.agenda_layout_hint')) ?></div>

        <label for="opt_highlight"><?= h(t('agenda.highlight_label')) ?></label>
        <select id="opt_highlight" name="opt[highlight]" data-auto>
          <?php foreach (['both' => 'agenda.hl_both', 'color' => 'agenda.hl_color', 'bold' => 'agenda.hl_bold'] as $v => $k): ?>
            <option value="<?= h($v) ?>"<?= $opt['highlight'] === $v ? ' selected' : '' ?>><?= h(t($k)) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="opt_color"><?= h(t('agenda.color_label')) ?></label>
        <?php $brandColors = Config::brandColors(); ?>
        <?php if ($brandColors): ?>
        <div class="import-brand-swatches" id="agendaSwatches" style="margin-bottom:8px;">
          <?php foreach ($brandColors as $bc): ?>
            <?php $hex = (string)($bc['hex'] ?? ''); if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) { continue; } ?>
            <button type="button" class="import-brand-swatch" data-hex="<?= h($hex) ?>"
                    style="background:<?= h($hex) ?>;" title="<?= h((string)($bc['name'] ?? $hex)) ?>"
                    aria-label="<?= h((string)($bc['name'] ?? $hex)) ?>"></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <input type="color" id="opt_color" name="opt[color]" value="<?= h($opt['color']) ?>" data-auto>

        <label for="opt_heading"><?= h(t('agenda.heading_label')) ?></label>
        <input type="text" id="opt_heading" name="opt[heading]" value="<?= h($opt['heading']) ?>">

        <label>
          <input type="hidden" name="opt[subSlides]" value="0">
          <input type="checkbox" name="opt[subSlides]" value="1"<?= $opt['subSlides'] ? ' checked' : '' ?> data-auto>
          <?= h(t('agenda.sub_slides')) ?>
        </label>
        <label for="opt_detail_layout"><?= h(t('agenda.detail_layout_label')) ?></label>
        <select id="opt_detail_layout" name="opt[detailLayout]" data-auto>
          <option value=""><?= h(t('agenda.layout_auto')) ?></option>
          <?php foreach ($layoutChoices as $c): ?>
            <option value="<?= h($c['id']) ?>"<?= $opt['detailLayout'] === $c['id'] ? ' selected' : '' ?>><?= h($c['label']) ?> (<?= (int)$c['bodies'] ?>)</option>
          <?php endforeach; ?>
        </select>

        <label for="extra_pptx"><?= h(t('agenda.extra_label')) ?></label>
        <input type="file" id="extra_pptx" name="extra_pptx" accept=".pptx">
        <label for="opt_insert"><?= h(t('agenda.insert_label')) ?></label>
        <select id="opt_insert" name="opt[insertAfter]">
          <option value="0"><?= h(t('agenda.insert_end')) ?></option>
          <?php foreach ($parsed['items'] as $i => $item): ?>
            <option value="<?= $i + 1 ?>"<?= $opt['insertAfter'] === $i + 1 ? ' selected' : '' ?>><?= ($i + 1) . '. ' . h($item['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <h2 style="margin:0;"><?= h(t('agenda.preview_heading')) ?></h2>
        <?php if ($plan): ?>
        <div class="props-video-note" style="margin-top:6px;"><?= h($plan['title']) ?> — <?= h(t('agenda.plan_heading', ['n' => $plan['slide_count']])) ?></div>
        <?php foreach ($plan['warnings'] as $w): ?><div class="alert alert-error" style="margin-top:8px;"><?= h($w) ?></div><?php endforeach; ?>
        <div class="agenda-grid">
          <?php $pw = (int)$plan['width']; $ph = (int)$plan['height']; foreach ($plan['slides'] as $n => $p): ?>
          <div class="import-preview-grid-item agenda-tile">
            <div class="import-preview-grid-thumb">
              <svg viewBox="0 0 <?= $pw ?> <?= $ph ?>" preserveAspectRatio="xMidYMid slice" width="100%" height="100%"><foreignObject width="<?= $pw ?>" height="<?= $ph ?>"><div xmlns="http://www.w3.org/1999/xhtml" style="width:100%;height:100%;"><?= $p['thumbHtml'] ?></div></foreignObject></svg>
            </div>
            <div class="import-preview-grid-meta"><span class="import-preview-grid-num"><?= $n + 1 ?></span><span><?= h($p['label']) ?></span></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="agenda-actions">
      <a href="agenda.php" class="button hub-btn"><?= h(t('agenda.cancel')) ?></a>
      <button type="submit" class="button hub-btn" onclick="document.getElementById('agendaStep').value='preview'"><?= h(t('agenda.preview')) ?></button>
      <button type="submit" class="button hub-btn hub-btn--primary" onclick="document.getElementById('agendaStep').value='create'"><?= h(t('agenda.create')) ?></button>
    </div>
  </form>
  <script>
  document.querySelectorAll('#agendaForm [data-auto]').forEach(function (el) {
    el.addEventListener('change', function () {
      var f = document.getElementById('agendaForm');
      document.getElementById('agendaStep').value = 'preview';
      f.submit();
    });
  });
  document.querySelectorAll('#agendaSwatches .import-brand-swatch').forEach(function (b) {
    b.addEventListener('click', function () {
      var c = document.getElementById('opt_color');
      c.value = b.getAttribute('data-hex');
      c.dispatchEvent(new Event('change'));
    });
  });
  </script>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
