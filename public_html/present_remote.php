<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$id = $_GET['id'] ?? '';
$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    http_response_code(403);
    die(t('remote.no_access'));
}
$meta = Presentation::getMeta($id);
$slidesData = Presentation::getSlides($id);
$slideCount = count($slidesData['slides'] ?? []);

$live = Presentation::getLivePosition($id, 'present');
$startIndex = ($live && isset($live['index'])) ? (int)$live['index'] : 0;
$startIndex = max(0, min($slideCount - 1, $startIndex));

$presentLayout = Auth::getPresentLayout($me);
$laserColor = $presentLayout['laserPointerColor'] ?? '#ff0000';
$laserSize = (int)($presentLayout['laserPointerSize'] ?? 24);
$laserTrail = !empty($presentLayout['laserPointerTrail']);
$laserEnabled = ($presentLayout['laserPointerEnabled'] ?? true) !== false;
$hasLayoutStops = array_key_exists('timebarStops', $me['present_layout'] ?? []);
$timebarStops = Presentation::normalizeTimebarStops(
    $hasLayoutStops ? ($presentLayout['timebarStops'] ?? null) : ($meta['timebar_stops'] ?? null)
);
$presentationDuration = max(1, (int)($meta['presentation_duration'] ?? 30));
$slideW = max(1, (int)$meta['width']);
$slideH = max(1, (int)$meta['height']);

$pageTitle = t('remote.page_title');
$headerPresentationTitle = $meta['title'];
$headerPresentationContext = 'present';
$bodyClass = 'present-remote-mode' . (client_is_touch_tablet() ? ' present-remote-tablet' : '');
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DSEG7+Classic:wght@700&display=swap">

<div class="present-remote-wrap">
  <div class="present-remote-status">
    <span id="remotePresentStatus">
      <span class="present-remote-status-dot waiting" id="remotePresentDot"></span>
      <span id="remotePresentLabel"><?= h(t('remote.waiting_present')) ?></span>
    </span>
    <span class="present-control-status" id="remoteControlStatus" hidden>
      <span class="present-control-status-dot" id="remoteControlDot" aria-hidden="true"></span>
      <?= h(t('remote.control_status')) ?>
    </span>
    <span id="remoteConnLabel"><?= h(t('remote.connected')) ?></span>
  </div>

  <div class="present-remote-title" title="<?= h($meta['title']) ?>"><?= h($meta['title']) ?></div>
  <div class="present-remote-counter" id="remoteSlideCounter"><?= $slideCount ? ($startIndex + 1) . ' / ' . $slideCount : '—' ?></div>

  <div class="present-remote-tools" role="tablist" aria-label="<?= h(t('remote.tools_label')) ?>">
    <button type="button" class="present-remote-tool-btn active" data-remote-mode="nav" role="tab" aria-selected="true"><?= h(t('remote.tool_nav')) ?></button>
    <button type="button" class="present-remote-tool-btn" data-remote-mode="preview" role="tab" aria-selected="false"><?= h(t('remote.tool_preview')) ?></button>
    <button type="button" class="present-remote-tool-btn" data-remote-mode="clock" role="tab" aria-selected="false"><?= h(t('remote.tool_clock')) ?></button>
    <button type="button" class="present-remote-tool-btn" data-remote-mode="timer" role="tab" aria-selected="false"><?= h(t('remote.tool_timer')) ?></button>
    <button type="button" class="present-remote-tool-btn" data-remote-mode="laser" role="tab" aria-selected="false"><?= h(t('remote.tool_laser')) ?></button>
  </div>

  <div class="present-remote-body">
  <div class="present-remote-main" id="remoteMainPanel">
    <div class="present-remote-panel active" data-remote-panel="nav">
      <div class="present-remote-preview-label" id="remoteNavLabel"><?= h(t('remote.nav_current')) ?></div>
      <div class="present-remote-preview-stage" id="remoteNavStage">
        <div class="present-remote-preview-scale" id="remoteNavScale"></div>
      </div>
      <p class="present-remote-panel-hint"><?= h(t('remote.nav_hint')) ?></p>
    </div>

    <div class="present-remote-panel" data-remote-panel="laser" hidden>
      <div class="present-remote-laser-disabled" id="remoteLaserDisabled"<?= $laserEnabled ? ' hidden' : '' ?>>
        <p><?= h(t('remote.laser_disabled_hint')) ?></p>
        <label class="present-remote-laser-enable">
          <input type="checkbox" id="remoteLaserEnabled"<?= $laserEnabled ? ' checked' : '' ?>>
          <span><?= h(t('remote.laser_enabled')) ?></span>
        </label>
      </div>
      <div class="present-remote-laser-pad" id="remoteLaserPad"<?= $laserEnabled ? '' : ' hidden' ?>>
        <span class="present-remote-laser-hint"><?= h(t('remote.laser_pad_hint')) ?></span>
      </div>
    </div>

    <div class="present-remote-panel" data-remote-panel="clock" hidden>
      <div class="present-remote-clock-face" id="remoteClockFace">
        <svg class="remote-analog-clock" viewBox="0 0 100 100" aria-hidden="true">
          <circle class="remote-analog-dial" cx="50" cy="50" r="46"/>
          <g id="remoteAnalogTicks"></g>
          <g id="remoteAnalogLabels"></g>
          <line id="remoteClockHour" class="remote-analog-hand remote-analog-hour" x1="50" y1="50" x2="50" y2="30" stroke-linecap="round"/>
          <line id="remoteClockMinute" class="remote-analog-hand remote-analog-minute" x1="50" y1="50" x2="50" y2="22" stroke-linecap="round"/>
          <line id="remoteClockSecond" class="remote-analog-hand remote-analog-second" x1="50" y1="54" x2="50" y2="14" stroke-linecap="round"/>
          <circle class="remote-analog-center" cx="50" cy="50" r="2.5"/>
        </svg>
      </div>
      <div class="present-remote-clock-digital" id="remoteClockDigital">00:00:00</div>
    </div>

    <div class="present-remote-panel" data-remote-panel="timer" hidden>
      <div class="present-remote-timer-widget">
        <button type="button" class="present-remote-timer-btn" id="remoteTimerPauseBtn" title="<?= h(t('remote.timer_pause')) ?>">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
        </button>
        <div class="present-remote-timer-ring-wrap" id="remoteTimerRingWrap">
          <svg viewBox="0 0 200 200" class="present-remote-timer-svg" aria-hidden="true">
            <g id="remoteTimerStopMarks"></g>
            <g id="remoteTimerRingDots"></g>
          </svg>
          <div class="present-remote-timer-center">
            <div class="present-remote-timer-digits" id="remoteTimerDisplay">00:00</div>
          </div>
        </div>
        <button type="button" class="present-remote-timer-btn" id="remoteTimerResetBtn" title="<?= h(t('remote.timer_reset')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>
        </button>
      </div>
    </div>

    <div class="present-remote-panel" data-remote-panel="preview" hidden>
      <div class="present-remote-preview-label" id="remotePreviewLabel"></div>
      <div class="present-remote-preview-stage" id="remotePreviewStage">
        <div class="present-remote-preview-scale" id="remotePreviewScale"></div>
      </div>
    </div>
  </div>

  <aside class="present-remote-timebar" id="remoteTimebar" aria-label="<?= h(t('remote.timebar_aria')) ?>">
    <div class="present-remote-timebar-scale">
      <div class="present-remote-timebar-ticks" id="remoteTimebarTicks">
        <?php foreach ($timebarStops as $i => $stop):
            if ($i === 0 || $i === count($timebarStops) - 1) continue;
        ?>
          <span class="remote-timebar-tick" style="bottom:<?= (float)$stop['pct'] ?>%;"><b><?= (int)$stop['pct'] ?></b></span>
        <?php endforeach; ?>
      </div>
      <div class="present-remote-timebar-track" id="remoteTimebarTrack">
        <div class="present-remote-timebar-fill" id="remoteTimebarFill"></div>
      </div>
    </div>
  </aside>
  </div>

  <div class="present-remote-nav" id="remoteNavRow">
    <button type="button" class="button button-ghost" id="remotePrevBtn" aria-label="<?= h(t('remote.prev')) ?>">← <?= h(t('remote.prev')) ?></button>
    <button type="button" class="button" id="remoteNextBtn" aria-label="<?= h(t('remote.next')) ?>"><?= h(t('remote.next')) ?> →</button>
  </div>
