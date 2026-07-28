<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/includes/element_icons.php';
Auth::requireLogin();
$me = Auth::currentUser();

$id = $_GET['id'] ?? '';
$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    http_response_code(403);
    die('Du hast keinen Zugriff auf diese Präsentation.');
}
$canEdit = in_array($perm, ['owner', 'edit'], true);
$meta = Presentation::getMeta($id);

if (Mobile::isMobileClient()) {
    $pageTitle = t('mobile.editor_unavailable_title');
    $bodyClass = 'mobile-blocked';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="mobile-blocked-page">
      <h1><?= h(t('mobile.editor_unavailable_title')) ?></h1>
      <p><?= h(t('mobile.editor_unavailable_body')) ?></p>
      <p style="margin-top:1.5rem;">
        <a href="index.php" class="button"><?= h(t('mobile.back_dashboard')) ?></a>
      </p>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    csrf_check();
    if (($_POST['action'] ?? '') === 'resize') {
        $baseUpdates = [
            'width' => max(100, (int)$_POST['width']),
            'height' => max(100, (int)$_POST['height']),
            'title' => trim($_POST['title']) !== '' ? trim($_POST['title']) : $meta['title'],
            'safe_margin' => max(0, (int)($_POST['safe_margin'] ?? 100)),
        ];
        if (!empty($meta['is_template'])) {
            Presentation::updateMeta($id, $baseUpdates);
        } else {
            Presentation::updateMeta($id, array_merge($baseUpdates, [
                'presentation_duration' => max(1, (int)($_POST['presentation_duration'] ?? 30)),
                'layout_set_id' => trim((string)($_POST['layout_set_id'] ?? '')),
            ]));
        }
        redirect('editor.php?id=' . urlencode($id));
        exit;
    }
}

$isTemplateMode = !empty($meta['is_template']);
$isLayoutSetMode = $isTemplateMode && !empty($meta['is_layout_set']);
$iconBrandColors = Config::brandColors();
$defaultIconColor = $iconBrandColors[0]['hex'] ?? '#3a6c8d';

$acl = Presentation::getAcl($id);
$publicUrl = '';
if (!$isTemplateMode && !empty($acl['public']['enabled']) && !empty($acl['public']['token'])) {
    $scheme = current_scheme();
    $host = $_SERVER['HTTP_HOST'];
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $publicUrl = "$scheme://$host$base/view.php?token=" . $acl['public']['token'];
}

if (!isset($meta['safe_margin'])) {
    $meta['safe_margin'] = 100; // ältere Präsentationen (von vor dieser Funktion) haben das Feld noch nicht gespeichert
}

$webdavDrives = Auth::listWebdavDrivesPublic($me);
[$mineLayoutSets, $sharedLayoutSets] = $isTemplateMode ? [[], []] : LayoutSet::listForUser($me['id']);
$editorLayoutSets = array_merge($mineLayoutSets, $sharedLayoutSets);

$logosImporterEnabled = Auth::logosImporterEnabled($me);

$elementLabelRoles = array_values(array_unique(array_merge(
    LayoutSet::STANDARD_ELEMENT_ROLES,
    LayoutSet::LOGOS_ZONE_ROLES,
    ['scripture_ref', 'scripture_verse'],
)));
$elementIconHtml = [];
foreach ($elementLabelRoles as $r) {
    $elementIconHtml[$r] = sf_element_icon($r);
}

$assignedLayoutSetId = trim((string)($meta['layout_set_id'] ?? ''));
$hasLayoutSet = $isLayoutSetMode
    || ($assignedLayoutSetId !== '' && LayoutSet::isLayoutSet($assignedLayoutSetId));

$linkedLayoutSetMeta = $isLayoutSetMode
    ? $meta
    : (($assignedLayoutSetId !== '' && LayoutSet::isLayoutSet($assignedLayoutSetId))
        ? (Presentation::getMeta($assignedLayoutSetId) ?? [])
        : null);
$elementTextLinks = $linkedLayoutSetMeta !== null
    ? LayoutSet::elementTextLinks($linkedLayoutSetMeta)
    : ElementLink::map();

$elementLinkRoles = array_values(array_unique(array_merge(
    LayoutSet::STANDARD_ELEMENT_ROLES,
    $logosImporterEnabled ? LayoutSet::LOGOS_ZONE_ROLES : [],
)));

$masterSlideReturnId = trim((string)($_GET['return'] ?? ''));
$masterSlideReturnSlide = max(0, (int)($_GET['return_slide'] ?? 0));
$showMasterSlideNav = false;
$masterSlideNavActive = false;
$masterSlideSetId = '';
if ($isLayoutSetMode) {
    $returnPerm = $masterSlideReturnId !== '' ? Presentation::checkPermission($masterSlideReturnId, $me['id']) : null;
    $returnMeta = $masterSlideReturnId !== '' ? Presentation::getMeta($masterSlideReturnId) : null;
    if ($returnMeta && $returnPerm && in_array($returnPerm, ['owner', 'edit'], true)
        && empty($returnMeta['is_template'])
        && trim((string)($returnMeta['layout_set_id'] ?? '')) === $id) {
        $showMasterSlideNav = true;
        $masterSlideNavActive = true;
        $masterSlideSetId = $id;
    }
} elseif ($canEdit && !$isTemplateMode && $assignedLayoutSetId !== '' && LayoutSet::isLayoutSet($assignedLayoutSetId)
    && Presentation::canUseTemplate($assignedLayoutSetId, $me['id'])) {
    $showMasterSlideNav = true;
    $masterSlideSetId = $assignedLayoutSetId;
}

$canImportSlideToSet = !$isTemplateMode && $assignedLayoutSetId !== ''
    && LayoutSet::isLayoutSet($assignedLayoutSetId)
    && $linkedLayoutSetMeta !== null
    && (($linkedLayoutSetMeta['owner_id'] ?? '') === $me['id'] || Auth::isAdmin());

$ribbonContext = RibbonLayout::buildContext([
    'canEdit' => $canEdit,
    'isTemplateMode' => $isTemplateMode,
    'isLayoutSetMode' => $isLayoutSetMode,
    'masterSlideEditing' => $masterSlideNavActive,
    'spellcheckEnabled' => Config::languageToolEnabled(),
    'pixabayEnabled' => Config::pixabayEnabled(),
    'iconifyEnabled' => Config::iconifyEnabled(),
    'openclipartEnabled' => Config::openclipartEnabled(),
    'showMasterSlideNav' => $showMasterSlideNav,
    /* Bei Masterfolie-Bearbeitung: Owner der Ausgangspräsentation, damit Teilen sichtbar bleibt (disabled). */
    'isOwner' => $masterSlideNavActive ? (($returnPerm ?? '') === 'owner') : ($perm === 'owner'),
]);
$ribbonUserLayout = $canEdit ? RibbonLayout::getLayout($me['id'], $ribbonContext) : null;

