<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();
$isAdmin = Auth::isAdmin();

Presentation::removeOrphanSlideTemplates();

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    // ---- Reihenfolge der Text-/Folienvorlagen (Drag & Drop, per fetch als JSON gesendet -
    // $_POST ist bei JSON-Bodies leer, daher eigene CSRF-Prüfung aus dem Body) ----
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $body['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Ungültiges CSRF-Token.']);
        exit;
    }
    $bodyAction = $body['action'] ?? '';
    if ($bodyAction === 'reorder_text_templates') {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Nur für Administratoren.']);
            exit;
        }
        if (is_array($body['ids'] ?? null)) {
            TextTemplate::reorder(array_map('strval', $body['ids']));
        }
    } elseif ($bodyAction === 'reorder_slide_templates') {
        if (is_array($body['ids'] ?? null)) {
            Presentation::reorderTemplates($me['id'], array_map('strval', $body['ids']));
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ---- Farbvorlagen (nur Admin) ----
    if ($action === 'save_colors' && $isAdmin) {
        $names = $_POST['color_name'] ?? [];
        $hexes = $_POST['color_hex'] ?? [];
        $colors = [];
        foreach ($hexes as $i => $hex) {
            $hex = trim($hex);
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) continue;
            $colors[] = ['name' => trim($names[$i] ?? '') !== '' ? trim($names[$i]) : $hex, 'hex' => $hex];
        }
        Config::update(['brand_colors' => $colors]);
        $msg = t('tpl.colors_saved');
    }
    if ($action === 'add_color' && $isAdmin) {
        $colors = Config::brandColors();
        $colors[] = ['name' => t('tpl.new_color_name'), 'hex' => '#3a6c8d'];
        Config::update(['brand_colors' => $colors]);
        $msg = t('tpl.color_added');
    }
    if ($action === 'duplicate_color' && $isAdmin) {
        $index = (int)($_POST['index'] ?? -1);
        $colors = Config::brandColors();
        if (isset($colors[$index])) {
            $copy = $colors[$index];
            $copy['name'] = $copy['name'] . ' (Kopie)';
            array_splice($colors, $index + 1, 0, [$copy]);
            Config::update(['brand_colors' => $colors]);
        }
        $msg = t('tpl.color_added');
    }
    if ($action === 'delete_color' && $isAdmin) {
        $index = (int)($_POST['index'] ?? -1);
        $colors = Config::brandColors();
        if (isset($colors[$index])) {
            array_splice($colors, $index, 1);
            Config::update(['brand_colors' => $colors]);
        }
        $msg = t('tpl.color_removed');
    }

    // ---- Schriftarten (nur Admin) ----
    if ($action === 'upload_font' && $isAdmin) {
        $fileList = FontLibrary::normalizeUploadedFiles($_FILES['font_file'] ?? []);
        $items = [];
        $singleFile = count($fileList) === 1;
        $globalName = trim($_POST['font_name'] ?? '');
        $globalFamily = trim($_POST['font_family'] ?? '');
        foreach ($fileList as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $items[] = [
                'file' => $file,
                'displayName' => $singleFile ? $globalName : '',
                'family' => $singleFile ? $globalFamily : '',
            ];
        }
        if (!$items) {
            $error = t('tpl.font_no_files');
        } else {
            $result = FontLibrary::uploadBatch($items);
            $okCount = count($result['fonts']);
            $failCount = count($result['errors']);
            if ($okCount > 0 && $failCount === 0) {
                $msg = $okCount === 1 ? t('tpl.font_uploaded') : t('tpl.font_uploaded_many', ['count' => $okCount]);
            } elseif ($okCount > 0) {
                $msg = t('tpl.font_upload_partial', ['ok' => $okCount, 'fail' => $failCount]);
                $error = implode("\n", array_map(
                    fn($e) => ($e['file'] !== '' ? $e['file'] . ': ' : '') . $e['error'],
                    $result['errors']
                ));
            } else {
                $error = implode("\n", array_map(
                    fn($e) => ($e['file'] !== '' ? $e['file'] . ': ' : '') . $e['error'],
                    $result['errors']
                ));
            }
        }
    }
    if ($action === 'delete_font' && $isAdmin) {
        if (FontLibrary::delete($_POST['id'] ?? '')) {
            $msg = t('tpl.font_deleted');
        } else {
            $error = t('tpl.font_not_found');
        }
    }

    // ---- Textvorlagen (nur Admin) ----
    if ($action === 'add_text_template' && $isAdmin) {
        TextTemplate::create(['name' => t('tpl.default_text_name')]);
        $msg = t('tpl.text_added');
    }
    if ($action === 'update_text_template' && $isAdmin) {
        TextTemplate::update($_POST['id'] ?? '', [
            'name' => trim($_POST['name'] ?? '') !== '' ? trim($_POST['name']) : t('tpl.default_text_name'),
            'fontFamily' => $_POST['fontFamily'] ?? 'Open Sans',
            'fontSize' => max(6, (int)($_POST['fontSize'] ?? 32)),
            'fontWeight' => ($_POST['fontWeight'] ?? '') === 'bold' ? 'bold' : 'normal',
            'italic' => isset($_POST['italic']),
            'underline' => isset($_POST['underline']),
            'strikethrough' => isset($_POST['strikethrough']),
            'uppercase' => isset($_POST['uppercase']),
            'smallCaps' => isset($_POST['smallCaps']),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#ffffff',
            'align' => in_array($_POST['align'] ?? '', ['left', 'center', 'right'], true) ? $_POST['align'] : 'left',
            'w' => max(20, (int)($_POST['w'] ?? 500)),
            'h' => max(10, (int)($_POST['h'] ?? 60)),
        ]);
        $msg = t('tpl.text_saved');
    }
    if ($action === 'delete_text_template' && $isAdmin) {
        TextTemplate::delete($_POST['id'] ?? '');
        $msg = t('tpl.text_deleted');
    }
    if ($action === 'duplicate_text_template' && $isAdmin) {
        TextTemplate::duplicate($_POST['id'] ?? '');
        $msg = t('tpl.text_duplicated');
    }

    // ---- Folienvorlagen (jeder für eigene, Admin für alle) ----
    if ($action === 'create_slide_template') {
        $title = trim($_POST['title'] ?? '') !== '' ? trim($_POST['title']) : t('tpl.default_slide_title');
        $tid = Presentation::createTemplate($me['id'], $title);
        redirect('editor.php?id=' . urlencode($tid));
        exit;
    }
    if ($action === 'duplicate_slide_template') {
        $tid = $_POST['id'] ?? '';
        $meta = Presentation::getMeta($tid);
        if ($meta && !empty($meta['is_template']) && ($meta['owner_id'] === $me['id'] || !empty($meta['template_shared']) || $isAdmin)) {
            Presentation::duplicateTemplate($tid, $me['id']);
            $msg = t('tpl.slide_duplicated');
        } else {
            $error = t('tpl.no_permission');
        }
    }
    if ($action === 'delete_slide_template') {
        $tid = $_POST['id'] ?? '';
        $meta = Presentation::getMeta($tid);
        if ($meta && !empty($meta['is_template']) && ($meta['owner_id'] === $me['id'] || $isAdmin)) {
            Presentation::delete($tid);
            $msg = t('tpl.slide_deleted');
        } else {
            $error = t('tpl.no_permission');
        }
    }
    if ($action === 'toggle_slide_template_shared') {
        $tid = $_POST['id'] ?? '';
        $meta = Presentation::getMeta($tid);
        if ($meta && !empty($meta['is_template']) && ($meta['owner_id'] === $me['id'] || $isAdmin)) {
            Presentation::setTemplateShared($tid, ($_POST['shared'] ?? '') === '1');
            $msg = t('tpl.share_updated');
        } else {
            $error = t('tpl.no_permission');
        }
    }
}

