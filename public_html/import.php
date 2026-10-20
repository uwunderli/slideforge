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

/* Zuletzt benutzte Dialog-Einstellungen des Benutzers. Sie gewinnen über die
   Vorgabe, aber nur wenn der gemerkte Eintrag noch existiert — ein gelöschter
   Bereich oder ein entzogenes Folien-Set darf den Dialog nicht leer lassen. */
$importUi = Auth::getEditorUi($me)['import'];
if (($importUi['sectionId'] ?? '') !== '') {
    foreach ($importSections as $importSection) {
        if ((string)$importSection['id'] === $importUi['sectionId']) {
            $defaultImportSectionId = $importUi['sectionId'];
            break;
        }
    }
}
if (($importUi['layoutSetId'] ?? '') !== '') {
    foreach ($layoutSets as $set) {
        if ((string)($set['id'] ?? '') === $importUi['layoutSetId']) {
            $defaultLayoutSetId = $importUi['layoutSetId'];
            break;
        }
    }
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
        $logosOptions = is_array($_POST['logos_opt'] ?? null) ? $_POST['logos_opt'] : [];
        $plan = LogosSermonImporter::planImport($htmlContent, $layoutSetId, $logosOptions);
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
            trim((string)($_POST['layout_set_id'] ?? '')),
            is_array($_POST['logos_opt'] ?? null) ? $_POST['logos_opt'] : []
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
<link rel="stylesheet" href="assets/css/import-dialog.css?v=<?= ASSET_VERSION ?>">
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
    <button type="submit" class="button hub-btn hub-btn--primary" style="margin-top:16px;"><?= h(t('import.submit')) ?></button>
  </form>
</div>
<div id="importDialog" class="modal-backdrop import-dialog-backdrop">
  <div class="modal modal-import-dialog" id="importDialogPanel">
    <div class="sf-dialog-header import-dialog-header">
      <h2 class="sf-dialog-title"><?= h(t('import.dialog_heading')) ?></h2>
    </div>
    <p class="sf-dialog-hint import-dialog-hint"><?= h(t('import.dialog_intro')) ?></p>

    <div class="import-dialog-body import-dialog-cols">
      <div class="import-dialog-col-settings">
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

        <h3 class="import-opt-heading"><?= h(t('import.opt_heading')) ?></h3>
        <div class="props-video-note" style="margin-bottom:10px;"><?= h(t('import.opt_hint')) ?></div>
        <?php foreach (['outline' => 'import.opt_outline', 'scripture' => 'import.opt_scripture', 'bullets' => 'import.opt_bullets'] as $optKey => $optLabel): ?>
        <div class="import-opt-row">
          <label for="import_opt_<?= h($optKey) ?>"><?= h(t($optLabel)) ?></label>
          <?php $optVal = (string)($importUi[$optKey] ?? 'set'); ?>
          <select id="import_opt_<?= h($optKey) ?>" data-import-opt="<?= h($optKey) ?>">
            <?php foreach (['set' => 'import.opt_zone_set', 'slides' => 'import.opt_zone_slides', 'notes' => 'import.opt_zone_notes', 'off' => 'import.opt_zone_off'] as $zoneVal => $zoneKey): ?>
            <option value="<?= h($zoneVal) ?>"<?= $optVal === $zoneVal ? ' selected' : '' ?>><?= h(t($zoneKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endforeach; ?>
        <div class="import-opt-row">
          <label for="import_opt_outlineDepth"><?= h(t('import.opt_depth')) ?></label>
          <select id="import_opt_outlineDepth" data-import-opt="outlineDepth">
            <option value="0"<?= (int)($importUi['outlineDepth'] ?? 0) === 0 ? ' selected' : '' ?>><?= h(t('import.opt_depth_all')) ?></option>
            <?php for ($lvl = 1; $lvl <= 5; $lvl++): ?>
            <option value="<?= $lvl ?>"<?= (int)($importUi['outlineDepth'] ?? 0) === $lvl ? ' selected' : '' ?>><?= h(str_replace('{n}', (string)$lvl, t('import.opt_depth_level'))) ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <label class="import-opt-check">
          <input type="checkbox" id="import_opt_scriptureTranslation" data-import-opt="scriptureTranslation"<?= !empty($importUi['scriptureTranslation']) ? ' checked' : '' ?>>
          <span><?= h(t('import.opt_translation')) ?></span>
        </label>
        <label class="import-opt-check">
          <input type="checkbox" id="import_opt_agendaMain" data-import-opt="agendaMain"<?= !empty($importUi['agendaMain']) ? ' checked' : '' ?>>
          <span><?= h(t('import.agenda_main')) ?></span>
        </label>
        <label class="import-opt-check">
          <input type="checkbox" id="import_opt_hierarchyNotes" data-import-opt="hierarchyNotes"<?= !empty($importUi['hierarchyNotes']) ? ' checked' : '' ?>>
          <span><?= h(t('import.hierarchy_notes')) ?></span>
        </label>
        <div class="import-opt-row">
          <label for="import_opt_hierarchyStyle"><?= h(t('import.hierarchy_style')) ?></label>
          <?php $hStil = (string)($importUi['hierarchyStyle'] ?? 'headings'); ?>
          <select id="import_opt_hierarchyStyle" data-import-opt="hierarchyStyle">
            <?php foreach (['headings' => 'import.hierarchy_style_headings', 'path' => 'import.hierarchy_style_path'] as $hVal => $hKey): ?>
            <option value="<?= h($hVal) ?>"<?= $hStil === $hVal ? ' selected' : '' ?>><?= h(t($hKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="import-opt-check">
          <input type="checkbox" id="import_opt_swissSs" data-import-opt="swissSs"<?= !empty($importUi['swissSs']) ? ' checked' : '' ?>>
          <span><?= h(t('import.swiss_ss')) ?></span>
        </label>
        <div class="import-opt-row import-opt-color">
          <label for="import_opt_agendaColor"><?= h(t('import.agenda_color')) ?></label>
          <?php $brandColors = Config::brandColors(); ?>
          <div class="import-color-field" id="importColorField">
            <button type="button" class="import-color-button" id="importColorButton"
                    aria-haspopup="true" aria-expanded="false">
              <?php
              $farbe = (string)($importUi['agendaColor'] ?? '#b7de45');
              /* Namen der Markenfarbe anzeigen statt des Hex-Werts — beim Aufbau der
                 Seite, damit nicht erst nach einer Auswahl der Name erscheint. */
              $farbName = $farbe;
              foreach ($brandColors as $bc) {
                  if (strtolower((string)($bc['hex'] ?? '')) === strtolower($farbe)) {
                      $farbName = (string)($bc['name'] ?? $farbe);
                      break;
                  }
              }
              ?>
              <span class="import-color-dot" id="importColorDot" style="background:<?= h($farbe) ?>;"></span>
              <span class="import-color-name" id="importColorName"><?= h($farbName) ?></span>
              <span class="import-color-caret" aria-hidden="true">▾</span>
            </button>
            <div class="import-color-panel" id="importColorPanel" hidden>
              <?php if ($brandColors): ?>
              <div class="import-brand-swatches" id="importBrandSwatches">
                <?php foreach ($brandColors as $bc): ?>
                  <?php $hex = (string)($bc['hex'] ?? ''); if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) { continue; } ?>
                  <button type="button" class="import-brand-swatch" data-hex="<?= h($hex) ?>"
                          data-name="<?= h((string)($bc['name'] ?? $hex)) ?>"
                          style="background:<?= h($hex) ?>;" title="<?= h((string)($bc['name'] ?? $hex)) ?>"
                          aria-label="<?= h((string)($bc['name'] ?? $hex)) ?>"></button>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <input type="color" id="import_opt_agendaColor" data-import-opt="agendaColor" value="<?= h((string)($importUi['agendaColor'] ?? '#b7de45')) ?>">
            </div>
          </div>
        </div>
        <?php elseif ($logosImporterEnabled): ?>
        <div class="alert alert-error" style="margin-top:0;"><?= t('import.layout_set_none_available') ?></div>
        <?php else: ?>
        <div class="alert alert-error" style="margin-top:0;"><?= h(t('import.logos_disabled')) ?></div>
        <?php endif; ?>
      </div>

      </div>
      <div class="import-dialog-col-preview">
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
      <div class="import-dialog-col-detail" id="importDetailCol" hidden>
        <div class="import-detail-sticky">
          <h3 id="importDetailTitle" class="import-detail-title"></h3>
          <div class="import-detail-stage" id="importDetailStage"></div>
          <div id="importDetailSlideOff" class="import-detail-off" hidden><?= h(t('import.detail_slide_off')) ?></div>
          <h4 class="import-detail-subtitle"><?= h(t('import.detail_notes')) ?></h4>
          <div id="importDetailNotes" class="import-detail-notes"></div>
          <h4 class="import-detail-subtitle"><?= h(t('import.own_notes')) ?></h4>
          <textarea id="importOwnNotes" class="import-own-notes" rows="6"
                    placeholder="<?= h(t('import.own_notes_hint')) ?>"></textarea>
        </div>
      </div>
    </div>

    <!-- `hub-float-dialog__footer`: hub-float-dialog schiebt beim Aufrüsten ALLE
         Kinder des Dialogs in einen erzeugten Rumpf — ausgenommen sind Titelzeile,
         Anfasser und genau dieses Element. Ohne die Klasse landeten die Schaltflächen
         im scrollenden Rumpf und legten sich über die letzte Folienreihe. Das Heben
         über `data-hub-dialog-footer` greift auf unserem Pfad nicht: Es läuft nur
         beim Erzeugen eines Dialogs, nicht beim Aufrüsten eines bestehenden. -->
    <div class="modal-actions import-dialog-actions hub-float-dialog__footer">
      <button type="button" class="button button-ghost hub-btn hub-btn--ghost" id="importDialogCancel"><?= h(t('common.cancel')) ?></button>
      <button type="button" class="button button-ghost hub-btn hub-btn--ghost" id="importPreviewReload" hidden><?= h(t('import.preview_reload')) ?></button>
      <button type="button" class="button hub-btn hub-btn--primary" id="importDialogConfirm"><?= h(t('import.submit')) ?></button>
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
  var dialogCols = document.querySelector('.import-dialog-cols');
  if (!form || !input || !modal || !sectionSel) return;

  var logosEnabled = <?= $logosImporterEnabled ? 'true' : 'false' ?>;
  /* Der vollständige gemerkte Zustand: set_editor_ui ersetzt ihn als Ganzes, wir
     müssen die übrigen Werte also mitschicken, sonst fielen sie auf die Vorgabe
     zurück (etwa die Höhe des Notizfeldes im Editor). */
  var editorUi = <?= json_encode(Auth::getEditorUi($me), JSON_UNESCAPED_UNICODE) ?>;
  var csrfToken = <?= json_encode(csrf_token()) ?>;
  var saveTimer = null;

  function saveImportUi() {
    var werte = {
      sectionId: sectionSel ? sectionSel.value : '',
      layoutSetId: layoutSel ? layoutSel.value : ''
    };
    optionControls().forEach(function (el) {
      var key = el.getAttribute('data-import-opt');
      werte[key] = el.type === 'checkbox' ? el.checked : el.value;
    });
    editorUi.import = werte;
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(function () {
      saveTimer = null;
      fetch('user_api.php?action=set_editor_ui', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ ui: editorUi })
      }).catch(function () { /* Merken ist eine Bequemlichkeit, kein Muss. */ });
    }, 400);
  }

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
    layoutSetRequired: <?= json_encode(strip_tags(t('import.layout_set_required'))) ?>,
    tileOff: <?= json_encode(t('import.tile_off')) ?>,
    tileOn: <?= json_encode(t('import.tile_on')) ?>,
    tileNotes: <?= json_encode(t('import.tile_notes')) ?>,
    detailHeading: <?= json_encode(t('import.detail_heading')) ?>,
    detailNotesNone: <?= json_encode(t('import.detail_notes_none')) ?>,
    detailHint: <?= json_encode(t('import.detail_hint')) ?>,
    skippedCount: <?= json_encode(t('import.skipped_count')) ?>,
    sectionRename: <?= json_encode(t('import.section_rename')) ?>,
    agendaToggle: <?= json_encode(t('import.agenda_toggle')) ?>
  };

  /* Abgewählte Folien und Notizteile, nach ihrem Inhaltsschlüssel. Der Schlüssel kommt
     vom Server und übersteht ein Neurechnen der Vorschau — eine Abwahl nach Position
     würde beim nächsten Einstellungswechsel die falsche Folie treffen. */
  var skipSlides = {};
  var skipNotes = {};
  // Spontan im Dialog getippte Notizen, je Folie. Gehen beim Import an die Folie.
  var ownNotes = {};
  // Im Dialog umbenannte Abschnitte: ursprünglicher Titel => neuer Titel.
  var sectionTitles = {};
  // Abschnitte mit Gliederungsübersicht, nach ursprünglichem Titel.
  var agendaSections = {};
  var selectedKey = '';
  var lastPlan = null;

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
      if (dialogCols) dialogCols.classList.remove('import-dialog-cols--preview');
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

  /* Abschnittsüberschrift mit Bleistift. Nur Abschnitte aus einer Überschrift der
     ersten Ebene sind umbenennbar — „Vorspann" ist unsere eigene Bezeichnung für alles
     vor dem ersten Abschnitt und hat keine Folie, auf die ein neuer Titel könnte. */
  function sectionHeadHtml(title, orig) {
    if (!orig) {
      return '<div class="import-preview-section-head">' + esc(title) + '</div>';
    }
    return '<div class="import-preview-section-head" data-section-orig="' + esc(orig) + '">'
      + '<span class="import-section-title">' + esc(sectionTitles[orig] || title) + '</span>'
      + '<button type="button" class="import-section-edit" data-act="rename"'
      + ' title="' + esc(i18n.sectionRename) + '" aria-label="' + esc(i18n.sectionRename) + '">'
      + '<svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true">'
      + '<path fill="currentColor" d="M11.6 1.6 14.4 4.4 5.8 13H3v-2.8l8.6-8.6z"/></svg></button>'
      + '<button type="button" class="import-section-agenda' + (agendaSections[orig] ? ' is-on' : '') + '"'
      + ' data-act="agenda" title="' + esc(i18n.agendaToggle) + '" aria-pressed="' + (agendaSections[orig] ? 'true' : 'false') + '">'
      + '<svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true">'
      + '<path fill="currentColor" d="M2 3h2v2H2V3zm4 0h8v2H6V3zM2 7h2v2H2V7zm4 0h8v2H6V7zm-4 4h2v2H2v-2zm4 0h8v2H6v-2z"/>'
      + '</svg></button>'
      + '</div>';
  }

  function slideThumbSvg(slide, w, h) {
    return '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="xMidYMid slice" width="100%" height="100%">'
      + '<foreignObject width="' + w + '" height="' + h + '">'
      + '<div xmlns="http://www.w3.org/1999/xhtml" style="width:100%;height:100%;">' + (slide.thumbHtml || '') + '</div>'
      + '</foreignObject></svg>';
  }

  /* Wie viele Notizteile dieser Folie noch mitkommen — abgewählte zählen nicht mit. */
  function activeNoteCount(slide) {
    var n = (slide.notes || []).filter(function (x) { return !skipNotes[x.key]; }).length;
    return n + (ownNotes[slide.key] ? 1 : 0);
  }

  function renderPreviewTile(slide, num, w, h) {
    var key = slide.key || '';
    var off = !!skipSlides[key];
    var notes = slide.notes || [];
    var active = activeNoteCount(slide);
    var cls = 'import-preview-grid-item'
      + (off ? ' is-off' : '')
      + (key && key === selectedKey ? ' is-selected' : '');
    return '<div class="' + cls + '" data-slide-key="' + esc(key) + '" data-slide-num="' + num + '">'
      + '<div class="import-preview-grid-thumb" data-act="select">' + slideThumbSvg(slide, w, h) + '</div>'
      + '<div class="import-preview-grid-meta">'
      + '<span class="import-preview-grid-num">' + num + '</span>'
      + (notes.length
          ? '<button type="button" class="import-tile-notes' + (active ? '' : ' is-empty') + '"'
            + ' data-act="notes" title="' + esc(i18n.tileNotes) + '">'
            + '<svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true">'
            + '<path fill="currentColor" d="M3 2h8l2 2v10H3V2zm1.5 3.5h7V7h-7V5.5zm0 3h7V10h-7V8.5zm0 3h4.5V13H4.5v-1.5z"/>'
            + '</svg><span>' + active + '</span></button>'
          : '')
      + '<button type="button" class="import-tile-toggle" data-act="toggle"'
      + ' title="' + esc(off ? i18n.tileOn : i18n.tileOff) + '">' + (off ? '+' : '×') + '</button>'
      + '</div></div>';
  }

  function showPreview(plan) {
    lastPlan = plan;
    // Auswahl nur behalten, wenn es die Folie nach dem Neurechnen noch gibt.
    if (selectedKey && !findSlide(selectedKey)) selectedKey = '';
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
        gridHtml += sectionHeadHtml(title, sec.original_title || '');
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
    if (dialogCols) dialogCols.classList.add('import-dialog-cols--preview');
    updateSummary();
    renderDetail();
    btnConfirm.textContent = i18n.submit;
    if (btnReload) btnReload.hidden = false;
    setPreviewLoading(false);
  }

  var detailCol = document.getElementById('importDetailCol');
  var detailTitle = document.getElementById('importDetailTitle');
  var detailStage = document.getElementById('importDetailStage');
  var detailNotes = document.getElementById('importDetailNotes');
  var detailSlideOff = document.getElementById('importDetailSlideOff');
  var ownNotesBox = document.getElementById('importOwnNotes');

  function keyList(map) {
    return Object.keys(map).filter(function (k) { return map[k]; });
  }

  /** Findet die gewählte Folie im zuletzt geladenen Plan wieder — über den Schlüssel. */
  function findSlide(key) {
    if (!key || !lastPlan) return null;
    var slides = lastPlan.slides || [];
    for (var i = 0; i < slides.length; i++) {
      if (slides[i].key === key) return { slide: slides[i], num: i + 1 };
    }
    return null;
  }

  /* Winziger Markdown-Setzer für die Notiz-Vorschau. Deckt genau das ab, was der
     Importer erzeugt: Überschriften (ausserhalb behandelt), fett und kursiv. Es wird
     ZUERST maskiert, danach werden nur die eigenen Auszeichnungen eingesetzt —
     fremdes HTML aus der Predigt kann so nicht wirksam werden. */
  function mdInline(text) {
    return esc(String(text || ''))
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>');
  }

  function renderDetail() {
    if (!detailCol) return;
    var found = findSlide(selectedKey);
    if (!found) {
      detailCol.hidden = true;
      if (dialogCols) dialogCols.classList.remove('import-dialog-cols--detail');
      return;
    }
    detailCol.hidden = false;
    if (dialogCols) dialogCols.classList.add('import-dialog-cols--detail');
    var slide = found.slide;
    detailTitle.textContent = i18n.detailHeading.replace('{n}', String(found.num));
    detailStage.innerHTML = slideThumbSvg(slide, lastPlan.width || 1920, lastPlan.height || 1080);
    detailStage.classList.toggle('is-off', !!skipSlides[slide.key]);
    detailSlideOff.hidden = !skipSlides[slide.key];

    if (ownNotesBox) ownNotesBox.value = ownNotes[slide.key] || '';
    var notes = slide.notes || [];
    if (!notes.length) {
      detailNotes.innerHTML = '<p class="import-detail-empty">' + esc(i18n.detailNotesNone) + '</p>';
      return;
    }
    detailNotes.innerHTML = notes.map(function (n) {
      var off = !!skipNotes[n.key];
      var m = /^(#{1,6})\s+(.*)$/.exec(n.text);
      var cls = 'import-note-part' + (off ? ' is-off' : '') + (n.list && !m ? ' is-list' : '');
      if (m) cls += ' is-head is-head-' + m[1].length;
      return '<label class="' + cls + '">'
        + '<input type="checkbox" data-note-key="' + esc(n.key) + '"' + (off ? '' : ' checked') + '>'
        + '<span>' + mdInline(m ? m[2] : n.text) + '</span></label>';
    }).join('');
  }

  /** Zählt mit, wie viel nach der Abwahl noch importiert wird. */
  function updateSummary() {
    if (!lastPlan) return;
    var total = (lastPlan.slides || []).length;
    var skipped = (lastPlan.slides || []).filter(function (s) { return skipSlides[s.key]; }).length;
    var text = (lastPlan.title || '') + ' — ' + i18n.previewSlides.replace('{count}', String(total - skipped));
    if (skipped > 0) text += ' (' + i18n.skippedCount.replace('{n}', String(skipped)) + ')';
    previewSummary.textContent = text;
  }

  function redrawTiles() {
    if (!lastPlan) return;
    var w = lastPlan.width || 1920;
    var h = lastPlan.height || 1080;
    [].slice.call(previewSlides.querySelectorAll('[data-slide-key]')).forEach(function (el) {
      var found = findSlide(el.getAttribute('data-slide-key'));
      if (!found) return;
      var num = parseInt(el.getAttribute('data-slide-num'), 10) || found.num;
      var tmp = document.createElement('div');
      tmp.innerHTML = renderPreviewTile(found.slide, num, w, h);
      el.parentNode.replaceChild(tmp.firstChild, el);
    });
    updateSummary();
  }

  /* Umbenennen an Ort: Überschrift wird zum Eingabefeld, Enter oder Verlassen
     übernimmt, Escape bricht ab. Danach die Vorschau neu rechnen lassen, damit der
     neue Titel auch auf der Abschnittsfolie steht und nicht nur darüber. */
  function startRename(head) {
    var orig = head.getAttribute('data-section-orig') || '';
    var span = head.querySelector('.import-section-title');
    if (!orig || !span || head.querySelector('input')) return;
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'import-section-input';
    input.value = span.textContent;
    var done = function (commit) {
      if (!input.parentNode) return;
      var val = input.value.trim();
      input.parentNode.replaceChild(span, input);
      if (!commit) return;
      if (val === '' || val === orig) delete sectionTitles[orig];
      else sectionTitles[orig] = val;
      span.textContent = val === '' ? orig : val;
      schedulePreviewRefresh();
    };
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); done(true); }
      else if (e.key === 'Escape') { e.preventDefault(); done(false); }
    });
    input.addEventListener('blur', function () { done(true); });
    span.parentNode.replaceChild(input, span);
    input.focus();
    input.select();
  }

  if (previewSlides) {
    previewSlides.addEventListener('click', function (ev) {
      var head = ev.target.closest ? ev.target.closest('[data-section-orig]') : null;
      if (head) {
        var hact = ev.target.closest('[data-act]');
        if (hact && hact.getAttribute('data-act') === 'agenda') {
          var o = head.getAttribute('data-section-orig') || '';
          if (o) {
            if (agendaSections[o]) delete agendaSections[o]; else agendaSections[o] = true;
            schedulePreviewRefresh();
          }
          return;
        }
        startRename(head);
        return;
      }
      var item = ev.target.closest ? ev.target.closest('[data-slide-key]') : null;
      if (!item) return;
      var act = ev.target.closest('[data-act]');
      var key = item.getAttribute('data-slide-key');
      if (!key) return;
      if (act && act.getAttribute('data-act') === 'toggle') {
        skipSlides[key] = !skipSlides[key];
        redrawTiles();
        if (selectedKey === key) renderDetail();
        return;
      }
      selectedKey = key;
      redrawTiles();
      renderDetail();
    });
  }

  if (ownNotesBox) {
    ownNotesBox.addEventListener('input', function () {
      if (!selectedKey) return;
      var v = ownNotesBox.value;
      if (v.trim() === '') delete ownNotes[selectedKey]; else ownNotes[selectedKey] = v;
      redrawTiles();
    });
  }

  if (detailNotes) {
    detailNotes.addEventListener('change', function (ev) {
      var box = ev.target;
      if (!box || box.type !== 'checkbox') return;
      var key = box.getAttribute('data-note-key');
      if (!key) return;
      skipNotes[key] = !box.checked;
      renderDetail();
      redrawTiles();
    });
  }

  /* Der Dialog wird von hub-float-dialog gerahmt und soll gleich maximiert aufgehen.
     Eine öffentliche Funktion dafür gibt es dort nicht, nur die Fensterschaltfläche —
     also betätigen wir deren eigenen Weg, statt die Grösse mit eigenem CSS zu erzwingen.
     Der Rahmen bestimmt damit, was „maximiert" heisst, nicht SlideForge. */
  function maximizeFloat() {
    var tries = 0;
    (function attempt() {
      var win = panel.closest ? panel.closest('[data-hub-float]') : null;
      if (win) {
        if (!win.classList.contains('is-maximized')) {
          var btn = win.querySelector('[data-hub-win="max"]');
          if (btn) btn.click();
        }
        return;
      }
      // Das Aufrüsten geschieht verzögert beim ersten Öffnen; kurz nachfassen.
      if (++tries < 20) setTimeout(attempt, 25);
    })();
  }

  function optionControls() {
    return [].slice.call(document.querySelectorAll('[data-import-opt]'));
  }

  /* Die Schalter liegen im Dialog, abgeschickt wird aber das Formular. Darum vor
     jedem Absenden als versteckte Felder logos_opt[...] hineinspiegeln. */
  function syncHiddenFields() {
    hiddenSection.value = sectionSel.value || '';
    if (layoutSel && hiddenLayout) {
      hiddenLayout.value = layoutSel.value || '';
    }
    optionControls().forEach(function (el) {
      var key = el.getAttribute('data-import-opt');
      var name = 'logos_opt[' + key + ']';
      var hidden = form.querySelector('input[type=hidden][name="' + name + '"]');
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        form.appendChild(hidden);
      }
      hidden.value = el.type === 'checkbox' ? (el.checked ? '1' : '0') : (el.value || '');
    });
    setHiddenOpt('skipSlides', keyList(skipSlides).join(','));
    setHiddenOpt('skipNotes', keyList(skipNotes).join(','));
    setHiddenOpt('ownNotes', JSON.stringify(ownNotes));
    setHiddenOpt('sectionTitles', JSON.stringify(sectionTitles));
    setHiddenOpt('agendaSections', JSON.stringify(keyList(agendaSections)));
  }

  function setHiddenOpt(key, value) {
    var name = 'logos_opt[' + key + ']';
    var hidden = form.querySelector('input[type=hidden][name="' + name + '"]');
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      form.appendChild(hidden);
    }
    hidden.value = value;
  }

  /* Vorschau zieht sich selbst nach, sobald eine Einstellung wechselt - aber erst,
     wenn sie schon einmal aufgebaut wurde, sonst lädt sie ungefragt beim Öffnen. */
  var previewTimer = null;
  function schedulePreviewRefresh() {
    if (!previewShown) return;
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(function () {
      previewTimer = null;
      fetchLogosPreview();
    }, 250);
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
      maximizeFloat();
      /* Vorschau gleich beim Öffnen aufbauen, statt auf einen Knopfdruck zu warten —
         bei einer Logos-Datei mit gewähltem Folien-Set ist sie ohnehin das Erste,
         was man sehen will. */
      if (isLogosHtml && hasLayoutSets && layoutSel && layoutSel.value) {
        fetchLogosPreview();
      }
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

  optionControls().forEach(function (el) {
    el.addEventListener('change', schedulePreviewRefresh);
    el.addEventListener('change', saveImportUi);
  });
  if (sectionSel) sectionSel.addEventListener('change', saveImportUi);
  if (layoutSel) layoutSel.addEventListener('change', saveImportUi);

  /* Markenfarben: ein Klick setzt das Farbfeld. `change` wird von Hand ausgelöst,
     weil ein gesetzter Wert allein kein Ereignis auslöst und die Vorschau sonst
     nicht nachzöge. */
  var colorField = document.getElementById('importColorField');
  var colorButton = document.getElementById('importColorButton');
  var colorPanel = document.getElementById('importColorPanel');
  var colorDot = document.getElementById('importColorDot');
  var colorName = document.getElementById('importColorName');
  var colorInput = document.getElementById('import_opt_agendaColor');
  var brandSwatches = document.getElementById('importBrandSwatches');

  function setColorLabel(hex, name) {
    if (colorDot) colorDot.style.background = hex;
    if (colorName) colorName.textContent = name || hex;
  }

  function openColorPanel(open) {
    if (!colorPanel || !colorButton) return;
    colorPanel.hidden = !open;
    colorButton.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  if (colorButton) {
    colorButton.addEventListener('click', function (ev) {
      ev.stopPropagation();
      openColorPanel(colorPanel.hidden);
    });
    // Aussenklick und Escape schliessen — wie bei den Ribbon-Auswahlfeldern.
    document.addEventListener('click', function (ev) {
      if (colorField && !colorField.contains(ev.target)) openColorPanel(false);
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') openColorPanel(false);
    });
  }

  if (brandSwatches) {
    brandSwatches.addEventListener('click', function (ev) {
      var btn = ev.target.closest ? ev.target.closest('[data-hex]') : null;
      if (!btn || !colorInput) return;
      colorInput.value = btn.getAttribute('data-hex');
      setColorLabel(btn.getAttribute('data-hex'), btn.getAttribute('data-name'));
      colorInput.dispatchEvent(new Event('change', { bubbles: true }));
      openColorPanel(false);
    });
  }

  if (colorInput) {
    colorInput.addEventListener('input', function () {
      // Trifft der freie Wert eine Markenfarbe, deren Namen zeigen statt des Hex.
      var treffer = brandSwatches
        ? brandSwatches.querySelector('[data-hex="' + colorInput.value.toLowerCase() + '"]')
        : null;
      setColorLabel(colorInput.value, treffer ? treffer.getAttribute('data-name') : null);
    });
  }
  if (layoutSel) layoutSel.addEventListener('change', schedulePreviewRefresh);

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
