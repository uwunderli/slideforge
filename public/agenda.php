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
            // Dateien aus dem FileForge-Dialog (Urs 27.11.: «FF Piker verwenden»), als die Person über AppCall geholt
            $ff = FfFile::fetchTemp((string)$me['id'], (string)($_POST['agenda_ref'] ?? ''));
            $outline = AgendaImporter::extractOutline($ff['path'], $ff['name']);
        } else {
            $outline = (string)($_POST['outline'] ?? '');
            if ($layoutSetId === '' || !LayoutSet::isLayoutSet($layoutSetId)) {
                throw new RuntimeException(strip_tags(t('import.layout_set_required')));
            }
            if ($step === 'create') {
                $extra = null;
                $extraRef = (string)($_POST['extra_ref'] ?? '');
                if ($extraRef !== '') {
                    $ff = FfFile::fetchTemp((string)$me['id'], $extraRef);
                    if (!str_ends_with(strtolower($ff['name']), '.pptx')) {
                        throw new RuntimeException(t('agenda.extra_not_pptx'));
                    }
                    $extra = $ff['path'];
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
<?php
/** Farbfeld; «▾» mit den Markenfarben des Hubs setzt sf-color.js (HubColorPicker, 3.9.2). Auswahl löst die Vorschau neu aus. */
$colorPicker = function (string $id, string $name, string $value, string $swatchesId): string {
    return '<input type="color" id="' . h($id) . '" name="' . h($name) . '" value="' . h($value) . '" data-auto>';
};
?>
<link rel="stylesheet" href="assets/css/import-dialog.css?v=<?= ASSET_VERSION ?>">
<div class="container" style="max-width: <?= $outline === '' ? '680px' : '1500px' ?>;">
  <a href="import.php" class="back-link">&larr; <?= h(t('import.heading')) ?></a>
  <h1 style="margin-top:14px;"><?= h(t('agenda.heading')) ?></h1>
  <p style="color:var(--text-muted); font-size:0.9rem;"><?= h(t('agenda.intro')) ?></p>
  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

<?php if ($outline === ''): ?>
  <form method="post" style="margin-top:20px;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="step" value="outline">
    <label for="agenda_file"><?= h(t('agenda.file_label')) ?></label>
    <?= FfFile::fieldHtml('agenda_ref', 'agenda_file', ['docx', 'md', 'markdown', 'pdf'], t('agenda.file_label')) ?>
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
    .agenda-cols.has-detail { grid-template-columns: 360px 1fr 520px; }
    .agenda-tile .import-preview-grid-thumb { cursor:pointer; }
    .agenda-tile.is-selected .import-preview-grid-thumb { outline:3px solid var(--accent, #b7de45); outline-offset:1px; }
    .agenda-detail-notes { white-space:pre-wrap; font-size:0.9rem; }
    @media (max-width: 1300px) { .agenda-cols.has-detail { grid-template-columns: 360px 1fr; } .agenda-detail { grid-column: 1 / -1; } }
    @media (max-width: 900px) { .agenda-cols, .agenda-cols.has-detail { grid-template-columns: 1fr; } }
  </style>
  <form method="post" id="agendaForm">
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
        <?= $colorPicker('opt_color', 'opt[color]', $opt['color'], 'agendaSwatches') ?>

        <label>
          <input type="hidden" name="opt[doneOn]" value="0">
          <input type="checkbox" name="opt[doneOn]" value="1"<?= $opt['doneOn'] ? ' checked' : '' ?> data-auto>
          <?= h(t('agenda.done_on')) ?>
        </label>
        <?php if ($opt['doneOn']): ?>
        <label for="opt_done_color"><?= h(t('agenda.done_color_label')) ?></label>
        <?= $colorPicker('opt_done_color', 'opt[doneColor]', $opt['doneColor'], 'doneSwatches') ?>
        <?php endif; ?>

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
        <?= FfFile::fieldHtml('extra_ref', 'extra_pptx', ['pptx'], t('agenda.extra_label'), (string)($_POST['extra_ref'] ?? '')) ?>
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
          <div class="import-preview-grid-item agenda-tile" data-num="<?= $n + 1 ?>" data-label="<?= h($p['label']) ?>" data-notes="<?= h($p['notes']) ?>">
            <div class="import-preview-grid-thumb">
              <svg viewBox="0 0 <?= $pw ?> <?= $ph ?>" preserveAspectRatio="xMidYMid slice" width="100%" height="100%"><foreignObject width="<?= $pw ?>" height="<?= $ph ?>"><div xmlns="http://www.w3.org/1999/xhtml" style="width:100%;height:100%;"><?= $p['thumbHtml'] ?></div></foreignObject></svg>
            </div>
            <div class="import-preview-grid-meta"><span class="import-preview-grid-num"><?= $n + 1 ?></span><span><?= h($p['label']) ?></span></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="agenda-detail" id="agendaDetail" hidden>
        <div class="import-detail-sticky">
          <h3 id="agendaDetailTitle" class="import-detail-title"></h3>
          <div class="import-detail-stage" id="agendaDetailStage"></div>
          <h4 class="import-detail-subtitle"><?= h(t('import.detail_notes')) ?></h4>
          <div id="agendaDetailNotes" class="import-detail-notes agenda-detail-notes"></div>
        </div>
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
  (function () {
    var cols = document.querySelector('.agenda-cols');
    var detail = document.getElementById('agendaDetail');
    var stage = document.getElementById('agendaDetailStage');
    var title = document.getElementById('agendaDetailTitle');
    var notes = document.getElementById('agendaDetailNotes');
    var none = <?= json_encode(t('import.detail_notes_none')) ?>;
    document.querySelectorAll('.agenda-tile').forEach(function (tile) {
      tile.addEventListener('click', function () {
        document.querySelectorAll('.agenda-tile.is-selected').forEach(function (x) { x.classList.remove('is-selected'); });
        tile.classList.add('is-selected');
        title.textContent = tile.getAttribute('data-num') + '. ' + tile.getAttribute('data-label');
        stage.innerHTML = tile.querySelector('.import-preview-grid-thumb').innerHTML;
        var n = tile.getAttribute('data-notes');
        notes.textContent = n ? n : none;
        detail.hidden = false;
        cols.classList.add('has-detail');
      });
    });
  })();
  </script>
<?php endif; ?>
</div>
<script src="assets/js/sf-ff.js?v=<?= ASSET_VERSION ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
