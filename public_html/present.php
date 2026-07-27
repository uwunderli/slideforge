<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$id = $_GET['id'] ?? '';
$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    http_response_code(403);
    die('Du hast keinen Zugriff auf diese Präsentation.');
}
$canBroadcast = in_array($perm, ['owner', 'edit'], true);
$meta = Presentation::getMeta($id);
$slidesData = Presentation::getSlides($id);
$slides = $slidesData['slides'] ?? [];
$slideCount = count($slides);
$slideDisabled = Presentation::slidePresentDisabledFlags($slidesData);

// Startfolie: expliziter Link aus dem Editor, sonst letzte Live-Position, sonst 0.
$startSlide = 0;
if (isset($_GET['slide'])) {
    $startSlide = max(0, min($slideCount - 1, (int)$_GET['slide']));
} else {
    $live = Presentation::getLivePosition($id, 'present');
    if ($live && isset($live['index'])) {
        $startSlide = max(0, min($slideCount - 1, (int)$live['index']));
    }
}
$startSlide = Presentation::normalizePresentStartIndex($slideDisabled, $startSlide);
$nextPreviewIdx = Presentation::nextPresentEnabledIndex($slideDisabled, $startSlide)
    ?? $startSlide;

// Notizen serverseitig zu HTML vorrendern (Markdown), damit present.js keinen
// eigenen Markdown-Parser braucht.
$notesHtml = array_map(fn($s) => Markdown::render($s['notes'] ?? ''), $slides);
$swatches = array_map(function ($s) {
    $bg = $s['background'] ?? null;
    if (!$bg) return '#222';
    if ($bg['type'] === 'color') return $bg['value'] ?? '#222';
    if ($bg['type'] === 'gradient') return $bg['value'] ?? '#222';
    return '#222';
}, $slides);

$acl = Presentation::getAcl($id);
$publicUrl = '';
if (!empty($acl['public']['enabled']) && !empty($acl['public']['token'])) {
    $scheme = current_scheme();
    $host = http_request_host();
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $publicUrl = "$scheme://$host$base/view.php?token=" . $acl['public']['token'];
}

$remoteUrl = '';
$scheme = current_scheme();
$host = http_request_host();
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$remoteUrl = "$scheme://$host$base/present_remote.php?id=" . urlencode($id);
$remoteQrSrc = ($canBroadcast && $remoteUrl !== '')
    ? ('qr.php?data=' . urlencode($remoteUrl))
    : '';

