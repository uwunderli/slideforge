<?php
/**
 * Katalog aller Ribbon-Befehle und -Widgets.
 * Befehle werden im Anpassungs-Dialog platziert; Widgets referenzieren PHP-Templates.
 *
 * @return list<array<string,mixed>>
 */
function ribbon_command_catalog(): array
{
    return [
        // --- Layout-Helfer ---
        ['id' => 'separator', 'kind' => 'separator', 'category' => 'start', 'labelKey' => 'ribbon.separator', 'icon' => 'separator', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'row_separator', 'kind' => 'row_separator', 'category' => 'start', 'labelKey' => 'ribbon.row_separator', 'icon' => 'row_separator', 'visibleWhen' => ['canEdit']],

        // --- Start ---
        ['id' => 'undo', 'kind' => 'command', 'category' => 'start', 'labelKey' => 'editor.undo', 'icon' => 'undo', 'domId' => 'undoBtn', 'shortcut' => 'Ctrl+Z', 'visibleWhen' => ['canEdit']],
        ['id' => 'redo', 'kind' => 'command', 'category' => 'start', 'labelKey' => 'editor.redo', 'icon' => 'redo', 'domId' => 'redoBtn', 'shortcut' => 'Ctrl+Y', 'visibleWhen' => ['canEdit']],
        ['id' => 'copy', 'kind' => 'command', 'category' => 'start', 'labelKey' => 'props.copy', 'icon' => 'copy', 'domId' => 'copyObjBtn', 'shortcut' => 'Ctrl+C', 'visibleWhen' => ['canEdit'], 'disabled' => true],
        ['id' => 'cut', 'kind' => 'command', 'category' => 'start', 'labelKey' => 'props.cut', 'icon' => 'cut', 'domId' => 'cutObjBtn', 'shortcut' => 'Ctrl+X', 'visibleWhen' => ['canEdit'], 'disabled' => true],
        ['id' => 'paste', 'kind' => 'command', 'category' => 'start', 'labelKey' => 'editor.paste', 'icon' => 'paste', 'domId' => 'pasteBtn', 'shortcut' => 'Ctrl+V', 'visibleWhen' => ['canEdit'], 'disabled' => true, 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'duplicate', 'kind' => 'command', 'category' => 'start', 'labelKey' => 'props.duplicate', 'icon' => 'duplicate', 'domId' => 'dupObjBtn', 'shortcut' => 'Ctrl+D', 'visibleWhen' => ['canEdit'], 'disabled' => true],
        ['id' => 'group', 'kind' => 'command', 'category' => 'start', 'labelKey' => 'editor.group', 'icon' => 'group', 'domId' => 'groupObjBtn', 'shortcut' => 'Ctrl+G', 'visibleWhen' => ['canEdit'], 'disabled' => true],
        ['id' => 'ungroup', 'kind' => 'command', 'category' => 'start', 'labelKey' => 'editor.ungroup', 'icon' => 'ungroup', 'domId' => 'ungroupObjBtn', 'shortcut' => 'Ctrl+Shift+G', 'visibleWhen' => ['canEdit'], 'disabled' => true],
        ['id' => 'spellcheck', 'kind' => 'command', 'category' => 'start', 'labelKey' => 'spell.menu', 'icon' => 'spellcheck', 'domId' => 'spellcheckBtn', 'visibleWhen' => ['canEdit', 'spellcheckEnabled']],

        ['id' => 'widget:zeichen', 'kind' => 'widget', 'category' => 'start', 'labelKey' => 'ribbon.group_zeichen', 'templateId' => 'widget-zeichen', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 8, 'rows' => 2]],
        ['id' => 'widget:templates', 'kind' => 'widget', 'category' => 'start', 'labelKey' => 'ribbon.group_templates', 'templateId' => 'widget-templates', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'widget:absatz', 'kind' => 'widget', 'category' => 'start', 'labelKey' => 'ribbon.group_paragraph', 'templateId' => 'widget-absatz', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 5, 'rows' => 2]],
        ['id' => 'widget:text_colors', 'kind' => 'widget', 'category' => 'start', 'labelKey' => 'ribbon.group_text_color', 'templateId' => 'widget-text-colors', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 4, 'rows' => 2]],

        // --- Einfügen ---
        ['id' => 'add_slide', 'kind' => 'trigger', 'category' => 'insert', 'labelKey' => 'editor.new_slide', 'icon' => 'add_slide', 'domId' => 'addSlideBtnRibbon', 'triggerId' => 'addSlideBtn', 'visibleWhen' => ['canEdit', 'showInsertSlides']],
        ['id' => 'tool:markdown-text', 'kind' => 'tool', 'category' => 'insert', 'labelKey' => 'shape.text_field', 'icon' => 'text_field', 'tool' => 'markdown-text', 'visibleWhen' => ['canEdit']],
        ['id' => 'tool:line', 'kind' => 'tool', 'category' => 'insert', 'labelKey' => 'shape.line', 'icon' => 'line', 'tool' => 'line', 'visibleWhen' => ['canEdit']],
        ['id' => 'tool:rect', 'kind' => 'tool', 'category' => 'insert', 'labelKey' => 'shape.rect', 'icon' => 'rect', 'tool' => 'rect', 'visibleWhen' => ['canEdit']],
        ['id' => 'tool:triangle', 'kind' => 'tool', 'category' => 'insert', 'labelKey' => 'shape.triangle', 'icon' => 'triangle', 'tool' => 'triangle', 'visibleWhen' => ['canEdit']],
        ['id' => 'tool:ellipse', 'kind' => 'tool', 'category' => 'insert', 'labelKey' => 'shape.ellipse', 'icon' => 'ellipse', 'tool' => 'ellipse', 'visibleWhen' => ['canEdit']],
        ['id' => 'tool:bracket', 'kind' => 'tool', 'category' => 'insert', 'labelKey' => 'shape.bracket', 'icon' => 'bracket', 'tool' => 'bracket', 'visibleWhen' => ['canEdit']],
        ['id' => 'tool:arrow', 'kind' => 'tool', 'category' => 'insert', 'labelKey' => 'shape.arrow', 'icon' => 'arrow', 'tool' => 'arrow', 'visibleWhen' => ['canEdit']],
        ['id' => 'tool:star', 'kind' => 'tool', 'category' => 'insert', 'labelKey' => 'shape.star', 'icon' => 'star', 'tool' => 'star', 'visibleWhen' => ['canEdit']],
        ['id' => 'tool:speech-bubble', 'kind' => 'tool', 'category' => 'insert', 'labelKey' => 'shape.speech_bubble', 'icon' => 'speech_bubble', 'tool' => 'speech-bubble', 'visibleWhen' => ['canEdit']],
        ['id' => 'media:image', 'kind' => 'media', 'category' => 'insert', 'labelKey' => 'shape.image', 'icon' => 'media_image', 'mediaAction' => 'image', 'visibleWhen' => ['canEdit']],
        ['id' => 'media:audio', 'kind' => 'media', 'category' => 'insert', 'labelKey' => 'shape.audio', 'icon' => 'media_audio', 'mediaAction' => 'audio', 'visibleWhen' => ['canEdit']],
        ['id' => 'media:video', 'kind' => 'media', 'category' => 'insert', 'labelKey' => 'shape.video', 'icon' => 'media_video', 'mediaAction' => 'video', 'visibleWhen' => ['canEdit']],
        ['id' => 'media:pixabay', 'kind' => 'media', 'category' => 'insert', 'labelKey' => 'pixabay.title', 'icon' => 'pixabay', 'mediaAction' => 'pixabay', 'visibleWhen' => ['canEdit', 'pixabayEnabled']],
        ['id' => 'media:iconify', 'kind' => 'media', 'category' => 'insert', 'labelKey' => 'iconify.title', 'icon' => 'iconify', 'mediaAction' => 'iconify', 'visibleWhen' => ['canEdit', 'iconifyEnabled']],
        ['id' => 'media:openclipart', 'kind' => 'media', 'category' => 'insert', 'labelKey' => 'openclipart.title', 'icon' => 'clipart', 'mediaAction' => 'openclipart', 'visibleWhen' => ['canEdit', 'openclipartEnabled']],
        ['id' => 'widget:layout_elements', 'kind' => 'widget', 'category' => 'insert', 'labelKey' => 'editor.tab_elements', 'templateId' => 'widget-layout-elements', 'visibleWhen' => ['canEdit', 'layoutSetMode'], 'gridSpan' => ['cols' => 8, 'rows' => 2]],

        // --- Folie / Entwurf ---
        ['id' => 'bg:none', 'kind' => 'bgtype', 'category' => 'design', 'labelKey' => 'bg.none', 'icon' => 'bg_none', 'bgType' => 'none', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'widget:slide_bg_color', 'kind' => 'widget', 'category' => 'design', 'labelKey' => 'bg.color', 'templateId' => 'widget-slide-bg-color', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 5, 'rows' => 1]],
        ['id' => 'bg:gradient', 'kind' => 'bgtype', 'category' => 'design', 'labelKey' => 'bg.gradient', 'icon' => 'bg_gradient', 'bgType' => 'gradient', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'bg:image', 'kind' => 'bgtype', 'category' => 'design', 'labelKey' => 'bg.image', 'icon' => 'bg_image', 'bgType' => 'image', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'bg:video', 'kind' => 'bgtype', 'category' => 'design', 'labelKey' => 'bg.video', 'icon' => 'bg_video', 'bgType' => 'video', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'widget:slide_bg_preview', 'kind' => 'widget', 'category' => 'design', 'labelKey' => 'ribbon.slide_bg_preview', 'templateId' => 'widget-slide-bg-preview', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'widget:slide_transition', 'kind' => 'widget', 'category' => 'design', 'labelKey' => 'ribbon.group_slide_transition', 'templateId' => 'widget-slide-transition', 'tileable' => true, 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 10, 'rows' => 2]],
        ['id' => 'widget:slide_autoadvance', 'kind' => 'widget', 'category' => 'design', 'labelKey' => 'ribbon.slide_autoadvance_short', 'templateId' => 'widget-slide-autoadvance', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'apply_transition_selected', 'kind' => 'command', 'category' => 'design', 'labelKey' => 'ribbon.slide_apply_selected_short', 'icon' => 'apply_selected', 'domId' => 'applyTransitionSelectedBtn', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'apply_transition_all', 'kind' => 'command', 'category' => 'design', 'labelKey' => 'ribbon.slide_apply_all_short', 'icon' => 'apply_all', 'domId' => 'applyTransitionAllBtn', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],

        // --- Präsentation ---
        ['id' => 'present_mode', 'kind' => 'link', 'category' => 'present', 'labelKey' => 'ribbon.present_mode_short', 'icon' => 'present', 'domId' => 'presentModeLink', 'hrefKey' => 'present', 'visibleWhen' => ['showPresentTab'], 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'show_progress', 'kind' => 'command', 'category' => 'present', 'labelKey' => 'ribbon.present_progress_short', 'icon' => 'progress_bar', 'domId' => 'showProgressToggle', 'visibleWhen' => ['canEdit', 'showPresentTab'], 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'show_controls', 'kind' => 'command', 'category' => 'present', 'labelKey' => 'ribbon.present_controls_short', 'icon' => 'navigation', 'domId' => 'showControlsToggle', 'visibleWhen' => ['canEdit', 'showPresentTab'], 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'share', 'kind' => 'command', 'category' => 'present', 'labelKey' => 'editor.share', 'icon' => 'share', 'visibleWhen' => ['canEdit', 'showPresentTab', 'isOwner'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'export', 'kind' => 'command', 'category' => 'present', 'labelKey' => 'editor.export', 'icon' => 'export', 'visibleWhen' => ['canEdit', 'showPresentTab'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'widget:settings_title', 'kind' => 'widget', 'category' => 'present', 'labelKey' => 'ribbon.settings_title_short', 'templateId' => 'widget-settings-title', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 6, 'rows' => 2]],
        ['id' => 'widget:settings_size', 'kind' => 'widget', 'category' => 'present', 'labelKey' => 'ribbon.settings_size_short', 'templateId' => 'widget-settings-size', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 3, 'rows' => 2]],
        ['id' => 'widget:settings_margin', 'kind' => 'widget', 'category' => 'present', 'labelKey' => 'ribbon.settings_margin_short', 'templateId' => 'widget-settings-margin', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 3, 'rows' => 1]],
        ['id' => 'widget:settings_duration', 'kind' => 'widget', 'category' => 'present', 'labelKey' => 'ribbon.settings_duration_short', 'templateId' => 'widget-settings-duration', 'visibleWhen' => ['canEdit', 'showPresentTab'], 'gridSpan' => ['cols' => 3, 'rows' => 1]],
        ['id' => 'widget:settings_spellcheck', 'kind' => 'widget', 'category' => 'present', 'labelKey' => 'ribbon.settings_spellcheck_short', 'templateId' => 'widget-settings-spellcheck', 'visibleWhen' => ['canEdit', 'showPresentTab', 'spellcheckEnabled'], 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'widget:settings_layout_set', 'kind' => 'widget', 'category' => 'present', 'labelKey' => 'ribbon.settings_layout_set_short', 'templateId' => 'widget-settings-layout-set', 'visibleWhen' => ['canEdit', 'showPresentTab'], 'gridSpan' => ['cols' => 4, 'rows' => 2]],

        // --- Ansicht ---
        ['id' => 'slide_grid_view', 'kind' => 'command', 'category' => 'view', 'labelKey' => 'editor.tab_slide_grid', 'icon' => 'grid', 'domId' => 'slideGridViewBtn', 'visibleWhen' => ['canEdit', 'showInsertSlides'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'master_slide_nav', 'kind' => 'command', 'category' => 'view', 'labelKey' => 'editor.master_slide', 'icon' => 'master_slide', 'domId' => 'masterSlideNavBtn', 'visibleWhen' => ['showMasterSlideNav'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'preview_tab', 'kind' => 'link', 'category' => 'view', 'labelKey' => 'ribbon.preview_tab_short', 'icon' => 'preview', 'domId' => 'previewTabLink', 'hrefKey' => 'preview', 'target' => '_blank', 'visibleWhen' => ['canEdit', 'showPresentTab'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'preview_window', 'kind' => 'command', 'category' => 'view', 'labelKey' => 'ribbon.preview_window_short', 'icon' => 'preview_window', 'visibleWhen' => ['canEdit', 'showPresentTab'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'present_local', 'kind' => 'command', 'category' => 'view', 'labelKey' => 'ribbon.present_local_short', 'icon' => 'present_local', 'visibleWhen' => ['canEdit', 'showPresentTab'], 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'widget:zoom', 'kind' => 'widget', 'category' => 'view', 'labelKey' => 'ribbon.group_zoom', 'templateId' => 'widget-zoom', 'visibleWhen' => ['canEdit'], 'gridSpan' => ['cols' => 5, 'rows' => 2]],
    ];
}
