<?php
/**
 * Standard-Ribbon-Layout (SoftMaker-orientiert, 5 Registerkarten).
 * Stand: Nutzer-Layout «urs» inkl. Ansicht→Vorschau (2026-07-27).
 *
 * @return array<string,mixed>
 */
function ribbon_default_layout(): array
{
    return [
        'version' => 1,
        'tabs' => [
            [
                'id' => 'start',
                'labelKey' => 'ribbon.tab_start',
                'groups' => [
                    ['id' => 'g_edit', 'labelKey' => 'ribbon.group_edit', 'items' => [
                        ['id' => 'undo', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'redo', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'cut', 'gridSpan' => ['cols' => 1, 'rows' => 1], 'showLabel' => false],
                        ['id' => 'copy', 'gridSpan' => ['cols' => 1, 'rows' => 1], 'showLabel' => false],
                        ['id' => 'duplicate', 'gridSpan' => ['cols' => 1, 'rows' => 1], 'showLabel' => false],
                        ['id' => 'paste', 'gridSpan' => ['cols' => 1, 'rows' => 1], 'showLabel' => false],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'ungroup', 'gridSpan' => ['cols' => 1, 'rows' => 1], 'showLabel' => false],
                        ['id' => 'group', 'gridSpan' => ['cols' => 1, 'rows' => 1], 'showLabel' => false],
                    ]],
                    ['id' => 'g_zeichen', 'labelKey' => 'ribbon.group_zeichen', 'items' => [
                        ['id' => 'widget:zeichen'],
                    ]],
                    ['id' => 'g_absatz', 'labelKey' => 'ribbon.group_paragraph', 'items' => [
                        ['id' => 'widget:absatz'],
                    ]],
                    ['id' => 'g_colors', 'labelKey' => 'ribbon.group_text_color', 'items' => [
                        ['id' => 'widget:text_colors'],
                    ]],
                    ['id' => 'g_templates', 'labelKey' => 'ribbon.group_templates', 'items' => [
                        ['id' => 'widget:templates'],
                    ]],
                    ['id' => 'g_review', 'labelKey' => 'ribbon.group_review', 'items' => [
                        ['id' => 'spellcheck', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                ],
            ],
            [
                'id' => 'insert',
                'labelKey' => 'ribbon.tab_insert',
                'groups' => [
                    ['id' => 'g_slides', 'labelKey' => 'ribbon.group_slides', 'items' => [
                        ['id' => 'add_slide', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_text', 'labelKey' => 'ribbon.group_text', 'items' => [
                        ['id' => 'tool:markdown-text', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_shapes', 'labelKey' => 'ribbon.group_shapes', 'items' => [
                        ['id' => 'tool:line', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'tool:rect', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'tool:ellipse', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'tool:triangle', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'tool:arrow', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'tool:bracket', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'tool:star', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'tool:speech-bubble', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_media', 'labelKey' => 'ribbon.group_media', 'items' => [
                        ['id' => 'media:image', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'media:audio', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'media:video', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_media_online', 'labelKey' => 'ribbon.group_media_online', 'items' => [
                        ['id' => 'media:pixabay', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'media:iconify', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'media:openclipart', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    /* Nur im Folien-Set-Editor sichtbar (visibleWhen layoutSetMode). */
                    ['id' => 'g_layout_elements', 'labelKey' => 'editor.tab_elements', 'items' => [
                        ['id' => 'widget:layout_elements'],
                    ]],
                ],
            ],
            [
                'id' => 'design',
                'labelKey' => 'ribbon.tab_design',
                'groups' => [
                    ['id' => 'g_slide_bg', 'labelKey' => 'ribbon.group_slide_background', 'items' => [
                        ['id' => 'widget:slide_bg_preview'],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'bg:none', 'gridSpan' => ['cols' => 1, 'rows' => 1], 'showLabel' => false],
                        ['id' => 'bg:gradient', 'gridSpan' => ['cols' => 1, 'rows' => 1], 'showLabel' => false],
                        ['id' => 'row_separator'],
                        ['id' => 'widget:slide_bg_color'],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'bg:image', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'bg:video', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_transition', 'labelKey' => 'ribbon.group_slide_transition', 'items' => [
                        ['id' => 'widget:slide_transition'],
                    ]],
                    ['id' => 'g_timing', 'labelKey' => 'ribbon.group_slide_timing', 'items' => [
                        ['id' => 'widget:slide_autoadvance'],
                    ]],
                    ['id' => 'g_design_settings', 'labelKey' => 'ribbon.group_settings', 'items' => [
                        ['id' => 'apply_transition_selected', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'apply_transition_all', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                ],
            ],
            [
                'id' => 'present',
                'labelKey' => 'ribbon.tab_present',
                'groups' => [
                    ['id' => 'g_present_start', 'labelKey' => 'ribbon.group_present', 'items' => [
                        ['id' => 'present_mode', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'widget:present_display', 'gridSpan' => ['cols' => 10, 'rows' => 2]],
                    ]],
                    ['id' => 'g_settings', 'labelKey' => 'ribbon.group_settings', 'items' => [
                        ['id' => 'widget:settings_title', 'gridSpan' => ['cols' => 6, 'rows' => 2]],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'widget:settings_size', 'gridSpan' => ['cols' => 3, 'rows' => 2]],
                        ['id' => 'widget:settings_margin', 'gridSpan' => ['cols' => 3, 'rows' => 1]],
                        ['id' => 'row_separator'],
                        ['id' => 'widget:settings_duration', 'gridSpan' => ['cols' => 3, 'rows' => 1]],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'widget:settings_layout_set', 'gridSpan' => ['cols' => 4, 'rows' => 2]],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'widget:settings_spellcheck', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_collab', 'labelKey' => 'ribbon.group_collaboration', 'items' => [
                        ['id' => 'share', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'export', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                    ]],
                ],
            ],
            [
                'id' => 'view',
                'labelKey' => 'ribbon.tab_view',
                'groups' => [
                    ['id' => 'g_view', 'labelKey' => 'ribbon.group_view', 'items' => [
                        ['id' => 'slide_grid_view', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'master_slide_nav', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_preview', 'labelKey' => 'ribbon.group_preview', 'items' => [
                        ['id' => 'preview_tab', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'preview_window', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'present_local', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_zoom', 'labelKey' => 'ribbon.group_zoom', 'items' => [
                        ['id' => 'widget:zoom'],
                    ]],
                ],
            ],
        ],
        'prefs' => [
            'activeTab' => 'start',
            'collapsed' => false,
            'iconSize' => 'medium',
            'showLabels' => true,
        ],
    ];
}
