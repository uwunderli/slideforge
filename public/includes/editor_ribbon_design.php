<?php
/** @var bool $canEdit */
/** @var bool $isTemplateMode */
?>
<?php /* 4.0: Einzelbefehle (kind 'field', config/ribbon_commands.php): das Element mit data-widget-id wandert in seine Zelle. */ ?>
<div class="ribbon-slide-bg-color-inner ribbon-object-farbe-solid ribbon-slide-farbe-solid" data-widget-id="field-slide-bg-color">
  <div class="ribbon-farben-grid">
    <div class="ribbon-object-fill-colors" id="ribbonSlideFillColors">
      <div class="ribbon-farben-row">
        <label class="ribbon-color-picker-wrap">
          <span class="sr-only"><?= h(t('bg.color')) ?></span>
          <input type="color" id="bgColorInput" value="#111111" title="<?= h(t('bg.color')) ?>" data-field-control>
        </label>
      </div>
    </div>
  </div>
</div>

<div class="ribbon-widget-shell" data-widget-id="widget-slide-bg-preview">
  <div class="ribbon-slide-bg-preview-inner ribbon-slide-bg-panel">
    <div class="bg-panel ribbon-slide-bg-fill" data-bgtype="fill">
      <div id="bgFillPreviewWrap" class="ribbon-slide-bg-preview ribbon-slide-bg-preview--fill bg-asset-preview" title="<?= h(t('ribbon.slide_bg_preview')) ?>">
        <div id="bgFillPreview" class="ribbon-slide-bg-fill-swatch" aria-hidden="true">
          <span class="ribbon-slide-bg-fill-label"><?= h(t('bg.none_preview')) ?></span>
        </div>
      </div>
    </div>
    <div class="bg-panel ribbon-slide-bg-image" data-bgtype="image" hidden>
      <div class="ribbon-slide-bg-media-row">
        <div id="bgImagePreviewWrap" class="ribbon-slide-bg-preview bg-asset-preview" hidden>
          <img id="bgImagePreview" alt="">
          <button type="button" id="bgImageRemove" class="ribbon-slide-bg-remove" title="<?= h(t('bg.remove')) ?>">×</button>
        </div>
      </div>
    </div>
    <div class="bg-panel ribbon-slide-bg-video" data-bgtype="video" hidden>
      <div class="ribbon-slide-bg-media-row">
        <div id="bgVideoPreviewWrap" class="ribbon-slide-bg-preview bg-asset-preview ribbon-slide-bg-preview--video" hidden>
          <video id="bgVideoPreview" muted loop autoplay playsinline></video>
          <button type="button" id="bgVideoRemove" class="ribbon-slide-bg-remove" title="<?= h(t('bg.remove')) ?>">×</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php /* 4.0: Übergänge als Einzelknöpfe; Symbol und Beschriftung setzt editor.js (initTransitionPicker, TRANSITION_OPTIONS). */ ?>
<input type="hidden" id="transitionSelect" value="slide">
<?php foreach (['none', 'fade', 'slide', 'convex', 'concave', 'zoom'] as $sfTransition): ?>
<button type="button" class="ribbon-btn ribbon-btn-tall ribbon-grid-btn effect-icon-btn sf-transition-btn" data-widget-id="field-transition-<?= $sfTransition ?>" data-icon-value="<?= $sfTransition ?>" data-field-control></button>
<?php endforeach; ?>

<div class="ribbon-slide-autoadvance-inner" data-widget-id="field-slide-autoadvance">
  <label class="ribbon-slide-field" for="autoAdvanceInput">
    <span class="ribbon-slide-field-label"><?= h(t('ribbon.slide_autoadvance_short')) ?></span>
    <input type="number" id="autoAdvanceInput" min="0" step="1" value="0" aria-label="<?= h(t('bg.autoadvance_label')) ?>" data-field-control>
  </label>
</div>
