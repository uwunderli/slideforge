<?php
/** @var bool $canEdit */
$rbIconFillNone = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M7 17L17 7"/></svg>';
$rbIconFillSolid = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2" fill="currentColor" fill-opacity="0.35" stroke="currentColor"/></svg>';
$rbIconFillGradient = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><defs><linearGradient id="rbGradIcon" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="currentColor" stop-opacity="0.35"/><stop offset="100%" stop-color="currentColor"/></linearGradient></defs><rect x="4" y="4" width="16" height="16" rx="2" fill="url(#rbGradIcon)" stroke="currentColor"/></svg>';
$rbIconOpacity = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="11" height="11" rx="1"/><rect x="9" y="9" width="11" height="11" rx="1" fill="currentColor" fill-opacity="0.28" stroke="currentColor"/></svg>';
?>
<div class="ribbon-group ribbon-group-object-fill" id="ribbonObjectFillGroup">
  <div class="ribbon-object-farbe-grid">
    <div class="ribbon-object-fill-modes" id="ribbonObjectFillModes">
      <button type="button" class="ribbon-btn ribbon-btn-tall ribbon-object-fill-none" id="rb_objFillNone" disabled title="<?= h(t('props.fill_none')) ?>">
        <?= $rbIconFillNone ?>
        <span class="ribbon-btn-label"><?= h(t('props.fill_none')) ?></span>
      </button>
      <button type="button" class="ribbon-btn ribbon-btn-tall ribbon-object-fill-solid" id="rb_objFillSolid" disabled title="<?= h(t('props.fill_solid')) ?>">
        <?= $rbIconFillSolid ?>
        <span class="ribbon-btn-label"><?= h(t('props.fill_solid')) ?></span>
      </button>
      <button type="button" class="ribbon-btn ribbon-btn-tall ribbon-object-fill-gradient" id="rb_objFillGradient" disabled title="<?= h(t('props.fill_gradient')) ?>">
        <?= $rbIconFillGradient ?>
        <span class="ribbon-btn-label"><?= h(t('props.fill_gradient')) ?></span>
      </button>
    </div>

    <div class="ribbon-object-farbe-solid" id="ribbonObjectFillSolid" hidden>
      <div class="ribbon-object-fill-colors" id="ribbonObjectFillColors">
        <label class="ribbon-color-picker-wrap ribbon-object-fill-picker">
          <span class="sr-only"><?= h(t('props.fill_color')) ?></span>
          <input type="color" id="rb_objFill" value="#3a6c8d" disabled title="<?= h(t('props.fill_color')) ?>">
        </label>
        <div class="ribbon-object-brand-swatches brand-palette mini" id="rb_objFillBrandSwatches" role="group" aria-label="<?= h(t('bg.brand_colors')) ?>"></div>
      </div>
    </div>

    <div class="ribbon-object-farbe-gradient" id="ribbonObjectFillGradientEditor" hidden>
      <div class="ribbon-object-grad-row">
        <div class="ribbon-object-grad-field">
          <span><?= h(t('props.color1')) ?></span>
          <label class="ribbon-color-picker-wrap ribbon-object-grad-picker">
            <span class="sr-only"><?= h(t('props.color1')) ?></span>
            <input type="color" id="objGradColor1" value="#3a6c8d" disabled title="<?= h(t('props.color1')) ?>">
          </label>
          <div class="ribbon-object-brand-swatches brand-palette mini" id="rb_objGrad1BrandSwatches" role="group" aria-label="<?= h(t('bg.brand_colors')) ?>"></div>
        </div>
        <div class="ribbon-object-grad-field">
          <span><?= h(t('props.color2')) ?></span>
          <label class="ribbon-color-picker-wrap ribbon-object-grad-picker">
            <span class="sr-only"><?= h(t('props.color2')) ?></span>
            <input type="color" id="objGradColor2" value="#87b42b" disabled title="<?= h(t('props.color2')) ?>">
          </label>
          <div class="ribbon-object-brand-swatches brand-palette mini" id="rb_objGrad2BrandSwatches" role="group" aria-label="<?= h(t('bg.brand_colors')) ?>"></div>
        </div>
      </div>
      <div class="ribbon-object-grad-angle">
        <label for="objGradAngle" id="objGradAngleLabel"><?= h(t('props.angle')) ?> (90°)</label>
        <input type="range" id="objGradAngle" min="0" max="360" value="90" disabled>
      </div>
      <div class="object-gradient-preview" id="objectGradientPreview" aria-hidden="true"></div>
    </div>

    <div class="ribbon-object-farbe-opacity" id="ribbonObjectFillOpacity" hidden>
      <div class="ribbon-field-inline ribbon-field-opacity">
        <span class="ribbon-field-icon ribbon-opacity-icon" id="rb_objOpacityIcon" title="<?= h(t('props.opacity')) ?>"><?= $rbIconOpacity ?></span>
        <input type="range" id="rb_objOpacity" class="ribbon-opacity-slider" min="0" max="100" value="100" disabled aria-label="<?= h(t('props.opacity')) ?>">
        <span class="sr-only" id="rb_objOpacityVal">100</span>
      </div>
    </div>
  </div>
  <div class="ribbon-group-label"><?= h(t('ribbon.group_object_fill')) ?></div>
</div>

<div class="ribbon-group ribbon-group-object-stroke" id="ribbonObjectStrokeGroup">
  <div class="ribbon-object-kontur-grid">
    <div class="ribbon-object-kontur-row">
      <label class="ribbon-color-picker-wrap ribbon-object-stroke-picker">
        <span class="sr-only"><?= h(t('props.border_color')) ?></span>
        <input type="color" id="rb_objStroke" value="#ffffff" disabled title="<?= h(t('props.border_color')) ?>">
      </label>
      <label class="ribbon-field ribbon-field-num ribbon-object-stroke-width">
        <span class="sr-only"><?= h(t('props.border_width')) ?></span>
        <input type="number" id="rb_objStrokeWidth" min="0" step="1" value="0" disabled title="<?= h(t('props.border_width')) ?>" aria-label="<?= h(t('props.border_width')) ?>">
      </label>
    </div>
    <div class="ribbon-object-brand-swatches brand-palette mini" id="rb_objStrokeBrandSwatches" role="group" aria-label="<?= h(t('bg.brand_colors')) ?>"></div>
  </div>
  <div class="ribbon-group-label"><?= h(t('ribbon.group_object_stroke')) ?></div>
</div>

<div class="ribbon-group ribbon-group-object-shapes" id="ribbonObjectShapesGroup" hidden>
  <div class="ribbon-object-shapes-content" id="ribbonObjectShapesPanel"></div>
  <div class="ribbon-group-label"><?= h(t('ribbon.group_object_shapes')) ?></div>
</div>
