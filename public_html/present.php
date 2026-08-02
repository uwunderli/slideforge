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
$presentRibbonContext = PresentRibbonLayout::buildContext([
    'canBroadcast' => $canBroadcast,
    'isOwner' => $perm === 'owner',
    'hasRemote' => $canBroadcast && $remoteUrl !== '',
]);
$presentRibbonRaw = PresentRibbonLayout::getLayout($me['id'], $presentRibbonContext);
$presentRibbonLayout = PresentRibbonLayout::layoutForClient($presentRibbonRaw, $presentRibbonContext);
$presentRibbonCommands = PresentRibbonLayout::commandDefsForClient($presentRibbonContext);
$presentRibbonCatalog = PresentRibbonLayout::catalogForClient($presentRibbonContext);

$bodyClass = 'present-mode';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DSEG7+Classic:wght@700&display=swap">
<div class="present-layout<?= $showTimebar ? '' : ' present-timebar-hidden' ?>" style="--present-slide-aspect: <?= (int)$meta['width'] ?> / <?= (int)$meta['height'] ?>;">
<?php require __DIR__ . '/includes/present_ribbon.php'; ?>
<div class="present-topbar present-main-grid" id="presentTopbar">
  <div class="present-topbar-main">
    <?php if ($canBroadcast && $remoteUrl): ?>
    <input type="hidden" id="presentRemoteLinkInput" value="<?= h($remoteUrl) ?>">
    <?php endif; ?>
    <span id="presentStatus" class="save-status present-topbar-status"><?= $canBroadcast ? h(t('present.live')) : h(t('present.view_only')) ?></span>
  </div>
  <div class="present-topbar-splitter present-layout-splitter" data-split="main-side" aria-hidden="true"></div>
  <div class="present-topbar-spacer present-topbar-side" aria-hidden="true"></div>
  <div class="present-topbar-splitter present-layout-splitter" data-split="side-time" aria-hidden="true"<?= $showTimebar ? '' : ' hidden' ?>></div>
  <div class="present-topbar-time" aria-hidden="true"></div>
</div>