$bootstrap = [
    'id' => $id,
    'meta' => $meta,
    'permission' => $perm,
    'canEdit' => $canEdit,
    'csrfToken' => csrf_token(),
    'brandColors' => Config::brandColors(),
    'fontFamilies' => FontLibrary::allFamilies(),
    'textTemplates' => TextTemplate::listAll(),
    'templateMode' => $isTemplateMode,
    'layoutSetMode' => $isLayoutSetMode,
    'masterSlideEditing' => $masterSlideNavActive,
    'hasLayoutSet' => $hasLayoutSet,
    'canImportSlideToSet' => $canImportSlideToSet,
    'importLayoutSetId' => $canImportSlideToSet ? $assignedLayoutSetId : '',
    'logosImporterEnabled' => $logosImporterEnabled,
    'logosImportedRoles' => LayoutSet::LOGOS_IMPORTED_ROLES,
    'logosExtraRoles' => LayoutSet::LOGOS_ZONE_ROLES,
    'logosZonesAccordionOpen' => Auth::logosZonesAccordionOpen($me),
    'editorGridThumbMin' => Auth::editorGridThumbMin($me),
    'logosRoles' => LayoutSet::LOGOS_ROLES,
    'logosNotesOrder' => $isLayoutSetMode ? LayoutSet::notesOrder($meta) : [],
    'elementZones' => $isLayoutSetMode ? LayoutSet::elementZones($meta) : [],
    'logosImportSettings' => $isLayoutSetMode ? LayoutSet::logosImportSettings($meta) : LayoutSet::DEFAULT_LOGOS_IMPORT_SETTINGS,
    'logosLayoutMap' => $linkedLayoutSetMeta ? LayoutSet::layoutMap($linkedLayoutSetMeta) : [],
    'logosLayoutSlideIds' => $linkedLayoutSetMeta ? LayoutSet::layoutSlideIdMap($linkedLayoutSetMeta) : [],
    'elementTextLinks' => $elementTextLinks,
    'elementLinkRoles' => $elementLinkRoles,
    'standardElementRoles' => LayoutSet::STANDARD_ELEMENT_ROLES,
    'logosElementLinkRoles' => $logosImporterEnabled
        ? LayoutSet::LOGOS_ZONE_ROLES
        : [],
    'elementZoneKeys' => LayoutSet::ELEMENT_ZONE_UI_KEYS,
    'elementIconHtml' => $elementIconHtml,
    'logosBadgeHtml' => sf_logos_badge(),
    'logosRoleLabels' => array_combine(
        $elementLabelRoles,
        array_map(fn($r) => t('logos.role_' . $r), $elementLabelRoles)
    ),
    'logosPlaceholderRoles' => $elementLabelRoles,
    'i18n' => [
        'loading' => t('template_modal.loading'),
        'saved' => t('editor.saved'),
        'exportPreparing' => t('export.preparing'),
        'exportStarted' => t('export.started'),
        'logosInsertEmpty' => t('elements.logos_insert_empty'),
        'error' => t('template_modal.error'),
        'empty' => t('template_modal.empty'),
        'own' => t('template_modal.own'),
        'shared' => t('template_modal.shared'),
        'apply' => t('template_modal.apply'),
        'propsEmpty' => t('props.empty'),
        'layersTitle' => t('props.layers_title'),
        'objectsTitle' => t('props.objects_title'),
        'settingsTemplates' => t('editor.settings_templates'),
        'mediaTitle' => t('editor.tab_media'),
        'textFieldDefault' => t('shape.text_field'),
        'templatePickerHint' => t('editor.template_picker_hint'),
        'layersEmpty' => t('props.layers_empty'),
        'layersHint' => t('props.layers_hint'),
        'layersDrag' => t('props.layers_drag'),
        'tabFormat' => t('props.tab_format'),
        'tabForm' => t('props.tab_form'),
        'tabPosition' => t('props.tab_position'),
        'tabEffect' => t('props.tab_effect'),
        'sideTabTemplates' => t('props.side_tab_templates'),
        'sideTabFormat' => t('props.side_tab_format'),
        'sideTabPosition' => t('props.side_tab_position'),
        'sideTabEffects' => t('props.side_tab_effects'),
        'sideTabSpell' => t('props.side_tab_spell'),
        'sideTabMedia' => t('props.side_tab_media'),
        'sidePosLayout' => t('props.side_pos_layout'),
        'sidePosLayers' => t('props.side_pos_layers'),
        'sideTemplatesEmpty' => t('props.side_templates_empty'),
        'positionAlign' => t('ribbon.group_position_align'),
        'positionSize' => t('ribbon.group_position_size'),
        'positionLayer' => t('ribbon.group_position_layer'),
        'positionTransfer' => t('ribbon.group_position_transfer'),
        'align' => t('props.align'),
        'typeShape' => t('props.type_shape'),
        'typeText' => t('props.type_text'),
        'typeEllipse' => t('props.type_ellipse'),
        'typeRect' => t('props.type_rect'),
        'typeImage' => t('props.type_image'),
        'typeVideo' => t('props.type_video'),
        'typeAudio' => t('props.type_audio'),
        'text' => t('props.text'),
        'formatSelection' => t('props.format_selection'),
        'bold' => t('props.bold'),
        'italic' => t('props.italic'),
        'underline' => t('props.underline'),
        'strikethrough' => t('props.strikethrough'),
        'uppercase' => t('props.uppercase'),
        'smallcaps' => t('props.smallcaps'),
        'mark' => t('props.mark'),
        'textColorBtn' => t('props.text_color_btn'),
        'markColorPick' => t('props.mark_color_pick'),
        'textColorPick' => t('props.text_color_pick'),
        'selectionHint' => t('props.selection_hint'),
        'selectionHintTitle' => t('props.selection_hint_title'),
        'markdownHint' => t('props.markdown_hint'),
        'markdownHintTitle' => t('props.markdown_hint_title'),
        'font' => t('props.font'),
        'size' => t('props.size'),
        'lineHeight' => t('props.line_height'),
        'letterSpacing' => t('props.letter_spacing'),
        'format' => t('props.format'),
        'uppercaseHint' => t('props.uppercase_hint'),
        'color' => t('props.color'),
        'align' => t('props.align'),
        'opacity' => t('props.opacity'),
        'lineColor' => t('props.line_color'),
        'lineWidth' => t('props.line_width'),
        'bracketStyle' => t('props.bracket_style'),
        'fillSolid' => t('props.fill_solid'),
        'fillGradient' => t('props.fill_gradient'),
        'color1' => t('props.color1'),
        'color2' => t('props.color2'),
        'angle' => t('props.angle'),
        'fillColor' => t('props.fill_color'),
        'fillNone' => t('props.fill_none'),
        'fillNoneHint' => t('props.fill_none_hint'),
        'borderColor' => t('props.border_color'),
        'iconColor' => t('props.icon_color'),
        'borderWidth' => t('props.border_width'),
        'starPoints' => t('props.star_points'),
        'starPointsLabel' => t('props.star_points_short'),
        'arrowStyle' => t('props.arrow_style'),
        'bubbleStyle' => t('props.bubble_style'),
        'replaceImage' => t('props.replace_image'),
        'removeBackground' => t('props.remove_background'),
        'removeBackgroundHint' => t('props.remove_background_hint'),
        'removeBackgroundWorking' => t('props.remove_background_working'),
        'removeBackgroundFailed' => t('props.remove_background_failed'),
        'replaceVideo' => t('props.replace_video'),
        'replaceAudio' => t('props.replace_audio'),
        'mediaPlaceholderHint' => t('props.media_placeholder_hint'),
        'mediaVideo' => t('props.media_video'),
        'mediaAudio' => t('props.media_audio'),
        'playTrigger' => t('props.play_trigger'),
        'playDelay' => t('props.play_delay'),
        'hideControls' => t('props.hide_controls'),
        'loop' => t('props.loop'),
        'duplicate' => t('props.duplicate'),
        'copy' => t('props.copy'),
        'cut' => t('props.cut'),
        'group' => t('editor.group'),
        'ungroup' => t('editor.ungroup'),
        'typeGroup' => t('props.type_group'),
        'groupChildCount' => t('props.group_child_count'),
        'multiSelect' => t('editor.multi_select'),
        'applyTextStyle' => t('props.apply_text_style'),
        'templateStyleSample' => t('ribbon.template_style_sample'),
        'templateNone' => t('ribbon.template_none'),
        'groupEdit' => t('props.group_edit'),
        'groupFormat' => t('props.group_format'),
        'groupFont' => t('props.group_font'),
        'groupColors' => t('props.group_colors'),
        'groupTemplates' => t('props.group_templates'),
        'templatesEmpty' => t('props.templates_empty'),
        'setPlaceholderRole' => t('props.set_placeholder_role'),
        'setPlaceholderNone' => t('props.set_placeholder_none'),
        'setPlaceholderHint' => t('props.set_placeholder_hint'),
        'setPlaceholderRefresh' => t('props.set_placeholder_refresh'),
        'setPlaceholderRefreshNone' => t('props.set_placeholder_refresh_none'),
        'setPlaceholderRefreshHint' => t('props.set_placeholder_refresh_hint'),
        'setPlaceholderRefreshNoSet' => t('props.set_placeholder_refresh_no_set'),
        'setPlaceholderRefreshNoLayout' => t('props.set_placeholder_refresh_no_layout'),
        'elementLinksConfigure' => t('elements.configure_element_links'),
        'elementLinksModalTitle' => t('elements.element_links_modal_title'),
        'elementLinksModalDesc' => t('elements.element_links_modal_desc'),
        'elementLinksColStandard' => t('elements.standard_heading'),
        'elementLinksColStandardDesc' => t('elements.element_links_col_standard_desc'),
        'elementLinksColLogos' => t('elements.logos_zones_heading'),
        'elementLinksTabStandard' => t('elements.element_links_tab_standard'),
        'elementLinksTabLogos' => t('elements.element_links_tab_logos'),
        'elementLinksTabAssignments' => t('elements.element_links_tab_assignments'),
        'elementLinksTabLogosImport' => t('elements.element_links_tab_logos_import'),
        'logosImportH1Opener' => t('elements.logos_import_h1_opener'),
        'logosImportH1Separate' => t('elements.logos_import_h1_separate'),
        'logosImportH1Combine' => t('elements.logos_import_h1_combine'),
        'logosImportScriptureHeading' => t('elements.logos_import_scripture_heading'),
        'logosImportScriptureSeparate' => t('elements.logos_import_scripture_separate'),
        'logosImportScriptureCombineFit' => t('elements.logos_import_scripture_combine_fit'),
        'logosImportScriptureCombineAlways' => t('elements.logos_import_scripture_combine_always'),
        'logosImportListGrouping' => t('elements.logos_import_list_grouping'),
        'logosImportTextMaxChars' => t('elements.logos_import_text_max_chars'),
        'logosImportListUnlimited' => t('elements.logos_import_list_unlimited'),
        'logosImportListLayout' => t('elements.logos_import_list_layout'),
        'elementLinksColLogosElements' => t('elements.element_links_col_logos_elements'),
        'elementLinksColLogosMapping' => t('elements.element_links_col_logos_mapping'),
        'elementLinksColLogosDesc' => t('elements.element_links_col_logos_desc'),
        'elementLinksZonesTitle' => t('elements.logos_zones_heading'),
        'elementLinksZonesDesc' => t('elements.logos_zones_desc'),
        'elementLinksZonesHelpBtn' => t('elements.logos_zones_help_btn'),
        'zoneSlides' => t('elements.zone_slides'),
        'zoneFooter' => t('elements.zone_footer'),
        'zoneCustom' => t('elements.zone_custom'),
        'zoneUnused' => t('elements.zone_unused'),
        'elementLinksSaved' => t('elements.element_links_saved'),
        'elementLinkNone' => t('tpl.element_link_none'),
        'elementLinkElement' => t('tpl.sermon_element'),
        'elementLinkTextTemplate' => t('tpl.sermon_text_template'),
        'playTriggerHint' => t('props.play_trigger_hint'),
        'alignToSlide' => t('props.align_to_slide'),
        'alignClickHint' => t('props.align_click_hint'),
        'alTop' => t('props.top'),
        'alLeft' => t('props.left'),
        'alHCenter' => t('props.hcenter'),
        'alVCenter' => t('props.vcenter'),
        'alBottom' => t('props.bottom'),
        'alRight' => t('props.right'),
        'advanced' => t('props.advanced'),
        'width' => t('props.width'),
        'height' => t('props.height'),
        'aspectLock' => t('props.aspect_lock'),
        'rotation' => t('props.rotation'),
        'rotationShort' => t('props.rotation_short'),
        'layerUp' => t('props.layer_up'),
        'layerDown' => t('props.layer_down'),
        'layerFront' => t('props.layer_front'),
        'layerBack' => t('props.layer_back'),
        'effect' => t('props.effect'),
        'animOrder' => t('props.anim_order'),
        'animDuration' => t('props.anim_duration'),
        'animAutostart' => t('props.anim_autostart'),
        'animHint' => t('props.anim_hint'),
        'animPerLine' => t('props.anim_per_line'),
        'animPerLineHint' => t('props.anim_per_line_hint'),
        'positionCopy' => t('props.position_copy'),
        'positionPaste' => t('props.position_paste'),
        'positionCopyShort' => t('ribbon.position_copy_short'),
        'positionPasteShort' => t('ribbon.position_paste_short'),
        'alVCenterShort' => t('ribbon.align_v_short'),
        'alHCenterShort' => t('ribbon.align_h_short'),
        'layerUpShort' => t('ribbon.layer_up_short'),
        'layerDownShort' => t('ribbon.layer_down_short'),
        'layerFrontShort' => t('ribbon.layer_front_short'),
        'layerBackShort' => t('ribbon.layer_back_short'),
        'animationCopy' => t('props.animation_copy'),
        'animationPaste' => t('props.animation_paste'),
        'propsCopied' => t('props.copied'),
        'deleteObject' => t('props.delete_object'),
        'optAnim' => [
            'none' => t('opt.anim_none'), 'fade-in' => t('opt.anim_fade_in'), 'fade-out' => t('opt.anim_fade_out'),
            'fade-up' => t('opt.anim_fade_up'), 'fade-down' => t('opt.anim_fade_down'),
            'fade-left' => t('opt.anim_fade_left'), 'fade-right' => t('opt.anim_fade_right'),
            'grow' => t('opt.anim_grow'), 'shrink' => t('opt.anim_shrink'), 'strike' => t('opt.anim_strike'),
        ],
        'optTransition' => [
            'none' => t('bg.transition_none'),
            'fade' => t('bg.transition_fade'),
            'slide' => t('bg.transition_slide'),
            'convex' => t('bg.transition_convex'),
            'concave' => t('bg.transition_concave'),
            'zoom' => t('bg.transition_zoom'),
        ],
        'optPlay' => ['manual' => t('opt.play_manual'), 'click' => t('opt.play_click'), 'timed' => t('opt.play_timed')],
        'optArrow' => [
            'right' => t('opt.arrow_right'), 'left' => t('opt.arrow_left'), 'up' => t('opt.arrow_up'), 'down' => t('opt.arrow_down'),
            'double-h' => t('opt.arrow_double_h'), 'double-v' => t('opt.arrow_double_v'),
        ],
        'optBracket' => [
            'square-left' => t('opt.bracket_square_left'), 'square-right' => t('opt.bracket_square_right'),
            'round-left' => t('opt.bracket_round_left'), 'round-right' => t('opt.bracket_round_right'),
            'curly-left' => t('opt.bracket_curly_left'), 'curly-right' => t('opt.bracket_curly_right'),
        ],
        'optBubble' => [
            'rect-left' => t('opt.bubble_rect_left'), 'rect-right' => t('opt.bubble_rect_right'),
            'oval' => t('opt.bubble_oval'), 'cloud' => t('opt.bubble_cloud'),
        ],
        'optSec' => [0 => t('opt.duration_default'), 500 => t('opt.sec_0_5'), 1000 => t('opt.sec_1'), 2000 => t('opt.sec_2'), 3000 => t('opt.sec_3'), 5000 => t('opt.sec_5')],
        'optAutostartOff' => t('opt.autostart_off'),
        'shapeTriangle' => t('shape.triangle'),
        'shapeStar' => t('shape.star'),
        'shapeArrow' => t('shape.arrow'),
        'shapeBracket' => t('shape.bracket'),
        'shapeSpeechBubble' => t('shape.speech_bubble'),
        'shapeLine' => t('shape.line'),
        'newSlide' => t('editor.new_slide'),
        'duplicateSlide' => t('editor.duplicate_slide'),
        'importSlideToSet' => t('editor.import_slide_to_set'),
        'importSlideToSetPrompt' => t('editor.import_slide_to_set_prompt'),
        'importSlideToSetDone' => t('editor.import_slide_to_set_done'),
        'deleteSlide' => t('editor.delete_slide'),
        'reorderSlide' => t('editor.reorder_slide'),
        'filmstripLabelPlaceholder' => t('editor.filmstrip_label_placeholder'),
        'unnamedSlide' => t('editor.unnamed_slide'),
        'togglePresentDisabled' => t('editor.toggle_present_disabled'),
        'slidePresentEnabled' => t('editor.slide_present_enabled'),
        'slidePresentDisabled' => t('editor.slide_present_disabled'),
        'slideGridTitle' => t('editor.slide_grid_title'),
        'slideGridOpen' => t('editor.slide_grid_open'),
        'slideGridSelected' => t('editor.slide_grid_selected'),
        'slideGridSelectAll' => t('editor.slide_grid_select_all'),
        'slideGridSelectNone' => t('editor.slide_grid_select_none'),
        'slideGridThumbSize' => t('editor.slide_grid_thumb_size'),
        'applyTransitionSelected' => t('editor.apply_transition_selected'),
        'applyTransitionAll' => t('bg.apply_transition_all'),
        'transitionTitle' => t('bg.transition_title'),
        'autoAdvanceLabel' => t('bg.autoadvance_label'),
        'bgImage' => t('bg.image'),
        'bgVideo' => t('bg.video'),
        'bgRemove' => t('bg.remove'),
        'bgColor' => t('bg.color'),
        'bgGradient' => t('bg.gradient'),
        'bgNonePreview' => t('bg.none_preview'),
        'slideBgPreview' => t('ribbon.slide_bg_preview'),
        'slideUpload' => t('ribbon.slide_upload'),
        'slideMediaChange' => t('ribbon.slide_media_change'),
        'slideMediaHint' => t('ribbon.slide_media_hint'),
        'slideMediaExisting' => t('ribbon.slide_media_existing'),
        'slideMediaExistingEmpty' => t('ribbon.slide_media_existing_empty'),
        'notesTitle' => t('present.notes'),
    ],
    'presentConfig' => [
        'enabled' => !$isTemplateMode && $canEdit,
        'isOwner' => $perm === 'owner',
        'publicUrl' => $publicUrl,
        'i18n' => [
            'copied' => t('present.copied'),
            'copyLink' => t('present.copy_link'),
            'screenPrimary' => t('present.screen_primary'),
            'screenSecondary' => t('present.screen_secondary'),
            'screenN' => t('present.screen_n'),
            'screenSingle' => t('present.screen_single'),
            'screenMultiHint' => t('present.screen_multi_hint'),
            'localStart' => t('present.local_start'),
            'localReopen' => t('present.local_reopen'),
        ],
    ],
    'share' => [
        'enabled' => $perm === 'owner' && !$isTemplateMode,
        'i18n' => [
            'noneYet' => t('share.none_yet'),
            'pleaseChoose' => t('share.please_choose'),
            'noOtherUsers' => t('share.no_other_users'),
            'alreadyShared' => t('share.already_shared'),
            'permEdit' => t('dashboard.permission_edit'),
            'permView' => t('dashboard.permission_view'),
            'remove' => t('common.remove'),
            'resetConfirm' => t('share.reset_confirm'),
            'copied' => t('present.copied'),
        ],
    ],
    'spellcheck' => [
        'enabled' => $canEdit && Config::languageToolEnabled(),
        'browserEnabled' => Auth::spellcheckBrowserEnabled($me),
        'beforePresent' => Auth::spellcheckBeforePresent($me),
        'lang' => Auth::spellcheckLanguage($me),
        'htmlLang' => Auth::spellcheckLanguage($me),
        'i18n' => [
            'title' => t('spell.title'),
            'run' => t('spell.run'),
            'checking' => t('spell.checking'),
            'noIssues' => t('spell.no_issues'),
            'noIssuesShort' => t('spell.no_issues_short'),
            'issueCount' => t('spell.issue_count'),
            'kindTitle' => t('spell.kind_title'),
            'kindNotes' => t('spell.kind_notes'),
            'kindObject' => t('spell.kind_object'),
            'suggestion' => t('spell.suggestion'),
            'apply' => t('spell.apply'),
            'goto' => t('spell.goto'),
            'ignore' => t('spell.ignore'),
            'errorGeneric' => t('spell.error_generic'),
            'beforePresentHint' => t('spell.before_present_hint'),
            'proceedPresent' => t('spell.proceed_present'),
        ],
    ],
    'pixabay' => [
        'enabled' => $canEdit && Config::pixabayEnabled(),
        'lang' => substr((string)($me['language'] ?? 'de'), 0, 2),
        'i18n' => [
            'title' => t('pixabay.title'),
            'search' => t('pixabay.search'),
            'searching' => t('pixabay.searching'),
            'importing' => t('pixabay.importing'),
            'importDone' => t('pixabay.import_done'),
            'noResults' => t('pixabay.no_results'),
            'resultCount' => t('pixabay.result_count'),
            'enterQuery' => t('pixabay.enter_query'),
            'errorGeneric' => t('pixabay.error_generic'),
            'useBackground' => t('pixabay.use_background'),
            'useObject' => t('pixabay.use_object'),
            'mediaImage' => t('pixabay.media_image'),
            'mediaVideo' => t('pixabay.media_video'),
            'attribution' => t('pixabay.attribution'),
            'targetBgImage' => t('pixabay.target_bg_image'),
            'targetBgVideo' => t('pixabay.target_bg_video'),
            'targetObjectImage' => t('pixabay.target_object_image'),
            'targetObjectVideo' => t('pixabay.target_object_video'),
            'openFromBg' => t('pixabay.open_from_bg'),
            'prev' => t('pixabay.prev'),
            'next' => t('pixabay.next'),
            'filterOrientation' => t('pixabay.filter_orientation'),
            'filterType' => t('pixabay.filter_type'),
            'orientationAll' => t('pixabay.orientation_all'),
            'orientationHorizontal' => t('pixabay.orientation_horizontal'),
            'orientationVertical' => t('pixabay.orientation_vertical'),
            'imageTypeAll' => t('pixabay.image_type_all'),
            'imageTypePhoto' => t('pixabay.image_type_photo'),
            'imageTypeIllustration' => t('pixabay.image_type_illustration'),
            'videoTypeAll' => t('pixabay.video_type_all'),
            'videoTypeFilm' => t('pixabay.video_type_film'),
            'videoTypeAnimation' => t('pixabay.video_type_animation'),
            'previewHint' => t('pixabay.preview_hint'),
            'previewBy' => t('pixabay.preview_by'),
            'searchEnglish' => t('media.search_english'),
            'searchEnglishHint' => t('media.search_english_hint'),
            'translating' => t('media.translating'),
        ],
    ],
    'iconify' => [
        'enabled' => $canEdit && Config::iconifyEnabled(),
        'i18n' => [
            'title' => t('iconify.title'),
            'search' => t('iconify.search'),
            'searching' => t('iconify.searching'),
            'importing' => t('iconify.importing'),
            'importDone' => t('iconify.import_done'),
            'noResults' => t('iconify.no_results'),
            'resultCount' => t('iconify.result_count'),
            'enterQuery' => t('iconify.enter_query'),
            'errorGeneric' => t('iconify.error_generic'),
            'useObject' => t('iconify.use_object'),
            'openFromMedia' => t('iconify.open_from_media'),
            'prev' => t('iconify.prev'),
            'next' => t('iconify.next'),
            'filterCollection' => t('iconify.filter_collection'),
            'collectionAll' => t('iconify.collection_all'),
            'collectionMdi' => t('iconify.collection_mdi'),
            'collectionFa6Solid' => t('iconify.collection_fa6_solid'),
            'collectionFa6Regular' => t('iconify.collection_fa6_regular'),
            'collectionLucide' => t('iconify.collection_lucide'),
            'collectionTabler' => t('iconify.collection_tabler'),
            'collectionMaterialSymbols' => t('iconify.collection_material_symbols'),
            'collectionBi' => t('iconify.collection_bi'),
            'collectionPh' => t('iconify.collection_ph'),
            'collectionCarbon' => t('iconify.collection_carbon'),
            'collectionRi' => t('iconify.collection_ri'),
            'collectionSimpleIcons' => t('iconify.collection_simple_icons'),
            'previewHint' => t('iconify.preview_hint'),
            'targetObject' => t('iconify.target_object'),
            'iconColor' => t('iconify.icon_color'),
            'searchEnglish' => t('media.search_english'),
            'searchEnglishHint' => t('media.search_english_hint'),
            'translating' => t('media.translating'),
        ],
    ],
    'openclipart' => [
        'enabled' => $canEdit && Config::openclipartEnabled(),
        'i18n' => [
            'title' => t('openclipart.title'),
            'search' => t('openclipart.search'),
            'searching' => t('openclipart.searching'),
            'importing' => t('openclipart.importing'),
            'importDone' => t('openclipart.import_done'),
            'noResults' => t('openclipart.no_results'),
            'resultCount' => t('openclipart.result_count'),
            'enterQuery' => t('openclipart.enter_query'),
            'errorGeneric' => t('openclipart.error_generic'),
            'useObject' => t('openclipart.use_object'),
            'openFromMedia' => t('openclipart.open_from_media'),
            'prev' => t('openclipart.prev'),
            'next' => t('openclipart.next'),
            'previewHint' => t('openclipart.preview_hint'),
            'targetObject' => t('openclipart.target_object'),
            'searchEnglish' => t('media.search_english'),
            'searchEnglishHint' => t('media.search_english_hint'),
            'translating' => t('media.translating'),
        ],
    ],
    'webdav' => [
        'enabled' => $canEdit && count($webdavDrives) > 0,
        'drives' => $webdavDrives,
        'i18n' => [
            'title' => t('webdav.title'),
            'loading' => t('webdav.loading'),
            'importing' => t('webdav.importing'),
            'importDone' => t('webdav.import_done'),
            'emptyFolder' => t('webdav.empty_folder'),
            'errorGeneric' => t('webdav.error_generic'),
            'useObject' => t('webdav.use_object'),
            'useBackground' => t('pixabay.use_background'),
            'previewHint' => t('pixabay.preview_hint'),
            'kindVideo' => t('media_lib.kind_video'),
            'kindAudio' => t('media_lib.kind_audio'),
            'targetObject' => t('webdav.target_object'),
            'up' => t('webdav.up'),
            'root' => t('webdav.root'),
            'folder' => t('webdav.folder'),
            'file' => t('webdav.file'),
            'openFolder' => t('webdav.open_folder'),
        ],
    ],
    'mediaLibrary' => [
        'i18n' => [
            'subInsert' => t('media_lib.sub_insert'),
            'subOverview' => t('media_lib.sub_overview'),
            'title' => t('media_lib.title'),
            'empty' => t('media_lib.empty'),
            'loading' => t('media_lib.loading'),
            'refresh' => t('media_lib.refresh'),
            'usedOn' => t('media_lib.used_on'),
            'unused' => t('media_lib.unused'),
            'delete' => t('media_lib.delete'),
            'deleteConfirm' => t('media_lib.delete_confirm'),
            'slideN' => t('media_lib.slide_n'),
            'kindImage' => t('media_lib.kind_image'),
            'kindVideo' => t('media_lib.kind_video'),
            'kindAudio' => t('media_lib.kind_audio'),
            'errorGeneric' => t('media_lib.error_generic'),
            'insertHint' => t('media_lib.insert_hint'),
            'inserted' => t('media_lib.inserted'),
            'useBackground' => t('media_lib.use_background'),
            'backgroundSet' => t('media_lib.background_set'),
            'cleanup' => t('media_lib.cleanup'),
            'cleanupConfirm' => t('media_lib.cleanup_confirm'),
            'cleanupDone' => t('media_lib.cleanup_done'),
            'cleanupNone' => t('media_lib.cleanup_none'),
        ],
    ],
    'ribbon' => $canEdit ? [
        'layout' => RibbonLayout::layoutForClient($ribbonUserLayout, $ribbonContext),
        'catalog' => RibbonLayout::catalogForClient($ribbonContext),
        'commands' => RibbonLayout::commandDefsForClient($ribbonContext),
        'apiUrl' => 'ribbon.php',
        'meta' => [
            'urls' => [
                'present' => 'present.php?id=' . urlencode($id),
                'preview' => 'preview.php?id=' . urlencode($id),
                'share' => 'presentation_share.php?id=' . urlencode($id),
                'export' => 'export.php?id=' . urlencode($id),
            ],
            'masterSlideNav' => $showMasterSlideNav ? [
                'active' => $masterSlideNavActive,
                'setId' => $masterSlideSetId,
                'presentationId' => $masterSlideNavActive ? $masterSlideReturnId : $id,
                'returnId' => $masterSlideReturnId,
                'returnSlide' => $masterSlideReturnSlide,
                'title' => t($masterSlideNavActive ? 'editor.master_slide_back' : 'editor.master_slide_open'),
            ] : null,
            'masterSlideEditing' => $masterSlideNavActive,
            'masterSlideCommandsDisabled' => t('editor.master_slide_commands_disabled'),
            'displayOptions' => [
                'show_progress' => ($meta['show_progress'] ?? true) ? true : false,
                'show_controls' => ($meta['show_controls'] ?? true) ? true : false,
            ],
            'displayProgressTitle' => t('present.progress_bar'),
            'displayControlsTitle' => t('present.controls_toggle'),
        ],
        'i18n' => [
            'title' => t('ribbon.customize_title'),
            'search' => t('ribbon.customize_search'),
            'categoryAll' => t('ribbon.customize_category_all'),
            'tabAdd' => t('ribbon.customize_tab_add'),
            'tabRename' => t('ribbon.customize_tab_rename'),
            'tabDelete' => t('ribbon.customize_tab_delete'),
            'tabNamePrompt' => t('ribbon.customize_tab_name'),
            'groupAdd' => t('ribbon.customize_group_add'),
            'groupRename' => t('ribbon.customize_group_rename'),
            'groupDelete' => t('ribbon.customize_group_delete'),
            'groupNamePrompt' => t('ribbon.customize_group_name'),
            'newTab' => t('ribbon.customize_new_tab'),
            'newGroup' => t('ribbon.customize_new_group'),
            'reset' => t('ribbon.customize_reset'),
            'resetConfirm' => t('ribbon.customize_reset_confirm'),
            'save' => t('ribbon.customize_save'),
            'cancel' => t('common.cancel'),
            'remove' => t('ribbon.customize_remove'),
            'livePreviewHint' => t('ribbon.customize_live_preview'),
            'emptyLayout' => t('ribbon.customize_empty_layout'),
            'toggleTab' => t('ribbon.customize_toggle_tab'),
            'toggleGroup' => t('ribbon.customize_toggle_group'),
            'appearanceIconSize' => t('ribbon.appearance_icon_size'),
            'appearanceIconSmall' => t('ribbon.appearance_icon_small'),
            'appearanceIconMedium' => t('ribbon.appearance_icon_medium'),
            'appearanceIconLarge' => t('ribbon.appearance_icon_large'),
            'appearanceLabelsShow' => t('ribbon.appearance_labels_show'),
            'appearanceLabelsHide' => t('ribbon.appearance_labels_hide'),
            'appearanceGroupRows' => t('ribbon.appearance_group_rows'),
            'appearanceGroupRows1' => t('ribbon.appearance_group_rows_1'),
            'appearanceGroupRows2' => t('ribbon.appearance_group_rows_2'),
            'separatorAdd' => t('ribbon.customize_separator_add'),
            'rowSeparatorAdd' => t('ribbon.customize_row_separator_add'),
            'itemTileSmall' => t('ribbon.item_tile_small'),
            'itemTileLarge' => t('ribbon.item_tile_large'),
            'itemTileLargeNeedsRows' => t('ribbon.item_tile_large_needs_rows'),
            'itemTileSep1' => t('ribbon.item_tile_sep_1'),
            'itemTileSep2' => t('ribbon.item_tile_sep_2'),
            'itemTileTransition1' => t('ribbon.item_tile_transition_1'),
            'itemTileTransition2' => t('ribbon.item_tile_transition_2'),
            'itemTileTransition2NeedsRows' => t('ribbon.item_tile_transition_2_needs_rows'),
            'itemLabelShow' => t('ribbon.item_label_show'),
            'itemLabelHide' => t('ribbon.item_label_hide'),
            'errorSave' => t('ribbon.error_save'),
            'errorReset' => t('ribbon.error_reset'),
            'categories' => [
                'start' => t('ribbon.tab_start'),
                'insert' => t('ribbon.tab_insert'),
                'design' => t('ribbon.tab_design'),
                'present' => t('ribbon.tab_present'),
                'view' => t('ribbon.tab_view'),
            ],
        ],
    ] : null,
];