$activeTab = $_GET['tab'] ?? ($_POST['tab'] ?? 'slides');
if (!in_array($activeTab, ['slides', 'texts', 'fonts', 'colors'], true)) $activeTab = 'slides';

$brandColors = Config::brandColors();
$customFonts = FontLibrary::customFonts();
$fontFamilies = FontLibrary::allFamilies();
$textTemplates = TextTemplate::listAll();
[$myTemplates, $sharedTemplates] = Presentation::listTemplatesForUser($me['id']);

$FONT_OPTIONS = $fontFamilies;

$pageTitle = t('tpl.heading');
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width: 860px;">
  <a href="index.php" class="back-link">&larr; <?= h(t('profile.back_to_dashboard')) ?></a>
  <h1 style="margin-top:14px;"><?= h(t('tpl.heading')) ?></h1>

  <div class="page-tabs">
    <a href="?tab=slides" class="page-tab-btn<?= $activeTab === 'slides' ? ' active' : '' ?>"><?= h(t('tpl.tab_slides')) ?></a>
    <a href="?tab=texts" class="page-tab-btn<?= $activeTab === 'texts' ? ' active' : '' ?>"><?= h(t('tpl.tab_texts')) ?></a>
    <a href="?tab=fonts" class="page-tab-btn<?= $activeTab === 'fonts' ? ' active' : '' ?>"><?= h(t('tpl.tab_fonts')) ?></a>
    <a href="?tab=colors" class="page-tab-btn<?= $activeTab === 'colors' ? ' active' : '' ?>"><?= h(t('tpl.tab_colors')) ?></a>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= nl2br(h($error)) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>

  <?php if ($activeTab === 'colors'): ?>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('tpl.brand_colors_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= h(t('tpl.brand_colors_desc')) ?></p>

    <?php if (!$isAdmin): ?>
      <div class="alert alert-success"><?= h(t('tpl.admin_only_colors')) ?></div>
      <div class="brand-palette" style="grid-template-columns: repeat(auto-fill, minmax(40px,1fr)); max-width:500px;">
        <?php foreach ($brandColors as $c): ?>
          <div class="brand-swatch" style="background:<?= h($c['hex']) ?>; cursor:default;" title="<?= h($c['name']) ?> (<?= h($c['hex']) ?>)"></div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="options-subtitle" style="margin-top:0;"><?= h(t('tpl.quick_select')) ?></div>
      <div class="brand-palette" style="grid-template-columns: repeat(auto-fill, minmax(40px,1fr)); max-width:500px; margin-bottom:20px;">
        <?php foreach ($brandColors as $c): ?>
          <div class="brand-swatch" style="background:<?= h($c['hex']) ?>; cursor:default;" title="<?= h($c['name']) ?> (<?= h($c['hex']) ?>)"></div>
        <?php endforeach; ?>
      </div>

      <form method="post" id="colorsForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save_colors">
        <?php foreach ($brandColors as $i => $c): ?>
          <div class="color-row" draggable="true">
            <span class="text-template-drag-handle" title="Ziehen zum Sortieren">⠿</span>
            <input type="color" name="color_hex[]" value="<?= h($c['hex']) ?>">
            <input type="text" name="color_name[]" value="<?= h($c['name']) ?>" placeholder="<?= h(t('tpl.name_placeholder')) ?>">
            <span class="color-row-hex"><?= h($c['hex']) ?></span>
            <button type="submit" form="duplicateColorForm<?= (int)$i ?>" class="icon-btn-ghost" title="<?= h(t('tpl.duplicate')) ?>">⧉</button>
            <button type="submit" form="deleteColorForm<?= (int)$i ?>" class="icon-btn-danger" title="<?= h(t('tpl.delete_color')) ?>">🗑</button>
          </div>
        <?php endforeach; ?>
        <button type="submit" class="button button-sm" style="margin-top:10px;"><?= h(t('tpl.save_all')) ?></button>
      </form>
      <?php foreach ($brandColors as $i => $c): ?>
        <form method="post" id="deleteColorForm<?= (int)$i ?>">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete_color">
          <input type="hidden" name="index" value="<?= (int)$i ?>">
        </form>
        <form method="post" id="duplicateColorForm<?= (int)$i ?>">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="duplicate_color">
          <input type="hidden" name="index" value="<?= (int)$i ?>">
        </form>
      <?php endforeach; ?>
      <form method="post" style="margin-top:10px; display:inline-block;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add_color">
        <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.add_color')) ?></button>
      </form>
    <?php endif; ?>

  <?php elseif ($activeTab === 'fonts'): ?>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('tpl.fonts_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= h(t('tpl.fonts_desc')) ?></p>

    <div class="options-subtitle" style="margin-top:0;"><?= h(t('tpl.fonts_builtin')) ?></div>
    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:16px;">
      <?= h(implode(', ', FontLibrary::SYSTEM_FONTS)) ?>
    </p>

    <?php if (!$isAdmin): ?>
      <div class="alert alert-success"><?= h(t('tpl.admin_only_fonts')) ?></div>
      <?php if ($customFonts): ?>
        <ul class="font-list-readonly">
          <?php foreach ($customFonts as $f): ?>
            <li style="font-family:'<?= h($f['family']) ?>', sans-serif;"><?= h($f['name']) ?> <span class="text-template-meta">(<?= h($f['family']) ?>)</span></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p style="color:var(--text-muted);"><?= h(t('tpl.fonts_empty')) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <form method="post" enctype="multipart/form-data" class="font-upload-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="upload_font">
        <input type="hidden" name="tab" value="fonts">
        <div class="row">
          <div>
            <label style="margin-top:0;"><?= h(t('tpl.font_display_name')) ?></label>
            <input type="text" name="font_name" placeholder="<?= h(t('tpl.font_display_name_ph')) ?>">
          </div>
          <div>
            <label style="margin-top:0;"><?= h(t('tpl.font_family_css')) ?></label>
            <input type="text" name="font_family" placeholder="<?= h(t('tpl.font_family_css_ph')) ?>">
          </div>
        </div>
        <div style="margin-top:10px;">
          <label><?= h(t('tpl.font_file')) ?></label>
          <input type="file" name="font_file[]" accept=".woff2,.woff,.ttf,.otf,font/woff2,font/woff,font/ttf,font/otf" multiple required>
          <p style="color:var(--text-muted); font-size:0.85rem; margin-top:6px;"><?= h(t('tpl.font_file_hint')) ?></p>
          <p style="color:var(--text-muted); font-size:0.85rem; margin-top:4px;"><?= h(t('tpl.font_multi_hint')) ?></p>
        </div>
        <button type="submit" class="button button-sm" style="margin-top:12px;"><?= h(t('tpl.font_upload')) ?></button>
      </form>

      <div class="options-subtitle" style="margin-top:28px;"><?= h(t('tpl.fonts_custom_heading')) ?></div>
      <?php if (!$customFonts): ?>
        <p style="color:var(--text-muted);"><?= h(t('tpl.fonts_empty')) ?></p>
      <?php else: ?>
        <?php foreach ($customFonts as $f): ?>
          <div class="font-row">
            <div class="font-row-preview" style="font-family:'<?= h($f['family']) ?>', sans-serif;">Aa Bb Cc 123</div>
            <div class="font-row-meta">
              <strong><?= h($f['name']) ?></strong>
              <span class="text-template-meta"><?= h($f['family']) ?> · <?= h(strtoupper($f['format'] ?? '')) ?></span>
            </div>
            <form method="post" onsubmit="return confirm('<?= h(t('tpl.font_delete_confirm')) ?>');">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="delete_font">
              <input type="hidden" name="tab" value="fonts">
              <input type="hidden" name="id" value="<?= h($f['id']) ?>">
              <button type="submit" class="icon-btn-danger" title="<?= h(t('tpl.font_delete')) ?>">🗑</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>

  <?php elseif ($activeTab === 'texts'): ?>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('tpl.texts_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= h(t('tpl.texts_desc')) ?></p>

    <?php if (!$isAdmin): ?>
      <div class="alert alert-success"><?= h(t('tpl.admin_only_texts')) ?></div>
    <?php endif; ?>

    <div id="textTemplatesList">
    <?php foreach ($textTemplates as $t): ?>
      <details class="text-template-row" <?= $isAdmin ? 'draggable="true" data-tpl-id="' . h($t['id']) . '"' : '' ?>>
        <summary class="text-template-summary">
          <?php if ($isAdmin): ?><span class="text-template-drag-handle" title="Ziehen zum Sortieren">⠿</span><?php endif; ?>
          <span class="text-template-preview" style="font-family:'<?= h($t['fontFamily']) ?>',sans-serif; font-weight:<?= h($t['fontWeight']) ?>; color:<?= h($t['color']) ?>;"><?= h($t['name']) ?></span>
          <span class="text-template-meta"><?= (int)$t['fontSize'] ?>px &middot; <?= h($t['fontFamily']) ?></span>
        </summary>
        <form method="post" style="margin-top:14px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="update_text_template">
        <input type="hidden" name="id" value="<?= h($t['id']) ?>">
        <div class="row">
          <div>
            <label style="margin-top:0;"><?= h(t('tpl.name')) ?></label>
            <input type="text" name="name" value="<?= h($t['name']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
          </div>
          <div>
            <label style="margin-top:0;"><?= h(t('tpl.font')) ?></label>
            <select name="fontFamily" <?= $isAdmin ? '' : 'disabled' ?>>
              <?php foreach ($FONT_OPTIONS as $f): ?>
                <option value="<?= h($f) ?>" <?= $t['fontFamily'] === $f ? 'selected' : '' ?>><?= h($f) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="row">
          <div>
            <label><?= h(t('tpl.size')) ?></label>
            <input type="number" name="fontSize" value="<?= (int)$t['fontSize'] ?>" <?= $isAdmin ? '' : 'disabled' ?>>
          </div>
          <div>
            <label><?= h(t('tpl.width')) ?></label>
            <input type="number" name="w" value="<?= (int)$t['w'] ?>" <?= $isAdmin ? '' : 'disabled' ?>>
          </div>
          <div>
            <label><?= h(t('tpl.height')) ?></label>
            <input type="number" name="h" value="<?= (int)$t['h'] ?>" <?= $isAdmin ? '' : 'disabled' ?>>
          </div>
        </div>
        <div class="row" style="align-items:center;">
          <div>
            <label><?= h(t('tpl.color')) ?></label>
            <input type="color" id="color_<?= h($t['id']) ?>" name="color" value="<?= h($t['color']) ?>" style="height:34px;" <?= $isAdmin ? '' : 'disabled' ?>>
            <?php if ($isAdmin): ?>
              <div class="brand-palette mini" style="margin-top:6px;">
                <?php foreach ($brandColors as $c): ?>
                  <button type="button" class="brand-swatch" style="background:<?= h($c['hex']) ?>" title="<?= h($c['name']) ?>" onclick="document.getElementById('color_<?= h($t['id']) ?>').value='<?= h($c['hex']) ?>'"></button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <div>
            <label><?= h(t('tpl.align')) ?></label>
            <select name="align" <?= $isAdmin ? '' : 'disabled' ?>>
              <option value="left" <?= $t['align'] === 'left' ? 'selected' : '' ?>><?= h(t('tpl.align_left')) ?></option>
              <option value="center" <?= $t['align'] === 'center' ? 'selected' : '' ?>><?= h(t('tpl.align_center')) ?></option>
              <option value="right" <?= $t['align'] === 'right' ? 'selected' : '' ?>><?= h(t('tpl.align_right')) ?></option>
            </select>
          </div>
        </div>
        <label><?= h(t('tpl.format')) ?></label>
        <div class="format-toggle-group">
          <label class="format-toggle-btn" style="font-weight:700;" title="<?= h(t('props.bold')) ?>"><input type="checkbox" name="fontWeight" value="bold" hidden <?= $t['fontWeight'] === 'bold' ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>B</label>
          <label class="format-toggle-btn" style="font-style:italic;" title="<?= h(t('props.italic')) ?>"><input type="checkbox" name="italic" hidden <?= !empty($t['italic']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>I</label>
          <label class="format-toggle-btn" style="text-decoration:underline;" title="<?= h(t('props.underline')) ?>"><input type="checkbox" name="underline" hidden <?= !empty($t['underline']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>U</label>
          <label class="format-toggle-btn" style="text-decoration:line-through;" title="<?= h(t('props.strikethrough')) ?>"><input type="checkbox" name="strikethrough" hidden <?= !empty($t['strikethrough']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>S</label>
          <label class="format-toggle-btn" style="font-size:0.72em; letter-spacing:0.02em;" title="<?= h(t('props.uppercase')) ?>"><input type="checkbox" name="uppercase" hidden <?= !empty($t['uppercase']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>AA</label>
          <label class="format-toggle-btn" style="font-variant:small-caps;" title="<?= h(t('props.smallcaps')) ?>"><input type="checkbox" name="smallCaps" hidden <?= !empty($t['smallCaps']) ? 'checked' : '' ?> <?= $isAdmin ? '' : 'disabled' ?>>Aa</label>
        </div>
        <?php if ($isAdmin): ?>
        <div style="display:flex; gap:8px; margin-top:12px;">
          <button type="submit" class="button button-sm"><?= h(t('tpl.save')) ?></button>
          <button type="submit" name="action" value="duplicate_text_template" class="button button-ghost button-sm"><?= h(t('tpl.duplicate')) ?></button>
          <button type="submit" name="action" value="delete_text_template" class="button button-ghost button-sm" onclick="return confirm('<?= h(t('tpl.delete_text_confirm', ['name' => $t['name']])) ?>')"><?= h(t('tpl.delete')) ?></button>
        </div>
        <?php endif; ?>
        </form>
      </details>
    <?php endforeach; ?>
    </div>

    <?php if ($isAdmin): ?>
      <form method="post" style="margin-top:10px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add_text_template">
        <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.add_text')) ?></button>
      </form>
    <?php endif; ?>

  <?php elseif ($activeTab === 'slides'): ?>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('tpl.new_slide_heading')) ?></h2></div>
    <form method="post" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create_slide_template">
      <div style="flex:1; min-width:200px;">
        <label style="margin-top:0;"><?= h(t('tpl.name')) ?></label>
        <input type="text" name="title" placeholder="<?= h(t('tpl.slide_name_placeholder')) ?>">
      </div>
      <button type="submit" class="button button-sm"><?= t('tpl.create_and_edit') ?></button>
    </form>

    <div class="section-header"><h2><?= h(t('tpl.my_slides_heading')) ?></h2></div>
    <?php if (empty($myTemplates)): ?>
      <div class="empty-state"><?= h(t('tpl.no_own_slides')) ?></div>
    <?php else: ?>
      <ul class="share-list" id="slideTemplatesList">
        <?php foreach ($myTemplates as $t): ?>
          <li draggable="true" data-tpl-id="<?= h($t['id']) ?>">
            <span><span class="text-template-drag-handle" title="Ziehen zum Sortieren">⠿</span> <?= h($t['title']) ?> <span class="perm-tag <?= !empty($t['template_shared']) ? 'edit' : 'view' ?>"><?= !empty($t['template_shared']) ? h(t('tpl.shared_badge')) : h(t('tpl.private_badge')) ?></span></span>
            <div style="display:flex; gap:8px; align-items:center;">
              <a href="editor.php?id=<?= urlencode($t['id']) ?>" class="button button-ghost button-sm"><?= h(t('tpl.edit')) ?></a>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle_slide_template_shared">
                <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                <input type="hidden" name="shared" value="<?= !empty($t['template_shared']) ? '' : '1' ?>">
                <button type="submit" class="button button-ghost button-sm"><?= !empty($t['template_shared']) ? h(t('tpl.make_private')) : h(t('tpl.share_all')) ?></button>
              </form>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="duplicate_slide_template">
                <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.duplicate')) ?></button>
              </form>
              <form method="post" class="inline-form" onsubmit="return confirm('<?= h(t('tpl.delete_slide_confirm', ['name' => $t['title']])) ?>')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_slide_template">
                <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.delete')) ?></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="section-header"><h2><?= h(t('tpl.shared_by_others_heading')) ?></h2></div>
    <?php if (empty($sharedTemplates)): ?>
      <div class="empty-state"><?= h(t('tpl.nothing_shared')) ?></div>
    <?php else: ?>
      <ul class="share-list">
        <?php foreach ($sharedTemplates as $t): ?>
          <li>
            <span><?= h($t['title']) ?></span>
            <div style="display:flex; gap:8px;">
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="duplicate_slide_template">
                <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.duplicate')) ?></button>
              </form>
              <?php if ($isAdmin): ?>
                <a href="editor.php?id=<?= urlencode($t['id']) ?>" class="button button-ghost button-sm"><?= h(t('tpl.edit')) ?></a>
                <form method="post" class="inline-form" onsubmit="return confirm('<?= h(t('tpl.delete_slide_confirm', ['name' => $t['title']])) ?>')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="delete_slide_template">
                  <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                  <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.delete')) ?></button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <p style="color:var(--text-muted); font-size:0.85rem;"><?= t('tpl.apply_hint') ?></p>
    <?php endif; ?>

  <?php endif; ?>
</div>
<script>
(function () {
  function makeSortable(listId, rowSelector, reorderAction) {
    const list = document.getElementById(listId);
    if (!list) return;
    let dragEl = null;

    list.querySelectorAll(rowSelector).forEach((row) => {
      row.addEventListener('dragstart', () => {
        dragEl = row;
        row.classList.add('dragging');
      });
      row.addEventListener('dragend', () => {
        row.classList.remove('dragging');
        list.querySelectorAll('.drag-over').forEach((r) => r.classList.remove('drag-over'));
        persistOrder();
      });
      row.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (row === dragEl) return;
        row.classList.add('drag-over');
        const rect = row.getBoundingClientRect();
        const before = (e.clientY - rect.top) < rect.height / 2;
        list.insertBefore(dragEl, before ? row : row.nextSibling);
      });
      row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
      row.addEventListener('drop', (e) => { e.preventDefault(); row.classList.remove('drag-over'); });
    });

    function persistOrder() {
      const ids = Array.from(list.querySelectorAll(rowSelector)).map((r) => r.dataset.tplId);
      fetch('templates.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: reorderAction, ids: ids, csrf_token: '<?= csrf_token() ?>' }),
      }).catch(() => {});
    }
  }

  <?php if ($isAdmin): ?>
  makeSortable('textTemplatesList', '.text-template-row', 'reorder_text_templates');
  <?php endif; ?>
  makeSortable('slideTemplatesList', 'li', 'reorder_slide_templates');

  <?php if ($isAdmin): ?>
  (function () {
    const form = document.getElementById('colorsForm');
    if (!form) return;
    let dragEl = null;
    let allowDrag = false;

    form.querySelectorAll('.text-template-drag-handle').forEach((handle) => {
      handle.addEventListener('mousedown', () => { allowDrag = true; });
    });

    form.querySelectorAll('.color-row').forEach((row) => {
      row.addEventListener('dragstart', (e) => {
        if (!allowDrag) { e.preventDefault(); return; }
        dragEl = row;
        row.classList.add('dragging');
      });
      row.addEventListener('dragend', () => {
        allowDrag = false;
        row.classList.remove('dragging');
        form.querySelectorAll('.drag-over').forEach((r) => r.classList.remove('drag-over'));
        form.requestSubmit();
      });
      row.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (row === dragEl) return;
        row.classList.add('drag-over');
        const rect = row.getBoundingClientRect();
        const before = (e.clientY - rect.top) < rect.height / 2;
        row.parentNode.insertBefore(dragEl, before ? row : row.nextSibling);
      });
      row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
      row.addEventListener('drop', (e) => { e.preventDefault(); row.classList.remove('drag-over'); });
    });
  })();
  <?php endif; ?>
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
