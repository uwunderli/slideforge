<?php
/** @var bool $canEdit */
?>
<div class="editor-media-insert-triggers" hidden aria-hidden="true">
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