$editorSlidesData = Presentation::getSlides($id);
$editorCurrentSlide = $editorSlidesData['slides'][0] ?? null;

if ($isLayoutSetMode && $editorCurrentSlide) {
    $headerPresentationTitle = $meta['title'] . ' - ' . LayoutSet::slideLabel($editorCurrentSlide);
    $headerPresentationContext = null;
} else {
    $headerPresentationTitle = $meta['title'];
    $headerPresentationContext = 'edit';
}
$pageTitle = 'Editor · ' . $headerPresentationTitle;
require __DIR__ . '/includes/header.php';
?>
<div class="editor-topbar editor-topbar-slim">
  <div class="editor-topbar-left">
    <a href="<?= $isTemplateMode ? 'templates.php?tab=slides' : 'index.php' ?>" class="back-link">&larr; <?= $isTemplateMode ? h(t('nav.templates')) : h(t('editor.dashboard')) ?></a>
  </div>
  <div class="editor-topbar-right">
    <?php if ($isTemplateMode): ?>
      <span class="perm-tag edit"><?= h(t('editor.template_badge')) ?></span>
      <span class="perm-tag <?= !empty($meta['template_shared']) ? 'edit' : 'view' ?>"><?= !empty($meta['template_shared']) ? 'Freigegeben' : 'Privat' ?></span>
    <?php else: ?>
      <span class="perm-tag <?= $perm === 'view' ? 'view' : 'edit' ?>"><?= $perm === 'owner' ? 'Eigentümer' : ($perm === 'edit' ? 'Bearbeiten' : 'Ansehen') ?></span>
    <?php endif; ?>
    <span id="saveStatus" class="save-status"><?= h(t('editor.saved')) ?></span>
  </div>
