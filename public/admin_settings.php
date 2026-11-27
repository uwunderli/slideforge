<?php
/**
 * Einstellungen der FolienSchmiede, Hub › FolienSchmiede › Modul-Einstellungen (Katalog settingsPath /admin_settings.php).
 * 3.8.3 (Urs 27.11.): ein Einstieg für alles, was es im alten SlideForge gab — Vorlagen, Textvorlagen, Vorlageelemente, Schriftarten,
 * Farben (templates.php) und hier Allgemein, Rechtschreibung, Medien (nur Admin, wie bisher). Ohne Reiter → Folienvorlagen.
 * SMTP entfällt: die FolienSchmiede verschickt keine Mails mehr (Einladungen/Konten macht der Hub).
 * Speichern automatisch (Hub-Standard Einstellungen): Felder senden bei Änderung mit ajax=1, Antwort JSON.
 */
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();
$isAdmin = Auth::isAdmin();

$allowedTabs = ['general', 'spellcheck', 'media'];
$activeTab = (string)($_GET['tab'] ?? '');
if (!$isAdmin || !in_array($activeTab, $allowedTabs, true)) {
    redirect('templates.php?tab=slides');
    exit;
}

$msg = '';
$error = '';
$ajax = ($_POST['ajax'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_general') {
        Config::update([
            'site_title' => trim($_POST['site_title'] ?? '') !== '' ? trim($_POST['site_title']) : APP_NAME,
        ]);
        $msg = t('admin.general_saved');
    }

    /* Logo aus dem FileForge-Dialog (Urs 27.11.: «FF Piker verwenden»), als die Person über AppCall geholt. SVG wird wie
       Medien entschärft (SvgHelper), weil es aus uploads/ auf derselben Domain ausgeliefert wird. */
    if ($action === 'upload_logo') {
        try {
            $ff = FfFile::fetchTemp((string)$me['id'], (string)($_POST['logo_ref'] ?? ''), MAX_LOGO_SIZE);
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? @finfo_file($finfo, $ff['path']) : false;
            if (in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'image/svg'], true)
                && stripos((string)@file_get_contents($ff['path'], false, null, 0, 512), '<svg') !== false) {
                $mime = 'image/svg+xml';
            }
            if (!$mime || !isset($allowed[$mime])) {
                throw new RuntimeException(t('admin.logo_unsupported_type'));
            }
            $filename = 'logo-' . Storage::generateId(6) . '.' . $allowed[$mime];
            $dest = PUBLIC_UPLOADS_PATH . '/' . $filename;
            if ($mime === 'image/svg+xml') {
                $clean = SvgHelper::sanitize((string)@file_get_contents($ff['path']));
                $saved = $clean !== null && @file_put_contents($dest, $clean) !== false;
            } else {
                $saved = @rename($ff['path'], $dest) || (@copy($ff['path'], $dest) && @unlink($ff['path']));
            }
            if (!$saved) {
                throw new RuntimeException(t('admin.logo_save_failed'));
            }
            @chmod($dest, 0644);
            $oldLogo = Config::get('logo', '');
            Config::update(['logo' => $filename]);
            if ($oldLogo && file_exists(PUBLIC_UPLOADS_PATH . '/' . $oldLogo)) {
                @unlink(PUBLIC_UPLOADS_PATH . '/' . $oldLogo);
            }
            $msg = t('admin.logo_updated');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'remove_logo') {
        $oldLogo = Config::get('logo', '');
        if ($oldLogo && file_exists(PUBLIC_UPLOADS_PATH . '/' . $oldLogo)) {
            @unlink(PUBLIC_UPLOADS_PATH . '/' . $oldLogo);
        }
        Config::update(['logo' => '']);
        $msg = t('admin.logo_removed');
    }

    if ($action === 'save_languagetool') {
        $existing = Config::languageTool();
        // Adressfeld: erst richtigstellen (Hub-Standard «Adressfelder», HubUrl), dann prüfen.
        $ltUrl = HubUrl::normalize((string)($_POST['lt_api_url'] ?? ''));
        if ($ltUrl !== '' && !filter_var($ltUrl, FILTER_VALIDATE_URL)) {
            $error = t('admin.languagetool_url_invalid');
        } else {
            Config::update(['languagetool' => [
                'enabled' => isset($_POST['lt_enabled']),
                'api_url' => $ltUrl,
                'api_username' => trim($_POST['lt_api_username'] ?? ''),
                'api_key' => ($_POST['lt_api_key'] ?? '') !== '' ? $_POST['lt_api_key'] : ($existing['api_key'] ?? ''),
            ]]);
            $msg = t('admin.languagetool_saved');
        }
    }

    if ($action === 'save_pixabay') {
        $existing = Config::pixabay();
        Config::update(['pixabay' => [
            'enabled' => isset($_POST['px_enabled']),
            'api_key' => ($_POST['px_api_key'] ?? '') !== '' ? trim($_POST['px_api_key']) : ($existing['api_key'] ?? ''),
        ]]);
        $msg = t('admin.pixabay_saved');
    }

    if ($action === 'save_iconify') {
        Config::update(['iconify' => ['enabled' => isset($_POST['ic_enabled'])]]);
        $msg = t('admin.iconify_saved');
    }

    if ($action === 'save_openclipart') {
        Config::update(['openclipart' => ['enabled' => isset($_POST['oc_enabled'])]]);
        $msg = t('admin.openclipart_saved');
    }

    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $error === '', 'error' => $error, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$cfg = Config::all();
