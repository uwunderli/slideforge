<?php
/** @var bool $canEdit */
$rbIconLineHeight = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h11"/><path d="M4 12h11"/><path d="M4 17h11"/><path d="M19 8v8"/><path d="M16.5 10.5L19 8l2.5 2.5"/><path d="M16.5 13.5L19 16l2.5-2.5"/></svg>';
$rbIconLetterSpacing = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V6l2.5 2.5L14 6v12"/><path d="M3 12h2.5"/><path d="M18.5 12H21"/><path d="M3 12l1.8-1.5"/><path d="M3 12l1.8 1.5"/><path d="M21 12l-1.8-1.5"/><path d="M21 12l-1.8 1.5"/></svg>';
$rbIconOpacity = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="11" height="11" rx="1"/><rect x="9" y="9" width="11" height="11" rx="1" fill="currentColor" fill-opacity="0.28" stroke="currentColor"/></svg>';
$rbIconBullet = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4" cy="6" r="1.25" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.25" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.25" fill="currentColor" stroke="none"/></svg>';
$rbIconNumber = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10 6h11M10 12h11M10 18h11"/><path d="M4 7.5h1.5M4.5 6v3"/><path d="M4 16.5c0-1 .8-1.5 1.5-1.5s1.5.6 1.5 1.5c0 .8-.7 1.2-1.5 1.8L4 20"/></svg>';
$rbIconAlignLeft = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h14"/></svg>';
$rbIconAlignCenter = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M5 18h14"/></svg>';
$rbIconAlignRight = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M10 12h10M6 18h14"/></svg>';
$rbTemplateSample = h(t('ribbon.template_style_sample'));
?>
<div class="ribbon-widget-grid ribbon-widget-grid--zeichen" data-widget-id="widget-zeichen" id="ribbonStartZeichen">
  <div class="ribbon-wcell ribbon-wcell-font">
    <label class="ribbon-field ribbon-field-font">
      <span class="sr-only"><?= h(t('props.font')) ?></span>
      <select id="rb_font" disabled title="<?= h(t('props.font')) ?>"></select>
    </label>
  </div>
  <div class="ribbon-wcell ribbon-wcell-size">
    <label class="ribbon-field ribbon-field-num">
      <span class="sr-only"><?= h(t('props.size')) ?></span>
      <input type="number" id="rb_fontsize" min="1" disabled title="<?= h(t('props.size')) ?>">
    </label>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon" id="ribbonStartFormat">
    <button type="button" class="ribbon-icon-btn" id="rb_bold" disabled title="<?= h(t('props.bold')) ?>">B</button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn" id="rb_italic" disabled title="<?= h(t('props.italic')) ?>">I</button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn" id="rb_underline" disabled title="<?= h(t('props.underline')) ?>">U</button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn" id="rb_strikethrough" disabled title="<?= h(t('props.strikethrough')) ?>">S</button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn ribbon-icon-btn-wide" id="rb_uppercase" disabled title="<?= h(t('props.uppercase')) ?>">AA</button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn ribbon-icon-btn-wide" id="rb_smallcaps" disabled title="<?= h(t('props.smallcaps')) ?>">Aa</button>
  </div>
</div>

<div class="ribbon-widget-grid ribbon-widget-grid--templates" data-widget-id="widget-templates">
  <div class="ribbon-wcell ribbon-wcell-templates">
    <div class="ribbon-vorlagen-wrap" data-ribbon-template-menu id="rb_templateGalleryWrap">
      <div class="ribbon-template-gallery" id="rb_templateGallery" role="listbox" aria-label="<?= h(t('ribbon.group_templates')) ?>"></div>
      <button type="button" class="ribbon-template-more-btn" id="rb_templateBtn" disabled aria-expanded="false" aria-haspopup="true" title="<?= h(t('ribbon.group_templates')) ?>">
        <span class="ribbon-brand-chevron" aria-hidden="true">▾</span>
      </button>
      <div class="ribbon-template-dropdown-panel" id="rb_templatePalette" hidden role="menu"></div>
    </div>
  </div>
