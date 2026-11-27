<?php
/** @var bool $canEdit */
/** @var bool $isTemplateMode */
/** @var bool $isLayoutSetMode */
/** @var bool $showMasterSlideNav */
/** @var bool $masterSlideNavActive */
/** @var string $masterSlideSetId */
/** @var string $masterSlideReturnId */
/** @var int $masterSlideReturnSlide */
/** @var string $id */
/** @var array $me */
?>
<div
  id="editorRibbon"
  class="editor-ribbon"
  data-user-id="<?= h($me['id'] ?? '') ?>"
>
  <div class="editor-ribbon-tabs" role="tablist" aria-label="<?= h(t('ribbon.tabs_label')) ?>"></div>
  <div class="editor-ribbon-panels"></div>
</div>

<div id="ribbonWidgetTemplates" class="ribbon-widget-templates" hidden aria-hidden="true">
  <?php require __DIR__ . '/editor_ribbon_widgets.php'; ?>
</div>

<?php require __DIR__ . '/editor_ribbon_tools.php'; ?>