<div class="present-main-row present-main-grid" id="presentMainRow">
  <div class="present-current-panel">
    <iframe id="mainFrame" src="present_frame.php?id=<?= urlencode($id) ?>&mode=main&amp;start=<?= (int)$startSlide ?>" title="<?= h(t('present.current_slide')) ?>"></iframe>
    <div class="present-notes-overlay" id="presentNotesOverlay" hidden>
      <div class="present-notes-sheet" id="presentNotesSheet">
        <button
          type="button"
          class="present-notes-register"
          id="presentNotesRegister"
          aria-controls="notesPanel"
          aria-expanded="true"
          title="<?= h(t('present.notes_expand_hint')) ?>"
        >
          <span class="present-notes-register-label"><?= h(t('present.notes')) ?></span>
        </button>
        <div class="present-notes-body" id="presentNotesBody" title="<?= h(t('present.notes_collapse_hint')) ?>">
          <div class="present-notes-overlay-inner" id="notesPanel" role="region" aria-label="<?= h(t('present.notes')) ?>"></div>
        </div>
      </div>
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

      <div class="props-accordion-group open" data-acc="media" id="mediaControlAccordion" data-user-visible="1" hidden>
        <button type="button" class="props-accordion-header">
          <span class="present-panel-drag-handle" aria-hidden="true" title="<?= h(t('editor.reorder_slide')) ?>">⋮⋮</span>
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
window.SF_BOOTSTRAP = {
  csrfToken: <?= json_encode(csrf_token()) ?>,
  ribbon: {
    rootId: 'presentRibbon',
    widgetStoreId: 'presentRibbonWidgetTemplates',
    layout: <?= json_encode($presentRibbonLayout, JSON_UNESCAPED_UNICODE) ?>,
    catalog: <?= json_encode($presentRibbonCatalog, JSON_UNESCAPED_UNICODE) ?>,
    commands: <?= json_encode($presentRibbonCommands, JSON_UNESCAPED_UNICODE) ?>,
    apiUrl: 'present_ribbon.php',
    meta: {
      urls: {
        editor: <?= json_encode('editor.php?id=' . urlencode($id) . '&slide=' . (int)$startSlide) ?>,
      },
      canBroadcast: <?= $canBroadcast ? 'true' : 'false' ?>,
      isOwner: <?= $perm === 'owner' ? 'true' : 'false' ?>,
      hasRemote: <?= ($canBroadcast && $remoteUrl !== '') ? 'true' : 'false' ?>,
      displayOptions: {
        show_progress: <?= json_encode(($meta['show_progress'] ?? true) ? true : false) ?>,
        show_controls: <?= json_encode(($meta['show_controls'] ?? true) ? true : false) ?>,
      },
      displayProgressTitle: <?= json_encode(t('present.progress_bar')) ?>,
      displayControlsTitle: <?= json_encode(t('present.controls_toggle')) ?>,
    },
    i18n: {
      title: <?= json_encode(t('ribbon.customize_title')) ?>,
      search: <?= json_encode(t('ribbon.customize_search')) ?>,
      categoryAll: <?= json_encode(t('ribbon.customize_category_all')) ?>,
      tabAdd: <?= json_encode(t('ribbon.customize_tab_add')) ?>,
      tabRename: <?= json_encode(t('ribbon.customize_tab_rename')) ?>,
      tabDelete: <?= json_encode(t('ribbon.customize_tab_delete')) ?>,
      tabNamePrompt: <?= json_encode(t('ribbon.customize_tab_name')) ?>,
      groupAdd: <?= json_encode(t('ribbon.customize_group_add')) ?>,
      groupRename: <?= json_encode(t('ribbon.customize_group_rename')) ?>,
      groupDelete: <?= json_encode(t('ribbon.customize_group_delete')) ?>,
      groupNamePrompt: <?= json_encode(t('ribbon.customize_group_name')) ?>,
      newTab: <?= json_encode(t('ribbon.customize_new_tab')) ?>,
      newGroup: <?= json_encode(t('ribbon.customize_new_group')) ?>,
      reset: <?= json_encode(t('ribbon.customize_reset')) ?>,
      resetConfirm: <?= json_encode(t('ribbon.customize_reset_confirm')) ?>,
      save: <?= json_encode(t('ribbon.customize_save')) ?>,
      cancel: <?= json_encode(t('common.cancel')) ?>,
      remove: <?= json_encode(t('ribbon.customize_remove')) ?>,
      livePreviewHint: <?= json_encode(t('ribbon.customize_live_preview')) ?>,
      emptyLayout: <?= json_encode(t('ribbon.customize_empty_layout')) ?>,
      toggleTab: <?= json_encode(t('ribbon.customize_toggle_tab')) ?>,
      toggleGroup: <?= json_encode(t('ribbon.customize_toggle_group')) ?>,
      appearanceIconSize: <?= json_encode(t('ribbon.appearance_icon_size')) ?>,
      appearanceIconSmall: <?= json_encode(t('ribbon.appearance_icon_small')) ?>,
      appearanceIconMedium: <?= json_encode(t('ribbon.appearance_icon_medium')) ?>,
      appearanceIconLarge: <?= json_encode(t('ribbon.appearance_icon_large')) ?>,
      appearanceLabelsShow: <?= json_encode(t('ribbon.appearance_labels_show')) ?>,
      appearanceLabelsHide: <?= json_encode(t('ribbon.appearance_labels_hide')) ?>,
      appearanceGroupRows: <?= json_encode(t('ribbon.appearance_group_rows')) ?>,
      appearanceGroupRows1: <?= json_encode(t('ribbon.appearance_group_rows_1')) ?>,
      appearanceGroupRows2: <?= json_encode(t('ribbon.appearance_group_rows_2')) ?>,
      separatorAdd: <?= json_encode(t('ribbon.customize_separator_add')) ?>,
      rowSeparatorAdd: <?= json_encode(t('ribbon.customize_row_separator_add')) ?>,
      itemTileSmall: <?= json_encode(t('ribbon.item_tile_small')) ?>,
      itemTileLarge: <?= json_encode(t('ribbon.item_tile_large')) ?>,
      itemTileLargeNeedsRows: <?= json_encode(t('ribbon.item_tile_large_needs_rows')) ?>,
      itemTileSep1: <?= json_encode(t('ribbon.item_tile_sep_1')) ?>,
      itemTileSep2: <?= json_encode(t('ribbon.item_tile_sep_2')) ?>,
      itemTileTransition1: <?= json_encode(t('ribbon.item_tile_transition_1')) ?>,
      itemTileTransition2: <?= json_encode(t('ribbon.item_tile_transition_2')) ?>,
      itemTileTransition2NeedsRows: <?= json_encode(t('ribbon.item_tile_transition_2_needs_rows')) ?>,
      itemLabelShow: <?= json_encode(t('ribbon.item_label_show')) ?>,
      itemLabelHide: <?= json_encode(t('ribbon.item_label_hide')) ?>,
      errorSave: <?= json_encode(t('ribbon.error_save')) ?>,
      errorReset: <?= json_encode(t('ribbon.error_reset')) ?>,
      categories: {
        nav: <?= json_encode(t('ribbon.group_nav')) ?>,
        present: <?= json_encode(t('present.section_present')) ?>,
        settings: <?= json_encode(t('ribbon.tab_view')) ?>,
        layout: <?= json_encode(t('ribbon.separator')) ?>,
      },
    },
  }
};
window.SF_PRESENT = {
  id: <?= json_encode($id) ?>,
  csrfToken: <?= json_encode(csrf_token()) ?>,
  canBroadcast: <?= $canBroadcast ? 'true' : 'false' ?>,
  remoteUrl: <?= json_encode($canBroadcast && $remoteUrl ? $remoteUrl : '') ?>,
  slideCount: <?= count($slides) ?>,
  startSlide: <?= (int)$startSlide ?>,
  slideDisabled: <?= json_encode($slideDisabled) ?>,
  notesHtml: <?= json_encode($notesHtml, JSON_UNESCAPED_UNICODE) ?>,
  slideBgSwatches: <?= json_encode($swatches, JSON_UNESCAPED_UNICODE) ?>,
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
    notesCollapseHint: <?= json_encode(t('present.notes_collapse_hint')) ?>,
    notesExpandHint: <?= json_encode(t('present.notes_expand_hint')) ?>,
    clockStudio: <?= json_encode(t('present.clock_studio')) ?>,
    clockAnalog: <?= json_encode(t('present.clock_analog')) ?>,
    clockDigital: <?= json_encode(t('present.clock_digital')) ?>,
    laserToggleOn: <?= json_encode(t('present.laser_toggle_on')) ?>,
    laserToggleOff: <?= json_encode(t('present.laser_toggle_off')) ?>,
    ghostToggleOn: <?= json_encode(t('present.ghost_toggle_on')) ?>,
    ghostToggleOff: <?= json_encode(t('present.ghost_toggle_off')) ?>,
    reorderPanel: <?= json_encode(t('editor.reorder_slide')) ?>,
    brandColors: <?= json_encode(t('bg.brand_colors')) ?>,
  }
};
</script>
<script src="assets/js/ribbon-renderer.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/ribbon-customize.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/ribbon.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/present-config.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/present-layout.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/present.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/present-user-settings.js?v=<?= ASSET_VERSION ?>"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