</div>
<?php if ($canEdit): ?>
<?php require __DIR__ . '/includes/editor_ribbon.php'; ?>
<?php endif; ?>

<?php if (!$canEdit): ?>
<div class="alert alert-success" style="margin:12px 24px;"><?= t('editor.view_only_notice') ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['import_warnings'])): ?>
<div class="alert alert-error" style="margin:12px 24px;">
  <strong><?= h(t('import.warnings_heading')) ?></strong>
  <ul style="margin:8px 0 0; padding-left:20px;">
    <?php foreach ($_SESSION['import_warnings'] as $w): ?>
      <li><?= h($w) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php unset($_SESSION['import_warnings']); ?>
<?php endif; ?>

<?php if (!$canEdit): ?>
<?php
$viewSlidesData = Presentation::getSlides($id);
$viewSlides = $viewSlidesData['slides'] ?? [];
$viewSwatches = array_map(function ($s) {
    $bg = $s['background'] ?? null;
    if (!$bg) return '#222';
    if (($bg['type'] ?? '') === 'color' || ($bg['type'] ?? '') === 'gradient') return $bg['value'] ?? '#222';
    return '#222';
}, $viewSlides);
$viewNotesHtml = array_map(fn($s) => Markdown::render($s['notes'] ?? ''), $viewSlides);
?>
<div class="view-only-layout">
  <div class="view-only-tabs" id="viewOnlyTabs">
    <?php foreach ($viewSlides as $i => $s): ?>
      <button type="button" class="view-only-tab<?= $i === 0 ? ' active' : '' ?>" data-view-index="<?= $i ?>" style="--dot-color: <?= h($viewSwatches[$i]) ?>;"><?= $i + 1 ?></button>
    <?php endforeach; ?>
  </div>
  <div class="view-only-main">
    <div class="view-only-slide-wrap">
      <div class="view-only-slide-frame" id="viewOnlySlideFrame">
        <div class="view-only-slide-scale" id="viewOnlySlideScale" style="width:<?= (int)$meta['width'] ?>px; height:<?= (int)$meta['height'] ?>px;">
          <?php foreach ($viewSlides as $i => $s): ?>
            <div class="view-only-slide" data-view-index="<?= $i ?>" <?= $i === 0 ? '' : 'style="display:none;"' ?>>
              <?= SlideRenderer::renderSlideThumbnailHtml($s, null) ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <aside class="view-only-notes">
      <div class="notes-panel-header"><span><?= h(t('notes.title')) ?></span></div>
      <?php foreach ($viewNotesHtml as $i => $nh): ?>
        <div class="view-only-notes-content" data-view-index="<?= $i ?>" <?= $i === 0 ? '' : 'style="display:none;"' ?>>
          <?= $nh !== '' ? $nh : '<span class="present-notes-empty">' . h(t('present.notes_empty')) . '</span>' ?>
        </div>
      <?php endforeach; ?>
    </aside>
  </div>