$pageTitle = 'Präsentationsmodus · ' . $meta['title'];
$headerPresentationTitle = $meta['title'];
$headerPresentationContext = 'present';
$presentPanels = Auth::getPresentPanels($me);
$presentLayout = Auth::getPresentLayout($me);
$hasLayoutClock = array_key_exists('clockOrder', $me['present_layout'] ?? []);
$hasLayoutStops = array_key_exists('timebarStops', $me['present_layout'] ?? []);
$clockOrder = Presentation::normalizeClockOrder(
    $hasLayoutClock ? ($presentLayout['clockOrder'] ?? null) : ($meta['clock_order'] ?? null)
);
$timebarStops = Presentation::normalizeTimebarStops(
    $hasLayoutStops ? ($presentLayout['timebarStops'] ?? null) : ($meta['timebar_stops'] ?? null)
);
$initialClock = $clockOrder[0] ?? 'analog';
$showTimebar = !empty($presentLayout['showTimebar']);
$presentPanelIcons = [
    'next' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="14" height="14" rx="1.5"/><path d="M17 10l4 2-4 2v-4z"/></svg>',
    'notes' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h16"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="12" x2="12" y2="7"/><line x1="12" y1="12" x2="16" y2="12"/></svg>',
    'timer' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5"/><path d="M9 2h6"/></svg>',
    'slides' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 6l-4 6 4 6"/><path d="M17 6l4 6-4 6"/><line x1="12" y1="4" x2="12" y2="20"/></svg>',
];
$presentPanelLabels = [
    'next' => t('present.next_slide'),
    'notes' => t('present.notes'),
    'clock' => t('present.clock_section'),
    'timer' => t('present.timer_section'),
    'slides' => t('present.slide_control'),
];
$bodyClass = 'present-mode';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DSEG7+Classic:wght@700&display=swap">
<div class="present-layout<?= $showTimebar ? '' : ' present-timebar-hidden' ?>" style="--present-slide-aspect: <?= (int)$meta['width'] ?> / <?= (int)$meta['height'] ?>;">
<div class="present-topbar present-main-grid" id="presentTopbar">
  <div class="present-topbar-main">
    <a href="editor.php?id=<?= urlencode($id) ?>&amp;slide=<?= (int)$startSlide ?>" class="back-link" id="editorBackLink">&larr; <?= h(t('present.back_to_editor')) ?></a>
    <span class="present-remote-badge" id="presentRemoteBadge" hidden>
      <span class="present-remote-badge-dot"></span>
      <?= h(t('remote.mobile_connected')) ?>
    </span>
    <?php if ($canBroadcast): ?>
    <span class="present-control-status" id="presentControlStatus" hidden>
      <span class="present-control-status-dot" id="presentControlDot" aria-hidden="true"></span>
      <?= h(t('present.control_status')) ?>
    </span>
    <?php endif; ?>
    <?php if ($canBroadcast && $remoteUrl): ?>
    <button type="button" class="button button-ghost button-sm" id="copyRemoteLinkBtn" title="<?= h(t('remote.copy_url')) ?>"><?= h(t('remote.copy_url')) ?></button>
    <input type="hidden" id="presentRemoteLinkInput" value="<?= h($remoteUrl) ?>">
    <?php endif; ?>
    <div class="present-topbar-menus">
      <?php if ($canBroadcast): ?>
      <div class="present-config-wrap" data-present-menu-wrap data-present-config-menu>
        <button type="button" class="present-config-btn" id="presentConfigBtn" data-menu-btn aria-expanded="false" aria-haspopup="true" aria-controls="presentConfigPanel">
          <svg class="present-config-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 22h8"/><path d="M12 18v4"/><path d="M7 15h10"/></svg>
          <?= h(t('present.section_present')) ?>
          <span class="present-config-chevron" aria-hidden="true">▾</span>
        </button>
        <div class="present-config-panel" id="presentConfigPanel" data-menu-panel hidden role="menu" aria-labelledby="presentConfigBtn">
          <div class="present-config-section">
            <div class="present-config-section-title"><?= h(t('present.menu_audience')) ?></div>
            <label class="present-config-check">
              <input type="checkbox" id="showProgressToggle" <?= ($meta['show_progress'] ?? true) ? 'checked' : '' ?>>
              <span><?= h(t('present.progress_bar')) ?></span>
            </label>
            <label class="present-config-check">
              <input type="checkbox" id="showControlsToggle" <?= ($meta['show_controls'] ?? true) ? 'checked' : '' ?>>
              <span><?= h(t('present.controls_toggle')) ?></span>
            </label>
            <?php if ($perm === 'owner'): ?>
            <div class="present-config-row">
              <label class="present-config-check present-config-check-grow">
                <input type="checkbox" id="publicLinkToggle" <?= !empty($acl['public']['enabled']) ? 'checked' : '' ?>>
                <span><?= h(t('present.public_link')) ?></span>
              </label>
              <button type="button" class="button button-ghost button-sm" id="copyPublicLinkBtn" <?= (!empty($acl['public']['enabled']) && $publicUrl) ? '' : 'disabled' ?>><?= h(t('present.copy_link')) ?></button>
            </div>
            <input type="hidden" id="presentPublicLinkInput" value="<?= h($publicUrl) ?>">
            <?php endif; ?>
          </div>
          <?php if ($canBroadcast && $remoteUrl): ?>
          <div class="present-config-section">
            <div class="present-config-section-title"><?= h(t('remote.qr_section')) ?></div>
            <p class="props-video-note present-panel-settings-hint"><?= h(t('remote.qr_hint')) ?></p>
            <div class="present-remote-qr-box">
              <img id="presentRemoteQr" src="<?= h($remoteQrSrc) ?>" width="220" height="220" alt="<?= h(t('remote.qr_section')) ?>" decoding="async">
            </div>
            <div class="present-config-row" style="margin-top:8px;">
              <button type="button" class="button button-ghost button-sm" id="copyRemoteLinkPanelBtn"><?= h(t('remote.copy_url')) ?></button>
            </div>
          </div>
          <?php endif; ?>
          <div class="present-config-section">
            <div class="present-config-section-title"><?= h(t('present.local_present')) ?></div>
            <label class="present-field-label" for="presentScreenSelect"><?= h(t('present.screen_label')) ?></label>
            <select id="presentScreenSelect" class="present-screen-select"></select>
            <p class="present-screen-hint" id="presentScreenHint"></p>
            <button type="button" class="button button-primary present-local-btn" id="presentLocalBtn"><?= h(t('present.local_start')) ?></button>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div class="present-config-wrap" data-present-menu-wrap data-present-settings-menu>
        <button type="button" class="present-config-btn" id="presentSettingsBtn" data-menu-btn aria-expanded="false" aria-haspopup="true">
          <svg class="present-config-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><circle cx="9" cy="6" r="2"/><line x1="4" y1="12" x2="20" y2="12"/><circle cx="15" cy="12" r="2"/><line x1="4" y1="18" x2="20" y2="18"/><circle cx="7" cy="18" r="2"/></svg>
          <?= h(t('editor.settings_menu')) ?>
          <span class="present-config-chevron" aria-hidden="true">▾</span>
        </button>
        <div class="present-config-panel editor-settings-submenu" data-settings-submenu hidden>
          <button type="button" class="dropdown-menu-item" data-settings-open="panels">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="18" rx="1"/></svg>
            <?= h(t('present.panel_settings_title')) ?>
          </button>
          <button type="button" class="dropdown-menu-item" data-settings-open="timebar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="18" y="3" width="3" height="18" rx="1"/><path d="M6 8h8"/><path d="M6 12h10"/><path d="M6 16h6"/></svg>
            <?= h(t('present.settings_submenu_timebar')) ?>
          </button>
          <button type="button" class="dropdown-menu-item" data-settings-open="clock">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="12" x2="12" y2="7"/><line x1="12" y1="12" x2="16" y2="12"/></svg>
            <?= h(t('present.clock_section')) ?>
          </button>
          <button type="button" class="dropdown-menu-item" data-settings-open="laser">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3" fill="currentColor" stroke="none"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M16.9 16.9l2.1 2.1M19.1 4.9l-2.1 2.1M7.1 16.9l-2.1 2.1"/></svg>
            <?= h(t('present.settings_submenu_laser')) ?>
          </button>
          <button type="button" class="dropdown-menu-item" data-settings-open="display">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 10h10"/><path d="M7 13h6" opacity="0.45"/></svg>
            <?= h(t('present.settings_submenu_display')) ?>
          </button>
        </div>
        <div class="present-config-panel editor-settings-panel" data-settings-panel="panels" hidden>
          <div class="present-config-section">
            <p class="props-video-note present-panel-settings-hint"><?= h(t('present.panel_settings_hint')) ?></p>
            <p class="props-video-note present-panel-settings-hint"><?= h(t('present.layout_user_hint')) ?></p>
            <div class="present-panel-settings-list" id="presentPanelSettingsList">
              <?php foreach ($presentPanels as $panel):
                $pid = $panel['id'];
                if (!isset($presentPanelIcons[$pid])) continue;
              ?>
              <div class="present-panel-settings-item" draggable="true" data-id="<?= h($pid) ?>">
                <span class="present-panel-settings-handle" aria-hidden="true">⋮⋮</span>
                <span class="present-panel-settings-icon"><?= $presentPanelIcons[$pid] ?></span>
                <span class="present-panel-settings-label"><?= h($presentPanelLabels[$pid]) ?></span>
                <label class="present-panel-settings-check" title="<?= h(t('present.panel_visible')) ?>">
                  <input type="checkbox" <?= !empty($panel['visible']) ? 'checked' : '' ?>>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="present-config-panel-footer">
            <button type="button" class="button button-ghost button-sm" data-settings-back><?= h(t('editor.settings_back')) ?></button>
          </div>
        </div>
        <div class="present-config-panel editor-settings-panel editor-settings-panel-wide" data-settings-panel="timebar" hidden>
          <div class="present-config-section">
            <label class="present-config-check present-config-check-block">
              <input type="checkbox" id="presentShowTimebarToggle" <?= $showTimebar ? 'checked' : '' ?>>
              <span><?= h(t('present.show_timebar')) ?></span>
            </label>
            <p class="props-video-note present-panel-settings-hint"><?= h(t('present.show_timebar_hint')) ?></p>
          </div>
          <div class="present-config-section">
            <div class="present-config-section-title"><?= h(t('present.timebar_stops_label')) ?></div>
            <p class="props-video-note present-panel-settings-hint"><?= h(t('present.timebar_stops_hint')) ?></p>
            <div id="presentTimebarStopsList"></div>
            <button type="button" class="button button-ghost button-sm" id="presentAddTimebarStopBtn"><?= h(t('present.add_timebar_stop')) ?></button>
          </div>
          <div class="present-config-panel-footer">
            <button type="button" class="button button-ghost button-sm" data-settings-back><?= h(t('editor.settings_back')) ?></button>
          </div>
        </div>
        <div class="present-config-panel editor-settings-panel" data-settings-panel="clock" hidden>
          <div class="present-config-section">
            <p class="props-video-note present-panel-settings-hint"><?= h(t('present.clock_order_hint')) ?></p>
            <div class="clock-order-list" id="presentClockOrderList"></div>
          </div>
          <div class="present-config-panel-footer">
            <button type="button" class="button button-ghost button-sm" data-settings-back><?= h(t('editor.settings_back')) ?></button>
          </div>
        </div>
        <div class="present-config-panel editor-settings-panel" data-settings-panel="laser" hidden>
          <div class="present-config-section">
            <label class="present-config-check present-config-check-block" style="margin-bottom:14px;">
              <input type="checkbox" id="presentLaserEnabled" <?= (($presentLayout['laserPointerEnabled'] ?? true) !== false) ? 'checked' : '' ?>>
              <span><?= h(t('present.laser_enabled')) ?></span>
            </label>
            <div id="presentLaserOptions">
            <p class="props-video-note present-panel-settings-hint"><?= h(t('present.laser_hint')) ?></p>
            <div class="present-laser-preview" id="presentLaserPreview" aria-hidden="true">
              <span class="present-laser-preview-dot" id="presentLaserPreviewDot"></span>
            </div>
            <label for="presentLaserColor"><?= h(t('present.laser_color')) ?></label>
            <input type="color" id="presentLaserColor" value="<?= h($presentLayout['laserPointerColor'] ?? '#ff0000') ?>">
            <div class="brand-palette mini" id="presentLaserPalette"></div>
            <label for="presentLaserSize" style="margin-top:12px;"><?= h(t('present.laser_size')) ?> (<span id="presentLaserSizeVal"><?= (int)($presentLayout['laserPointerSize'] ?? 24) ?></span> px)</label>
            <input type="range" id="presentLaserSize" min="8" max="64" step="1" value="<?= (int)($presentLayout['laserPointerSize'] ?? 24) ?>">
            <label style="display:flex;align-items:center;gap:8px;margin-top:14px;cursor:pointer;">
              <input type="checkbox" id="presentLaserTrail" style="width:auto;" <?= !empty($presentLayout['laserPointerTrail']) ? 'checked' : '' ?>>
              <?= h(t('present.laser_trail')) ?>
            </label>
            <p class="props-video-note" style="margin-top:6px;"><?= h(t('present.laser_trail_hint')) ?></p>
            </div>
          </div>
          <div class="present-config-panel-footer">
            <button type="button" class="button button-ghost button-sm" data-settings-back><?= h(t('editor.settings_back')) ?></button>
          </div>
        </div>
        <div class="present-config-panel editor-settings-panel" data-settings-panel="display" hidden>
          <div class="present-config-section">
            <label class="present-config-check present-config-check-block">
              <input type="checkbox" id="presentShowSlideGhostToggle" <?= !empty($presentLayout['showSlideGhost']) ? 'checked' : '' ?>>
              <span><?= h(t('present.show_slide_ghost')) ?></span>
            </label>
            <p class="props-video-note present-panel-settings-hint"><?= h(t('present.show_slide_ghost_hint')) ?></p>
            <label for="presentSlideGhostOpacity" style="margin-top:12px;"><?= h(t('present.slide_ghost_opacity')) ?> (<span id="presentSlideGhostOpacityVal"><?= (int)($presentLayout['slideGhostOpacity'] ?? 25) ?></span> %)</label>
            <input type="range" id="presentSlideGhostOpacity" min="5" max="80" step="1" value="<?= (int)($presentLayout['slideGhostOpacity'] ?? 25) ?>" <?= empty($presentLayout['showSlideGhost']) ? 'disabled' : '' ?>>
          </div>
          <div class="present-config-panel-footer">
            <button type="button" class="button button-ghost button-sm" data-settings-back><?= h(t('editor.settings_back')) ?></button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="present-topbar-splitter present-layout-splitter" data-split="main-side" aria-hidden="true"></div>
  <div class="present-topbar-spacer present-topbar-side" aria-hidden="true"></div>
  <div class="present-topbar-splitter present-layout-splitter" data-split="side-time" aria-hidden="true"<?= $showTimebar ? '' : ' hidden' ?>></div>
  <div class="present-topbar-time">
    <span id="presentStatus" class="save-status"><?= $canBroadcast ? h(t('present.live')) : h(t('present.view_only')) ?></span>
  </div>
