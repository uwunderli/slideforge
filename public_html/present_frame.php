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
$startSlide = max(0, (int)($_GET['start'] ?? 0));
$slideCount = count($slidesData['slides'] ?? []);
if ($startSlide >= $slideCount) {
    $startSlide = max(0, $slideCount - 1);
}
$sections = SlideRenderer::renderSections($slidesData, null);
$isMain = $mode === 'main';
$presentLayout = Auth::getPresentLayout($me);
$laserColor = $presentLayout['laserPointerColor'] ?? '#ff0000';
$laserSize = (int)($presentLayout['laserPointerSize'] ?? 24);
$laserTrail = !empty($presentLayout['laserPointerTrail']);
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
<?php if ($isMain): ?>
<script>
window.SF_LASER = <?= json_encode(['color' => $laserColor, 'size' => $laserSize, 'trail' => $laserTrail], JSON_UNESCAPED_UNICODE) ?>;
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
    keyboard: <?= $isMain ? 'true' : 'false' ?>,
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
    window.SlideForgePresentLaser?.init();
  });
  (function () {
    const playTimers = [];
<?= Exporter::mediaRuntimeJs(false) ?>
    sfMediaStatusInit();
    function prepMedia() {
      // Im Präsentationsmodus soll der Ton NICHT hier abgespielt werden (das übernimmt
      // die echte Präsentation auf dem zweiten Bildschirm) - dafür immer Steuerelemente
      // zeigen, damit man von hier aus starten/stoppen kann.
      document.querySelectorAll('video, audio').forEach(function (el) {
        el.muted = true;
        el.setAttribute('controls', '');
      });
    }
    function onSlideReady(slideEl) {
      prepMedia();
      sfMediaStatusTrackSlide(slideEl);
      sfArmMediaTriggers(slideEl, playTimers);
    }
    document.addEventListener('sf-media-all-ready', function () {
      var slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
      if (slide) sfArmMediaTriggers(slide, playTimers);
    });
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