</div>

<script>
window.SF_REMOTE = {
  id: <?= json_encode($id) ?>,
  csrfToken: <?= json_encode(csrf_token()) ?>,
  slideCount: <?= (int)$slideCount ?>,
  startIndex: <?= (int)$startIndex ?>,
  slideWidth: <?= (int)$slideW ?>,
  slideHeight: <?= (int)$slideH ?>,
  laser: {
    color: <?= json_encode($laserColor) ?>,
    size: <?= (int)$laserSize ?>,
    trail: <?= $laserTrail ? 'true' : 'false' ?>,
    enabled: <?= $laserEnabled ? 'true' : 'false' ?>,
  },
  presentLayout: <?= json_encode($presentLayout, JSON_UNESCAPED_UNICODE) ?>,
  isTablet: <?= client_is_touch_tablet() ? 'true' : 'false' ?>,
  presentationDuration: <?= (int)$presentationDuration ?>,
  timebarStops: <?= json_encode($timebarStops, JSON_UNESCAPED_UNICODE) ?>,
  i18n: {
    navCurrent: <?= json_encode(t('remote.nav_current')) ?>,
    waitingPresent: <?= json_encode(t('remote.waiting_present')) ?>,
    presentActive: <?= json_encode(t('remote.present_active')) ?>,
    previewNext: <?= json_encode(t('remote.preview_next')) ?>,
    previewLast: <?= json_encode(t('remote.preview_last')) ?>,
    timerPause: <?= json_encode(t('remote.timer_pause')) ?>,
    timerResume: <?= json_encode(t('remote.timer_resume')) ?>,
  },
};
</script>
<script src="assets/js/present-remote.js?v=<?= ASSET_VERSION ?>"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
