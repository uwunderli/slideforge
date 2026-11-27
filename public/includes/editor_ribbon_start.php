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
$rbIconMarkClear = '<svg class="ribbon-type-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 19h7"/><path d="M8 15l8-10 3 3-10 8z"/><path d="M15 15l5 5M20 15l-5 5"/></svg>';
$rbTemplateSample = h(t('ribbon.template_style_sample'));
?>
<?php
/* 4.0 (Urs 27.11.: «Ok, umsetzen»): Zeichen, Absatz und Schriftfarbe sind keine festen Blöcke mehr. Jedes Element ist ein eigener
   Menüband-Befehl (kind 'field', config/ribbon_commands.php) und liegt hier einzeln im Vorrat (data-widget-id = templateId).
   Das Element wandert beim Zeichnen in seine Zelle und zurück — die IDs (rb_*) und alle Zuhörer in editor.js bleiben.
   data-field-control = das bedienbare Element (bekommt data-ribbon-command: Tooltip mit Kürzel, Tastentipps). */
?>
<div class="ribbon-wcell ribbon-wcell-font ribbon-field-item" data-widget-id="field-text-font">
  <label class="ribbon-field ribbon-field-font">
    <span class="sr-only"><?= h(t('props.font')) ?></span>
    <select id="rb_font" disabled title="<?= h(t('props.font')) ?>" data-field-control></select>
  </label>
</div>
<div class="ribbon-wcell ribbon-wcell-size ribbon-field-item" data-widget-id="field-text-fontsize">
  <label class="ribbon-field ribbon-field-num">
    <span class="sr-only"><?= h(t('ribbon.font_size')) ?></span>
    <input type="number" id="rb_fontsize" min="1" disabled aria-label="<?= h(t('ribbon.font_size')) ?>" title="<?= h(t('ribbon.font_size')) ?>" data-field-control>
  </label>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-bold">
  <button type="button" class="ribbon-icon-btn" id="rb_bold" disabled title="<?= h(t('props.bold')) ?>" data-field-control>B</button>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-italic">
  <button type="button" class="ribbon-icon-btn" id="rb_italic" disabled title="<?= h(t('props.italic')) ?>" data-field-control>I</button>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-underline">
  <button type="button" class="ribbon-icon-btn" id="rb_underline" disabled title="<?= h(t('props.underline')) ?>" data-field-control>U</button>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-strike">
  <button type="button" class="ribbon-icon-btn" id="rb_strikethrough" disabled title="<?= h(t('props.strikethrough')) ?>" data-field-control>S</button>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-uppercase">
  <button type="button" class="ribbon-icon-btn ribbon-icon-btn-wide" id="rb_uppercase" disabled title="<?= h(t('props.uppercase')) ?>" data-field-control>AA</button>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-smallcaps">
  <button type="button" class="ribbon-icon-btn ribbon-icon-btn-wide" id="rb_smallcaps" disabled title="<?= h(t('props.smallcaps')) ?>" data-field-control>Aa</button>
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

<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-bullet">
  <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_bullet" disabled title="<?= h(t('ribbon.bullet_list')) ?>" data-field-control><?= $rbIconBullet ?></button>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-number">
  <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_number" disabled title="<?= h(t('ribbon.number_list')) ?>" data-field-control><?= $rbIconNumber ?></button>
</div>
<div class="ribbon-wcell ribbon-wcell-field ribbon-wcell-lineheight ribbon-field-item" data-widget-id="field-text-lineheight">
  <div class="ribbon-field-inline">
    <span class="ribbon-field-icon" title="<?= h(t('props.line_height')) ?>"><?= $rbIconLineHeight ?></span>
    <input type="number" id="rb_lineheight" min="0.8" max="3" step="0.05" disabled title="<?= h(t('props.line_height')) ?>" aria-label="<?= h(t('props.line_height')) ?>" data-field-control>
  </div>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-align-left">
  <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_align_left" disabled title="<?= h(t('ribbon.align_left')) ?>" data-align="left" data-field-control><?= $rbIconAlignLeft ?></button>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-align-center">
  <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_align_center" disabled title="<?= h(t('ribbon.align_center')) ?>" data-align="center" data-field-control><?= $rbIconAlignCenter ?></button>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-field-item" data-widget-id="field-text-align-right">
  <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_align_right" disabled title="<?= h(t('ribbon.align_right')) ?>" data-align="right" data-field-control><?= $rbIconAlignRight ?></button>
