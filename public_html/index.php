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
            Dashboard::removePresentation($me['id'], $id);
        } else {
            $error = t('dashboard.owner_only_delete');
        }
    }

    if ($action === 'archive' || $action === 'unarchive') {
        $id = $_POST['id'] ?? '';
        $perm = Presentation::checkPermission($id, $me['id']);
        if ($perm === 'owner') {
            $archived = $action === 'archive';
            Presentation::setArchived($id, $archived);
            Dashboard::syncArchiveState($me['id'], $id, $archived);
        } elseif ($perm !== null) {
            Dashboard::setSharedArchived($me['id'], $id, $action === 'archive');
        } else {
            $error = t('dashboard.owner_only_archive');
        }
    }
}

[$owned, $shared] = Presentation::listForUser($me['id']);
$activeTab = ($_GET['tab'] ?? 'active') === 'archive' ? 'archive' : 'active';
$archivedTab = $activeTab === 'archive';
$dashboardSections = Dashboard::buildView($me['id'], $owned, $shared, $archivedTab);
$dashboardViewMode = Dashboard::tabViewMode($me['id'], $archivedTab);
$ownedActive = array_values(array_filter($owned, fn($p) => empty($p['archived'])));
$ownedArchived = array_values(array_filter($owned, fn($p) => !empty($p['archived'])));
$sharedActive = array_values(array_filter($shared, fn($p) => !Dashboard::isSharedArchived($me['id'], $p['id'])));
$sharedArchived = array_values(array_filter($shared, fn($p) => Dashboard::isSharedArchived($me['id'], $p['id'])));
$archiveTabCount = count($ownedArchived) + count($sharedArchived);
$hasContentInTab = array_sum(array_map(fn($s) => count($s['presentations'] ?? []), $dashboardSections)) > 0;

function render_card(array $p, bool $isOwner, string $csrf): void {
    $perm = $isOwner ? 'owner' : ($p['my_permission'] ?? 'view');
    $badgeColor = $perm === 'view' ? 'var(--accent-view)' : 'var(--accent)';
    $badgeLabel = $perm === 'owner' ? t('dashboard.owner') : ($perm === 'edit' ? t('dashboard.permission_edit') : t('dashboard.permission_view'));
    $archived = !empty($p['_dashboard_archived']) || !empty($p['archived']);
    $slidesData = Presentation::getSlides($p['id']);
    $firstSlide = $slidesData['slides'][0] ?? null;
    $w = max(1, (int)$p['width']);
    $h = max(1, (int)$p['height']);
    ?>
    <div class="slide-card dashboard-presentation-card" draggable="true" data-presentation-id="<?= h($p['id']) ?>">
      <a href="editor.php?id=<?= urlencode($p['id']) ?>" class="dashboard-card-link" style="text-decoration:none; color:inherit;">
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
          <?php endif; ?>
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
          <?php if ($isOwner): ?>
            <form method="post" class="inline-form" data-sf-confirm="<?= h(t('dashboard.confirm_delete')) ?>" data-sf-confirm-danger>
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

function render_mobile_item(array $p, bool $isOwner): void {
    $perm = $isOwner ? 'owner' : ($p['my_permission'] ?? 'view');
    if (!in_array($perm, ['owner', 'edit', 'view'], true)) {
        return;
    }
    ?>
    <div class="mobile-pres-item">
      <div class="mobile-pres-info">
        <div class="mobile-pres-title"><?= h($p['title']) ?></div>
        <div class="mobile-pres-meta"><?= h(date('d.m.Y H:i', strtotime($p['updated_at']))) ?></div>
      </div>
      <a href="present_remote.php?id=<?= urlencode($p['id']) ?>" class="button"><?= h(t('mobile.remote_control')) ?></a>
    </div>
    <?php
}

$pageTitle = t('dashboard.own_presentations');
require __DIR__ . '/includes/header.php';
?>
<div class="container dashboard-mobile">
  <div class="mobile-dashboard-header">
    <h1><?= h(t('dashboard.heading')) ?></h1>
  </div>

  <div class="mobile-section-title"><?= h(t('dashboard.own_presentations')) ?></div>
  <?php if (empty($ownedActive)): ?>
    <div class="mobile-empty"><?= h(t('dashboard.empty_active')) ?></div>
  <?php else: ?>
    <div class="mobile-pres-list">
      <?php foreach ($ownedActive as $p) render_mobile_item($p, true); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($shared)): ?>
    <div class="mobile-section-title"><?= h(t('dashboard.shared_with_you')) ?></div>
    <div class="mobile-pres-list">
      <?php foreach ($shared as $p) render_mobile_item($p, false); ?>
    </div>
  <?php endif; ?>

  <p class="mobile-empty" style="margin-top:2rem; font-size:0.85rem;"><?= h(t('mobile.dashboard_hint')) ?></p>
