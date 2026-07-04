<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $width = max(100, (int)($_POST['width'] ?? DEFAULT_SLIDE_WIDTH));
        $height = max(100, (int)($_POST['height'] ?? DEFAULT_SLIDE_HEIGHT));
        $id = Presentation::create($me['id'], $title, $width, $height);
        redirect('editor.php?id=' . urlencode($id));
    }

    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if (Presentation::checkPermission($id, $me['id']) === 'owner') {
            Presentation::delete($id);
        } else {
            $error = t('dashboard.owner_only_delete');
        }
    }

    if ($action === 'archive' || $action === 'unarchive') {
        $id = $_POST['id'] ?? '';
        if (Presentation::checkPermission($id, $me['id']) === 'owner') {
            Presentation::setArchived($id, $action === 'archive');
        } else {
            $error = t('dashboard.owner_only_archive');
        }
    }
}

[$owned, $shared] = Presentation::listForUser($me['id']);
$ownedActive = array_values(array_filter($owned, fn($p) => empty($p['archived'])));
$ownedArchived = array_values(array_filter($owned, fn($p) => !empty($p['archived'])));

$activeTab = ($_GET['tab'] ?? 'active') === 'archive' ? 'archive' : 'active';

function render_card(array $p, bool $isOwner, string $csrf): void {
    $perm = $isOwner ? 'owner' : ($p['my_permission'] ?? 'view');
    $badgeColor = $perm === 'view' ? 'var(--accent-view)' : 'var(--accent)';
    $badgeLabel = $perm === 'owner' ? t('dashboard.owner') : ($perm === 'edit' ? t('dashboard.permission_edit') : t('dashboard.permission_view'));
    $archived = !empty($p['archived']);
    $slidesData = Presentation::getSlides($p['id']);
    $firstSlide = $slidesData['slides'][0] ?? null;
    $w = max(1, (int)$p['width']);
    $h = max(1, (int)$p['height']);
    ?>
    <div class="slide-card">
      <a href="editor.php?id=<?= urlencode($p['id']) ?>" style="text-decoration:none; color:inherit;">
        <div class="stage" style="--spot-color: <?= $badgeColor ?>;">
          <?php if ($firstSlide): ?>
            <svg class="stage-thumb" viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="xMidYMid slice" width="100%" height="100%">
              <foreignObject width="<?= $w ?>" height="<?= $h ?>">
                <div xmlns="http://www.w3.org/1999/xhtml" style="width:100%; height:100%;">
                  <?= SlideRenderer::renderSlideThumbnailHtml($firstSlide, null) ?>
                </div>
              </foreignObject>
            </svg>
          <?php endif; ?>
          <span class="badge" style="--badge-color: <?= $badgeColor ?>;"><?= h($badgeLabel) ?></span>
          <span class="dims"><?= (int)$p['width'] ?>×<?= (int)$p['height'] ?></span>
        </div>
      </a>
      <div class="meta">
        <div class="title"><?= h($p['title']) ?></div>
        <div class="owner"><?= $isOwner ? h(t('dashboard.created_by_you')) : h(t('dashboard.shared_label')) ?> · <?= h(t('dashboard.modified', ['date' => date('d.m.Y H:i', strtotime($p['updated_at']))])) ?></div>
        <div class="actions">
          <a href="editor.php?id=<?= urlencode($p['id']) ?>" class="button button-sm"><?= $perm === 'view' ? h(t('dashboard.view')) : h(t('dashboard.edit')) ?></a>
          <a href="present.php?id=<?= urlencode($p['id']) ?>" class="icon-action-btn" title="<?= h(t('dashboard.present')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M10 8l6 4-6 4V8z"/></svg>
          </a>
          <a href="export.php?id=<?= urlencode($p['id']) ?>" class="icon-action-btn" title="<?= h(t('dashboard.export')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 19h16"/></svg>
          </a>
          <?php if ($isOwner): ?>
            <a href="presentation_share.php?id=<?= urlencode($p['id']) ?>" class="icon-action-btn" title="<?= h(t('dashboard.share')) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.7l7.6-4.4M8.2 13.3l7.6 4.4"/></svg>
            </a>
            <form method="post" class="inline-form">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="<?= $archived ? 'unarchive' : 'archive' ?>">
              <input type="hidden" name="id" value="<?= h($p['id']) ?>">
              <button type="submit" class="icon-action-btn" title="<?= $archived ? h(t('dashboard.unarchive')) : h(t('dashboard.archive')) ?>">
                <?php if ($archived): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9"/><path d="M9 13h6"/></svg>
                <?php else: ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9"/><path d="M10 13.5l2 2 2-2M12 15.5v-4"/></svg>
                <?php endif; ?>
              </button>
            </form>
            <form method="post" class="inline-form" onsubmit="return confirm('<?= h(t('dashboard.confirm_delete')) ?>');">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= h($p['id']) ?>">
              <button type="submit" class="icon-action-btn icon-action-danger" title="<?= h(t('dashboard.delete')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/></svg>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
}

