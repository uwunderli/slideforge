<?php
/**
 * Zuschauer-Ansicht für lokale Präsentation (Beamer/ zweiter Monitor).
 * Folgt live.php wie view.php, aber per Login statt öffentlichem Token.
 */
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$id = $_GET['id'] ?? '';
if (!Presentation::canView($id, $me['id'])) {
    http_response_code(403);
    die('Kein Zugriff.');
}

$meta = Presentation::getMeta($id);
$slidesData = Storage::read(Presentation::dir($id) . '/slides.json', ['slides' => []]);
$sections = SlideRenderer::renderSections($slidesData, null);
$screenIndex = isset($_GET['screen']) ? max(0, (int)$_GET['screen']) : 0;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Permissions-Policy" content="window-management=(self), fullscreen=(self)">
<title><?= h($meta['title']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/theme/black.css">
<style>
html,body{margin:0;height:100%;background:#000;} .sf-object{color:#fff;}
.sf-laser-dot{
  position:fixed; z-index:9999; border-radius:50%; pointer-events:none;
  transform:translate(-50%,-50%); opacity:0.92;
}
.sf-laser-trail-dot{
  position:fixed; z-index:9998; border-radius:50%; pointer-events:none;
  transform:translate(-50%,-50%);
}
#sfPresentOverlay{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.82);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:24px;text-align:center;}
#sfPresentOverlay[hidden]{display:none;}
#sfPresentOverlay p{margin:0;color:rgba(255,255,255,.85);font:14px/1.45 system-ui,sans-serif;max-width:360px;}
#sfPresentFsBtn{font:600 15px system-ui,sans-serif;padding:12px 28px;border:none;border-radius:8px;background:#3a6c8d;color:#fff;cursor:pointer;}
#sfPresentFsBtn:hover{filter:brightness(1.12);}
</style>
<?= Exporter::mediaStatusMarkup() ?>
</head>
<body>
<div class="reveal">
  <div class="slides">
    <?= $sections ?>
  </div>
</div>
<div id="sfPresentOverlay" hidden>
  <p><?= h(t('present.fullscreen_hint')) ?></p>
  <button type="button" id="sfPresentFsBtn"><?= h(t('present.fullscreen_start')) ?></button>
</div>
<script src="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.js"></script>
<script src="assets/js/present-laser-audience.js?v=<?= ASSET_VERSION ?>"></script>
<script>
  Reveal.initialize({
    width: <?= (int)$meta['width'] ?>,
    height: <?= (int)$meta['height'] ?>,
    hash: false,
    controls: <?= ($meta['show_controls'] ?? true) ? 'true' : 'false' ?>,
    progress: <?= ($meta['show_progress'] ?? true) ? 'true' : 'false' ?>,
    center: false,
    margin: 0,
    keyboard: false,
    touch: false
  });

<?= Exporter::mediaRuntimeJs(false) ?>
<?= Exporter::mediaSlideBootJs(false) ?>

  (function () {
    var targetScreenIndex = <?= (int)$screenIndex ?>;
    var overlay = document.getElementById('sfPresentOverlay');
    var fsBtn = document.getElementById('sfPresentFsBtn');

    function showOverlay() {
      if (overlay) overlay.hidden = false;
    }
    function hideOverlay() {
      if (overlay) overlay.hidden = true;
    }

    async function resolveTargetScreen(index) {
      if (!window.getScreenDetails) return null;
      try {
        var details = await window.getScreenDetails();
        return details.screens[index] || details.screens.find(function (s) { return !s.isPrimary; }) || details.screens[0] || null;
      } catch (e) {
        return null;
      }
    }

    async function enterPresentFullscreen(screenIndex) {
      if (typeof screenIndex === 'number') targetScreenIndex = screenIndex;
      var el = document.documentElement;
      var opts = { navigationUI: 'hide' };
      var screen = await resolveTargetScreen(targetScreenIndex);
      if (screen) opts.screen = screen;
      var req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
      if (!req) {
        showOverlay();
        return false;
      }
      try {
        await req.call(el, opts);
        hideOverlay();
        return true;
      } catch (e) {
        try {
          await req.call(el);
          hideOverlay();
          return true;
        } catch (e2) {
          showOverlay();
          return false;
        }
      }
    }

    function schedulePositionAttempts() {
      if (!window.opener || window.opener.closed) return;
      var attempts = 0;
      var timer = setInterval(function () {
        attempts++;
        try {
          window.opener.postMessage({ type: 'sf_present_reposition', screenIndex: targetScreenIndex }, window.location.origin);
        } catch (e) {}
        if (attempts >= 6) clearInterval(timer);
      }, 250);
    }

    window.addEventListener('message', function (e) {
      if (e.origin !== window.location.origin) return;
      if (!e.data) return;
      if (e.data.type === 'sf_present') {
        enterPresentFullscreen(typeof e.data.screenIndex === 'number' ? e.data.screenIndex : targetScreenIndex);
      }
    });

    if (fsBtn) {
      fsBtn.addEventListener('click', function () {
        enterPresentFullscreen(targetScreenIndex);
      });
    }

    document.addEventListener('fullscreenchange', function () {
      if (document.fullscreenElement) hideOverlay();
    });

    Reveal.on('ready', function () { enterPresentFullscreen(targetScreenIndex); });
    window.addEventListener('load', function () {
      enterPresentFullscreen(targetScreenIndex);
      schedulePositionAttempts();
    });

    var lastIndex = null;
    var lastFrag = undefined;
    var lastMediaCmdTs = null;
    setInterval(function () {
      fetch('live.php?id=<?= urlencode($id) ?>&channel=present')
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok || !data.live) return;
          var frag = typeof data.live.frag === 'number' ? data.live.frag : null;
          if (data.live.index !== lastIndex || frag !== lastFrag) {
            lastIndex = data.live.index;
            lastFrag = frag;
            if (frag !== null) { Reveal.slide(data.live.index, 0, frag); }
            else { Reveal.slide(data.live.index, 0); }
          }
          var media = data.live.media;
          if (media && media.cmd_ts && media.cmd_ts !== lastMediaCmdTs) {
            lastMediaCmdTs = media.cmd_ts;
            var el = document.querySelector('[data-media-id="' + media.id + '"]');
            if (el) {
              if (media.action === 'play') { el.play && el.play().catch(function () {}); }
              else if (media.action === 'pause') { el.pause && el.pause(); }
              else if (media.action === 'stop') { el.pause && el.pause(); try { el.currentTime = 0; } catch (e) {} }
              else if (media.action === 'seek' && typeof media.time === 'number') {
                try { el.currentTime = media.time; } catch (e) {}
              }
            }
          }
        })
        .catch(function () {});
    }, 1500);

    Reveal.on('ready', function () {
      SlideForgePresentLaserAudience?.init({
        pollUrl: 'live.php?id=<?= urlencode($id) ?>&channel=present',
      });
    });
  })();
</script>
</body>
</html>