</div>
<script>
(function () {
  const W = <?= (int)$meta['width'] ?>, H = <?= (int)$meta['height'] ?>;
  const scaleEl = document.getElementById('viewOnlySlideScale');
  const frameEl = document.getElementById('viewOnlySlideFrame');
  const wrap = frameEl.parentElement;
  function applyScale() {
    const availW = wrap.clientWidth - 32;
    const availH = wrap.clientHeight - 32;
    const s = Math.min(availW / W, availH / H, 1);
    scaleEl.style.transform = 'scale(' + s + ')';
    frameEl.style.width = Math.round(W * s) + 'px';
    frameEl.style.height = Math.round(H * s) + 'px';
  }
  applyScale();
  window.addEventListener('resize', applyScale);
  document.querySelectorAll('.view-only-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const idx = btn.dataset.viewIndex;
      document.querySelectorAll('.view-only-tab').forEach(function (b) { b.classList.toggle('active', b === btn); });
      document.querySelectorAll('.view-only-slide').forEach(function (el) { el.style.display = el.dataset.viewIndex === idx ? '' : 'none'; });
      document.querySelectorAll('.view-only-notes-content').forEach(function (el) { el.style.display = el.dataset.viewIndex === idx ? '' : 'none'; });
    });
  });
})();
</script>
<?php else: ?>

<div class="editor-layout<?= ($isTemplateMode && !$isLayoutSetMode) ? ' editor-layout-no-filmstrip' : '' ?>">
  <?php if (!$isTemplateMode || $isLayoutSetMode): ?>
  <aside class="options-panel options-panel-filmstrip">
    <div class="editor-slides-toolbar">
      <?php if ($canEdit): ?>
        <button type="button" class="tool-btn-block" id="addSlideBtn">+ <?= h(t('editor.new_slide')) ?></button>
      <?php endif; ?>
    </div>
    <div id="slideFilmstrip" class="editor-slide-filmstrip"></div>
  </aside>
  <?php endif; ?>

  <main class="canvas-area" id="canvasArea">
    <div class="canvas-slide-view" id="canvasSlideView">
    <div class="canvas-scroll">
      <div id="stageWrap" class="stage-wrap">
        <div id="stageBgLayer" class="stage-bg-layer"></div>
        <div id="stageContainer"></div>
      </div>
    </div>
    <?php if (!$canEdit): ?>
    <div class="zoom-bar">
      <button type="button" class="zoom-btn" id="zoomOutBtn" title="<?= h(t('zoom.out')) ?>">&minus;</button>
      <span id="zoomLabel">100%</span>
      <button type="button" class="zoom-btn" id="zoomInBtn" title="<?= h(t('zoom.in')) ?>">+</button>
      <span class="zoom-sep"></span>
      <button type="button" class="zoom-btn zoom-btn-text" id="zoomFitBtn" title="<?= h(t('zoom.fit')) ?>"><?= h(t('zoom.fit')) ?></button>
    </div>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <div class="notes-panel-editor">
      <div class="notes-panel-header">
        <span><?= h(t('notes.title')) ?></span>
        <span><?= t('notes.hint') ?></span>
      </div>
      <textarea id="slideNotesInput" placeholder="<?= h(t('notes.placeholder')) ?>"></textarea>
    </div>
    <?php endif; ?>
    </div>
    <?php if ($canEdit && (!$isTemplateMode || $isLayoutSetMode)): ?>
    <div class="canvas-grid-view" id="canvasGridView" hidden>
      <div class="editor-slide-grid-panel" id="slideGridPanel">
        <div class="editor-slide-grid-toolbar">
          <div class="editor-slide-grid-toolbar-row">
            <span class="editor-slide-grid-selection" id="slideGridSelectionInfo"></span>
            <button type="button" class="button button-ghost button-sm" id="slideGridSelectAllBtn"><?= h(t('editor.slide_grid_select_all')) ?></button>
            <button type="button" class="button button-ghost button-sm" id="slideGridSelectNoneBtn"><?= h(t('editor.slide_grid_select_none')) ?></button>
            <span class="editor-slide-grid-toolbar-hint"><?= h(t('editor.slide_grid_ribbon_hint')) ?></span>
          </div>
        </div>
        <div class="editor-slide-grid-scroll">
          <div class="editor-slide-grid" id="slideGrid"></div>
        </div>
        <div class="editor-grid-statusbar" id="slideGridStatusBar">
          <label class="editor-grid-statusbar-label" for="gridThumbSizeSlider"><?= h(t('editor.slide_grid_thumb_size')) ?></label>
          <input type="range" id="gridThumbSizeSlider" class="editor-grid-thumb-slider" min="100" max="360" step="4" value="<?= (int)Auth::editorGridThumbMin($me) ?>">
          <span class="editor-grid-thumb-size-value" id="gridThumbSizeLabel"><?= (int)Auth::editorGridThumbMin($me) ?> px</span>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </main>

  <?php if ($canEdit): ?>
  <?php
  $sideTabIcon = static function (string $name): string {
      $icons = [
          'templates' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="1.5"/><path d="M3 9h18"/><path d="M8 4v14"/></svg>',
          'format' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h6"/><path d="M7 20V6.5"/><path d="M4 6.5h6"/><path d="M14 20l3.5-14h1L22 20"/><path d="M15.2 15h5.1"/></svg>',
          'position' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18"/><path d="M3 12h18"/><path d="M12 3l3 3M12 3L9 6"/><path d="M12 21l3-3M12 21l-3-3"/><path d="M3 12l3-3M3 12l3 3"/><path d="M21 12l-3-3M21 12l-3 3"/></svg>',
          'effects' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/><path d="M18.5 15.5l.9 2.6 2.6.9-2.6.9-.9 2.6-.9-2.6-2.6-.9 2.6-.9.9-2.6z"/></svg>',
          'spell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V6.5A1.5 1.5 0 0 1 5.5 5H14"/><path d="M8 19h12.5A1.5 1.5 0 0 0 22 17.5V9"/><path d="M8 9h7"/><path d="M8 13h5"/><path d="M15 17l2 2 4-5"/></svg>',
          'media' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="12" r="2.2"/><path d="M13 15l2.2-2.5a1 1 0 0 1 1.5 0L21 17"/></svg>',
      ];
      return $icons[$name] ?? '';
  };
  ?>
  <div class="editor-right-sidebar" id="editorRightSidebar">
    <aside class="props-panel-wrap props-sidebar-a props-sidebar-tabs" id="propsPanelWrap">
      <nav class="props-side-tabs" id="propsSideTabs" role="tablist" aria-label="<?= h(t('props.side_tabs')) ?>">
        <button type="button" class="props-side-tab" role="tab" id="propsSideTabTemplates" data-side-tab="templates" aria-selected="true" aria-controls="propsSidePanelTemplates" title="<?= h(t('props.side_tab_templates')) ?>">
          <?= $sideTabIcon('templates') ?>
          <span class="sr-only"><?= h(t('props.side_tab_templates')) ?></span>
        </button>
        <button type="button" class="props-side-tab" role="tab" id="propsSideTabFormat" data-side-tab="format" aria-selected="false" aria-controls="propsSidePanelFormat" title="<?= h(t('props.side_tab_format')) ?>">
          <?= $sideTabIcon('format') ?>
          <span class="sr-only"><?= h(t('props.side_tab_format')) ?></span>
        </button>
        <button type="button" class="props-side-tab" role="tab" id="propsSideTabPosition" data-side-tab="position" aria-selected="false" aria-controls="propsSidePanelPosition" title="<?= h(t('props.side_tab_position')) ?>">
          <?= $sideTabIcon('position') ?>
          <span class="sr-only"><?= h(t('props.side_tab_position')) ?></span>
        </button>
        <button type="button" class="props-side-tab" role="tab" id="propsSideTabEffects" data-side-tab="effects" aria-selected="false" aria-controls="propsSidePanelEffects" title="<?= h(t('props.side_tab_effects')) ?>">
          <?= $sideTabIcon('effects') ?>
          <span class="sr-only"><?= h(t('props.side_tab_effects')) ?></span>
        </button>
        <?php if (Config::languageToolEnabled()): ?>
        <button type="button" class="props-side-tab" role="tab" id="propsSideTabSpell" data-side-tab="spell" aria-selected="false" aria-controls="propsSidePanelSpell" title="<?= h(t('props.side_tab_spell')) ?>">
          <?= $sideTabIcon('spell') ?>
          <span class="sr-only"><?= h(t('props.side_tab_spell')) ?></span>
        </button>
        <?php endif; ?>
        <button type="button" class="props-side-tab" role="tab" id="propsSideTabMedia" data-side-tab="media" aria-selected="false" aria-controls="propsSidePanelMedia" title="<?= h(t('props.side_tab_media')) ?>">
          <?= $sideTabIcon('media') ?>
          <span class="sr-only"><?= h(t('props.side_tab_media')) ?></span>
        </button>
        <button type="button" class="props-side-tab props-side-tab-customize" id="ribbonCustomizeBtn" title="<?= h(t('ribbon.customize_title')) ?>" aria-label="<?= h(t('ribbon.customize_short')) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4.5h4.5M10.5 4.5h5"/><rect x="3" y="7" width="18" height="11.5" rx="1.5"/><rect x="5.5" y="9.5" width="3.8" height="6.5" rx="0.7"/><rect x="10.8" y="9.5" width="3.8" height="6.5" rx="0.7"/><path d="M15.4 18.2l5.5-5.5 2 2-5.5 5.5-2.55.55.55-2.55z" fill="currentColor" stroke="none"/></svg>
        </button>
      </nav>

      <div class="props-side-panels" id="propsSidePanels">
        <section class="props-side-panel is-active" id="propsSidePanelTemplates" data-side-panel="templates" role="tabpanel" aria-labelledby="propsSideTabTemplates">
          <header class="props-side-panel-head">
            <h2 class="props-side-panel-title"><?= h(t('props.side_tab_templates')) ?></h2>
          </header>
          <div class="props-side-panel-body">
            <div class="props-templates-section" id="propsTemplatesPanel"></div>
          </div>
        </section>

        <section class="props-side-panel" id="propsSidePanelFormat" data-side-panel="format" role="tabpanel" aria-labelledby="propsSideTabFormat" hidden>
          <header class="props-side-panel-head">
            <h2 class="props-side-panel-title"><?= h(t('props.side_tab_format')) ?></h2>
          </header>
          <div class="props-pos-subtabs" id="propsFormatSubtabs" role="tablist" hidden>
            <button type="button" class="props-pos-subtab" role="tab" data-format-subtab="text" aria-selected="false"><?= h(t('props.side_format_text')) ?></button>
            <button type="button" class="props-pos-subtab" role="tab" data-format-subtab="templates" aria-selected="false"><?= h(t('props.side_format_templates')) ?></button>
            <button type="button" class="props-pos-subtab active" role="tab" data-format-subtab="format" aria-selected="true"><?= h(t('props.side_format_style')) ?></button>
          </div>
          <div class="props-side-panel-body" id="propsSelectionBody">
            <div class="props-format-subpanel" data-format-panel="text" hidden>
              <div class="props-text-panel" id="propsTextPanel"></div>
            </div>
            <div class="props-format-subpanel" data-format-panel="templates" hidden>
              <div class="props-text-templates-panel" id="propsTextTemplatesPanel"></div>
            </div>
            <div class="props-format-subpanel is-active" data-format-panel="format">
              <div class="props-object-widgets" id="propsObjectWidgets">
                <?php require __DIR__ . '/includes/editor_ribbon_object.php'; ?>
              </div>
              <div class="props-object-panel" id="propsObjectPanel" hidden aria-hidden="true"></div>
            </div>
          </div>
          <footer class="props-side-panel-footer" id="propsFormatFooter">
            <div class="ribbon-group ribbon-props-section ribbon-group-object-delete" id="ribbonObjectDeleteGroup">
              <div class="ribbon-group-content ribbon-props-section-body">
                <button type="button" class="button button-danger button-sm" id="deleteObjBtn" style="width:100%;" disabled><?= h(t('props.delete_object')) ?></button>
              </div>
            </div>
          </footer>
        </section>

        <section class="props-side-panel" id="propsSidePanelPosition" data-side-panel="position" role="tabpanel" aria-labelledby="propsSideTabPosition" hidden>
          <header class="props-side-panel-head">
            <h2 class="props-side-panel-title"><?= h(t('props.side_tab_position')) ?></h2>
          </header>
          <div class="props-pos-subtabs" id="propsPosSubtabs" role="tablist">
            <button type="button" class="props-pos-subtab active" role="tab" data-pos-subtab="layout" aria-selected="true"><?= h(t('props.side_pos_layout')) ?></button>
            <button type="button" class="props-pos-subtab" role="tab" data-pos-subtab="layers" aria-selected="false"><?= h(t('props.side_pos_layers')) ?></button>
          </div>
          <div class="props-side-panel-body">
            <div class="props-pos-subpanel is-active" data-pos-panel="layout">
              <div class="props-position-panel" id="propsPositionPanel"></div>
            </div>
            <div class="props-pos-subpanel" data-pos-panel="layers" hidden>
              <div class="props-layers-section" id="propsLayersPanel"></div>
            </div>
          </div>
        </section>

        <section class="props-side-panel" id="propsSidePanelEffects" data-side-panel="effects" role="tabpanel" aria-labelledby="propsSideTabEffects" hidden>
          <header class="props-side-panel-head">
            <h2 class="props-side-panel-title"><?= h(t('props.side_tab_effects')) ?></h2>
          </header>
          <div class="props-side-panel-body">
            <div class="props-effects-panel" id="propsEffectsPanel">
              <div class="props-empty"><?= t('props.empty') ?></div>
            </div>
          </div>
        </section>

        <?php if (Config::languageToolEnabled()): ?>
        <section class="props-side-panel" id="propsSidePanelSpell" data-side-panel="spell" role="tabpanel" aria-labelledby="propsSideTabSpell" hidden>
          <header class="props-side-panel-head">
            <h2 class="props-side-panel-title"><?= h(t('props.side_tab_spell')) ?></h2>
          </header>
          <div class="props-side-panel-body">
            <aside class="spell-panel spell-panel--embedded" id="spellPanel" aria-label="<?= h(t('spell.title')) ?>">
              <div class="spell-panel-toolbar">
                <button type="button" class="button button-sm" id="spellRunBtn"><?= h(t('spell.run')) ?></button>
                <button type="button" class="button button-ghost button-sm" id="spellProceedBtn" hidden><?= h(t('spell.proceed_present')) ?></button>
                <span class="spell-status" id="spellStatus"></span>
              </div>
              <div class="spell-panel-body" id="spellResults"></div>
            </aside>
          </div>
        </section>
        <?php endif; ?>

        <section class="props-side-panel" id="propsSidePanelMedia" data-side-panel="media" role="tabpanel" aria-labelledby="propsSideTabMedia" hidden>
          <header class="props-side-panel-head">
            <h2 class="props-side-panel-title"><?= h(t('props.side_tab_media')) ?></h2>
          </header>
          <div class="props-side-panel-body">
            <div class="props-media-section" id="propsMediaPanel">
              <div class="props-media-accordion open" id="propsMediaAccordion">
                <div class="props-media-body props-section-body">
                  <div class="media-library-header">
                    <div class="media-library-header-actions">
                      <button type="button" class="button button-ghost button-sm" id="mediaLibraryRefresh"><?= h(t('media_lib.refresh')) ?></button>
                      <?php if ($canEdit): ?>
                      <button type="button" class="button button-ghost button-sm" id="mediaLibraryCleanup" disabled><?= h(t('media_lib.cleanup')) ?></button>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php if ($canEdit): ?>
                  <p class="media-library-hint"><?= h(t('media_lib.insert_hint')) ?></p>
                  <?php endif; ?>
                  <div class="media-library-status" id="mediaLibraryStatus"></div>
                  <div class="media-library-list" id="mediaLibraryList"></div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </aside>
  </div>
  <?php endif; ?>