</div>

<div class="present-main-row present-main-grid" id="presentMainRow">
  <div class="present-current-panel">
    <iframe id="mainFrame" src="present_frame.php?id=<?= urlencode($id) ?>&mode=main&amp;start=<?= (int)$startSlide ?>" title="<?= h(t('present.current_slide')) ?>"></iframe>
    <div class="present-notes-overlay" id="presentNotesOverlay" hidden>
      <div class="present-notes-overlay-inner" id="notesPanel" role="region" aria-label="<?= h(t('present.notes')) ?>"></div>
    </div>
  </div>
  <div class="present-col-splitter present-layout-splitter" data-split="main-side" role="separator" aria-orientation="vertical" title="<?= h(t('present.resize_columns')) ?>"></div>
  <div class="present-side-panel">
    <div class="props-accordion present-side-accordions" data-accordion-name="presentSide">
      <div class="props-accordion-group open" data-acc="next">
        <button type="button" class="props-accordion-header">
          <span class="present-panel-drag-handle" aria-hidden="true" title="<?= h(t('editor.reorder_slide')) ?>">⋮⋮</span>
          <span class="present-panel-header-icon"><?= $presentPanelIcons['next'] ?></span>
          <span class="present-panel-header-title"><?= h(t('present.next_slide')) ?></span>
          <span class="props-accordion-chevron">▾</span>
        </button>
        <div class="props-accordion-body">
          <div class="present-panel-body-inner">
          <div class="present-next-panel" id="nextSlidePreview">
            <div class="present-next-thumb-scale" style="width:<?= (int)$meta['width'] ?>px; height:<?= (int)$meta['height'] ?>px;">
              <?= SlideRenderer::renderSlideThumbnailHtml($slides[$nextPreviewIdx] ?? $slides[0], null) ?>
            </div>
          </div>
          </div>
          <div class="present-panel-resize-handle" data-panel-resize="next" role="separator" aria-orientation="horizontal" title="<?= h(t('present.resize_panel')) ?>" hidden aria-hidden="true"></div>
        </div>
      </div>

      <div class="props-accordion-group open" data-acc="clock">
        <button type="button" class="props-accordion-header">
          <span class="present-panel-drag-handle" aria-hidden="true" title="<?= h(t('editor.reorder_slide')) ?>">⋮⋮</span>
          <span class="present-panel-header-icon"><?= $presentPanelIcons['clock'] ?></span>
          <span class="present-panel-header-title"><?= h(t('present.clock_section')) ?></span>
          <span class="props-accordion-chevron">▾</span>
        </button>
        <div class="props-accordion-body">
          <div class="present-panel-body-inner">
          <div class="present-clock-area" id="presentClockArea" role="button" tabindex="0" title="<?= h(t('present.clock_cycle_hint')) ?>">
            <div class="present-clock-stage">
            <div class="present-clock-face<?= $initialClock === 'studio' ? ' is-active' : '' ?>" data-clock="studio" id="clockFaceStudio"<?= $initialClock !== 'studio' ? ' hidden' : '' ?>>
              <div class="studio-clock-gorgy">
                <svg viewBox="0 0 200 200" class="studio-clock-svg" aria-hidden="true">
                  <g id="studioHourMarks"></g>
                  <g id="studioRingDots"></g>
                  <text id="studioTimeText" x="100" y="114" text-anchor="middle" class="studio-gorgy-digits">00:00</text>
                </svg>
              </div>
            </div>
            <div class="present-clock-face<?= $initialClock === 'analog' ? ' is-active' : '' ?>" data-clock="analog" id="clockFaceAnalog"<?= $initialClock !== 'analog' ? ' hidden' : '' ?>>
              <svg class="present-analog-clock" viewBox="0 0 100 100" aria-hidden="true">
                <circle class="analog-dial-face" cx="50" cy="50" r="46"/>
                <circle class="analog-dial-ring" cx="50" cy="50" r="46" fill="none"/>
                <g id="analogHourTicks"></g>
                <g id="analogHourLabels"></g>
                <line id="clockHourHand" class="analog-hand analog-hand-hour" x1="50" y1="50" x2="50" y2="30" stroke-linecap="round"/>
                <line id="clockMinuteHand" class="analog-hand analog-hand-minute" x1="50" y1="50" x2="50" y2="22" stroke-linecap="round"/>
                <line id="clockSecondHand" class="analog-hand analog-hand-second" x1="50" y1="54" x2="50" y2="14" stroke-linecap="round"/>
                <circle class="analog-center-dot" cx="50" cy="50" r="2.5"/>
              </svg>
            </div>
            <div class="present-clock-face<?= $initialClock === 'digital' ? ' is-active' : '' ?>" data-clock="digital" id="clockFaceDigital"<?= $initialClock !== 'digital' ? ' hidden' : '' ?>>
              <span class="present-time-display" id="wallClockDigital">00:00:00</span>
            </div>
            </div>
            <p class="present-clock-hint"><?= h(t('present.clock_cycle_hint')) ?></p>
          </div>
          </div>
          <div class="present-panel-resize-handle" data-panel-resize="clock" role="separator" aria-orientation="horizontal" title="<?= h(t('present.resize_panel')) ?>"></div>
        </div>
      </div>

      <div class="props-accordion-group open" data-acc="timer">
        <button type="button" class="props-accordion-header">
          <span class="present-panel-drag-handle" aria-hidden="true" title="<?= h(t('editor.reorder_slide')) ?>">⋮⋮</span>
          <span class="present-panel-header-icon"><?= $presentPanelIcons['timer'] ?></span>
          <span class="present-panel-header-title"><?= h(t('present.timer_section')) ?></span>
          <span class="props-accordion-chevron">▾</span>
        </button>
        <div class="props-accordion-body">
          <div class="present-panel-body-inner">
          <div class="present-timer-widget">
            <button type="button" class="present-timer-btn" id="timerPauseBtn" title="<?= h(t('present.timer_pause')) ?>">
              <svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
            </button>
            <div class="present-timer-ring-wrap" id="timerRingWrap">
              <svg viewBox="0 0 200 200" class="present-timer-svg" aria-hidden="true">
                <g id="timerStopMarks"></g>
                <g id="timerRingDots"></g>
              </svg>
              <div class="present-timer-center">
                <div class="present-time-display present-timer-digits present-timer-elapsed" id="timerDisplay">00:00</div>
              </div>
            </div>
            <button type="button" class="present-timer-btn" id="timerResetBtn" title="<?= h(t('present.timer_reset')) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>
            </button>
          </div>
          </div>
          <div class="present-panel-resize-handle" data-panel-resize="timer" role="separator" aria-orientation="horizontal" title="<?= h(t('present.resize_panel')) ?>"></div>
        </div>
      </div>

      <div class="props-accordion-group open" data-acc="media" id="mediaControlAccordion" hidden>
        <button type="button" class="props-accordion-header">
          <span class="present-panel-header-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg></span>
          <span class="present-panel-header-title"><?= h(t('present.media_control')) ?></span>
          <span class="props-accordion-chevron">▾</span>
        </button>
        <div class="props-accordion-body">
          <div class="present-panel-body-inner">
          <div id="mediaControlList"></div>
          </div>
          <div class="present-panel-resize-handle" data-panel-resize="media" role="separator" aria-orientation="horizontal" title="<?= h(t('present.resize_panel')) ?>"></div>
        </div>
      </div>

      <div class="props-accordion-group open" data-acc="slides">
        <button type="button" class="props-accordion-header">
          <span class="present-panel-drag-handle" aria-hidden="true" title="<?= h(t('editor.reorder_slide')) ?>">⋮⋮</span>
          <span class="present-panel-header-icon"><?= $presentPanelIcons['slides'] ?></span>
          <span class="present-panel-header-title"><?= h(t('present.slide_control')) ?></span>
          <span class="props-accordion-chevron">▾</span>
        </button>
        <div class="props-accordion-body">
          <div class="present-panel-body-inner present-slides-widget-inner">
          <div class="present-slides-control-stack">
          <div class="present-slides-toolbar">
            <?php
              $laserOn = ($presentLayout['laserPointerEnabled'] ?? true) !== false;
              $laserQuickColor = $presentLayout['laserPointerColor'] ?? '#ff0000';
              $ghostOn = !empty($presentLayout['showSlideGhost']);
            ?>
            <button type="button" id="presentLaserQuickToggle" class="present-toolbar-quick-toggle present-laser-quick-toggle<?= $laserOn ? ' is-on' : '' ?>" aria-pressed="<?= $laserOn ? 'true' : 'false' ?>" title="<?= h(t('present.laser_toggle')) ?>"<?= $laserOn ? ' style="color:' . h($laserQuickColor) . '"' : '' ?>>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3" fill="currentColor" stroke="none"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M16.9 16.9l2.1 2.1M19.1 4.9l-2.1 2.1M7.1 16.9l-2.1 2.1"/></svg>
            </button>
            <div class="present-ghost-inline<?= $ghostOn ? '' : ' is-disabled' ?>" id="presentGhostInline">
              <label for="presentSlideGhostOpacityInline" class="present-ghost-inline-label"><?= h(t('present.slide_ghost_opacity')) ?></label>
              <div class="present-ghost-inline-row">
                <input type="range" id="presentSlideGhostOpacityInline" min="5" max="80" step="1" value="<?= (int)($presentLayout['slideGhostOpacity'] ?? 25) ?>" aria-valuetext="<?= (int)($presentLayout['slideGhostOpacity'] ?? 25) ?>%" aria-label="<?= h(t('present.slide_ghost_opacity')) ?>"<?= $ghostOn ? '' : ' disabled' ?>>
                <span class="present-ghost-inline-val" id="presentSlideGhostOpacityInlineVal"><?= (int)($presentLayout['slideGhostOpacity'] ?? 25) ?></span>
              </div>
            </div>
            <button type="button" id="presentGhostQuickToggle" class="present-toolbar-quick-toggle present-ghost-quick-toggle<?= $ghostOn ? ' is-on' : '' ?>" aria-pressed="<?= $ghostOn ? 'true' : 'false' ?>" title="<?= h(t('present.ghost_toggle')) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="8" width="14" height="10" rx="1.5" opacity="0.45"/><rect x="3" y="5" width="14" height="10" rx="1.5"/><path d="M7 9h6" opacity="0.5"/><path d="M7 12h4" opacity="0.5"/></svg>
            </button>
          </div>
          <div class="present-controls">
            <button type="button" id="presPrevBtn" class="present-nav-btn" title="<?= h(t('present.prev')) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
            </button>
            <span id="presCounter" class="present-counter">1 / <?= count($slides) ?></span>
            <button type="button" id="presNextBtn" class="present-nav-btn" title="<?= h(t('present.next')) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
            </button>
          </div>
          </div>
          </div>
          <div class="present-panel-resize-handle" data-panel-resize="slides" role="separator" aria-orientation="horizontal" title="<?= h(t('present.resize_panel')) ?>"></div>
        </div>
      </div>

    </div>
  </div>
  <div class="present-col-splitter present-layout-splitter" data-split="side-time" role="separator" aria-orientation="vertical" title="<?= h(t('present.resize_columns')) ?>"<?= $showTimebar ? '' : ' hidden' ?>></div>
  <div class="present-timebar-panel"<?= $showTimebar ? '' : ' hidden' ?>>
    <div class="present-timebar-scale">
      <div class="present-timebar-ticks">
        <?php foreach ($timebarStops as $i => $stop):
            if ($i === 0 || $i === count($timebarStops) - 1) continue;
        ?>
          <span class="timebar-tick" style="bottom:<?= (float)$stop['pct'] ?>%;"><b><?= (int)$stop['pct'] ?></b></span>
        <?php endforeach; ?>
      </div>
      <div class="present-timebar-track" id="timeBarTrack" title="<?= h(t('present.timebar_hint')) ?>">
        <div class="present-timebar-fill" id="timeBarFill"></div>
      </div>
    </div>
    <label class="present-timebar-duration-label" for="timeBarDuration"><?= h(t('present.duration_min')) ?></label>
    <input type="number" id="timeBarDuration" class="present-timebar-duration-input" min="1" step="1" value="<?= (int)($meta['presentation_duration'] ?? 30) ?>">
  </div>
