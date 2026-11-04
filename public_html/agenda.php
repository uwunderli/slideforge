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
            $plan = AgendaImporter::planImport($outline, $layoutSetId, $opt);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if ($step === 'create' || $step === 'preview') {
            $outline = (string)($_POST['outline'] ?? '');
        }
    }
}

$parsed = $outline !== '' ? AgendaImporter::parseOutline($outline) : null;
$pageTitle = t('agenda.heading');
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/import-dialog.css?v=<?= ASSET_VERSION ?>">
<div class="container" style="max-width: 680px;">
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
  <form method="post" enctype="multipart/form-data" style="margin-top:20px;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <label for="outline"><?= h(t('agenda.outline_label')) ?></label>
    <textarea id="outline" name="outline" rows="14" style="width:100%; font-family:monospace;"><?= h($outline) ?></textarea>
    <div class="props-video-note" style="margin-top:6px;"><?= h(t('agenda.outline_hint')) ?></div>

    <label for="layout_set_id" style="margin-top:14px;"><?= h(t('import.layout_set_label')) ?></label>
    <select id="layout_set_id" name="layout_set_id">
      <?php foreach ($layoutSets as $set): ?>
        <option value="<?= h($set['id']) ?>"<?= $set['id'] === $layoutSetId ? ' selected' : '' ?>><?= h($set['title'] ?? $set['id']) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="opt_highlight" style="margin-top:14px;"><?= h(t('agenda.highlight_label')) ?></label>
    <select id="opt_highlight" name="opt[highlight]">
      <?php foreach (['both' => 'agenda.hl_both', 'color' => 'agenda.hl_color', 'bold' => 'agenda.hl_bold'] as $v => $k): ?>
        <option value="<?= h($v) ?>"<?= $opt['highlight'] === $v ? ' selected' : '' ?>><?= h(t($k)) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="opt_color" style="margin-top:14px;"><?= h(t('agenda.color_label')) ?></label>
    <input type="color" id="opt_color" name="opt[color]" value="<?= h($opt['color']) ?>">

    <label for="opt_heading" style="margin-top:14px;"><?= h(t('agenda.heading_label')) ?></label>
    <input type="text" id="opt_heading" name="opt[heading]" value="<?= h($opt['heading']) ?>">

    <label style="margin-top:14px; display:block;">
      <input type="hidden" name="opt[subSlides]" value="0">
      <input type="checkbox" name="opt[subSlides]" value="1"<?= $opt['subSlides'] ? ' checked' : '' ?>>
      <?= h(t('agenda.sub_slides')) ?>
    </label>

    <label for="extra_pptx" style="margin-top:14px;"><?= h(t('agenda.extra_label')) ?></label>
    <input type="file" id="extra_pptx" name="extra_pptx" accept=".pptx">
    <label for="opt_insert" style="margin-top:10px;"><?= h(t('agenda.insert_label')) ?></label>
    <select id="opt_insert" name="opt[insertAfter]">
      <option value="0"><?= h(t('agenda.insert_end')) ?></option>
      <?php foreach ($parsed['items'] as $i => $item): ?>
        <option value="<?= $i + 1 ?>"<?= $opt['insertAfter'] === $i + 1 ? ' selected' : '' ?>><?= ($i + 1) . '. ' . h($item['title']) ?></option>
      <?php endforeach; ?>
    </select>

    <?php if ($plan): ?>
    <h3 style="margin-top:20px;"><?= h(t('agenda.plan_heading', ['n' => $plan['slide_count']])) ?></h3>
    <ol style="margin:6px 0 0 18px;">
      <?php foreach ($plan['slides'] as $p): ?><li><?= h($p['label']) ?> <span style="color:var(--text-muted);">(<?= h(t('agenda.kind_' . $p['kind'])) ?>)</span></li><?php endforeach; ?>
    </ol>
    <?php foreach ($plan['warnings'] as $w): ?><div class="alert alert-error" style="margin-top:8px;"><?= h($w) ?></div><?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top:20px; display:flex; gap:10px;">
      <button type="submit" name="step" value="preview" class="button hub-btn"><?= h(t('agenda.preview')) ?></button>
      <button type="submit" name="step" value="create" class="button hub-btn hub-btn--primary"><?= h(t('agenda.create')) ?></button>
    </div>
  </form>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
