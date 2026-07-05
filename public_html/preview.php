<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$id = $_GET['id'] ?? '';
if (!Presentation::canView($id, $me['id'])) {
    http_response_code(403);
    die('Kein Zugriff auf diese Präsentation.');
}

$meta = Presentation::getMeta($id);
$slidesData = Presentation::getSlides($id);
$slideCount = count($slidesData['slides'] ?? []);
$startSlide = 0;
if (isset($_GET['slide'])) {
    $startSlide = max(0, min(max(0, $slideCount - 1), (int)$_GET['slide']));
}
$sections = SlideRenderer::renderSections($slidesData);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($meta['title']) ?></title>
<?= FontLibrary::headMarkup('fonts.css.php') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/theme/black.css">
<style>html,body{margin:0;height:100%;background:#000;}</style>
<?= Exporter::mediaStatusMarkup() ?>
</head>
<body>
<div class="reveal">
  <div class="slides">
    <?= $sections ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.js"></script>
<script src="assets/js/reveal-autoslide.js?v=<?= ASSET_VERSION ?>"></script>
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
    window.SlideForgeRevealAutoSlide?.install(Reveal);
  });
  <?php if (isset($_GET['slide'])): ?>
  Reveal.on('ready', function () { Reveal.slide(<?= (int)$startSlide ?>, 0); });
  <?php endif; ?>

  window.addEventListener('message', function (e) {
    if (e.origin !== window.location.origin) return;
    if (!e.data || e.data.type !== 'sf_goto') return;
    const index = e.data.index;
    if (typeof index !== 'number' || index < 0) return;
    function go() { Reveal.slide(index, 0); }
    if (Reveal.isReady && Reveal.isReady()) go();
    else Reveal.on('ready', go);
  });
</script>
</body>
</html>