</div>

<div class="present-filmstrip" id="filmstrip">
  <?php
    $fsThumbW = client_is_touch_tablet() ? 200 : 168;
    $fsScale = $fsThumbW / max(1, (int)$meta['width']);
  ?>
  <?php foreach ($slides as $i => $s):
    $slideHasNotes = trim($s['notes'] ?? '') !== '';
    $slidePresentOff = Presentation::isSlidePresentDisabled($s);
  ?>
    <button type="button" class="filmstrip-item<?= $slideHasNotes ? ' has-notes' : '' ?><?= $slidePresentOff ? ' is-present-disabled' : '' ?>" data-index="<?= (int)$i ?>" style="--fs-color: <?= h($swatches[$i]) ?>;"<?= $slidePresentOff ? ' title="' . h(t('editor.slide_present_disabled')) . '"' : '' ?>>
      <div class="filmstrip-thumb-scale" style="width:<?= (int)$meta['width'] ?>px; height:<?= (int)$meta['height'] ?>px; transform:scale(<?= $fsScale ?>);">
        <?= SlideRenderer::renderSlideThumbnailHtml($s, null) ?>
      </div>
      <?php if ($slideHasNotes): ?>
      <span class="filmstrip-notes-badge" title="<?= h(t('present.notes')) ?>"><?= $presentPanelIcons['notes'] ?></span>
      <?php endif; ?>
      <span class="filmstrip-num"><?= (int)$i + 1 ?></span>
    </button>
  <?php endforeach; ?>