$languagetool = Config::languageTool();
$pixabay = Config::pixabay();
$iconify = Config::iconify();
$openclipart = Config::openclipart();
$keepPh = t('admin.smtp_password_unchanged');

$pageTitle = t('admin.heading');
require __DIR__ . '/includes/header.php';
?>
<div class="container sf-set" style="max-width: 860px;">
  <?php $sfSetActive = $activeTab; $sfSetIsAdmin = $isAdmin; require __DIR__ . '/includes/settings_nav.php'; ?>

  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success" role="status"><?= h($msg) ?></div><?php endif; ?>

  <?php if ($activeTab === 'general'): ?>
    <div class="section-header" style="margin-top:8px;"><h2><?= h(t('admin.general_heading')) ?></h2></div>
    <form method="post" class="sf-set__form" data-sf-autosave>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_general">
      <label class="hub-admin-field"><span><?= h(t('admin.site_title')) ?></span>
        <input type="text" name="site_title" value="<?= h($cfg['site_title'] ?? APP_NAME) ?>" autocomplete="off"></label>
      <span class="sf-set__state" data-sf-save-state aria-live="polite"></span>
    </form>

    <div class="section-header" style="margin-top:20px;"><h2><?= h(t('admin.logo_heading')) ?></h2></div>
    <?php if (Config::logoUrl()): ?>
      <img src="<?= h(Config::logoUrl()) ?>" alt="Logo" class="sf-set__logo">
    <?php else: ?>
      <p class="muted small"><?= h(t('admin.no_logo')) ?></p>
    <?php endif; ?>
    <?php /* Logo: Auswahl im FileForge-Dialog lädt sofort hoch (kein eigener Knopf), danach neu laden für die Vorschau. */ ?>
    <form method="post" class="sf-set__row" data-sf-submit-on-pick>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="upload_logo">
      <?= FfFile::fieldHtml('logo_ref', 'logo_file', ['png', 'jpg', 'jpeg', 'webp', 'svg'], 'Logo') ?>
    </form>
    <?php if (Config::logoUrl()): ?>
      <form method="post" style="margin-top:8px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="remove_logo">
        <button type="submit" class="hub-btn hub-btn--ghost hub-btn--sm"><?= h(t('bg.remove')) ?> Logo</button>
      </form>
    <?php endif; ?>

  <?php elseif ($activeTab === 'spellcheck'): ?>
    <div class="section-header" style="margin-top:8px;"><h2><?= h(t('admin.languagetool_heading')) ?></h2></div>
    <p class="muted small"><?= t('admin.languagetool_intro') ?></p>
    <form method="post" class="sf-set__form" data-sf-autosave>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_languagetool">
      <label class="hub-admin-check"><input type="checkbox" name="lt_enabled" <?= !empty($languagetool['enabled']) ? 'checked' : '' ?>>
        <span><?= h(t('admin.languagetool_enabled')) ?></span></label>
      <label class="hub-admin-field"><span><?= h(t('admin.languagetool_url')) ?></span>
        <input type="url" name="lt_api_url" value="<?= h($languagetool['api_url'] ?? '') ?>" placeholder="https://api.languagetool.org/v2/check" autocomplete="off"></label>
      <div class="sf-set__grid">
        <label class="hub-admin-field"><span><?= h(t('admin.languagetool_username')) ?></span>
          <input type="text" name="lt_api_username" value="<?= h($languagetool['api_username'] ?? '') ?>" placeholder="<?= h(t('admin.languagetool_optional')) ?>" autocomplete="off"></label>
        <label class="hub-admin-field"><span><?= h(t('admin.languagetool_api_key')) ?></span>
          <input type="password" name="lt_api_key" data-sf-secret placeholder="<?= !empty($languagetool['api_key']) ? h($keepPh) : h(t('admin.languagetool_optional')) ?>" data-keep-ph="<?= h($keepPh) ?>" autocomplete="new-password"></label>
      </div>
      <span class="sf-set__state" data-sf-save-state aria-live="polite"></span>
    </form>

  <?php else: /* media */ ?>
    <div class="section-header" style="margin-top:8px;"><h2><?= h(t('admin.pixabay_heading')) ?></h2></div>
    <p class="muted small"><?= t('admin.pixabay_intro') ?></p>
    <form method="post" class="sf-set__form" data-sf-autosave>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_pixabay">
      <label class="hub-admin-check"><input type="checkbox" name="px_enabled" <?= !empty($pixabay['enabled']) ? 'checked' : '' ?>>
        <span><?= h(t('admin.pixabay_enabled')) ?></span></label>
      <label class="hub-admin-field"><span><?= h(t('admin.pixabay_api_key')) ?></span>
        <input type="password" name="px_api_key" data-sf-secret placeholder="<?= !empty($pixabay['api_key']) ? h($keepPh) : 'Pixabay API Key' ?>" data-keep-ph="<?= h($keepPh) ?>" autocomplete="new-password"></label>
      <p class="muted small" style="margin:0;">
        <button type="button" class="hub-btn hub-btn--ghost hub-btn--sm" id="pixabayHelpBtn" aria-haspopup="dialog"><?= h(t('admin.pixabay_help_btn')) ?></button>
        <a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener"><?= h(t('admin.pixabay_docs_link')) ?></a>
      </p>
      <span class="sf-set__state" data-sf-save-state aria-live="polite"></span>
    </form>

    <div class="section-header" style="margin-top:24px;"><h2><?= h(t('admin.iconify_heading')) ?></h2></div>
    <p class="muted small"><?= t('admin.iconify_intro') ?></p>
    <form method="post" class="sf-set__form" data-sf-autosave>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_iconify">
      <label class="hub-admin-check"><input type="checkbox" name="ic_enabled" <?= !empty($iconify['enabled']) ? 'checked' : '' ?>>
        <span><?= h(t('admin.iconify_enabled')) ?></span></label>
      <span class="sf-set__state" data-sf-save-state aria-live="polite"></span>
    </form>

    <div class="section-header" style="margin-top:24px;"><h2><?= h(t('admin.openclipart_heading')) ?></h2></div>
    <p class="muted small"><?= t('admin.openclipart_intro') ?></p>
    <form method="post" class="sf-set__form" data-sf-autosave>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_openclipart">
      <label class="hub-admin-check"><input type="checkbox" name="oc_enabled" <?= !empty($openclipart['enabled']) ? 'checked' : '' ?>>
        <span><?= h(t('admin.openclipart_enabled')) ?></span></label>
      <span class="sf-set__state" data-sf-save-state aria-live="polite"></span>
    </form>

    <div class="modal-backdrop" id="pixabayHelpModal" aria-hidden="true">
      <div class="modal admin-help-modal" role="dialog" aria-modal="true" aria-labelledby="pixabayHelpTitle">
        <div class="sf-dialog-header admin-help-modal-header">
          <h2 id="pixabayHelpTitle" class="sf-dialog-title"><?= h(t('admin.pixabay_help_title')) ?></h2>
          <button type="button" class="sf-dialog-close" id="pixabayHelpClose" aria-label="<?= h(t('common.close')) ?>">×</button>
        </div>
        <div class="admin-help-modal-body">
          <?= str_replace('assets/images/pixabay-api-key-help.png', 'assets/images/pixabay-api-key-help.png?v=' . ASSET_VERSION, t('admin.pixabay_help_body')) ?>
        </div>
        <div class="modal-actions">
          <button type="button" class="hub-btn hub-btn--primary" id="pixabayHelpOk"><?= h(t('common.close')) ?></button>
        </div>
      </div>
    </div>
    <script>
    (function () {
      var modal = document.getElementById('pixabayHelpModal'), btn = document.getElementById('pixabayHelpBtn');
      if (!modal || !btn) return;
      function open() { modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false'); document.getElementById('pixabayHelpClose').focus(); }
      function close() { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); btn.focus(); }
      btn.addEventListener('click', open);
      document.getElementById('pixabayHelpClose').addEventListener('click', close);
      document.getElementById('pixabayHelpOk').addEventListener('click', close);
      if (window.SFModalBackdrop) SFModalBackdrop.bindDismiss(modal, close);
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) close(); });
    })();
    </script>
  <?php endif; ?>
</div>

<script src="<?= h(sf_hub_asset('js/hub-url.js')) ?>"></script>
<script src="assets/js/sf-ff.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/sf-settings.js?v=<?= ASSET_VERSION ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
