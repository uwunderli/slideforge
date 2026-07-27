<?php
/** @var bool $canEdit */
/** @var bool $isTemplateMode */
/** @var bool $isLayoutSetMode */
/** @var bool $showMasterSlideNav */
/** @var bool $masterSlideNavActive */
/** @var string $masterSlideSetId */
/** @var string $masterSlideReturnId */
/** @var int $masterSlideReturnSlide */
/** @var string $perm */
/** @var string $id */
/** @var array $meta */
/** @var array $acl */
/** @var string $publicUrl */
/** @var bool $logosImporterEnabled */
?>
<?php require __DIR__ . '/editor_ribbon_start.php'; ?>

<?php if ($isLayoutSetMode): ?>
<?php
$elementZones = LayoutSet::elementZones($meta);
$logosSlideInsertRoles = array_values(array_filter(
    LayoutSet::slideInsertRolesFromZones($elementZones),
    fn($role) => !in_array($role, LayoutSet::STANDARD_ELEMENT_ROLES, true)
));
$activeLogosRoles = [];
foreach ($elementZones as $zone => $roles) {
    if ($zone === 'unused' || !is_array($roles)) {
        continue;
    }
    foreach ($roles as $role) {
        $activeLogosRoles[$role] = true;
    }
}
?>
<div class="ribbon-group ribbon-group-wide" data-widget-id="widget-layout-elements">
  <div class="ribbon-group-label"><?= h(t('editor.tab_elements')) ?></div>
  <div class="ribbon-group-content ribbon-tool-panel">
    <div class="elements-panel-inner elements-panel-ribbon">
      <div class="options-subtitle"><?= h(t('elements.standard_heading')) ?></div>
      <div class="element-rows element-rows-ribbon" id="standardElementButtons">
        <?php foreach (LayoutSet::STANDARD_ELEMENT_ROLES as $role): ?>
        <button type="button" class="element-row-btn ribbon-tool-btn" data-set-role="<?= h($role) ?>">
          <span class="element-row-icon" aria-hidden="true"><?= sf_element_icon($role) ?></span>
          <span class="element-row-label"><?= h(t('logos.role_' . $role)) ?></span>
          <?php if ($logosImporterEnabled && !empty($activeLogosRoles[$role])): ?><?= sf_logos_badge() ?><?php endif; ?>
        </button>
        <?php endforeach; ?>
      </div>
      <?php if ($logosImporterEnabled): ?>
      <div class="element-logos-insert-section" id="logosSlideInsertSection"<?= $logosSlideInsertRoles ? '' : ' style="display:none;"' ?>>
        <div class="options-subtitle"><?= h(t('elements.logos_insert_heading_more')) ?></div>
        <div id="logosSlideInsertButtons" class="element-rows element-rows-ribbon">
          <?php if ($logosSlideInsertRoles): ?>
            <?php foreach ($logosSlideInsertRoles as $role): ?>
            <button type="button" class="element-row-btn ribbon-tool-btn" data-set-role="<?= h($role) ?>">
              <span class="element-row-icon" aria-hidden="true"><?= sf_element_icon($role) ?></span>
              <span class="element-row-label"><?= h(t('logos.role_' . $role)) ?></span>
              <?= sf_logos_badge() ?>
            </button>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="elements-panel-hint elements-panel-hint-empty"><?= h(t('elements.logos_insert_empty')) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($canEdit): ?>
      <button type="button" class="element-row-btn ribbon-tool-btn" id="configureElementLinksBtn">
        <span class="element-row-label"><?= h(t('elements.configure_element_links')) ?></span>
        <?php if ($logosImporterEnabled): ?><?= sf_logos_badge() ?><?php endif; ?>
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/editor_ribbon_design.php'; ?>

