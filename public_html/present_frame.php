<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$id = $_GET['id'] ?? '';
if (!Presentation::canView($id, $me['id'])) {
    http_response_code(403);
    die('Kein Zugriff.');
}
$mode = ($_GET['mode'] ?? 'main') === 'next' ? 'next' : 'main';
$meta = Presentation::getMeta($id);
$slidesData = Presentation::getSlides($id);
$slideDisabled = Presentation::slidePresentDisabledFlags($slidesData);
$startSlide = max(0, (int)($_GET['start'] ?? 0));
$slideCount = count($slidesData['slides'] ?? []);
if ($startSlide >= $slideCount) {
    $startSlide = max(0, $slideCount - 1);
}
$startSlide = Presentation::normalizePresentStartIndex($slideDisabled, $startSlide);
$sections = SlideRenderer::renderSections($slidesData, null);
$isMain = $mode === 'main';
$presentLayout = Auth::getPresentLayout($me);
$laserColor = $presentLayout['laserPointerColor'] ?? '#ff0000';
$laserSize = (int)($presentLayout['laserPointerSize'] ?? 24);
$laserTrail = !empty($presentLayout['laserPointerTrail']);
$laserEnabled = ($presentLayout['laserPointerEnabled'] ?? true) !== false;
$showGhost = $isMain && !empty($presentLayout['showSlideGhost']);
$ghostOpacity = max(5, min(80, (int)($presentLayout['slideGhostOpacity'] ?? 25)));
$slideW = (int)$meta['width'];
$slideH = (int)$meta['height'];
$slides = $slidesData['slides'] ?? [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?= h($meta['title']) ?></title>
<?= FontLibrary::headMarkup('fonts.css.php') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/theme/black.css">
<style>
html,body{margin:0;height:100%;background:#000;overflow:hidden;}
.sf-object{color:#fff;}
.sf-laser-dot{
  position:fixed; z-index:9999; border-radius:50%; pointer-events:none;
  transform:translate(-50%,-50%); opacity:0.92;
}
.sf-laser-trail-dot{
  position:fixed; z-index:9998; border-radius:50%; pointer-events:none;
  transform:translate(-50%,-50%);
}
.sf-present-stack{position:relative;width:100%;height:100%;background:#000;}
.sf-present-stack .reveal.sf-present-live{position:absolute;inset:0;z-index:2;}
.sf-present-stack.sf-ghost-on .reveal.sf-present-live{background:transparent!important;}
.sf-present-stack.sf-ghost-on .reveal.sf-present-live .slide-background{display:none!important;}
.sf-slide-ghost{
  position:absolute;left:0;top:0;width:100%;height:100%;
  pointer-events:none;z-index:1;overflow:hidden;
  opacity:var(--slide-ghost-opacity,0.25);
}
.sf-present-stack.sf-ghost-on .slides section > :not(.sf-slide-ghost){
  position:relative;
  z-index:2;
}
.sf-slide-ghost .sf-ghost-frag:not(.sf-ghost-suppressed){
  opacity:1!important;visibility:visible!important;transform:none!important;filter:none!important;
}
.sf-slide-ghost .sf-text-line.sf-ghost-frag:not(.sf-ghost-suppressed){
  opacity:1!important;visibility:visible!important;transform:none!important;filter:none!important;
}
.sf-slide-ghost .sf-ghost-suppressed{
  opacity:0!important;visibility:hidden!important;
}
.sf-slide-ghost .sf-text-line-gap{
  visibility:hidden!important;
}
</style>
<?= Exporter::mediaStatusMarkup() ?>
</head>
<body>
<?php if ($isMain): ?>
<div class="sf-present-stack<?= $showGhost ? ' sf-ghost-on' : '' ?>" style="--slide-ghost-opacity: <?= $ghostOpacity / 100 ?>;">
  <div id="slideGhostStore" hidden aria-hidden="true">
    <?php foreach ($slides as $i => $s): ?>
    <div data-slide-index="<?= (int)$i ?>"><?= SlideRenderer::renderSlideGhostHtml($s, null) ?></div>
    <?php endforeach; ?>
  </div>
  <div class="reveal sf-present-live">
    <div class="slides">
      <?= $sections ?>
    </div>
  </div>
</div>
<?php else: ?>
<div class="reveal">
  <div class="slides">
    <?= $sections ?>
  </div>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.js"></script>
<script>
window.SF_SLIDE_DISABLED = <?= json_encode($slideDisabled) ?>;
</script>
<script src="assets/js/present-slide-skip.js?v=<?= ASSET_VERSION ?>"></script>
<?php if ($isMain): ?>
<script src="assets/js/reveal-autoslide.js?v=<?= ASSET_VERSION ?>"></script>
<script>
window.SF_LASER = <?= json_encode(['color' => $laserColor, 'size' => $laserSize, 'trail' => $laserTrail, 'enabled' => $laserEnabled], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/present-laser.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; ?>
<script>
  Reveal.initialize({
    width: <?= (int)$meta['width'] ?>,
    height: <?= (int)$meta['height'] ?>,
    hash: false,
    controls: false,
    progress: false,
    center: false, // unsere Objekte sind absolut positioniert (x/y) - reveal.js soll nichts selbst verschieben
    margin: 0,
    keyboard: <?= $isMain ? '{ 13: "next", 32: "next", 33: "prev", 34: "next", 37: "prev", 39: "next", 38: "prev", 40: "next" }' : 'false' ?>,
    touch: <?= $isMain ? 'true' : 'false' ?>,
    help: false
  });
  window.sfReady = true;
  <?php if ($isMain && $startSlide > 0): ?>
  Reveal.on('ready', function () { Reveal.slide(<?= (int)$startSlide ?>, 0); });
  <?php endif; ?>
  <?php if ($isMain): ?>
  Reveal.on('ready', function () {
    // Enter ist in reveal.js 5 nicht standardmässig gebunden; im iframe übernimmt
    // present.js die Weiter-Taste, wenn die Folie eingebettet ist.
    if (window.parent === window) {
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.shiftKey) return;
        const tag = (e.target && e.target.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
        e.preventDefault();
        Reveal.next();
      });
    }
  });
  <?php endif; ?>
  <?php if ($isMain): ?>
  Reveal.on('ready', function () {
    window.SlideForgePresentSlideSkip?.install(Reveal, window.SF_SLIDE_DISABLED || []);
    window.SlideForgePresentLaser?.init();
    // Auto-Weiter erst nach Present-Boot — sonst springt Folie 1 sofort weiter.
    window.SlideForgeRevealAutoSlide?.install(Reveal, { pausedUntilBoot: true });
    // Erste echte Nutzer-Geste im Frame → Parent schaltet Auto-Weiter frei.
    var navNotified = false;
    function notifyUserNav() {
      if (navNotified || window.parent === window) return;
      navNotified = true;
      window.parent.postMessage({ type: 'sf-present-user-nav' }, '*');
    }
    ['keydown', 'pointerdown'].forEach(function (ev) {
      document.addEventListener(ev, function (e) {
        if (ev === 'keydown') {
          var k = e.key;
          if (k !== 'ArrowRight' && k !== 'ArrowLeft' && k !== ' ' && k !== 'PageDown'
            && k !== 'PageUp' && k !== 'Enter') return;
        }
        notifyUserNav();
      }, true);
    });
  });
  (function () {
    const ghostStore = document.getElementById('slideGhostStore');
    let ghostEnabled = <?= $showGhost ? 'true' : 'false' ?>;

    function removeAllGhosts() {
      document.querySelectorAll('.sf-slide-ghost').forEach(function (el) { el.remove(); });
    }

    function setGhostStackActive(on) {
      const stack = document.querySelector('.sf-present-stack');
      if (stack) stack.classList.toggle('sf-ghost-on', !!on);
    }

    function setGhostOpacity(pct) {
      const val = Math.max(5, Math.min(80, parseInt(pct, 10) || 25)) / 100;
      const stack = document.querySelector('.sf-present-stack');
      if (stack) stack.style.setProperty('--slide-ghost-opacity', String(val));
    }

    function slideWantsGhost(slideEl) {
      if (!slideEl) return false;
      // Vom Renderer gesetzt: ≥2 Schritte und mindestens ein manueller (nicht nur Auto-Kette)
      return slideEl.getAttribute('data-sf-ghost-preview') === '1';
    }

    function syncGhostVisibility() {
      const slideEl = typeof Reveal !== 'undefined' && Reveal.getCurrentSlide
        ? Reveal.getCurrentSlide()
        : null;
      if (!slideEl || !ghostEnabled) return;
      const ghost = slideEl.querySelector(':scope > .sf-slide-ghost');
      if (!ghost) return;

      function isLiveEl(el) {
        return el && !el.closest('.sf-slide-ghost');
      }

      function matchGhostEl(liveEl) {
        const idx = liveEl.getAttribute('data-fragment-index');
        if (idx == null) return null;
        return ghost.querySelector('[data-fragment-index="' + idx + '"]');
      }

      function setSuppressed(liveEl, ghostEl) {
        if (!ghostEl) return;
        ghostEl.classList.toggle('sf-ghost-suppressed', liveEl.classList.contains('visible'));
      }

      ghost.querySelectorAll('.sf-ghost-suppressed').forEach(function (el) {
        el.classList.remove('sf-ghost-suppressed');
      });

      // Nur wirklich statische Objekte ausblenden (kein Fragment auf sich oder in Kindern).
      ghost.querySelectorAll('.sf-object').forEach(function (go) {
        const animated = go.classList.contains('fragment')
          || go.classList.contains('sf-ghost-frag')
          || go.querySelector('.fragment, .sf-ghost-frag');
        if (!animated) go.classList.add('sf-ghost-suppressed');
      });

      const liveLines = Array.from(slideEl.querySelectorAll('.sf-text-line.fragment')).filter(isLiveEl);
      if (liveLines.length) {
        const ghostLines = ghost.querySelectorAll('.sf-text-line');
        liveLines.forEach(function (lf, i) {
          setSuppressed(lf, matchGhostEl(lf) || ghostLines[i]);
        });
        return;
      }

      const ghostFrags = ghost.querySelectorAll('.sf-ghost-frag');
      Array.from(slideEl.querySelectorAll('.fragment')).filter(isLiveEl).forEach(function (lf, i) {
        const gf = matchGhostEl(lf) || ghostFrags[i];
        if (gf) setSuppressed(lf, gf);
      });
    }

    function sanitizeGhostHtml(html) {
      return html.replace(/\bfragment\b/g, 'sf-ghost-frag');
    }

    function scheduleGhostVisibilitySync() {
      requestAnimationFrame(function () {
        requestAnimationFrame(syncGhostVisibility);
      });
    }

    function notifyParentPosition() {
      if (window.parent === window || typeof Reveal === 'undefined') return;
      const idx = Reveal.getIndices() || { h: 0 };
      window.parent.postMessage({
        type: 'sf-present-position',
        index: idx.h || 0,
        frag: typeof idx.f === 'number' ? idx.f : null,
      }, '*');
    }

    function syncGhostSlide(index) {
      removeAllGhosts();
      if (!ghostEnabled || !ghostStore) return;
      const slideEl = typeof Reveal !== 'undefined' && Reveal.getCurrentSlide
        ? Reveal.getCurrentSlide()
        : null;
      if (!slideEl || !slideWantsGhost(slideEl)) return;
      const idx = index != null
        ? index
        : ((Reveal.getIndices() || { h: 0 }).h || 0);
      const tpl = ghostStore.querySelector('[data-slide-index="' + idx + '"]');
      if (!tpl) return;
      const ghost = document.createElement('div');
      ghost.className = 'sf-slide-ghost';
      ghost.setAttribute('aria-hidden', 'true');
      ghost.innerHTML = sanitizeGhostHtml(tpl.innerHTML);
      slideEl.insertBefore(ghost, slideEl.firstChild);
      scheduleGhostVisibilitySync();
    }

    window.sfApplySlideGhost = function (enabled, opacityPct) {
      ghostEnabled = !!enabled;
      setGhostOpacity(opacityPct);
      setGhostStackActive(ghostEnabled);
      if (ghostEnabled) syncGhostSlide();
      else removeAllGhosts();
    };

    window.addEventListener('message', function (e) {
      if (!e.data || e.data.type !== 'sf-slide-ghost') return;
      window.sfApplySlideGhost(!!e.data.enabled, e.data.opacity);
    });

    Reveal.on('ready', function () { syncGhostSlide(); notifyParentPosition(); });
    Reveal.on('slidechanged', function () { notifyParentPosition(); });
    Reveal.on('slidetransitionend', function () { syncGhostSlide(); notifyParentPosition(); });
    Reveal.on('fragmentshown', function () { scheduleGhostVisibilitySync(); notifyParentPosition(); });
    Reveal.on('fragmenthidden', function () { scheduleGhostVisibilitySync(); notifyParentPosition(); });
  })();
  (function () {
<?= Exporter::mediaRuntimeJs(false) ?>
    sfMediaStatusInit();
    // Steuerung: immer stumm. Timed-Autoplay hier NICHT starten — sonst sieht man
    // „läuft“, der Ton kommt aber nur auf view/present_audience (Beamer-PC).
    function prepMedia() {
      document.querySelectorAll('video, audio').forEach(function (el) {
        el.muted = true;
        el.setAttribute('controls', '');
        el.volume = 0;
      });
    }
    function notifyParentMedia(action, el) {
      if (window.parent === window || !el) return;
      var mid = el.getAttribute('data-media-id');
      if (!mid) return;
      window.parent.postMessage({
        type: 'sf-media-cmd',
        action: action,
        mediaId: mid,
      }, '*');
    }
    // Lokale Controls in der Konsole → Play/Pause/Stop an die Präsentationsanzeige.
    document.addEventListener('play', function (e) {
      var el = e.target;
      if (!el || (el.tagName !== 'AUDIO' && el.tagName !== 'VIDEO')) return;
      if (el.dataset && el.dataset.sfSyncing === '1') return;
      el.muted = true;
      el.volume = 0;
      notifyParentMedia('play', el);
    }, true);
    document.addEventListener('pause', function (e) {
      var el = e.target;
      if (!el || (el.tagName !== 'AUDIO' && el.tagName !== 'VIDEO')) return;
      if (el.dataset && el.dataset.sfSyncing === '1') return;
      // Natürliches Ende: kein Stop an die Zuschauer — Reset würde Timed-Medien
      // erneut scharf schalten und ohne Loop wie eine Dauerschleife wirken.
      if (el.ended || (el.currentTime > 0 && el.currentTime >= (el.duration - 0.05))) {
        return;
      }
      notifyParentMedia('pause', el);
    }, true);
    function onSlideReady(slideEl) {
      prepMedia();
      sfMediaStatusTrackSlide(slideEl);
      // Kein sfArmMediaTriggers — Start kommt von present.js an die Zuschauer-Ansicht.
    }
    prepMedia();
    Reveal.on('ready', function (e) { onSlideReady(e.currentSlide); });
    Reveal.on('slidechanged', function (e) {
      prepMedia();
      sfResetMedia(e.previousSlide);
      onSlideReady(e.currentSlide);
    });
  })();
  <?php endif; ?>
</script>
</body>
</html>
