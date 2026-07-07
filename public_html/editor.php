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
                'show_progress' => isset($_POST['show_progress']),
                'show_controls' => isset($_POST['show_controls']),
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
    'hasLayoutSet' => $hasLayoutSet,
    'logosImporterEnabled' => $logosImporterEnabled,
    'logosImportedRoles' => LayoutSet::LOGOS_IMPORTED_ROLES,
    'logosExtraRoles' => LayoutSet::LOGOS_ZONE_ROLES,
    'logosZonesAccordionOpen' => Auth::logosZonesAccordionOpen($me),
    'logosRoles' => LayoutSet::LOGOS_ROLES,
    'logosNotesOrder' => $isLayoutSetMode ? LayoutSet::notesOrder($meta) : [],
    'elementZones' => $isLayoutSetMode ? LayoutSet::elementZones($meta) : [],
    'logosLayoutMap' => $linkedLayoutSetMeta ? LayoutSet::layoutMap($linkedLayoutSetMeta) : [],
    'logosLayoutSlideIds' => $linkedLayoutSetMeta ? LayoutSet::layoutSlideIdMap($linkedLayoutSetMeta) : [],
    'elementTextLinks' => $elementTextLinks,
    'elementLinkRoles' => $elementLinkRoles,
    'standardElementRoles' => LayoutSet::STANDARD_ELEMENT_ROLES,
    'logosElementLinkRoles' => $logosImporterEnabled
        ? LayoutSet::LOGOS_ZONE_ROLES
        : [],
    'elementZoneKeys' => LayoutSet::ELEMENT_ZONES,
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
        'logosInsertEmpty' => t('elements.logos_insert_empty'),
        'error' => t('template_modal.error'),
        'empty' => t('template_modal.empty'),
        'own' => t('template_modal.own'),
        'shared' => t('template_modal.shared'),
        'apply' => t('template_modal.apply'),
        'propsEmpty' => t('props.empty'),
        'layersTitle' => t('props.layers_title'),
        'layersEmpty' => t('props.layers_empty'),
        'layersHint' => t('props.layers_hint'),
        'layersDrag' => t('props.layers_drag'),
        'tabFormat' => t('props.tab_format'),
        'tabForm' => t('props.tab_form'),
        'tabPosition' => t('props.tab_position'),
        'tabEffect' => t('props.tab_effect'),
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
        'markdownHint' => t('props.markdown_hint'),
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
        'elementLinksColLogosElements' => t('elements.element_links_col_logos_elements'),
        'elementLinksColLogosMapping' => t('elements.element_links_col_logos_mapping'),
        'elementLinksColLogosDesc' => t('elements.element_links_col_logos_desc'),
        'elementLinksZonesTitle' => t('elements.logos_zones_heading'),
        'elementLinksZonesDesc' => t('elements.logos_zones_desc'),
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
        'applyTransitionSelected' => t('editor.apply_transition_selected'),
        'applyTransitionAll' => t('bg.apply_transition_all'),
        'transitionTitle' => t('bg.transition_title'),
        'autoAdvanceLabel' => t('bg.autoadvance_label'),
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
<div class="editor-topbar editor-topbar-grid">
  <div class="editor-topbar-left">
    <a href="<?= $isTemplateMode ? 'templates.php?tab=slides' : 'index.php' ?>" class="back-link">&larr; <?= $isTemplateMode ? h(t('nav.templates')) : h(t('editor.dashboard')) ?></a>
  </div>
  <div class="editor-topbar-canvas">
  <div class="editor-topbar-icons">
    <button type="button" class="button button-ghost button-sm icon-only" id="undoBtn" title="<?= h(t('editor.undo')) ?> (Ctrl+Z)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14l-4-4 4-4"/><path d="M5 10h11a4 4 0 0 1 0 8h-1"/></svg>
    </button>
    <button type="button" class="button button-ghost button-sm icon-only" id="redoBtn" title="<?= h(t('editor.redo')) ?> (Ctrl+Y)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14l4-4-4-4"/><path d="M19 10H8a4 4 0 0 0 0 8h1"/></svg>
    </button>
    <span class="present-toolbar-sep"></span>
    <button type="button" class="button button-ghost button-sm icon-only" id="copyObjBtn" title="<?= h(t('props.copy')) ?> (Ctrl+C)" disabled>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
    </button>
    <button type="button" class="button button-ghost button-sm icon-only" id="cutObjBtn" title="<?= h(t('props.cut')) ?> (Ctrl+X)" disabled>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.5 15.5M8.5 8.5L20 20"/></svg>
    </button>
    <button type="button" class="button button-ghost button-sm icon-only" id="pasteBtn" title="<?= h(t('editor.paste')) ?> (Ctrl+V)" disabled>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="4" width="8" height="4" rx="1"/><path d="M8 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/></svg>
    </button>
    <span class="present-toolbar-sep"></span>
    <button type="button" class="button button-ghost button-sm icon-only" id="dupObjBtn" title="<?= h(t('props.duplicate')) ?> (Ctrl+D)" disabled>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16V6a2 2 0 0 1 2-2h10"/><line x1="12" y1="14" x2="18" y2="14"/><line x1="15" y1="11" x2="15" y2="17"/></svg>
    </button>
    <button type="button" class="button button-ghost button-sm icon-only" id="groupObjBtn" title="<?= h(t('editor.group')) ?> (Ctrl+G)" disabled>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    </button>
    <button type="button" class="button button-ghost button-sm icon-only" id="ungroupObjBtn" title="<?= h(t('editor.ungroup')) ?> (Ctrl+Shift+G)" disabled>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="8" height="8" rx="1"/><rect x="14" y="2" width="8" height="8" rx="1"/><rect x="2" y="14" width="8" height="8" rx="1"/><rect x="14" y="14" width="8" height="8" rx="1"/><path d="M10 6h4M6 10v4M18 10v4M10 18h4"/></svg>
    </button>
    <?php if ($canEdit && Config::languageToolEnabled()): ?>
    <span class="present-toolbar-sep"></span>
    <button type="button" class="button button-ghost button-sm icon-only" id="spellcheckBtn" title="<?= h(t('spell.menu')) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h16"/><path d="M6 16l6-10 6 10"/><path d="M8.5 13h7"/><path d="M16 18l2 2 4-4"/></svg>
    </button>
    <?php endif; ?>
  </div>
  <div class="editor-topbar-menus">
    <?php if (!$isTemplateMode): ?>
    <a href="present.php?id=<?= urlencode($id) ?>" class="present-config-btn editor-topbar-link" id="presentModeLink">
      <svg class="present-config-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M10 8l6 4-6 4V8z"/></svg>
      <?= h(t('editor.present_mode')) ?>
    </a>
    <div class="present-config-wrap" data-editor-menu data-present-menu>
      <button type="button" class="present-config-btn" data-menu-btn aria-expanded="false" aria-haspopup="true">
        <svg class="present-config-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 22h8"/><path d="M12 18v4"/><path d="M7 15h10"/></svg>
        <?= h(t('present.section_present')) ?>
        <span class="present-config-chevron" aria-hidden="true">▾</span>
      </button>
      <div class="present-config-panel editor-present-panel" data-menu-panel hidden role="menu">
        <div class="present-config-section">
          <div class="present-config-section-title"><?= h(t('editor.menu_preview')) ?></div>
          <a href="preview.php?id=<?= urlencode($id) ?>" target="_blank" class="dropdown-menu-item" id="previewTabLink" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
            <?= h(t('editor.preview_tab')) ?>
          </a>
          <button type="button" class="dropdown-menu-item" id="previewWindowBtn" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="12" height="10" rx="1"/><path d="M14 3h7v7"/><path d="M21 3l-8 8"/></svg>
            <?= h(t('editor.preview_window')) ?>
          </button>
        </div>
        <?php if ($canEdit): ?>
        <div class="present-config-section">
          <div class="present-config-section-title"><?= h(t('present.menu_audience')) ?></div>
          <label class="present-config-check">
            <input type="checkbox" id="showProgressToggle" <?= ($meta['show_progress'] ?? true) ? 'checked' : '' ?>>
            <span><?= h(t('present.progress_bar')) ?></span>
          </label>
          <label class="present-config-check">
            <input type="checkbox" id="showControlsToggle" <?= ($meta['show_controls'] ?? true) ? 'checked' : '' ?>>
            <span><?= h(t('present.controls_toggle')) ?></span>
          </label>
          <?php if ($perm === 'owner'): ?>
          <div class="present-config-row">
            <label class="present-config-check present-config-check-grow">
              <input type="checkbox" id="publicLinkToggle" <?= !empty($acl['public']['enabled']) ? 'checked' : '' ?>>
              <span><?= h(t('present.public_link')) ?></span>
            </label>
            <button type="button" class="button button-ghost button-sm" id="copyPublicLinkBtn" <?= $publicUrl ? '' : 'disabled' ?>><?= h(t('present.copy_link')) ?></button>
          </div>
          <input type="hidden" id="presentPublicLinkInput" value="<?= h($publicUrl) ?>">
          <?php endif; ?>
        </div>
        <div class="present-config-section">
          <div class="present-config-section-title"><?= h(t('present.local_present')) ?></div>
          <label class="present-field-label" for="presentScreenSelect"><?= h(t('present.screen_label')) ?></label>
          <select id="presentScreenSelect" class="present-screen-select"></select>
          <p class="present-screen-hint" id="presentScreenHint"></p>
          <button type="button" class="button button-primary present-local-btn" id="presentLocalBtn"><?= h(t('present.local_start')) ?></button>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="present-config-wrap" data-editor-menu>
      <button type="button" class="present-config-btn" data-menu-btn aria-expanded="false" aria-haspopup="true">
        <svg class="present-config-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.7l7.6-4.4M8.2 13.3l7.6 4.4"/></svg>
        <?= h(t('editor.collaboration')) ?>
        <span class="present-config-chevron" aria-hidden="true">▾</span>
      </button>
      <div class="present-config-panel editor-collab-panel" data-menu-panel hidden role="menu">
        <div class="present-config-section">
          <?php if ($perm === 'owner'): ?>
          <a href="presentation_share.php?id=<?= urlencode($id) ?>" class="dropdown-menu-item" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.7l7.6-4.4M8.2 13.3l7.6 4.4"/></svg>
            <?= h(t('editor.share')) ?>
          </a>
          <?php endif; ?>
          <a href="export.php?id=<?= urlencode($id) ?>" class="dropdown-menu-item" role="menuitem">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 19h16"/></svg>
            <?= h(t('editor.export')) ?>
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <div class="present-config-wrap" data-editor-menu data-settings-menu>
      <button type="button" class="present-config-btn" data-menu-btn aria-expanded="false" aria-haspopup="true">
        <svg class="present-config-btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><circle cx="9" cy="6" r="2"/><line x1="4" y1="12" x2="20" y2="12"/><circle cx="15" cy="12" r="2"/><line x1="4" y1="18" x2="20" y2="18"/><circle cx="7" cy="18" r="2"/></svg>
        <?= h(t('editor.settings_menu')) ?>
        <span class="present-config-chevron" aria-hidden="true">▾</span>
      </button>
      <div class="present-config-panel editor-settings-submenu" data-settings-submenu hidden>
        <button type="button" class="dropdown-menu-item" data-settings-open="slides">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="14" height="14" rx="1.5"/><path d="M17 10l4 2-4 2v-4z"/></svg>
          <?= h(t('editor.tab_slides')) ?>
        </button>
        <?php if (!$isTemplateMode): ?>
        <button type="button" class="dropdown-menu-item" data-settings-open="presentation">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5"/><path d="M9 2h6"/></svg>
          <?= h(t('modal.group_presentation')) ?>
        </button>
        <button type="button" class="dropdown-menu-item" data-settings-open="navigation">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h16"/><circle cx="19" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="19" cy="18" r="2" fill="currentColor" stroke="none"/></svg>
          <?= h(t('modal.group_navigation')) ?>
        </button>
        <?php endif; ?>
      </div>
      <div class="present-config-panel editor-settings-panel" data-settings-panel="slides" hidden>
        <div class="present-config-section">
          <label for="edTitle"><?= h(t('modal.title_label')) ?></label>
          <input type="text" id="edTitle" name="title" form="metaSettingsForm" value="<?= h($meta['title']) ?>">
          <div class="row" style="display:flex; gap:12px; margin-top:10px;">
            <div style="flex:1;">
              <label for="edWidth"><?= h(t('modal.width_label')) ?></label>
              <input type="number" id="edWidth" name="width" form="metaSettingsForm" value="<?= (int)$meta['width'] ?>">
            </div>
            <div style="flex:1;">
              <label for="edHeight"><?= h(t('modal.height_label')) ?></label>
              <input type="number" id="edHeight" name="height" form="metaSettingsForm" value="<?= (int)$meta['height'] ?>">
            </div>
          </div>
          <label for="edSafeMargin" style="margin-top:10px;"><?= h(t('modal.safe_margin_label')) ?></label>
          <input type="number" id="edSafeMargin" name="safe_margin" form="metaSettingsForm" min="0" value="<?= (int)($meta['safe_margin'] ?? 100) ?>">
          <div class="props-video-note" style="margin-top:4px;"><?= h(t('modal.safe_margin_hint')) ?></div>
          <?php if (!$isTemplateMode): ?>
          <label for="edLayoutSet" style="margin-top:14px;"><?= h(t('editor.layout_set_label')) ?></label>
          <select id="edLayoutSet" name="layout_set_id" form="metaSettingsForm">
            <option value=""><?= h(t('editor.layout_set_none')) ?></option>
            <?php foreach ($editorLayoutSets as $set): ?>
              <option value="<?= h($set['id']) ?>" <?= ($meta['layout_set_id'] ?? '') === $set['id'] ? 'selected' : '' ?>><?= h($set['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="props-video-note" style="margin-top:4px;"><?= h(t('editor.layout_set_hint')) ?></div>
          <?php endif; ?>
        </div>
        <div class="present-config-panel-footer">
          <button type="button" class="button button-ghost button-sm" data-settings-back><?= h(t('editor.settings_back')) ?></button>
          <button type="button" class="button button-ghost button-sm" data-menu-close><?= h(t('modal.cancel')) ?></button>
          <button type="submit" class="button button-sm" form="metaSettingsForm"><?= h(t('modal.save')) ?></button>
        </div>
      </div>
      <?php if (!$isTemplateMode): ?>
      <div class="present-config-panel editor-settings-panel" data-settings-panel="presentation" hidden>
        <div class="present-config-section">
          <label for="edDuration"><?= h(t('editor.duration_label')) ?></label>
          <input type="number" id="edDuration" name="presentation_duration" form="metaSettingsForm" min="1" value="<?= (int)($meta['presentation_duration'] ?? 30) ?>">
          <div class="props-video-note" style="margin-top:4px;"><?= h(t('editor.duration_hint')) ?></div>
          <?php if (Config::languageToolEnabled()): ?>
          <label class="present-config-check" style="margin-top:14px;">
            <input type="checkbox" id="spellcheckBeforePresentToggle" style="width:auto;" <?= Auth::spellcheckBeforePresent($me) ? 'checked' : '' ?>>
            <span><?= h(t('editor.spellcheck_before_present')) ?></span>
          </label>
          <div class="props-video-note" style="margin-top:4px;"><?= h(t('editor.spellcheck_before_present_hint')) ?></div>
          <?php endif; ?>
        </div>
        <div class="present-config-panel-footer">
          <button type="button" class="button button-ghost button-sm" data-settings-back><?= h(t('editor.settings_back')) ?></button>
          <button type="button" class="button button-ghost button-sm" data-menu-close><?= h(t('modal.cancel')) ?></button>
          <button type="submit" class="button button-sm" form="metaSettingsForm"><?= h(t('modal.save')) ?></button>
        </div>
      </div>
      <div class="present-config-panel editor-settings-panel" data-settings-panel="navigation" hidden>
        <div class="present-config-section">
          <label class="present-config-check">
            <input type="checkbox" name="show_progress" form="metaSettingsForm" style="width:auto;" <?= ($meta['show_progress'] ?? true) ? 'checked' : '' ?>>
            <span><?= h(t('editor.show_progress_label')) ?></span>
          </label>
          <label class="present-config-check">
            <input type="checkbox" name="show_controls" form="metaSettingsForm" style="width:auto;" <?= ($meta['show_controls'] ?? true) ? 'checked' : '' ?>>
            <span><?= h(t('editor.show_controls_label')) ?></span>
          </label>
        </div>
        <div class="present-config-panel-footer">
          <button type="button" class="button button-ghost button-sm" data-settings-back><?= h(t('editor.settings_back')) ?></button>
          <button type="button" class="button button-ghost button-sm" data-menu-close><?= h(t('modal.cancel')) ?></button>
          <button type="submit" class="button button-sm" form="metaSettingsForm"><?= h(t('modal.save')) ?></button>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
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

<div class="editor-layout">
  <aside class="options-panel">
    <div class="obj-tabs">
      <button type="button" class="obj-tab-btn active" data-objtab="slides" title="<?= h(t('editor.tab_slides')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        <span><?= h(t('editor.tab_slides')) ?></span>
      </button>
      <?php if ($isLayoutSetMode): ?>
      <button type="button" class="obj-tab-btn" data-objtab="elements" title="<?= h(t('editor.tab_elements')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17.5h7M14 14h4"/></svg>
        <span><?= h(t('editor.tab_elements')) ?></span>
      </button>
      <?php endif; ?>
      <button type="button" class="obj-tab-btn" data-objtab="text" title="<?= h(t('editor.tab_text')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
        <span><?= h(t('editor.tab_text')) ?></span>
      </button>
      <button type="button" class="obj-tab-btn" data-objtab="shapes" title="<?= h(t('editor.tab_objects')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="9" height="9" rx="1.5"/><circle cx="16.5" cy="16.5" r="5.5"/></svg>
        <span><?= h(t('editor.tab_objects')) ?></span>
      </button>
      <button type="button" class="obj-tab-btn" data-objtab="media" title="<?= h(t('editor.tab_media')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 8l7 4-7 4V8z"/></svg>
        <span><?= h(t('editor.tab_media')) ?></span>
      </button>
      <button type="button" class="obj-tab-btn" data-objtab="background" title="<?= h(t('editor.tab_background')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 15l-5-5-9 9"/></svg>
        <span><?= h(t('editor.tab_background')) ?></span>
      </button>
      <?php if ($showMasterSlideNav): ?>
      <button type="button"
        class="obj-tab-btn obj-tab-btn-master<?= $masterSlideNavActive ? ' active' : '' ?>"
        id="masterSlideNavBtn"
        title="<?= h($masterSlideNavActive ? t('editor.master_slide_back') : t('editor.master_slide_open')) ?>"
        data-set-id="<?= h($masterSlideSetId) ?>"
        data-presentation-id="<?= h($masterSlideNavActive ? $masterSlideReturnId : $id) ?>"
        data-return-id="<?= h($masterSlideReturnId) ?>"
        data-return-slide="<?= (int)$masterSlideReturnSlide ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="4" y="5" width="14" height="10" rx="1.5"/>
          <path d="M8 19h12"/>
          <rect x="10" y="9" width="14" height="10" rx="1.5"/>
        </svg>
        <span><?= h(t('editor.master_slide')) ?></span>
      </button>
      <?php endif; ?>
    </div>

    <div class="obj-tab-content">
    <div class="obj-tab-panel active" data-objtab="slides">
      <div class="editor-slides-toolbar">
        <?php if ($canEdit && (!$isTemplateMode || $isLayoutSetMode)): ?>
          <button type="button" class="tool-btn-block" id="addSlideBtn">+ <?= h(t('editor.new_slide')) ?></button>
          <?php if (!$isTemplateMode): ?>
          <button type="button" class="button button-ghost button-sm" id="slideGridViewBtn" style="width:100%; margin-top:8px;"><?= h(t('editor.slide_grid_open')) ?></button>
          <button type="button" class="button button-ghost button-sm" id="applyTemplateBtn" style="width:100%; margin-top:8px;"><?= h(t('editor.apply_template')) ?></button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div id="slideFilmstrip" class="editor-slide-filmstrip"></div>
    </div>
    <?php if ($isLayoutSetMode): ?>
    <?php $elementZones = LayoutSet::elementZones($meta); ?>
    <?php
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
    <div class="obj-tab-panel" data-objtab="elements">
      <div class="elements-panel-inner">
        <div class="options-subtitle"><?= h(t('elements.standard_heading')) ?></div>
        <p class="elements-panel-hint"><?= h(t('elements.standard_desc')) ?></p>
        <div class="element-rows" id="standardElementButtons">
          <?php foreach (LayoutSet::STANDARD_ELEMENT_ROLES as $role): ?>
          <button type="button" class="element-row-btn tool-btn-block" data-set-role="<?= h($role) ?>">
            <span class="element-row-icon" aria-hidden="true"><?= sf_element_icon($role) ?></span>
            <span class="element-row-label"><?= h(t('logos.role_' . $role)) ?></span>
            <?php if ($logosImporterEnabled && !empty($activeLogosRoles[$role])): ?><?= sf_logos_badge() ?><?php endif; ?>
          </button>
          <?php endforeach; ?>
        </div>
        <?php if ($logosImporterEnabled): ?>
        <div class="element-logos-insert-section" id="logosSlideInsertSection"<?= $logosSlideInsertRoles ? '' : ' style="display:none;"' ?>>
          <div class="options-subtitle"><?= h(t('elements.logos_insert_heading_more')) ?></div>
          <div id="logosSlideInsertButtons" class="element-rows">
            <?php if ($logosSlideInsertRoles): ?>
              <?php foreach ($logosSlideInsertRoles as $role): ?>
              <button type="button" class="element-row-btn tool-btn-block" data-set-role="<?= h($role) ?>">
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
        <button type="button" class="element-row-btn tool-btn-block" id="configureElementLinksBtn" style="margin-top:14px;">
          <span class="element-row-label"><?= h(t('elements.configure_element_links')) ?></span>
          <?php if ($logosImporterEnabled): ?><?= sf_logos_badge() ?><?php endif; ?>
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="obj-tab-panel" data-objtab="text">
      <button type="button" class="tool-btn-block" data-tool="text"><?= h(t('shape.text')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="markdown-text"><?= h(t('shape.markdown_text')) ?></button>
      <div id="textTemplateButtons"></div>
    </div>

    <div class="obj-tab-panel" data-objtab="shapes">
      <button type="button" class="tool-btn-block" data-tool="line">╱ <?= h(t('shape.line')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="rect">▭ <?= h(t('shape.rect')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="triangle">△ <?= h(t('shape.triangle')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="ellipse">◯ <?= h(t('shape.ellipse')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="bracket">( <?= h(t('shape.bracket')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="arrow">→ <?= h(t('shape.arrow')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="star">★ <?= h(t('shape.star')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="speech-bubble">💬 <?= h(t('shape.speech_bubble')) ?></button>
    </div>

    <div class="obj-tab-panel" data-objtab="media">
      <div class="media-subnav">
        <button type="button" class="media-subnav-btn active" data-mediasub="insert"><?= h(t('media_lib.sub_insert')) ?></button>
        <button type="button" class="media-subnav-btn" data-mediasub="library"><?= h(t('media_lib.sub_overview')) ?></button>
      </div>
      <div class="media-sub-panel active" data-mediasub="insert">
      <button type="button" class="tool-btn-block" data-tool="image">🖼 <?= h(t('shape.image')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="audio"><?= h(t('shape.audio')) ?></button>
      <button type="button" class="tool-btn-block" data-tool="video"><?= h(t('shape.video')) ?></button>
      <input type="file" id="objImageInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
      <input type="file" id="objAudioInput" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp4" hidden>
      <input type="file" id="objVideoInput" accept="video/mp4,video/webm" hidden>
      <?php if ($canEdit && Config::pixabayEnabled()): ?>
      <button type="button" class="tool-btn-block" id="pixabayOpenBtn">📷 <?= h(t('pixabay.open_from_bg')) ?></button>
      <?php endif; ?>
      <?php if ($canEdit && Config::iconifyEnabled()): ?>
      <button type="button" class="tool-btn-block" id="iconifyOpenBtn">▣ <?= h(t('iconify.open_from_media')) ?></button>
      <?php endif; ?>
      <?php if ($canEdit && Config::openclipartEnabled()): ?>
      <button type="button" class="tool-btn-block" id="openclipartOpenBtn">✂️ <?= h(t('openclipart.open_from_media')) ?></button>
      <?php endif; ?>
      <?php if ($canEdit && count($webdavDrives) > 0): ?>
      <div class="media-source-divider" role="separator" aria-hidden="true"></div>
      <?php foreach ($webdavDrives as $wdDrive): ?>
      <button type="button" class="tool-btn-block webdav-drive-btn" data-drive-id="<?= h($wdDrive['id']) ?>" data-drive-label="<?= h($wdDrive['label']) ?>">☁ <?= h($wdDrive['label']) ?></button>
      <?php endforeach; ?>
      <?php endif; ?>
      </div>
      <div class="media-sub-panel" data-mediasub="library" id="mediaLibraryPanel" hidden>
        <div class="media-library-header">
          <div class="options-title"><?= h(t('media_lib.title')) ?></div>
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

    <div class="obj-tab-panel" data-objtab="background">
      <div class="options-section">
        <div class="options-title"><?= h(t('bg.title')) ?></div>
        <div class="bg-type-tabs">
          <button type="button" class="bg-type-btn active" data-bgtype="color"><?= h(t('bg.color')) ?></button>
          <button type="button" class="bg-type-btn" data-bgtype="gradient"><?= h(t('bg.gradient')) ?></button>
          <button type="button" class="bg-type-btn" data-bgtype="image"><?= h(t('bg.image')) ?></button>
          <button type="button" class="bg-type-btn" data-bgtype="video"><?= h(t('bg.video')) ?></button>
          <button type="button" class="bg-type-btn" data-bgtype="none"><?= h(t('bg.none')) ?></button>
        </div>

        <div class="bg-panel" data-bgtype="color">
          <input type="color" id="bgColorInput" value="#111111" style="width:100%; height:34px;">
          <div class="options-subtitle"><?= h(t('bg.brand_colors')) ?></div>
          <div class="brand-palette" id="brandPalette"></div>
        </div>

        <div class="bg-panel" data-bgtype="gradient" hidden>
          <div class="row">
            <div><label for="bgGradColor1" style="margin-top:0;"><?= h(t('bg.color1')) ?></label><input type="color" id="bgGradColor1" value="#3a6c8d"></div>
            <div><label for="bgGradColor2" style="margin-top:0;"><?= h(t('bg.color2')) ?></label><input type="color" id="bgGradColor2" value="#87b42b"></div>
          </div>
          <label for="bgGradAngle"><?= h(t('bg.angle')) ?> (<span id="bgGradAngleVal">90</span>°)</label>
          <input type="range" id="bgGradAngle" min="0" max="360" value="90">
        </div>

        <div class="bg-panel" data-bgtype="image" hidden>
          <input type="file" id="bgImageInput" accept="image/jpeg,image/png,image/gif,image/webp">
          <?php if ($canEdit && Config::pixabayEnabled()): ?>
          <button type="button" class="button button-ghost button-sm pixabay-open-btn" data-pixabay-open="background-image" style="width:100%; margin-top:8px;"><?= h(t('pixabay.open_from_bg')) ?></button>
          <?php endif; ?>
          <div id="bgImagePreviewWrap" class="bg-asset-preview" hidden>
            <img id="bgImagePreview" alt="Hintergrundbild">
            <button type="button" id="bgImageRemove" class="button button-ghost button-sm"><?= h(t('bg.remove')) ?></button>
          </div>
        </div>

        <div class="bg-panel" data-bgtype="video" hidden>
          <input type="file" id="bgVideoInput" accept="video/mp4,video/webm">
          <?php if ($canEdit && Config::pixabayEnabled()): ?>
          <button type="button" class="button button-ghost button-sm pixabay-open-btn" data-pixabay-open="background-video" style="width:100%; margin-top:8px;"><?= h(t('pixabay.open_from_bg')) ?></button>
          <?php endif; ?>
          <div id="bgVideoPreviewWrap" class="bg-asset-preview" hidden>
            <video id="bgVideoPreview" muted loop autoplay playsinline></video>
            <button type="button" id="bgVideoRemove" class="button button-ghost button-sm"><?= h(t('bg.remove')) ?></button>
          </div>
        </div>
        <div class="bg-panel" data-bgtype="none" hidden>
          <div class="props-video-note" style="margin-top:0;"><?= h(t('bg.none_hint')) ?></div>
        </div>
      </div>
      <div class="options-section">
        <div class="options-title"><?= h(t('bg.transition_title')) ?></div>
        <input type="hidden" id="transitionSelect" value="slide">
        <div id="transitionPickerGroup" class="effect-icon-grid transition-icon-grid"></div>
        <button type="button" class="button button-ghost button-sm" id="applyTransitionAllBtn" style="width:100%; margin-top:8px;"><?= h(t('bg.apply_transition_all')) ?></button>
        <label for="autoAdvanceInput"><?= h(t('bg.autoadvance_label')) ?></label>
        <input type="number" id="autoAdvanceInput" min="0" step="1" value="0">
      </div>
    </div>

    </div>
  </aside>

  <main class="canvas-area">
    <div class="canvas-scroll">
      <div id="stageWrap" class="stage-wrap">
        <div id="stageBgLayer" class="stage-bg-layer"></div>
        <div id="stageContainer"></div>
      </div>
    </div>
    <div class="zoom-bar">
      <button type="button" class="zoom-btn" id="zoomOutBtn" title="<?= h(t('zoom.out')) ?>">&minus;</button>
      <span id="zoomLabel">100%</span>
      <button type="button" class="zoom-btn" id="zoomInBtn" title="<?= h(t('zoom.in')) ?>">+</button>
      <span class="zoom-sep"></span>
      <button type="button" class="zoom-btn zoom-btn-text" id="zoomFitBtn" title="<?= h(t('zoom.fit')) ?>"><?= h(t('zoom.fit')) ?></button>
    </div>
    <?php if ($canEdit): ?>
    <div class="notes-panel-editor">
      <div class="notes-panel-header">
        <span><?= h(t('notes.title')) ?></span>
        <span><?= t('notes.hint') ?></span>
      </div>
      <textarea id="slideNotesInput" placeholder="<?= h(t('notes.placeholder')) ?>"></textarea>
    </div>
    <?php endif; ?>
  </main>

  <?php if ($canEdit): ?>
  <aside class="spell-panel" id="spellPanel" hidden aria-label="<?= h(t('spell.title')) ?>">
    <div class="spell-panel-header">
      <h2><?= h(t('spell.title')) ?></h2>
      <button type="button" class="spell-panel-close" id="spellPanelClose" aria-label="<?= h(t('common.close')) ?>">&times;</button>
    </div>
    <div class="spell-panel-toolbar">
      <button type="button" class="button button-sm" id="spellRunBtn"><?= h(t('spell.run')) ?></button>
      <button type="button" class="button button-ghost button-sm" id="spellProceedBtn" hidden><?= h(t('spell.proceed_present')) ?></button>
      <span class="spell-status" id="spellStatus"></span>
    </div>
    <div class="spell-panel-body" id="spellResults"></div>
  </aside>
  <aside class="props-panel-wrap" id="propsPanelWrap">
    <div class="props-layers-section" id="propsLayersPanel"></div>
    <div class="props-object-panel" id="propsObjectPanel">
      <div class="props-empty"><?= t('props.empty') ?></div>
    </div>
  </aside>
  <div class="editor-slide-grid-overlay" id="slideGridOverlay" hidden>
    <div class="editor-slide-grid-inner" role="dialog" aria-modal="true" aria-labelledby="slideGridTitle">
      <header class="editor-slide-grid-header">
        <h2 id="slideGridTitle"><?= h(t('editor.slide_grid_title')) ?></h2>
        <button type="button" class="button button-ghost button-sm" id="slideGridCloseBtn" aria-label="<?= h(t('common.close')) ?>">✕</button>
      </header>
      <div class="editor-slide-grid-toolbar">
        <div class="editor-slide-grid-toolbar-row">
          <span class="editor-slide-grid-selection" id="slideGridSelectionInfo"></span>
          <button type="button" class="button button-ghost button-sm" id="slideGridSelectAllBtn"><?= h(t('editor.slide_grid_select_all')) ?></button>
          <button type="button" class="button button-ghost button-sm" id="slideGridSelectNoneBtn"><?= h(t('editor.slide_grid_select_none')) ?></button>
          <span class="present-toolbar-sep"></span>
          <button type="button" class="button button-sm" id="applyTransitionSelectedBtn" disabled><?= h(t('editor.apply_transition_selected')) ?></button>
          <button type="button" class="button button-ghost button-sm" id="applyTransitionAllGridBtn"><?= h(t('bg.apply_transition_all')) ?></button>
        </div>
        <div class="editor-slide-grid-toolbar-row editor-slide-grid-toolbar-row-settings">
          <div class="editor-slide-grid-setting-block">
            <span class="editor-slide-grid-toolbar-label"><?= h(t('bg.transition_title')) ?></span>
            <input type="hidden" id="gridTransitionSelect" value="slide">
            <div id="gridTransitionPickerGroup" class="effect-icon-grid editor-grid-transition-picker"></div>
          </div>
          <div class="editor-slide-grid-setting-block editor-slide-grid-autoadvance-block">
            <label class="editor-slide-grid-toolbar-label" for="gridAutoAdvanceInput"><?= h(t('bg.autoadvance_label')) ?></label>
            <input type="number" id="gridAutoAdvanceInput" class="editor-slide-grid-autoadvance-input" min="0" step="1" value="0">
          </div>
        </div>
      </div>
      <div class="editor-slide-grid-scroll">
        <div class="editor-slide-grid" id="slideGrid"></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; // canEdit-Verzweigung Editor-Layout ?>

<?php if ($canEdit): ?>
<form method="post" id="metaSettingsForm" class="hidden-form" hidden aria-hidden="true">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="resize">
</form>

<div class="modal-backdrop" id="elementLinksModal">
  <div class="modal modal-element-config">
    <h2 style="font-size:1.2rem; text-transform:none;"><?= h(t('elements.element_links_modal_title')) ?></h2>
    <p style="color:var(--text-muted); font-size:0.85rem; margin-top:-8px;"><?= h(t('elements.element_links_modal_desc')) ?></p>
    <div id="elementLinksModalBody" class="element-links-modal-body"></div>
    <div class="modal-actions">
      <button type="button" class="button button-ghost" id="elementLinksModalClose"><?= h(t('common.close')) ?></button>
      <button type="button" class="button" id="elementLinksModalSave"><?= h(t('tpl.save')) ?></button>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="templateModal">
  <div class="modal">
    <h2 style="font-size:1.2rem; text-transform:none;"><?= h(t('editor.apply_template')) ?></h2>
    <p style="color:var(--text-muted); font-size:0.85rem; margin-top:-8px;"><?= h(t('template_modal.hint')) ?></p>
    <div id="templateList" style="max-height:340px; overflow-y:auto; margin-top:14px;"></div>
    <div class="modal-actions">
      <button type="button" class="button button-ghost" onclick="document.getElementById('templateModal').classList.remove('open')"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>

<?php if (Config::pixabayEnabled()): ?>
<div class="modal-backdrop" id="pixabayModal" aria-hidden="true">
  <div class="modal pixabay-modal" role="dialog" aria-modal="true" aria-labelledby="pixabayModalTitle">
    <div class="pixabay-modal-header">
      <div>
        <h2 id="pixabayModalTitle" class="pixabay-modal-title"><?= h(t('pixabay.title')) ?></h2>
        <p class="pixabay-target-hint" id="pixabayTargetHint"></p>
      </div>
      <button type="button" class="button button-ghost button-sm" id="pixabayModalClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    </div>
    <div class="pixabay-modal-toolbar">
      <div class="pixabay-search-row">
        <input type="search" id="pixabayQuery" placeholder="<?= h(t('pixabay.search_placeholder')) ?>" autocomplete="off">
        <button type="button" class="button button-sm" id="pixabaySearchBtn"><?= h(t('pixabay.search')) ?></button>
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
    <div class="pixabay-modal-header">
      <div>
        <h2 id="iconifyModalTitle" class="pixabay-modal-title"><?= h(t('iconify.title')) ?></h2>
        <p class="pixabay-target-hint"><?= h(t('iconify.target_object')) ?></p>
      </div>
      <button type="button" class="button button-ghost button-sm" id="iconifyModalClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    </div>
    <div class="pixabay-modal-toolbar iconify-modal-toolbar">
      <div class="iconify-toolbar-row iconify-search-row">
        <div class="pixabay-search-row">
          <input type="search" id="iconifyQuery" placeholder="<?= h(t('iconify.search_placeholder')) ?>" autocomplete="off">
          <button type="button" class="button button-sm" id="iconifySearchBtn"><?= h(t('iconify.search')) ?></button>
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
    <div class="pixabay-modal-header">
      <div>
        <h2 id="openclipartModalTitle" class="pixabay-modal-title"><?= h(t('openclipart.title')) ?></h2>
        <p class="pixabay-target-hint"><?= h(t('openclipart.target_object')) ?></p>
      </div>
      <button type="button" class="button button-ghost button-sm" id="openclipartModalClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    </div>
    <div class="pixabay-modal-toolbar openclipart-modal-toolbar">
      <div class="iconify-toolbar-row iconify-search-row">
        <div class="pixabay-search-wrap">
          <input type="search" id="openclipartQuery" placeholder="<?= h(t('openclipart.search_placeholder')) ?>" autocomplete="off">
          <button type="button" class="button button-sm" id="openclipartSearchBtn"><?= h(t('openclipart.search')) ?></button>
        </div>
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
    <div class="pixabay-modal-header">
      <div>
        <h2 id="webdavModalTitle" class="pixabay-modal-title"><?= h(t('webdav.title')) ?></h2>
        <p class="pixabay-target-hint"><?= h(t('webdav.target_object')) ?></p>
      </div>
      <button type="button" class="button button-ghost button-sm" id="webdavModalClose" aria-label="<?= h(t('common.close')) ?>">✕</button>
    </div>
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
<script src="assets/js/editor.js?v=<?= ASSET_VERSION ?>"></script>
<?php endif; // canEdit-Verzweigung Modals/Skripte ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
