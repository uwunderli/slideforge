<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/includes/element_icons.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export_layout_set') {
    $setId = (string)($_GET['id'] ?? '');
    $meta = Presentation::getMeta($setId);
    $canUse = $meta
        && LayoutSet::isLayoutSet($setId)
        && ($meta['owner_id'] === $me['id'] || !empty($meta['template_shared']) || $isAdmin);
    if (!$canUse) {
        http_response_code(403);
        die(t('tpl.no_permission'));
    }
    $filenameBase = Exporter::sanitizeFilename((string)($meta['title'] ?? t('tpl.default_layout_set_title')));
    $tmpArchive = sys_get_temp_dir() . '/sf_layoutset_' . bin2hex(random_bytes(6)) . '.chs';
    try {
        LayoutSet::exportArchive($setId, $tmpArchive);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.chs"');
        header('Content-Length: ' . filesize($tmpArchive));
        readfile($tmpArchive);
    } finally {
        @unlink($tmpArchive);
    }
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
        $deleteId = (string)($_POST['id'] ?? '');
        if (TextTemplate::isFallback($deleteId)) {
            $error = t('tpl.text_fallback_cannot_delete');
        } else {
            TextTemplate::delete($deleteId);
            $msg = t('tpl.text_deleted');
        }
    }
    if ($action === 'duplicate_text_template' && $isAdmin) {
        TextTemplate::duplicate($_POST['id'] ?? '');
        $msg = t('tpl.text_duplicated');
    }

    if ($action === 'save_element_links' && $isAdmin) {
        $links = [];
        foreach (ElementLink::allRoles() as $role) {
            if (!array_key_exists($role, $_POST['el_text_template'] ?? [])) {
                continue;
            }
            $val = trim((string)$_POST['el_text_template'][$role]);
            $links[$role] = $val !== '' ? $val : null;
        }
        ElementLink::save($links);
        $msg = t('tpl.element_links_saved');
    }

    // ---- Logos-Importvorlagen (Legacy, nur noch API) ----
    if ($action === 'add_sermon_import_template' && $isAdmin) {
        SermonImportTemplate::create(['name' => t('tpl.default_sermon_import_name')]);
        $msg = t('tpl.sermon_import_added');
    }
    if ($action === 'update_sermon_import_template' && $isAdmin) {
        $id = $_POST['id'] ?? '';
        $existing = SermonImportTemplate::find($id);
        $existingElements = $existing['elements'] ?? [];
        $elements = [];
        foreach (SermonImportTemplate::ELEMENT_TYPES as $elType) {
            $target = $_POST['el_target'][$elType] ?? 'skip';
            if (!in_array($target, ['slide', 'notes', 'skip', 'title_slide'], true)) {
                $target = 'skip';
            }
            $next = array_merge($existingElements[$elType] ?? [], [
                'target' => $target,
                'x' => max(0, (int)($_POST['el_x'][$elType] ?? 100)),
                'y' => max(0, (int)($_POST['el_y'][$elType] ?? 100)),
                'w' => max(20, (int)($_POST['el_w'][$elType] ?? 1720)),
                'h' => max(10, (int)($_POST['el_h'][$elType] ?? 100)),
            ]);
            $tplId = trim((string)($_POST['el_text_template'][$elType] ?? ''));
            if ($tplId !== '') {
                $next['textTemplateId'] = $tplId;
            } else {
                unset($next['textTemplateId']);
            }
            $elements[$elType] = $next;
        }
        SermonImportTemplate::update($id, [
            'name' => trim($_POST['name'] ?? '') !== '' ? trim($_POST['name']) : t('tpl.default_sermon_import_name'),
            'createTitleSlide' => isset($_POST['create_title_slide']),
            'background' => [
                'type' => 'color',
                'value' => preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['background_color'] ?? '') ? $_POST['background_color'] : '#111111',
            ],
            'elements' => $elements,
        ]);
        $msg = t('tpl.sermon_import_saved');
    }
    if ($action === 'delete_sermon_import_template' && $isAdmin) {
        if (SermonImportTemplate::delete($_POST['id'] ?? '')) {
            $msg = t('tpl.sermon_import_deleted');
        } else {
            $error = t('tpl.sermon_import_delete_last');
        }
    }
    if ($action === 'duplicate_sermon_import_template' && $isAdmin) {
        SermonImportTemplate::duplicate($_POST['id'] ?? '');
        $msg = t('tpl.sermon_import_duplicated');
    }

    // ---- Folienvorlagen (jeder für eigene, Admin für alle) ----
    if ($action === 'create_slide_template') {
        $title = trim($_POST['title'] ?? '') !== '' ? trim($_POST['title']) : t('tpl.default_slide_title');
        $tid = Presentation::createTemplate($me['id'], $title);
        redirect('editor.php?id=' . urlencode($tid));
        exit;
    }
    if ($action === 'create_layout_set') {
        $title = trim($_POST['title'] ?? '') !== '' ? trim($_POST['title']) : t('tpl.default_layout_set_title');
        $tid = LayoutSet::create($me['id'], $title);
        redirect('editor.php?id=' . urlencode($tid));
        exit;
    }
    if ($action === 'import_layout_set_archive') {
        $file = $_FILES['layout_set_archive'] ?? null;
        if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = t('tpl.layout_set_import_no_file');
        } elseif (!LayoutSet::isAllowedArchiveUpload((string)($file['tmp_name'] ?? ''), (string)($file['name'] ?? ''))) {
            $error = t('tpl.layout_set_import_invalid_type');
        } else {
            try {
                LayoutSet::importArchive($me['id'], (string)$file['tmp_name']);
                $msg = t('tpl.layout_set_imported');
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
    if ($action === 'import_template_to_layout_set') {
        $setId = $_POST['layout_set_id'] ?? '';
        $templateId = $_POST['template_id'] ?? '';
        $layoutKey = trim((string)($_POST['layout_key'] ?? ''));
        $deleteSource = isset($_POST['delete_source']);
        $tMeta = Presentation::getMeta($templateId);
        $canUse = $tMeta && !empty($tMeta['is_template']) && empty($tMeta['is_layout_set'])
            && ($tMeta['owner_id'] === $me['id'] || !empty($tMeta['template_shared']) || $isAdmin);
        $canEditSet = LayoutSet::isLayoutSet($setId) && Presentation::canUseTemplate($setId, $me['id'])
            && (Presentation::getMeta($setId)['owner_id'] === $me['id'] || $isAdmin);
        if (!$canUse || !$canEditSet) {
            $error = t('tpl.no_permission');
        } else {
            try {
                if ($layoutKey === '') {
                    $layoutKey = LayoutSet::layoutKeyFromTitle($tMeta['title'] ?? '');
                }
                LayoutSet::importSlideTemplate($setId, $templateId, $layoutKey);
                if ($deleteSource && $tMeta['owner_id'] === $me['id']) {
                    Presentation::delete($templateId);
                    $msg = t('tpl.template_moved_to_set');
                } else {
                    $msg = t('tpl.template_imported_to_set');
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
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
            if (Presentation::delete($tid)) {
                $msg = t('tpl.slide_deleted');
            } else {
                $error = t('tpl.delete_failed');
            }
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
    if ($action === 'duplicate_layout_set') {
        $setId = $_POST['id'] ?? '';
        $meta = Presentation::getMeta($setId);
        $canUse = $meta && LayoutSet::isLayoutSet($setId)
            && ($meta['owner_id'] === $me['id'] || !empty($meta['template_shared']) || $isAdmin);
        if (!$canUse) {
            $error = t('tpl.no_permission');
        } else {
            try {
                LayoutSet::duplicate($setId, $me['id']);
                $msg = t('tpl.layout_set_duplicated');
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
    if ($action === 'delete_layout_set') {
        $setId = $_POST['id'] ?? '';
        $meta = Presentation::getMeta($setId);
        if ($meta && LayoutSet::isLayoutSet($setId) && ($meta['owner_id'] === $me['id'] || $isAdmin)) {
            if (Presentation::delete($setId)) {
                $msg = t('tpl.layout_set_deleted');
            } else {
                $error = t('tpl.delete_failed');
            }
        } else {
            $error = t('tpl.no_permission');
        }
    }
}

$logosImporterEnabled = Auth::logosImporterEnabled($me);
ElementLink::ensureDefaults();
$allowedTplTabs = ['slides', 'texts', 'vorlageelemente', 'fonts', 'colors'];
$activeTab = $_GET['tab'] ?? ($_POST['tab'] ?? 'slides');
if (!in_array($activeTab, $allowedTplTabs, true)) $activeTab = 'slides';

$brandColors = Config::brandColors();
$customFonts = FontLibrary::customFonts();
$fontFamilies = FontLibrary::allFamilies();
$textTemplates = TextTemplate::listAll();
$elementTextLinks = ElementLink::map();
[$myTemplates, $sharedTemplates] = Presentation::listTemplatesForUser($me['id']);
$mySlideTemplates = array_values(array_filter($myTemplates, fn($t) => empty($t['is_layout_set'])));
$sharedSlideTemplates = array_values(array_filter($sharedTemplates, fn($t) => empty($t['is_layout_set'])));
[$myLayoutSets, $sharedLayoutSets] = LayoutSet::listForUser($me['id']);

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
    <a href="?tab=vorlageelemente" class="page-tab-btn<?= $activeTab === 'vorlageelemente' ? ' active' : '' ?>"><?= h(t('tpl.tab_vorlageelemente')) ?></a>
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

  <?php elseif ($activeTab === 'vorlageelemente'): ?>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('tpl.vorlageelemente_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= h(t('tpl.vorlageelemente_desc')) ?></p>

    <?php if (!$isAdmin): ?>
      <div class="alert alert-success"><?= h(t('tpl.admin_only_vorlageelemente')) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_element_links">
      <input type="hidden" name="tab" value="vorlageelemente">
      <div style="overflow-x:auto; margin-top:16px;">
        <table class="data-table element-links-table" style="width:100%; font-size:0.88rem;">
          <thead>
            <tr>
              <th></th>
              <th><?= h(t('tpl.sermon_element')) ?></th>
              <th><?= h(t('tpl.sermon_text_template')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $linkRoles = LayoutSet::STANDARD_ELEMENT_ROLES;
            if ($logosImporterEnabled) {
                $linkRoles = array_values(array_unique(array_merge(
                    $linkRoles,
                    LayoutSet::LOGOS_ZONE_ROLES
                )));
            }
            foreach ($linkRoles as $role):
              $currentTpl = $elementTextLinks[$role] ?? '';
            ?>
            <tr>
              <td class="element-links-icon-cell"><span class="element-row-icon"><?= sf_element_icon($role) ?></span></td>
              <td>
                <?= h(t('logos.role_' . $role)) ?>
                <?php if ($logosImporterEnabled && in_array($role, LayoutSet::LOGOS_ZONE_ROLES, true)): ?>
                  <span class="element-links-logos-tag">Logos</span>
                <?php endif; ?>
              </td>
              <td>
                <select name="el_text_template[<?= h($role) ?>]" <?= $isAdmin ? '' : 'disabled' ?>>
                  <option value="">— <?= h(t('tpl.element_link_none')) ?> —</option>
                  <?php foreach ($textTemplates as $tt): ?>
                    <option value="<?= h($tt['id']) ?>" <?= $currentTpl === $tt['id'] ? 'selected' : '' ?>><?= h($tt['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($isAdmin): ?>
      <button type="submit" class="button button-sm" style="margin-top:14px;"><?= h(t('tpl.save')) ?></button>
      <?php endif; ?>
    </form>

  <?php elseif ($activeTab === 'texts'): ?>

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('tpl.texts_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= h(t('tpl.texts_desc')) ?></p>

    <?php if (!$isAdmin): ?>
      <div class="alert alert-success"><?= h(t('tpl.admin_only_texts')) ?></div>
    <?php endif; ?>

    <div id="textTemplatesList">
    <?php foreach ($textTemplates as $t): ?>
      <?php $isFallbackTpl = TextTemplate::isFallback((string)($t['id'] ?? '')); ?>
      <details class="text-template-row<?= $isFallbackTpl ? ' text-template-row--fallback' : '' ?>" <?= ($isAdmin && !$isFallbackTpl) ? 'draggable="true" data-tpl-id="' . h($t['id']) . '"' : '' ?>>
        <summary class="text-template-summary">
          <?php if ($isAdmin && !$isFallbackTpl): ?><span class="text-template-drag-handle" title="Ziehen zum Sortieren">⠿</span><?php endif; ?>
          <span class="text-template-preview" style="font-family:'<?= h($t['fontFamily']) ?>',sans-serif; font-weight:<?= h($t['fontWeight']) ?>; color:<?= h($t['color']) ?>;"><?= h($t['name']) ?></span>
          <?php if ($isFallbackTpl): ?>
            <span class="perm-tag edit"><?= h(t('tpl.text_fallback_badge')) ?></span>
          <?php endif; ?>
          <span class="text-template-meta"><?= (int)$t['fontSize'] ?>px &middot; <?= h($t['fontFamily']) ?></span>
        </summary>
        <?php if ($isFallbackTpl): ?>
          <p class="text-template-fallback-hint"><?= h(t('tpl.text_fallback_desc')) ?></p>
        <?php endif; ?>
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
          <?php if (!$isFallbackTpl): ?>
          <button type="submit" name="action" value="duplicate_text_template" class="button button-ghost button-sm"><?= h(t('tpl.duplicate')) ?></button>
          <button type="submit" name="action" value="delete_text_template" class="button button-ghost button-sm" onclick="return confirm('<?= h(t('tpl.delete_text_confirm', ['name' => $t['name']])) ?>')"><?= h(t('tpl.delete')) ?></button>
          <?php endif; ?>
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

    <div class="section-header" style="margin-top:28px;"><h2><?= h(t('tpl.layout_sets_heading')) ?></h2></div>
    <p style="color:var(--text-muted); font-size:0.9rem;"><?= h(t('tpl.layout_sets_desc')) ?></p>
    <form method="post" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:10px;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create_layout_set">
      <div style="flex:1; min-width:200px;">
        <label style="margin-top:0;"><?= h(t('tpl.name')) ?></label>
        <input type="text" name="title" placeholder="<?= h(t('tpl.layout_set_name_placeholder')) ?>">
      </div>
      <button type="submit" class="button button-sm"><?= h(t('tpl.create_layout_set')) ?></button>
    </form>
    <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:20px;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="import_layout_set_archive">
      <div style="flex:1; min-width:240px;">
        <label style="margin-top:0;"><?= h(t('tpl.layout_set_import_label')) ?></label>
        <input type="file" name="layout_set_archive" required>
      </div>
      <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.layout_set_import_submit')) ?></button>
    </form>
    <?php if (empty($myLayoutSets) && empty($sharedLayoutSets)): ?>
      <div class="empty-state"><?= h(t('tpl.no_layout_sets')) ?></div>
    <?php else: ?>
      <?php if (!empty($myLayoutSets)): ?>
      <ul class="share-list">
        <?php foreach ($myLayoutSets as $t): ?>
          <li>
            <span><?= h($t['title']) ?> <span class="perm-tag <?= !empty($t['template_shared']) ? 'edit' : 'view' ?>"><?= !empty($t['template_shared']) ? h(t('tpl.shared_badge')) : h(t('tpl.private_badge')) ?></span></span>
            <div style="display:flex; gap:8px; align-items:center;">
              <a href="editor.php?id=<?= urlencode($t['id']) ?>" class="button button-ghost button-sm"><?= h(t('tpl.edit')) ?></a>
              <a href="templates.php?tab=slides&action=export_layout_set&id=<?= urlencode($t['id']) ?>" class="button button-ghost button-sm"><?= h(t('tpl.layout_set_export')) ?></a>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle_slide_template_shared">
                <input type="hidden" name="tab" value="slides">
                <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                <input type="hidden" name="shared" value="<?= !empty($t['template_shared']) ? '' : '1' ?>">
                <button type="submit" class="button button-ghost button-sm"><?= !empty($t['template_shared']) ? h(t('tpl.make_private')) : h(t('tpl.share_all')) ?></button>
              </form>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="duplicate_layout_set">
                <input type="hidden" name="tab" value="slides">
                <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.duplicate')) ?></button>
              </form>
              <form method="post" class="inline-form" onsubmit="return confirm('<?= h(t('tpl.delete_layout_set_confirm', ['name' => $t['title']])) ?>')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_layout_set">
                <input type="hidden" name="tab" value="slides">
                <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.delete')) ?></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if (!empty($sharedLayoutSets)): ?>
      <div class="section-header" style="margin-top:20px;"><h2><?= h(t('tpl.shared_by_others_heading')) ?></h2></div>
      <ul class="share-list">
        <?php foreach ($sharedLayoutSets as $t): ?>
          <li>
            <span><?= h($t['title']) ?></span>
            <div style="display:flex; gap:8px; align-items:center;">
              <a href="editor.php?id=<?= urlencode($t['id']) ?>" class="button button-ghost button-sm"><?= h(t('tpl.edit')) ?></a>
              <a href="templates.php?tab=slides&action=export_layout_set&id=<?= urlencode($t['id']) ?>" class="button button-ghost button-sm"><?= h(t('tpl.layout_set_export')) ?></a>
              <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="duplicate_layout_set">
                <input type="hidden" name="tab" value="slides">
                <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                <button type="submit" class="button button-ghost button-sm"><?= h(t('tpl.duplicate')) ?></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    <?php endif; ?>

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
    <?php if (empty($mySlideTemplates)): ?>
      <div class="empty-state"><?= h(t('tpl.no_own_slides')) ?></div>
    <?php else: ?>
      <ul class="share-list" id="slideTemplatesList">
        <?php foreach ($mySlideTemplates as $t): ?>
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
              <?php if (!empty($myLayoutSets) || !empty($sharedLayoutSets)): ?>
              <details class="template-import-to-set" style="display:inline-block;">
                <summary class="button button-ghost button-sm" style="cursor:pointer; list-style:none;"><?= h(t('tpl.to_layout_set')) ?></summary>
                <form method="post" style="position:absolute; z-index:5; margin-top:6px; padding:12px; background:var(--surface); border:1px solid var(--border); border-radius:8px; min-width:260px;">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="import_template_to_layout_set">
                  <input type="hidden" name="tab" value="slides">
                  <input type="hidden" name="template_id" value="<?= h($t['id']) ?>">
                  <label style="margin-top:0; font-size:0.85rem;"><?= h(t('tpl.layout_set_pick')) ?></label>
                  <select name="layout_set_id" required style="width:100%; margin-bottom:8px;">
                    <?php foreach (array_merge($myLayoutSets, $sharedLayoutSets) as $set): ?>
                      <?php if ($set['owner_id'] === $me['id'] || $isAdmin): ?>
                      <option value="<?= h($set['id']) ?>"><?= h($set['title']) ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                  <label style="font-size:0.85rem;"><?= h(t('tpl.layout_key_label')) ?></label>
                  <input type="text" name="layout_key" value="<?= h(LayoutSet::layoutKeyFromTitle($t['title'])) ?>" placeholder="heading1" style="width:100%; margin-bottom:8px;">
                  <label class="present-config-check" style="font-size:0.85rem; margin-bottom:8px;">
                    <input type="checkbox" name="delete_source" style="width:auto;">
                    <?= h(t('tpl.move_delete_source')) ?>
                  </label>
                  <button type="submit" class="button button-sm" style="width:100%;"><?= h(t('tpl.import_to_set_submit')) ?></button>
                </form>
              </details>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="section-header"><h2><?= h(t('tpl.shared_by_others_heading')) ?></h2></div>
    <?php if (empty($sharedSlideTemplates)): ?>
      <div class="empty-state"><?= h(t('tpl.nothing_shared')) ?></div>
    <?php else: ?>
      <ul class="share-list">
        <?php foreach ($sharedSlideTemplates as $t): ?>
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