</div>
<?php endif; // canEdit-Verzweigung Editor-Layout ?>

<?php if ($canEdit): ?>
<form method="post" id="metaSettingsForm" class="hidden-form" hidden aria-hidden="true">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="resize">
</form>

<div class="modal-backdrop" id="slideBgGradientModal" aria-hidden="true">
  <div class="modal modal-sm">
    <h2 class="sf-dialog-title modal-dialog-title"><?= h(t('bg.gradient')) ?></h2>
    <div class="modal-dialog-body">
    <div class="row">
      <div><label for="bgGradColor1"><?= h(t('bg.color1')) ?></label><input type="color" id="bgGradColor1" value="#3a6c8d" style="width:100%; height:36px;"></div>
      <div><label for="bgGradColor2"><?= h(t('bg.color2')) ?></label><input type="color" id="bgGradColor2" value="#87b42b" style="width:100%; height:36px;"></div>
    </div>
    <div style="margin-top:12px;">
      <label for="bgGradAngle" id="bgGradAngleLabel"><?= h(t('bg.angle')) ?> (90°)</label>
      <input type="range" id="bgGradAngle" min="0" max="360" value="90" style="width:100%;">
    </div>
    <div class="object-gradient-preview" id="slideBgGradientPreview" aria-hidden="true"></div>
    </div>
    <div class="present-config-panel-footer modal-actions">
      <button type="button" class="button button-ghost button-sm" id="slideBgGradientModalClose"><?= h(t('common.close')) ?></button>
      <button type="button" class="button button-sm" id="slideBgGradientModalApply"><?= h(t('tpl.save')) ?></button>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="slideBgMediaModal" aria-hidden="true">
  <div class="modal modal-sm slide-bg-media-modal" role="dialog" aria-modal="true" aria-labelledby="slideBgMediaModalTitle">
    <h2 id="slideBgMediaModalTitle" class="sf-dialog-title modal-dialog-title"><?= h(t('bg.image')) ?></h2>
    <p class="sf-dialog-hint modal-dialog-hint slide-bg-media-modal-hint"><?= h(t('ribbon.slide_media_hint')) ?></p>
    <div class="modal-dialog-body">
    <div class="slide-bg-media-modal-preview" id="slideBgMediaModalPreview" hidden></div>
    <input type="file" id="slideBgMediaModalFile" class="sr-only" accept="image/jpeg,image/png,image/gif,image/webp">
    <div class="slide-bg-media-modal-actions">
      <button type="button" class="button" id="slideBgMediaModalBrowse"><?= h(t('ribbon.slide_upload')) ?></button>
      <?php if ($canEdit && Config::pixabayEnabled()): ?>
      <button type="button" class="button button-ghost pixabay-open-btn" id="slideBgMediaModalPixabay" data-pixabay-open="background-image"><?= h(t('pixabay.open_from_bg')) ?></button>
      <?php endif; ?>
    </div>
    <div class="slide-bg-media-modal-existing" id="slideBgMediaModalExisting" hidden>
      <h3 class="slide-bg-media-modal-existing-title present-config-section-title" id="slideBgMediaModalExistingTitle"><?= h(t('ribbon.slide_media_existing')) ?></h3>
      <div class="slide-bg-media-modal-existing-grid" id="slideBgMediaModalExistingGrid" role="list"></div>
      <p class="slide-bg-media-modal-existing-empty" id="slideBgMediaModalExistingEmpty" hidden><?= h(t('ribbon.slide_media_existing_empty')) ?></p>
    </div>
    </div>
    <div class="present-config-panel-footer modal-actions">
      <button type="button" class="button button-ghost button-sm" id="slideBgMediaModalRemove"><?= h(t('bg.remove')) ?></button>
      <button type="button" class="button button-ghost button-sm" id="slideBgMediaModalClose"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>