</div>
</div>

<script>
window.SF_PRESENT = {
  id: <?= json_encode($id) ?>,
  csrfToken: <?= json_encode(csrf_token()) ?>,
  canBroadcast: <?= $canBroadcast ? 'true' : 'false' ?>,
  remoteUrl: <?= json_encode($canBroadcast && $remoteUrl ? $remoteUrl : '') ?>,
  slideCount: <?= count($slides) ?>,
  startSlide: <?= (int)$startSlide ?>,
  slideDisabled: <?= json_encode($slideDisabled) ?>,
  notesHtml: <?= json_encode($notesHtml, JSON_UNESCAPED_UNICODE) ?>,
  slideWidth: <?= (int)$meta['width'] ?>,
  slideHeight: <?= (int)$meta['height'] ?>,
  timebarStops: <?= json_encode($timebarStops, JSON_UNESCAPED_UNICODE) ?>,
  clockOrder: <?= json_encode($clockOrder, JSON_UNESCAPED_UNICODE) ?>,
  brandColors: <?= json_encode(Config::brandColors(), JSON_UNESCAPED_UNICODE) ?>,
  presentPanels: <?= json_encode($presentPanels, JSON_UNESCAPED_UNICODE) ?>,
  presentLayout: <?= json_encode($presentLayout, JSON_UNESCAPED_UNICODE) ?>,
  laserPointer: <?= json_encode([
      'color' => $presentLayout['laserPointerColor'] ?? '#ff0000',
      'size' => (int)($presentLayout['laserPointerSize'] ?? 24),
      'trail' => !empty($presentLayout['laserPointerTrail']),
      'enabled' => ($presentLayout['laserPointerEnabled'] ?? true) !== false,
  ], JSON_UNESCAPED_UNICODE) ?>,
  i18n: {
    copied: <?= json_encode(t('present.copied')) ?>,
    copy: <?= json_encode(t('present.copy')) ?>,
    screenPrimary: <?= json_encode(t('present.screen_primary')) ?>,
    screenSecondary: <?= json_encode(t('present.screen_secondary')) ?>,
    screenN: <?= json_encode(t('present.screen_n')) ?>,
    screenSingle: <?= json_encode(t('present.screen_single')) ?>,
    screenMultiHint: <?= json_encode(t('present.screen_multi_hint')) ?>,
    localStart: <?= json_encode(t('present.local_start')) ?>,
    localReopen: <?= json_encode(t('present.local_reopen')) ?>,
    copyLink: <?= json_encode(t('present.copy_link')) ?>,
    copyRemote: <?= json_encode(t('remote.copy_url')) ?>,
    notesEmpty: <?= json_encode(t('present.notes_empty')) ?>,
    clockStudio: <?= json_encode(t('present.clock_studio')) ?>,
    clockAnalog: <?= json_encode(t('present.clock_analog')) ?>,
    clockDigital: <?= json_encode(t('present.clock_digital')) ?>,
    laserToggleOn: <?= json_encode(t('present.laser_toggle_on')) ?>,
    laserToggleOff: <?= json_encode(t('present.laser_toggle_off')) ?>,
    ghostToggleOn: <?= json_encode(t('present.ghost_toggle_on')) ?>,
    ghostToggleOff: <?= json_encode(t('present.ghost_toggle_off')) ?>,
  }
};
</script>
<script src="assets/js/present-config.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/present-layout.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/present.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/present-user-settings.js?v=<?= ASSET_VERSION ?>"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
