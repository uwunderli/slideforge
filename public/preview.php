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
/* Abspielen im Editor (3.11.0): nur diese eine Folie, Effekte Schritt für Schritt ab Schritt 0.
   Der Editor steuert von aussen (gleiche Herkunft) über window.sfPlay; kein Folienwechsel möglich. */
$playMode = ($_GET['play'] ?? '') === '1' && isset($_GET['slide']);
if ($playMode) {
    $slidesData['slides'] = array_slice($slidesData['slides'] ?? [], $startSlide, 1);
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
<style>html,body{margin:0;height:100%;background:#000;}<?php if ($playMode): ?>body{cursor:pointer;}<?php endif; ?></style>
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
<?php if ($playMode): ?>
    hash: false,
    controls: false,
    progress: false,
    keyboard: false,
    touch: false,
    overview: false,
    loop: false,
<?php else: ?>
    hash: true,
    controls: <?= ($meta['show_controls'] ?? true) ? 'true' : 'false' ?>,
    progress: <?= ($meta['show_progress'] ?? true) ? 'true' : 'false' ?>,
<?php endif; ?>
    center: false, // unsere Objekte sind absolut positioniert (x/y) - reveal.js soll nichts selbst verschieben
    margin: 0
  });

  // Medien: Ladehinweis, automatische Wiedergabe nach Verzögerung.
<?= Exporter::mediaRuntimeJs(false) ?>
<?= Exporter::mediaSlideBootJs(false) ?>
<?php if ($playMode): ?>
  /* Abspielen (3.11.0): Schritt 0 = Folie vor dem ersten Klick. Weiter/zurück nur innerhalb der Folie;
     am Ende bleibt sie stehen. Jeder Schrittwechsel geht als Nachricht an den Editor (Anzeige «Schritt x von y»,
     Mitlaufen). Tasten und Klicks hier gehen ebenfalls an den Editor, damit nur eine Stelle steuert. */
  (function () {
    function total() {
      var slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
      if (!slide) return 0;
      var seen = {};
      slide.querySelectorAll('.fragment[data-fragment-index]').forEach(function (el) { seen[el.getAttribute('data-fragment-index')] = 1; });
      return Object.keys(seen).length;
    }
    function step() {
      var f = Reveal.getIndices().f;
      return typeof f === 'number' ? f + 1 : 0;
    }
    function melden() {
      try { window.parent.postMessage({ type: 'sf-play-step', step: step(), total: total() }, window.location.origin); } catch (e) {}
    }
    window.sfPlay = {
      next: function () { if (Reveal.availableFragments().next) Reveal.nextFragment(); },
      prev: function () { if (Reveal.availableFragments().prev) Reveal.prevFragment(); },
      state: function () { return { step: step(), total: total() }; }
    };
    Reveal.on('ready', function () {
      window.SlideForgeRevealAutoSlide?.install(Reveal);
      Reveal.slide(0, 0, -1);
      melden();
    });
    Reveal.on('fragmentshown', melden);
    Reveal.on('fragmenthidden', melden);
    /* 3.12.1: jede Taste an den Editor; welche Taste was tut, bestimmt dort der Katalog (Weitere Befehle, scope «play»). */
    document.addEventListener('keydown', function (e) {
      if (['Control', 'Shift', 'Alt', 'Meta', 'AltGraph'].indexOf(e.key) !== -1) return;
      if (!e.ctrlKey && !e.metaKey && !e.altKey
          && [' ', 'ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'PageDown', 'PageUp', 'Escape', 'Enter', 'Home', 'End'].indexOf(e.key) !== -1) {
        e.preventDefault();
      }
      try {
        window.parent.postMessage({ type: 'sf-play-key', key: e.key, code: e.code, ctrlKey: e.ctrlKey, metaKey: e.metaKey, altKey: e.altKey, shiftKey: e.shiftKey }, window.location.origin);
      } catch (err) {}
    });
    document.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('video, audio, a')) return;
      try { window.parent.postMessage({ type: 'sf-play-key', click: true }, window.location.origin); } catch (err) {}
    });
  })();
<?php else: ?>
  /* In der Vorschau alle Einblendungen sofort zeigen.
     Die Vorschau dient dem Prüfen der Gestaltung, nicht dem Vorführen: Der Editor
     zeigt immer die fertige Folie, reveal.js dagegen baut Fragmente nacheinander auf.
     Dadurch fehlten in der Vorschau Objekte, die im Editor sichtbar waren — was wie
     ein Darstellungsfehler aussah, aber der Animationsstand war. Präsentation und
     Export bleiben unberührt. */
  function sfShowAllFragments() {
    var slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
    if (!slide) return;
    slide.querySelectorAll('.fragment').forEach(function (el) {
      // Ausblende-Hüllen (sf-exit) bleiben unsichtbar geschaltet = Objekt sichtbar.
      el.classList.toggle('visible', !el.classList.contains('sf-exit'));
      el.classList.remove('current-fragment');
    });
  }

  Reveal.on('ready', function () {
    window.SlideForgeRevealAutoSlide?.install(Reveal);
    sfShowAllFragments();
  });
  Reveal.on('slidechanged', sfShowAllFragments);
  Reveal.on('fragmentshown', sfShowAllFragments);
  Reveal.on('fragmenthidden', sfShowAllFragments);
  <?php if (isset($_GET['slide'])): ?>
  Reveal.on('ready', function () { Reveal.slide(<?= (int)$startSlide ?>, 0); });
  <?php endif; ?>
<?php endif; ?>
</script>
</body>
</html>