<?php if ($perm === 'owner' && !$isTemplateMode): ?>
<div class="modal-backdrop" id="shareModal" aria-hidden="true">
  <div class="modal share-modal" role="dialog" aria-modal="true" aria-labelledby="shareModalTitle">
    <div class="sf-dialog-header share-modal-header">
      <h2 id="shareModalTitle" class="sf-dialog-title"><?= h(t('editor.share')) ?></h2>
      <button type="button" class="sf-dialog-close" id="shareModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <div class="share-modal-scroll">
      <div id="shareModalStatus" class="share-modal-status" hidden></div>
      <div class="present-config-section">
        <div class="present-config-section-title"><?= h(t('share.with_people')) ?></div>
        <div class="share-modal-row">
          <div class="share-modal-field share-modal-field-grow">
            <label for="shareUsername"><?= h(t('share.user_label')) ?></label>
            <select id="shareUsername"></select>
          </div>
          <div class="share-modal-field">
            <label for="sharePermission"><?= h(t('share.permission')) ?></label>
            <select id="sharePermission">
              <option value="view"><?= h(t('share.view_only_opt')) ?></option>
              <option value="edit"><?= h(t('share.edit_opt')) ?></option>
            </select>
          </div>
        </div>
        <button type="button" class="button" id="shareAddBtn" style="margin-top:12px;"><?= h(t('share.share_btn')) ?></button>
        <ul class="share-list" id="shareList"></ul>
      </div>
      <div class="present-config-section">
        <div class="present-config-section-title"><?= h(t('share.public_link')) ?></div>
        <p class="props-video-note" style="margin:0 0 10px;"><?= t('share.public_link_desc') ?></p>
        <label class="present-config-check">
          <input type="checkbox" id="sharePublicEnabled">
          <span><?= h(t('share.enable_public_link')) ?></span>
        </label>
        <button type="button" class="button button-sm" id="sharePublicSaveBtn" style="margin-top:10px;"><?= h(t('common.save')) ?></button>
        <div class="public-link-box" id="sharePublicLinkBox" hidden>
          <input type="text" id="sharePublicUrl" readonly>
          <button type="button" class="button button-ghost button-sm" id="sharePublicCopyBtn"><?= h(t('present.copy')) ?></button>
        </div>
        <button type="button" class="button button-ghost button-sm" id="sharePublicResetBtn" style="margin-top:10px;" hidden><?= h(t('share.reset_link')) ?></button>
      </div>
    </div>
    <div class="present-config-panel-footer modal-actions">
      <button type="button" class="button button-ghost button-sm" id="shareModalDone"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($canEdit && !$isTemplateMode): ?>
<div class="modal-backdrop" id="exportModal" aria-hidden="true">
  <div class="modal export-modal" role="dialog" aria-modal="true" aria-labelledby="exportModalTitle">
    <div class="sf-dialog-header share-modal-header">
      <h2 id="exportModalTitle" class="sf-dialog-title"><?= h(t('editor.export')) ?></h2>
      <button type="button" class="sf-dialog-close" id="exportModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint"><?= h(t('export.intro')) ?></p>
    <div class="share-modal-scroll">
      <div class="present-config-section">
        <div class="present-config-section-title"><?= h(t('export.single_file')) ?></div>
        <p class="props-video-note" style="margin:0 0 10px;"><?= t('export.single_file_desc') ?></p>
        <a class="button button-sm" href="export.php?id=<?= urlencode($id) ?>&amp;format=html" data-export-download><?= h(t('export.download_html')) ?></a>
      </div>
      <div class="present-config-section">
        <div class="present-config-section-title"><?= h(t('export.zip_heading')) ?></div>
        <p class="props-video-note" style="margin:0 0 10px;"><?= t('export.zip_desc') ?></p>
        <a class="button button-ghost button-sm" href="export.php?id=<?= urlencode($id) ?>&amp;format=zip" data-export-download><?= h(t('export.download_zip')) ?></a>
      </div>
      <div class="present-config-section">
        <div class="alert alert-success" style="margin:0;"><?= t('export.reimport_hint') ?></div>
      </div>
      <div class="present-config-section">
        <div class="present-config-section-title"><?= h(t('export.pptx_heading')) ?></div>
        <p class="props-video-note" style="margin:0 0 10px;"><?= t('export.pptx_desc') ?></p>
        <a class="button button-ghost button-sm" href="export.php?id=<?= urlencode($id) ?>&amp;format=pptx" data-export-download><?= h(t('export.download_pptx')) ?></a>
      </div>
      <div class="present-config-section">
        <div class="present-config-section-title"><?= h(t('export.odp_heading')) ?></div>
        <p class="props-video-note" style="margin:0 0 10px;"><?= t('export.odp_desc') ?></p>
        <a class="button button-ghost button-sm" href="export.php?id=<?= urlencode($id) ?>&amp;format=odp" data-export-download><?= h(t('export.download_odp')) ?></a>
      </div>
      <div class="present-config-section">
        <div class="present-config-section-title"><?= h(t('export.pdf_heading')) ?></div>
        <p class="props-video-note" style="margin:0 0 10px;"><?= t('export.pdf_desc') ?></p>
        <a class="button button-ghost button-sm" href="pdf_export.php?id=<?= urlencode($id) ?>" target="_blank" rel="noopener noreferrer"><?= h(t('export.open_pdf_view')) ?></a>
      </div>
    </div>
    <div class="present-config-panel-footer modal-actions">
      <button type="button" class="button button-ghost button-sm" id="exportModalDone"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="modal-backdrop" id="elementLinksModal">
  <div class="modal modal-element-config">
    <h2 class="sf-dialog-title modal-dialog-title"><?= h(t('elements.element_links_modal_title')) ?></h2>
    <p class="sf-dialog-hint modal-dialog-hint"><?= h(t('elements.element_links_modal_desc')) ?></p>
    <div id="elementLinksModalBody" class="element-links-modal-body modal-dialog-body"></div>
    <div class="present-config-panel-footer modal-actions">
      <button type="button" class="button button-ghost button-sm" id="elementLinksModalClose"><?= h(t('common.close')) ?></button>
      <button type="button" class="button button-sm" id="elementLinksModalSave"><?= h(t('tpl.save')) ?></button>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="templateModal">
  <div class="modal">
    <h2 class="sf-dialog-title modal-dialog-title"><?= h(t('editor.apply_template')) ?></h2>
    <p class="sf-dialog-hint modal-dialog-hint"><?= h(t('template_modal.hint')) ?></p>
    <div id="templateList" class="modal-dialog-body" style="max-height:340px; overflow-y:auto;"></div>
    <div class="present-config-panel-footer modal-actions">
      <button type="button" class="button button-ghost button-sm" onclick="document.getElementById('templateModal').classList.remove('open')"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>

<?php if (Config::pixabayEnabled()): ?>
<div class="modal-backdrop" id="pixabayModal" aria-hidden="true">
  <div class="modal pixabay-modal" role="dialog" aria-modal="true" aria-labelledby="pixabayModalTitle">
    <div class="sf-dialog-header pixabay-modal-header">
      <h2 id="pixabayModalTitle" class="sf-dialog-title pixabay-modal-title"><?= h(t('pixabay.title')) ?></h2>
      <button type="button" class="sf-dialog-close" id="pixabayModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint pixabay-target-hint" id="pixabayTargetHint"></p>
    <div class="pixabay-modal-toolbar">
      <div class="pixabay-search-block">
        <div class="pixabay-search-row">
          <input type="search" id="pixabayQuery" placeholder="<?= h(t('pixabay.search_placeholder')) ?>" autocomplete="off">
          <div class="pixabay-search-actions">
            <button type="button" class="button button-sm" id="pixabaySearchBtn"><?= h(t('pixabay.search')) ?></button>
            <button type="button" class="button button-ghost button-sm" id="pixabaySearchEnglishBtn"><?= h(t('media.search_english')) ?></button>
          </div>
        </div>
        <p class="media-search-english-hint" id="pixabaySearchEnglishHint" hidden></p>
      </div>
      <div class="pixabay-filters">
        <label class="pixabay-filter-item">
          <span><?= h(t('pixabay.media_label')) ?></span>
          <select id="pixabayMedia">
            <option value="image"><?= h(t('pixabay.media_image')) ?></option>
            <option value="video"><?= h(t('pixabay.media_video')) ?></option>
          </select>
        </label>
        <div id="pixabayImageFilters" class="pixabay-filter-group">
          <label class="pixabay-filter-item">
            <span><?= h(t('pixabay.filter_type')) ?></span>
            <select id="pixabayImageType">
              <option value="all"><?= h(t('pixabay.image_type_all')) ?></option>
              <option value="photo"><?= h(t('pixabay.image_type_photo')) ?></option>
              <option value="illustration"><?= h(t('pixabay.image_type_illustration')) ?></option>
            </select>
          </label>
          <label class="pixabay-filter-item">
            <span><?= h(t('pixabay.filter_orientation')) ?></span>
            <select id="pixabayOrientation">
              <option value="all"><?= h(t('pixabay.orientation_all')) ?></option>
              <option value="horizontal"><?= h(t('pixabay.orientation_horizontal')) ?></option>
              <option value="vertical"><?= h(t('pixabay.orientation_vertical')) ?></option>
            </select>
          </label>
        </div>
        <div id="pixabayVideoFilters" class="pixabay-filter-group" hidden>
          <label class="pixabay-filter-item">
            <span><?= h(t('pixabay.filter_type')) ?></span>
            <select id="pixabayVideoType">
              <option value="all"><?= h(t('pixabay.video_type_all')) ?></option>
              <option value="film"><?= h(t('pixabay.video_type_film')) ?></option>
              <option value="animation"><?= h(t('pixabay.video_type_animation')) ?></option>
            </select>
          </label>
        </div>
      </div>
    </div>
    <div class="pixabay-modal-meta">
      <div class="pixabay-status" id="pixabayStatus"></div>
      <div class="pixabay-pager" id="pixabayPager" hidden>
        <button type="button" class="button button-ghost button-sm" id="pixabayPrev"><?= h(t('pixabay.prev')) ?></button>
        <button type="button" class="button button-ghost button-sm" id="pixabayNext"><?= h(t('pixabay.next')) ?></button>
      </div>
    </div>
    <div class="pixabay-modal-body">
      <div class="pixabay-grid" id="pixabayGrid"></div>
    </div>
    <p class="pixabay-attribution pixabay-modal-footer"><?= t('pixabay.attribution') ?></p>
  </div>
  <div class="pixabay-lightbox" id="pixabayLightbox" aria-hidden="true">
    <button type="button" class="pixabay-lightbox-close" id="pixabayLightboxClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    <div class="pixabay-lightbox-backdrop" id="pixabayLightboxBackdrop" aria-hidden="true"></div>
    <div class="pixabay-lightbox-panel" role="dialog" aria-modal="true" aria-labelledby="pixabayLightboxTitle">
      <div class="pixabay-lightbox-media" id="pixabayLightboxMedia"></div>
      <div class="pixabay-lightbox-footer">
        <div class="pixabay-lightbox-meta" id="pixabayLightboxMeta"></div>
        <div class="pixabay-lightbox-actions" id="pixabayLightboxActions"></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (Config::iconifyEnabled()): ?>
