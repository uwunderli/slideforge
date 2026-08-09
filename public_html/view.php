<?php
require __DIR__ . '/../config.php';

$token = $_GET['token'] ?? '';
$id = $token !== '' ? Presentation::findByPublicToken($token) : null;

if (!$id) {
    http_response_code(404);
    ?>
    <!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Nicht gefunden</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= ASSET_VERSION ?>"></head>
    <body><div class="container"><div class="form-card">
      <h1>Link ungültig</h1>
      <p style="color:var(--text-muted);">Dieser öffentliche Präsentations-Link existiert nicht (mehr) oder wurde deaktiviert.</p>
    </div></div></body></html>
    <?php
    exit;
}

$meta = Presentation::getMeta($id);
$slidesData = Storage::read(Presentation::dir($id) . '/slides.json', ['slides' => []]);
$slideDisabled = Presentation::slidePresentDisabledFlags($slidesData);
$sections = SlideRenderer::renderSections($slidesData, $token);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Permissions-Policy" content="window-management=(self), fullscreen=(self)">
<title><?= h($meta['title']) ?></title>
<?= FontLibrary::headMarkup('fonts.css.php') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/theme/black.css">
<style>
html,body{margin:0;height:100%;background:#000;}
.sf-object{color:#fff;}
.sf-laser-dot{
  position:fixed; z-index:9999; border-radius:50%; pointer-events:none;
  transform:translate(-50%,-50%); opacity:0.92;
}
.sf-laser-trail-dot{
  position:fixed; z-index:9998; border-radius:50%; pointer-events:none;
  transform:translate(-50%,-50%);
}
</style>
<?= Exporter::mediaStatusMarkup() ?>
</head>
<body>
<div class="reveal">
  <div class="slides">
    <?= $sections ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.js"></script>
<script>
window.SF_SLIDE_DISABLED = <?= json_encode($slideDisabled) ?>;
</script>
<script src="assets/js/present-slide-skip.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/present-laser-audience.js?v=<?= ASSET_VERSION ?>"></script>
<script>
  Reveal.initialize({
    width: <?= (int)$meta['width'] ?>,
    height: <?= (int)$meta['height'] ?>,
    hash: true,
    controls: <?= ($meta['show_controls'] ?? true) ? 'true' : 'false' ?>,
    progress: <?= ($meta['show_progress'] ?? true) ? 'true' : 'false' ?>,
    center: false, // unsere Objekte sind absolut positioniert (x/y) - reveal.js soll nichts selbst verschieben
    margin: 0
  });

  // Medien: Ladehinweis, automatische Wiedergabe nach Verzögerung.
<?= Exporter::mediaRuntimeJs(false) ?>
<?= Exporter::mediaSlideBootJs(false) ?>

  Reveal.on('ready', function () {
    window.SlideForgePresentSlideSkip?.install(Reveal, window.SF_SLIDE_DISABLED || []);
    SlideForgePresentLaserAudience?.init({
      pollUrl: 'live.php?id=<?= urlencode($id) ?>&token=<?= urlencode($token) ?>&channel=present',
    });

    // Live-Sync erst nach Reveal-Init — sonst leere Folie bei aktiver Remote-Sitzung.
    let lastIndex = null;
    let lastFrag = undefined;
    let lastMediaCmdTs = null;
    const disabled = window.SF_SLIDE_DISABLED || [];

    function applyLivePosition(live) {
      if (!live || typeof live.index !== 'number') return;
      const total = Reveal.getTotalSlides ? Reveal.getTotalSlides() : 0;
      if (total < 1) return;
      let idx = Math.max(0, Math.min(total - 1, live.index));
      const rawIdx = idx;
      if (window.SlideForgePresentSlideSkip?.normalizeLiveIndex) {
        idx = window.SlideForgePresentSlideSkip.normalizeLiveIndex(disabled, idx, 1);
      }
      const frag = (idx === rawIdx && typeof live.frag === 'number') ? live.frag : null;
      if (idx === lastIndex && frag === lastFrag) return;
      lastIndex = idx;
      lastFrag = frag;
      try {
        if (frag !== null) Reveal.slide(idx, 0, frag);
        else Reveal.slide(idx, 0);
      } catch (e) { /* ignore */ }
    }

    function pollLive() {
      fetch('live.php?id=<?= urlencode($id) ?>&token=<?= urlencode($token) ?>&channel=present')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok || !data.live) return;
          applyLivePosition(data.live);
          const media = data.live.media;
          if (media && media.cmd_ts && media.cmd_ts !== lastMediaCmdTs) {
            lastMediaCmdTs = media.cmd_ts;
            if (typeof sfApplyLiveMediaCommand === 'function') {
              sfApplyLiveMediaCommand(media);
            }
          }
        })
        .catch(function () {});
    }

    // Nach Live-Folienwechsel Timed-Medien erneut scharf schalten.
    Reveal.on('slidechanged', function () {
      var slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
      if (slide && typeof sfArmMediaTriggers === 'function') {
        if (!window.__sfViewPlayTimers) window.__sfViewPlayTimers = [];
        sfArmMediaTriggers(slide, window.__sfViewPlayTimers);
      }
    });

    pollLive();
    setInterval(pollLive, 400);
  });
</script>
<script>
  (function tryAutofullscreen() {
    async function enterFullscreen() {
      var el = document.documentElement;
      var opts = { navigationUI: 'hide' };
      if (window.getScreenDetails) {
        try {
          var details = await window.getScreenDetails();
          var screen = details.screens.find(function (s) { return s.isPrimary; }) || details.screens[0];
          if (screen) opts.screen = screen;
        } catch (e) {}
      }
      var req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
      if (!req) return;
      try {
        await req.call(el, opts);
      } catch (e) {
        req.call(el).catch(function () {});
      }
    }
    Reveal.on('ready', enterFullscreen);
    window.addEventListener('load', enterFullscreen);
  })();
</script>
</body>
</html>