</div>
<div class="ribbon-wcell ribbon-wcell-field ribbon-wcell-letterspacing ribbon-field-item" data-widget-id="field-text-letterspacing">
  <div class="ribbon-field-inline">
    <span class="ribbon-field-icon" title="<?= h(t('props.letter_spacing')) ?>"><?= $rbIconLetterSpacing ?></span>
    <input type="number" id="rb_letterspacing" min="-0.2" max="1" step="0.05" disabled title="<?= h(t('props.letter_spacing')) ?>" aria-label="<?= h(t('props.letter_spacing')) ?>" data-field-control>
  </div>
</div>

<?php /* 3.12.52 (Urs 27.11.): «A» bzw. Marker wenden die zuletzt gewählte Farbe mit einem Klick an (wie Office); das Farbfeld öffnet die Wahl. */ ?>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-wcell-colorapply ribbon-field-item" data-widget-id="field-text-color-apply">
  <button type="button" class="ribbon-icon-btn ribbon-color-apply" id="rb_color_apply" data-apply-for="rb_color" disabled aria-label="<?= h(t('ribbon.color_apply')) ?>" title="<?= h(t('ribbon.color_apply')) ?>" data-field-control><span class="ribbon-color-apply-a">A</span><span class="ribbon-color-apply-bar" aria-hidden="true"></span></button>
</div>
<div class="ribbon-wcell ribbon-wcell-color ribbon-field-item" data-widget-id="field-text-color">
  <label class="ribbon-color-picker-wrap">
    <span class="sr-only"><?= h(t('ribbon.group_text_color')) ?></span>
    <input type="color" id="rb_color" value="#ffffff" disabled title="<?= h(t('ribbon.group_text_color')) ?>" data-field-control>
  </label>
</div>
<?php /* Markerfarbe (3.12.49, Urs 27.11.): wie die Schriftfarbe, [mark=#hex] auf Markierung bzw. ganzen Text; daneben «Kein Marker». */ ?>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-wcell-markapply ribbon-field-item" data-widget-id="field-text-mark-apply">
  <button type="button" class="ribbon-icon-btn ribbon-color-apply" id="rb_mark_apply" data-apply-for="rb_mark" disabled aria-label="<?= h(t('ribbon.mark_apply')) ?>" title="<?= h(t('ribbon.mark_apply')) ?>" data-field-control><span class="ribbon-color-apply-a ribbon-color-apply-a--mark">ab</span><span class="ribbon-color-apply-bar" aria-hidden="true"></span></button>
</div>
<div class="ribbon-wcell ribbon-wcell-color ribbon-wcell-mark ribbon-field-item" data-widget-id="field-text-mark-color">
  <label class="ribbon-color-picker-wrap">
    <span class="sr-only"><?= h(t('ribbon.mark_color')) ?></span>
    <input type="color" id="rb_mark" value="#fff176" disabled aria-label="<?= h(t('ribbon.mark_color')) ?>" title="<?= h(t('ribbon.mark_color')) ?>" data-field-control>
  </label>
</div>
<div class="ribbon-wcell ribbon-wcell-icon ribbon-wcell-markclear ribbon-field-item" data-widget-id="field-text-mark-clear">
  <button type="button" class="ribbon-icon-btn ribbon-icon-btn-svg" id="rb_mark_clear" disabled aria-label="<?= h(t('ribbon.mark_clear')) ?>" title="<?= h(t('ribbon.mark_clear')) ?>" data-field-control><?= $rbIconMarkClear ?></button>
</div>
<div class="ribbon-wcell ribbon-wcell-opacity ribbon-field-item" data-widget-id="field-text-opacity">
  <div class="ribbon-field-inline ribbon-field-opacity">
    <span class="ribbon-field-icon ribbon-opacity-icon" id="rb_opacityIcon" title="<?= h(t('props.opacity')) ?>"><?= $rbIconOpacity ?></span>
    <input type="range" data-hub-range data-hub-range-unit="%" id="rb_opacity" class="ribbon-opacity-slider" min="0" max="100" value="100" disabled aria-label="<?= h(t('props.opacity')) ?>" data-field-control>
    <span class="ribbon-opacity-val" id="rb_opacityVal" aria-hidden="true">100</span>
  </div>
</div>