$pageTitle = t('dashboard.own_presentations');
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="section-header" style="margin-top:0;">
    <h1 style="font-size:1.6rem; text-transform:none; letter-spacing:0; color:var(--text);"><?= h(t('dashboard.heading')) ?></h1>
    <div style="display:flex; gap:10px;">
      <a href="import.php" class="button button-ghost"><?= h(t('dashboard.import')) ?></a>
      <button class="button" onclick="document.getElementById('createModal').classList.add('open')"><?= h(t('dashboard.new_presentation')) ?></button>
    </div>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <div class="section-header">
    <h2><?= h(t('dashboard.own_presentations')) ?></h2>
  </div>
  <div class="page-tabs">
    <a href="?tab=active" class="page-tab-btn<?= $activeTab === 'active' ? ' active' : '' ?>"><?= h(t('dashboard.tab_active')) ?></a>
    <a href="?tab=archive" class="page-tab-btn<?= $activeTab === 'archive' ? ' active' : '' ?>"><?= h(t('dashboard.tab_archive')) ?><?= count($ownedArchived) ? ' (' . count($ownedArchived) . ')' : '' ?></a>
  </div>

  <?php if ($activeTab === 'active'): ?>
    <?php if (empty($ownedActive)): ?>
      <div class="empty-state" style="margin-top:20px;"><?= h(t('dashboard.empty_active')) ?></div>
    <?php else: ?>
      <div class="grid" style="margin-top:20px;">
        <?php foreach ($ownedActive as $p) render_card($p, true, csrf_token()); ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <?php if (empty($ownedArchived)): ?>
      <div class="empty-state" style="margin-top:20px;"><?= h(t('dashboard.empty_archive')) ?></div>
    <?php else: ?>
      <div class="grid" style="margin-top:20px;">
        <?php foreach ($ownedArchived as $p) render_card($p, true, csrf_token()); ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="section-header">
    <h2><?= h(t('dashboard.shared_with_you')) ?></h2>
  </div>
  <?php if (empty($shared)): ?>
    <div class="empty-state"><?= h(t('dashboard.empty_shared')) ?></div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($shared as $p) render_card($p, false, csrf_token()); ?>
    </div>
  <?php endif; ?>
</div>

<div class="modal-backdrop" id="createModal">
  <div class="modal">
    <h2 style="font-size:1.2rem; text-transform:none;"><?= h(t('modal.new_presentation_title')) ?></h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create">

      <label for="title"><?= h(t('modal.title_label')) ?></label>
      <input type="text" id="title" name="title" placeholder="<?= h(t('modal.title_placeholder')) ?>" autofocus>

      <div class="row">
        <div>
          <label for="width"><?= h(t('modal.width_label')) ?></label>
          <input type="number" id="width" name="width" value="<?= DEFAULT_SLIDE_WIDTH ?>">
        </div>
        <div>
          <label for="height"><?= h(t('modal.height_label')) ?></label>
          <input type="number" id="height" name="height" value="<?= DEFAULT_SLIDE_HEIGHT ?>">
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="button button-ghost" onclick="document.getElementById('createModal').classList.remove('open')"><?= h(t('modal.cancel')) ?></button>
        <button type="submit" class="button"><?= h(t('modal.create')) ?></button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
