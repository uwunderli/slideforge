<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();
$me = Auth::currentUser();

$id = $_GET['id'] ?? '';
if (!Presentation::canView($id, $me['id'])) {
    http_response_code(403);
    die(t('export.no_access'));
}

$meta = Presentation::getMeta($id);
$slidesData = Presentation::getSlides($id);
$sections = SlideRenderer::renderSections($slidesData);
?>
<!DOCTYPE html>
<html lang="<?= h(I18n::currentLang()) ?>">
<head>
<meta charset="UTF-8">
<title><?= h($meta['title']) ?></title>
<?= FontLibrary::headMarkup('', $slidesData, true) ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/theme/black.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/css/print/pdf.css">
<style>
  html, body { margin: 0; background: #000; }
  #pdfHint {
    position: fixed; top: 0; left: 0; right: 0; z-index: 999;
    background: #1d2a17; color: #cfe6a8; font-family: sans-serif;
    padding: 14px 20px; text-align: center; font-size: 0.95rem;
  }
  #pdfHint button { margin-left: 14px; padding: 6px 14px; cursor: pointer; }
  @media print { #pdfHint { display: none; } }
</style>
</head>
<body>
<div id="pdfHint">
  <?= h(t('export.pdf_hint')) ?>
  <button type="button" onclick="window.print()"><?= h(t('export.pdf_print_btn')) ?></button>
</div>
<div class="reveal">
  <div class="slides">
    <?= $sections ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/reveal.js@5.1.0/dist/reveal.js"></script>
<script>
  Reveal.initialize({
    width: <?= (int)$meta['width'] ?>,
    height: <?= (int)$meta['height'] ?>,
    hash: false,
    controls: false,
    progress: false,
    center: false,
    margin: 0,
    // Im Druck-/PDF-Modus zeigt reveal.js ohnehin alle Fragmente sofort sichtbar an
    // (siehe css/print/pdf.css) - Animationen/Übergänge spielen für ein PDF keine Rolle.
    view: 'print'
  });
</script>
</body>
</html>
