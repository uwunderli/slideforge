<?php
/** @var bool $canEdit */
/** @var array $webdavDrives */
?>
<div class="editor-media-insert-triggers" hidden aria-hidden="true">
  <input type="file" id="objImageInput" accept="image/jpeg,image/png,image/gif,image/webp">
  <input type="file" id="objAudioInput" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp4">
  <input type="file" id="objVideoInput" accept="video/mp4,video/webm">
  <?php if ($canEdit && Config::pixabayEnabled()): ?>
  <button type="button" id="pixabayOpenBtn" tabindex="-1"></button>
  <?php endif; ?>
  <?php if ($canEdit && Config::iconifyEnabled()): ?>
  <button type="button" id="iconifyOpenBtn" tabindex="-1"></button>
  <?php endif; ?>
  <?php if ($canEdit && Config::openclipartEnabled()): ?>
  <button type="button" id="openclipartOpenBtn" tabindex="-1"></button>
  <?php endif; ?>
  <?php if ($canEdit && count($webdavDrives) > 0): ?>
  <?php foreach ($webdavDrives as $wdDrive): ?>
  <button type="button" class="webdav-drive-btn" data-drive-id="<?= h($wdDrive['id']) ?>" data-drive-label="<?= h($wdDrive['label']) ?>" tabindex="-1"></button>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
