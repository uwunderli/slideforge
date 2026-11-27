<?php
/** @var bool $canEdit */
?>
<div class="editor-media-insert-triggers" hidden aria-hidden="true">
  <input type="file" id="objImageInput" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
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
</div>
