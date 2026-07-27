<?php
/** @var bool $canEdit */
/** @var bool $isTemplateMode */
?>
<div class="ribbon-widget-shell" data-widget-id="widget-slide-bg-color">
  <div class="ribbon-slide-bg-color-inner ribbon-object-farbe-solid ribbon-slide-farbe-solid">
    <div class="ribbon-farben-grid">
      <div class="ribbon-object-fill-colors" id="ribbonSlideFillColors">
        <div class="ribbon-farben-row">
          <label class="ribbon-color-picker-wrap">
            <span class="sr-only"><?= h(t('bg.color')) ?></span>
            <input type="color" id="bgColorInput" value="#111111" title="<?= h(t('bg.color')) ?>">
          </label>
          <div class="ribbon-brand-dropdown-wrap" data-ribbon-brand-menu="bgColor">
            <button type="button" class="ribbon-brand-dropdown-btn" id="bgBrandBtn" aria-expanded="false" aria-haspopup="true">
              <span><?= h(t('bg.brand_colors')) ?></span>
              <span class="ribbon-brand-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="ribbon-brand-dropdown-panel" id="bgBrandPalette" hidden role="menu"></div>
          </div>
        </div>
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

<div class="ribbon-widget-shell" data-widget-id="widget-slide-transition">
  <div class="ribbon-slide-transition-inner">
    <input type="hidden" id="transitionSelect" value="slide">
    <div id="transitionPickerGroup" class="ribbon-tall-row ribbon-slide-transition-picker"></div>
  </div>
</div>

<div class="ribbon-widget-shell" data-widget-id="widget-slide-autoadvance">
  <div class="ribbon-slide-autoadvance-inner">
    <label class="ribbon-slide-field" for="autoAdvanceInput">
      <span class="ribbon-slide-field-label"><?= h(t('ribbon.slide_autoadvance_short')) ?></span>
      <input type="number" id="autoAdvanceInput" min="0" step="1" value="0" aria-label="<?= h(t('bg.autoadvance_label')) ?>">
    </label>
  </div>
</div>