<?php if (!$isTemplateMode || $masterSlideNavActive): ?>
<?php $masterHint = t('editor.master_slide_commands_disabled'); ?>
<div class="ribbon-widget-shell" data-widget-id="widget-present-display">
  <div class="ribbon-present-display-inner<?= $perm === 'owner' ? ' ribbon-present-display-inner--owner' : '' ?><?= $masterSlideNavActive ? ' is-master-disabled' : '' ?>"<?= $masterSlideNavActive ? ' title="' . h($masterHint) . '"' : '' ?>>
    <div class="ribbon-present-display-col">
      <label class="ribbon-toggle" title="<?= h($masterSlideNavActive ? $masterHint : t('present.progress_bar')) ?>">
        <input type="checkbox" id="showProgressToggle" role="switch" <?= ($meta['show_progress'] ?? true) ? 'checked' : '' ?><?= $masterSlideNavActive ? ' disabled' : '' ?>>
        <span class="ribbon-toggle-track" aria-hidden="true"></span>
        <span class="ribbon-toggle-label"><?= h(t('ribbon.present_progress_short')) ?></span>
      </label>
      <label class="ribbon-toggle" title="<?= h($masterSlideNavActive ? $masterHint : t('present.controls_toggle')) ?>">
        <input type="checkbox" id="showControlsToggle" role="switch" <?= ($meta['show_controls'] ?? true) ? 'checked' : '' ?><?= $masterSlideNavActive ? ' disabled' : '' ?>>
        <span class="ribbon-toggle-track" aria-hidden="true"></span>
        <span class="ribbon-toggle-label"><?= h(t('ribbon.present_controls_short')) ?></span>
      </label>
    </div>
    <?php if ($perm === 'owner'): ?>
    <div class="ribbon-present-display-col ribbon-present-display-col--sep">
      <label class="ribbon-toggle" title="<?= h($masterSlideNavActive ? $masterHint : t('present.public_link')) ?>">
        <input type="checkbox" id="publicLinkToggle" role="switch" <?= !empty($acl['public']['enabled']) ? 'checked' : '' ?><?= $masterSlideNavActive ? ' disabled' : '' ?>>
        <span class="ribbon-toggle-track" aria-hidden="true"></span>
        <span class="ribbon-toggle-label"><?= h(t('ribbon.present_public_short')) ?></span>
      </label>
      <button type="button" class="button button-ghost button-sm ribbon-present-display-copy" id="copyPublicLinkBtn" disabled title="<?= h($masterSlideNavActive ? $masterHint : t('present.copy_link')) ?>"><?= h(t('present.copy')) ?></button>
      <input type="hidden" id="presentPublicLinkInput" value="<?= h($publicUrl) ?>">
    </div>
    <?php endif; ?>
    <div class="ribbon-present-display-screen ribbon-present-display-col--sep">
      <label class="ribbon-present-display-screen-label" for="presentScreenSelect"><?= h(t('ribbon.present_screen_short')) ?></label>
      <select id="presentScreenSelect" class="present-screen-select ribbon-present-screen-select" aria-describedby="presentScreenHint"<?= $masterSlideNavActive ? ' disabled' : '' ?>></select>
      <p class="present-screen-hint ribbon-present-screen-hint" id="presentScreenHint" hidden></p>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$rbIconWidth = '<svg class="ribbon-settings-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h16"/><path d="M7 9L4 12l3 3"/><path d="M17 9l3 3-3 3"/><rect x="9" y="7" width="6" height="10" rx="1"/></svg>';
$rbIconHeight = '<svg class="ribbon-settings-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v16"/><path d="M9 7l3-3 3 3"/><path d="M9 17l3 3 3-3"/><rect x="7" y="9" width="10" height="6" rx="1"/></svg>';
$rbIconMargin = '<svg class="ribbon-settings-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="1.5"/><rect x="7" y="8" width="10" height="8" rx="1" stroke-dasharray="2.5 2"/></svg>';
$rbIconDuration = '<svg class="ribbon-settings-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>';
?>
<div class="ribbon-widget-shell" data-widget-id="widget-settings-title">
  <div class="ribbon-settings-title-inner">
    <label class="ribbon-settings-field ribbon-settings-field-title" title="<?= h(t('modal.title_label')) ?>">
      <span><?= h(t('ribbon.settings_title_short')) ?></span>
      <input type="text" id="edTitle" name="title" value="<?= h($meta['title']) ?>">
    </label>
  </div>
</div>

<div class="ribbon-widget-shell" data-widget-id="widget-settings-size">
  <div class="ribbon-settings-size-inner" data-size-layout="stack">
    <label class="ribbon-settings-field ribbon-settings-field--icon" title="<?= h(t('ribbon.settings_width_hint')) ?>">
      <?= $rbIconWidth ?>
      <span class="sr-only"><?= h(t('ribbon.settings_width_short')) ?></span>
      <input type="number" id="edWidth" name="width" value="<?= (int)$meta['width'] ?>" aria-label="<?= h(t('ribbon.settings_width_hint')) ?>">
    </label>
    <span class="ribbon-settings-size-x" aria-hidden="true">×</span>
    <label class="ribbon-settings-field ribbon-settings-field--icon" title="<?= h(t('ribbon.settings_height_hint')) ?>">
      <?= $rbIconHeight ?>
      <span class="sr-only"><?= h(t('ribbon.settings_height_short')) ?></span>
      <input type="number" id="edHeight" name="height" value="<?= (int)$meta['height'] ?>" aria-label="<?= h(t('ribbon.settings_height_hint')) ?>">
    </label>
  </div>
</div>

<div class="ribbon-widget-shell" data-widget-id="widget-settings-margin">
  <div class="ribbon-settings-margin-inner">
    <label class="ribbon-settings-field ribbon-settings-field--icon" title="<?= h(t('modal.safe_margin_hint')) ?>">
      <?= $rbIconMargin ?>
      <span class="sr-only"><?= h(t('ribbon.settings_margin_short')) ?></span>
      <input type="number" id="edSafeMargin" name="safe_margin" min="0" value="<?= (int)($meta['safe_margin'] ?? 100) ?>" aria-label="<?= h(t('ribbon.settings_margin_short')) ?>">
    </label>
  </div>