</div>

<div class="container dashboard-desktop">
  <div class="section-header" style="margin-top:0;">
    <h1 style="font-size:1.6rem; text-transform:none; letter-spacing:0; color:var(--text);"><?= h(t('dashboard.heading')) ?></h1>
    <div style="display:flex; gap:10px;">
      <a href="import.php" class="button button-ghost"><?= h(t('dashboard.import')) ?></a>
      <button class="button" onclick="document.getElementById('createModal').classList.add('open')"><?= h(t('dashboard.new_presentation')) ?></button>
    </div>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <div class="section-header" style="margin-bottom:0;">
    <h2><?= h(t('dashboard.own_presentations')) ?></h2>
  </div>

  <div class="dashboard-tab-bar">
    <div class="page-tabs dashboard-page-tabs">
      <a href="?tab=active" class="page-tab-btn<?= $activeTab === 'active' ? ' active' : '' ?>"><?= h(t('dashboard.tab_active')) ?></a>
      <a href="?tab=archive" class="page-tab-btn<?= $activeTab === 'archive' ? ' active' : '' ?>"><?= h(t('dashboard.tab_archive')) ?><?= $archiveTabCount ? ' (' . $archiveTabCount . ')' : '' ?></a>
    </div>
    <div class="dashboard-view-toggle" role="group" aria-label="<?= h(t('dashboard.view_mode_label')) ?>">
      <button type="button" class="icon-action-btn dashboard-view-btn<?= $dashboardViewMode === 'grid' ? ' active' : '' ?>" data-view="grid" title="<?= h(t('dashboard.view_grid')) ?>" aria-label="<?= h(t('dashboard.view_grid')) ?>" aria-pressed="<?= $dashboardViewMode === 'grid' ? 'true' : 'false' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg>
      </button>
      <button type="button" class="icon-action-btn dashboard-view-btn<?= $dashboardViewMode === 'list' ? ' active' : '' ?>" data-view="list" title="<?= h(t('dashboard.view_list')) ?>" aria-label="<?= h(t('dashboard.view_list')) ?>" aria-pressed="<?= $dashboardViewMode === 'list' ? 'true' : 'false' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
      </button>
    </div>
  </div>

  <div class="dashboard-sections-toolbar">
    <button type="button" class="button button-ghost button-sm" id="dashboardAddSectionBtn">+ <?= h(t('dashboard.add_section')) ?></button>
  </div>

  <div
    class="dashboard-sections dashboard-view-<?= h($dashboardViewMode) ?>"
    id="dashboardSections"
    data-csrf="<?= h(csrf_token()) ?>"
    data-tab="<?= h($activeTab) ?>"
    data-view-mode="<?= h($dashboardViewMode) ?>"
  >
    <?php if (!$hasContentInTab && $activeTab === 'active' && empty($ownedActive) && empty($sharedActive)): ?>
      <div class="empty-state"><?= h(t('dashboard.empty_active')) ?></div>
    <?php elseif (!$hasContentInTab && $activeTab === 'archive' && empty($ownedArchived) && empty($sharedArchived)): ?>
      <div class="empty-state"><?= h(t('dashboard.empty_archive')) ?></div>
    <?php else: ?>
    <?php foreach ($dashboardSections as $section):
      $sectionTitle = Dashboard::sectionTitle($section);
      $isDefault = !empty($section['is_default']);
      $isSharedInbox = !empty($section['is_shared_inbox']);
      $collapsed = !empty($section['collapsed']);
      $count = count($section['presentations'] ?? []);
      $archivedInSection = (int)($section['archived_count'] ?? 0);
    ?>
    <section
      class="dashboard-section<?= $collapsed ? ' is-collapsed' : '' ?><?= $isSharedInbox ? ' is-shared-inbox' : '' ?>"
      data-section-id="<?= h($section['id']) ?>"
      data-default="<?= $isDefault ? '1' : '0' ?>"
      data-shared-inbox="<?= $isSharedInbox ? '1' : '0' ?>"
    >
      <header class="dashboard-section-header">
        <button type="button" class="dashboard-section-collapse" title="<?= h(t('dashboard.toggle_section')) ?>" aria-expanded="<?= $collapsed ? 'false' : 'true' ?>">
          <span class="dashboard-section-chevron" aria-hidden="true"></span>
        </button>
        <?php if (!$isSharedInbox): ?>
        <button type="button" class="dashboard-section-drag" draggable="true" title="<?= h(t('dashboard.drag_section')) ?>" aria-label="<?= h(t('dashboard.drag_section')) ?>">≡</button>
        <?php endif; ?>
        <h3 class="dashboard-section-title"><?= h($sectionTitle) ?></h3>
        <span class="dashboard-section-count">(<?= (int)$count ?>)</span>
        <?php if (!$archivedTab && $archivedInSection > 0): ?>
        <span class="dashboard-section-archive-count" title="<?= h(t('dashboard.section_archived_count', ['count' => $archivedInSection])) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9"/><path d="M10 13.5l2 2 2-2M12 15.5v-4"/></svg>
          <span class="dashboard-section-archive-count-num">(<?= $archivedInSection ?>)</span>
        </span>
        <?php endif; ?>
        <?php if (!$isSharedInbox): ?>
        <div class="dashboard-section-actions">
          <button type="button" class="button button-ghost button-sm dashboard-section-rename" title="<?= h(t('dashboard.rename_section')) ?>">✎</button>
          <?php if (!$isDefault): ?>
            <button type="button" class="button button-ghost button-sm dashboard-section-delete" title="<?= h(t('dashboard.delete_section')) ?>">🗑</button>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </header>
      <div class="dashboard-section-body">
        <div class="dashboard-section-items">
          <?php if ($count === 0): ?>
            <div class="dashboard-section-empty"><?= h(t('dashboard.section_empty')) ?></div>
          <?php endif; ?>
          <?php foreach ($section['presentations'] as $p) render_card($p, !empty($p['_dashboard_owned']), csrf_token()); ?>
        </div>
      </div>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="modal-backdrop" id="createModal">
  <div class="modal">
    <h2 class="sf-dialog-title modal-dialog-title"><?= h(t('modal.new_presentation_title')) ?></h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-dialog-body">
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
      </div>
      <div class="modal-actions">
        <button type="button" class="button button-ghost" onclick="document.getElementById('createModal').classList.remove('open')"><?= h(t('modal.cancel')) ?></button>
        <button type="submit" class="button"><?= h(t('modal.create')) ?></button>
      </div>
    </form>
  </div>
</div>

<script>
window.SF_DASHBOARD_I18N = <?= json_encode([
    'addSectionPrompt' => t('dashboard.add_section_prompt'),
    'renameSectionPrompt' => t('dashboard.rename_section_prompt'),
    'confirmDeleteSection' => t('dashboard.confirm_delete_section'),
    'errorGeneric' => t('dashboard.error_generic'),
    'sectionEmpty' => t('dashboard.section_empty'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/dashboard.js?v=<?= ASSET_VERSION ?>"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