<div class="modal-backdrop" id="iconifyModal" aria-hidden="true">
  <div class="modal pixabay-modal" role="dialog" aria-modal="true" aria-labelledby="iconifyModalTitle">
    <div class="sf-dialog-header pixabay-modal-header">
      <h2 id="iconifyModalTitle" class="sf-dialog-title pixabay-modal-title"><?= h(t('iconify.title')) ?></h2>
      <button type="button" class="sf-dialog-close" id="iconifyModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint pixabay-target-hint"><?= h(t('iconify.target_object')) ?></p>
    <div class="pixabay-modal-toolbar iconify-modal-toolbar">
      <div class="iconify-toolbar-row iconify-search-row">
        <div class="pixabay-search-block">
          <div class="pixabay-search-row">
            <input type="search" id="iconifyQuery" placeholder="<?= h(t('iconify.search_placeholder')) ?>" autocomplete="off">
            <div class="pixabay-search-actions">
              <button type="button" class="button button-sm" id="iconifySearchBtn"><?= h(t('iconify.search')) ?></button>
              <button type="button" class="button button-ghost button-sm" id="iconifySearchEnglishBtn"><?= h(t('media.search_english')) ?></button>
            </div>
          </div>
          <p class="media-search-english-hint" id="iconifySearchEnglishHint" hidden></p>
        </div>
        <label class="pixabay-filter-item iconify-set-filter">
          <span><?= h(t('iconify.filter_collection')) ?></span>
          <select id="iconifyPrefix">
            <option value=""><?= h(t('iconify.collection_all')) ?></option>
            <option value="mdi"><?= h(t('iconify.collection_mdi')) ?></option>
            <option value="fa6-solid"><?= h(t('iconify.collection_fa6_solid')) ?></option>
            <option value="fa6-regular"><?= h(t('iconify.collection_fa6_regular')) ?></option>
            <option value="lucide"><?= h(t('iconify.collection_lucide')) ?></option>
            <option value="tabler"><?= h(t('iconify.collection_tabler')) ?></option>
            <option value="material-symbols"><?= h(t('iconify.collection_material_symbols')) ?></option>
            <option value="bi"><?= h(t('iconify.collection_bi')) ?></option>
            <option value="ph"><?= h(t('iconify.collection_ph')) ?></option>
            <option value="carbon"><?= h(t('iconify.collection_carbon')) ?></option>
            <option value="ri"><?= h(t('iconify.collection_ri')) ?></option>
            <option value="simple-icons"><?= h(t('iconify.collection_simple_icons')) ?></option>
          </select>
        </label>
      </div>
      <div class="iconify-toolbar-row iconify-color-row">
        <label class="pixabay-filter-item iconify-color-filter">
          <span><?= h(t('iconify.icon_color')) ?></span>
          <input type="color" id="iconifyColor" value="<?= h($defaultIconColor) ?>">
        </label>
        <?php if (!empty($iconBrandColors)): ?>
        <div class="pixabay-filter-item iconify-color-palette-wrap">
          <span><?= h(t('iconify.brand_colors')) ?></span>
          <div class="brand-palette mini" id="iconifyColorPalette">
            <?php foreach ($iconBrandColors as $c): ?>
            <button type="button" class="brand-swatch" data-color="<?= h($c['hex']) ?>" style="background:<?= h($c['hex']) ?>" title="<?= h($c['name'] ?? $c['hex']) ?>"></button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="pixabay-modal-meta">
      <div class="pixabay-status" id="iconifyStatus"></div>
      <div class="pixabay-pager" id="iconifyPager" hidden>
        <button type="button" class="button button-ghost button-sm" id="iconifyPrev"><?= h(t('iconify.prev')) ?></button>
        <button type="button" class="button button-ghost button-sm" id="iconifyNext"><?= h(t('iconify.next')) ?></button>
      </div>
    </div>
    <div class="pixabay-modal-body">
      <div class="pixabay-grid iconify-grid" id="iconifyGrid"></div>
    </div>
    <p class="pixabay-attribution pixabay-modal-footer"><?= t('iconify.attribution') ?></p>
  </div>
  <div class="pixabay-lightbox" id="iconifyLightbox" aria-hidden="true">
    <button type="button" class="pixabay-lightbox-close" id="iconifyLightboxClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    <div class="pixabay-lightbox-backdrop" id="iconifyLightboxBackdrop" aria-hidden="true"></div>
    <div class="pixabay-lightbox-panel" role="dialog" aria-modal="true">
      <div class="pixabay-lightbox-media iconify-lightbox-media" id="iconifyLightboxMedia"></div>
      <div class="pixabay-lightbox-footer">
        <div class="pixabay-lightbox-meta" id="iconifyLightboxMeta"></div>
        <div class="pixabay-lightbox-actions" id="iconifyLightboxActions"></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (Config::openclipartEnabled()): ?>
<div class="modal-backdrop" id="openclipartModal" aria-hidden="true">
  <div class="modal pixabay-modal" role="dialog" aria-modal="true" aria-labelledby="openclipartModalTitle">
    <div class="sf-dialog-header pixabay-modal-header">
      <h2 id="openclipartModalTitle" class="sf-dialog-title pixabay-modal-title"><?= h(t('openclipart.title')) ?></h2>
      <button type="button" class="sf-dialog-close" id="openclipartModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint pixabay-target-hint"><?= h(t('openclipart.target_object')) ?></p>
    <div class="pixabay-modal-toolbar openclipart-modal-toolbar">
      <div class="pixabay-search-block">
        <div class="pixabay-search-row">
          <input type="search" id="openclipartQuery" placeholder="<?= h(t('openclipart.search_placeholder')) ?>" autocomplete="off">
          <div class="pixabay-search-actions">
            <button type="button" class="button button-sm" id="openclipartSearchBtn"><?= h(t('openclipart.search')) ?></button>
            <button type="button" class="button button-ghost button-sm" id="openclipartSearchEnglishBtn"><?= h(t('media.search_english')) ?></button>
          </div>
        </div>
        <p class="media-search-english-hint" id="openclipartSearchEnglishHint" hidden></p>
      </div>
    </div>
    <div class="pixabay-modal-meta">
      <div class="pixabay-status" id="openclipartStatus"></div>
      <div class="pixabay-pager" id="openclipartPager" hidden>
        <button type="button" class="button button-ghost button-sm" id="openclipartPrev"><?= h(t('openclipart.prev')) ?></button>
        <button type="button" class="button button-ghost button-sm" id="openclipartNext"><?= h(t('openclipart.next')) ?></button>
      </div>
    </div>
    <div class="pixabay-modal-body">
      <div class="pixabay-grid openclipart-grid" id="openclipartGrid"></div>
    </div>
    <p class="pixabay-attribution pixabay-modal-footer"><?= t('openclipart.attribution') ?></p>
  </div>
  <div class="pixabay-lightbox" id="openclipartLightbox" aria-hidden="true">
    <button type="button" class="pixabay-lightbox-close" id="openclipartLightboxClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    <div class="pixabay-lightbox-backdrop" id="openclipartLightboxBackdrop" aria-hidden="true"></div>
    <div class="pixabay-lightbox-panel" role="dialog" aria-modal="true">
      <div class="pixabay-lightbox-media openclipart-lightbox-media" id="openclipartLightboxMedia"></div>
      <div class="pixabay-lightbox-footer">
        <div class="pixabay-lightbox-meta" id="openclipartLightboxMeta"></div>
        <div class="pixabay-lightbox-actions" id="openclipartLightboxActions"></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($canEdit && count($webdavDrives) > 0): ?>
<div class="modal-backdrop" id="webdavModal" aria-hidden="true">
  <div class="modal pixabay-modal webdav-modal" role="dialog" aria-modal="true" aria-labelledby="webdavModalTitle">
    <div class="sf-dialog-header pixabay-modal-header">
      <h2 id="webdavModalTitle" class="sf-dialog-title pixabay-modal-title"><?= h(t('webdav.title')) ?></h2>
      <button type="button" class="sf-dialog-close" id="webdavModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint pixabay-target-hint"><?= h(t('webdav.target_object')) ?></p>
    <div class="pixabay-modal-toolbar webdav-modal-toolbar">
      <nav class="webdav-breadcrumb" id="webdavBreadcrumb" aria-label="<?= h(t('webdav.breadcrumb')) ?>"></nav>
    </div>
    <div class="pixabay-modal-meta">
      <div class="pixabay-status" id="webdavStatus"></div>
    </div>
    <div class="pixabay-modal-body">
      <div class="webdav-folder-bar" id="webdavFolderBar" hidden></div>
      <div class="pixabay-grid" id="webdavGrid"></div>
    </div>
  </div>
  <div class="pixabay-lightbox" id="webdavLightbox" aria-hidden="true">
    <button type="button" class="pixabay-lightbox-close" id="webdavLightboxClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    <div class="pixabay-lightbox-backdrop" id="webdavLightboxBackdrop" aria-hidden="true"></div>
    <div class="pixabay-lightbox-panel" role="dialog" aria-modal="true">
      <div class="pixabay-lightbox-media" id="webdavLightboxMedia"></div>
      <div class="pixabay-lightbox-footer">
        <div class="pixabay-lightbox-meta" id="webdavLightboxMeta"></div>
        <div class="pixabay-lightbox-actions" id="webdavLightboxActions"></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
window.SF_BOOTSTRAP = <?= json_encode($bootstrap, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/present-config.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/spellcheck.js?v=<?= ASSET_VERSION ?>"></script>
<?php if ($canEdit && (Config::pixabayEnabled() || Config::iconifyEnabled() || Config::openclipartEnabled())): ?>
<script src="assets/js/media-search-translate.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; ?>
<?php if ($canEdit && Config::pixabayEnabled()): ?>
<script src="assets/js/pixabay.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; ?>
<?php if ($canEdit && Config::iconifyEnabled()): ?>
<script src="assets/js/icons.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; ?>
<?php if ($canEdit && Config::openclipartEnabled()): ?>
<script src="assets/js/clipart.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; ?>
<?php if ($canEdit && count($webdavDrives) > 0): ?>
<script src="assets/js/webdav.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/konva@9/konva.min.js"></script>
<?php if ($canEdit): ?>
<script src="assets/js/modal-backdrop.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/ui-dialog.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/ribbon-renderer.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/ribbon-customize.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/js/ribbon.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; ?>
<script src="assets/js/editor.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; // canEdit-Verzweigung Modals/Skripte ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