</div>

<?php if (!$isTemplateMode || $masterSlideNavActive): ?>
<div class="ribbon-widget-shell" data-widget-id="widget-settings-duration">
  <div class="ribbon-settings-duration-inner<?= $masterSlideNavActive ? ' is-master-disabled' : '' ?>"<?= $masterSlideNavActive ? ' title="' . h(t('editor.master_slide_commands_disabled')) . '"' : '' ?>>
    <label class="ribbon-settings-field ribbon-settings-field--icon" title="<?= h($masterSlideNavActive ? t('editor.master_slide_commands_disabled') : t('editor.duration_hint')) ?>">
      <?= $rbIconDuration ?>
      <span class="sr-only"><?= h(t('ribbon.settings_duration_short')) ?></span>
      <input type="number" id="edDuration" name="presentation_duration" min="1" value="<?= (int)($meta['presentation_duration'] ?? 30) ?>" aria-label="<?= h(t('ribbon.settings_duration_short')) ?>"<?= $masterSlideNavActive ? ' disabled' : '' ?>>
    </label>
  </div>
</div>

<?php if (Config::languageToolEnabled()): ?>
<?php
$rbIconSpellcheck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h16"/><path d="M6 16l6-10 6 10"/><path d="M8.5 13h7"/><path d="M16 18l2 2 4-4"/></svg>';
?>
<div class="ribbon-widget-shell" data-widget-id="widget-settings-spellcheck">
  <div class="ribbon-settings-spellcheck-inner<?= $masterSlideNavActive ? ' is-master-disabled' : '' ?>"<?= $masterSlideNavActive ? ' title="' . h(t('editor.master_slide_commands_disabled')) . '"' : '' ?>>
    <label class="ribbon-btn ribbon-btn-tall ribbon-settings-spellcheck<?= Auth::spellcheckBeforePresent($me) ? ' active' : '' ?>" title="<?= h($masterSlideNavActive ? t('editor.master_slide_commands_disabled') : t('editor.spellcheck_before_present_hint')) ?>">
      <input type="checkbox" id="spellcheckBeforePresentToggle" class="sr-only" <?= Auth::spellcheckBeforePresent($me) ? 'checked' : '' ?><?= $masterSlideNavActive ? ' disabled' : '' ?>>
      <?= $rbIconSpellcheck ?>
      <span class="ribbon-btn-label"><?= h(t('ribbon.settings_spellcheck_short')) ?></span>
    </label>
  </div>
</div>
<?php endif; ?>

<div class="ribbon-widget-shell" data-widget-id="widget-settings-layout-set">
  <div class="ribbon-settings-layout-set-inner<?= $masterSlideNavActive ? ' is-master-disabled' : '' ?>"<?= $masterSlideNavActive ? ' title="' . h(t('editor.master_slide_commands_disabled')) . '"' : '' ?>>
    <label class="ribbon-settings-field ribbon-settings-field-grow" title="<?= h($masterSlideNavActive ? t('editor.master_slide_commands_disabled') : t('editor.layout_set_hint')) ?>">
      <span><?= h(t('ribbon.settings_layout_set_short')) ?></span>
      <select id="edLayoutSet" name="layout_set_id"<?= $masterSlideNavActive ? ' disabled' : '' ?>>
        <option value=""><?= h(t('editor.layout_set_none')) ?></option>
        <?php foreach ($editorLayoutSets as $set): ?>
          <option value="<?= h($set['id']) ?>" <?= ($meta['layout_set_id'] ?? '') === $set['id'] ? 'selected' : '' ?>><?= h($set['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
</div>
<?php endif; ?>

<div class="ribbon-widget-shell" data-widget-id="widget-zoom">
  <div class="ribbon-zoom-inner">
    <div class="ribbon-zoom-stepper" role="group" aria-label="<?= h(t('ribbon.group_zoom')) ?>">
      <button type="button" class="ribbon-zoom-step" id="zoomOutBtn" title="<?= h(t('zoom.out')) ?>" aria-label="<?= h(t('zoom.out')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 12h12"/></svg>
      </button>
      <span id="zoomLabel" class="ribbon-zoom-value">100%</span>
      <button type="button" class="ribbon-zoom-step" id="zoomInBtn" title="<?= h(t('zoom.in')) ?>" aria-label="<?= h(t('zoom.in')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 6v12M6 12h12"/></svg>
      </button>
    </div>
    <button type="button" class="ribbon-zoom-fit" id="zoomFitBtn" title="<?= h(t('zoom.fit')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9V5h4M20 9V5h-4M4 15v4h4M20 15v4h-4"/><rect x="8" y="8" width="8" height="8" rx="1"/></svg>
      <span><?= h(t('zoom.fit')) ?></span>
    </button>
  </div>
</div>
