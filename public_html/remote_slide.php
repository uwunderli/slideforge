<?php
require __DIR__ . '/../config.php';
Auth::requireLogin();

$id = $_GET['id'] ?? '';
$slideIndex = max(0, (int)($_GET['slide'] ?? 0));
$me = Auth::currentUser();
if (!$me || !Presentation::canView($id, $me['id'])) {
    http_response_code(403);
    exit;
}

$meta = Presentation::getMeta($id);
$slidesData = Presentation::getSlides($id);
$slides = $slidesData['slides'] ?? [];
if (!isset($slides[$slideIndex])) {
    http_response_code(404);
    exit;
}

$slide = $slides[$slideIndex];
$w = max(1, (int)$meta['width']);
$h = max(1, (int)$meta['height']);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, max-age=30');
?>
<div class="remote-preview-slide" style="width:<?= $w ?>px;height:<?= $h ?>px;transform-origin:top left;">
<?= SlideRenderer::renderSlideThumbnailHtml($slide, null) ?>
</div>