</div>

<div class="ribbon-widget-grid ribbon-widget-grid--absatz" data-widget-id="widget-absatz" id="ribbonStartAbsatz">
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_bullet" disabled title="<?= h(t('ribbon.bullet_list')) ?>"><?= $rbIconBullet ?></button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_number" disabled title="<?= h(t('ribbon.number_list')) ?>"><?= $rbIconNumber ?></button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-field ribbon-wcell-lineheight">
    <div class="ribbon-field-inline">
      <span class="ribbon-field-icon" title="<?= h(t('props.line_height')) ?>"><?= $rbIconLineHeight ?></span>
      <input type="number" id="rb_lineheight" min="0.8" max="3" step="0.05" disabled title="<?= h(t('props.line_height')) ?>" aria-label="<?= h(t('props.line_height')) ?>">
    </div>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_align_left" disabled title="<?= h(t('ribbon.align_left')) ?>" data-align="left"><?= $rbIconAlignLeft ?></button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_align_center" disabled title="<?= h(t('ribbon.align_center')) ?>" data-align="center"><?= $rbIconAlignCenter ?></button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-icon">
    <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_align_right" disabled title="<?= h(t('ribbon.align_right')) ?>" data-align="right"><?= $rbIconAlignRight ?></button>
  </div>
  <div class="ribbon-wcell ribbon-wcell-field ribbon-wcell-letterspacing">
    <div class="ribbon-field-inline">
      <span class="ribbon-field-icon" title="<?= h(t('props.letter_spacing')) ?>"><?= $rbIconLetterSpacing ?></span>
      <input type="number" id="rb_letterspacing" min="-0.2" max="1" step="0.05" disabled title="<?= h(t('props.letter_spacing')) ?>" aria-label="<?= h(t('props.letter_spacing')) ?>">
    </div>
  </div>
</div>

<div class="ribbon-widget-grid ribbon-widget-grid--farben" data-widget-id="widget-text-colors" id="ribbonStartColors">
  <div class="ribbon-wcell ribbon-wcell-color">
    <label class="ribbon-color-picker-wrap">
      <span class="sr-only"><?= h(t('props.color')) ?></span>
      <input type="color" id="rb_color" value="#ffffff" disabled title="<?= h(t('props.color')) ?>">
    </label>
  </div>
  <div class="ribbon-wcell ribbon-wcell-brand">
    <div class="ribbon-brand-dropdown-wrap" data-ribbon-brand-menu="textColor">
      <button type="button" class="ribbon-brand-dropdown-btn" id="rb_brandBtn" disabled aria-expanded="false" aria-haspopup="true" title="<?= h(t('bg.brand_colors')) ?>">
        <span class="ribbon-brand-btn-label"><?= h(t('ribbon.brand_colors_short')) ?></span>
        <span class="ribbon-brand-chevron" aria-hidden="true">▾</span>
      </button>
      <div class="ribbon-brand-dropdown-panel" id="rb_brandPalette" hidden role="menu" aria-label="<?= h(t('bg.brand_colors')) ?>"></div>
    </div>
  </div>
  <div class="ribbon-wcell ribbon-wcell-opacity">
    <div class="ribbon-field-inline ribbon-field-opacity">
      <span class="ribbon-field-icon ribbon-opacity-icon" id="rb_opacityIcon" title="<?= h(t('props.opacity')) ?>"><?= $rbIconOpacity ?></span>
      <input type="range" id="rb_opacity" class="ribbon-opacity-slider" min="0" max="100" value="100" disabled aria-label="<?= h(t('props.opacity')) ?>">
      <span class="ribbon-opacity-val" id="rb_opacityVal" aria-hidden="true">100</span>
    </div>
  </div>
</div>
