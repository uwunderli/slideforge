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
                    ['id' => 'g_file', 'labelKey' => 'ff.group', 'items' => [
                        ['id' => 'ff_save', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'ff_save_copy', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'ff_save_template', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
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
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'find_replace', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'notes_to_path', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    /* 4.0: Zeichen, Absatz und Schriftfarbe aus Einzelbefehlen, je zwei Zeilen (row_separator = neue Zeile). */
                    ['id' => 'g_zeichen', 'labelKey' => 'ribbon.group_zeichen', 'items' => ribbon_default_items_zeichen()],
                    ['id' => 'g_absatz', 'labelKey' => 'ribbon.group_paragraph', 'items' => ribbon_default_items_absatz()],
                    ['id' => 'g_colors', 'labelKey' => 'ribbon.group_text_color', 'items' => ribbon_default_items_text_colors()],
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
                        ['id' => 'media:insert_presentation', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'delete_slides', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_text', 'labelKey' => 'ribbon.group_text', 'items' => [
                        ['id' => 'tool:markdown-text', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'tool:table', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'tool:timeline', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
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
                        ['id' => 'media:fromurl', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
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
                        ['id' => 'slide_bg_color'],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'bg:image', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'bg:video', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_transition', 'labelKey' => 'ribbon.group_slide_transition', 'items' => ribbon_default_items_transitions()],
                    ['id' => 'g_timing', 'labelKey' => 'ribbon.group_slide_timing', 'items' => [
                        ['id' => 'slide_autoadvance'],
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
                        ['id' => 'show_on_device', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
                        ['id' => 'show_progress', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'show_controls', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_settings', 'labelKey' => 'ribbon.group_settings', 'items' => ribbon_default_items_settings()],
                    ['id' => 'g_collab', 'labelKey' => 'ribbon.group_collaboration', 'items' => [
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
                        ['id' => 'play_slide', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                        ['id' => 'preview_device', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
                    ]],
                    ['id' => 'g_zoom', 'labelKey' => 'ribbon.group_zoom', 'items' => ribbon_default_items_zoom()],
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

/**
 * Einzelbefehle der früheren Blöcke (4.0) — Vorgabe-Layout und Übergang alter Layouts (src/RibbonLayout.php) nehmen dieselbe Liste,
 * damit ein ersetzter Block genau so aussieht wie die Vorgabe.
 *
 * @return list<array<string,mixed>>
 */
function ribbon_default_items_zeichen(): array
{
    return [
        ['id' => 'text_font', 'gridSpan' => ['cols' => 5, 'rows' => 1]],
        ['id' => 'text_fontsize', 'gridSpan' => ['cols' => 2, 'rows' => 1]],
        ['id' => 'row_separator'],
        ['id' => 'text_bold', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_italic', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_underline', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_strike', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_uppercase', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_smallcaps', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
    ];
}

/** @return list<array<string,mixed>> */
function ribbon_default_items_absatz(): array
{
    return [
        ['id' => 'text_bullet', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_number', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_lineheight', 'gridSpan' => ['cols' => 3, 'rows' => 1]],   // rechtsbündig über dem Zeichenabstand
        ['id' => 'row_separator'],
        ['id' => 'text_align_left', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_align_center', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_align_right', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_letterspacing', 'gridSpan' => ['cols' => 2, 'rows' => 1]],
    ];
}

/** @return list<array<string,mixed>> */
function ribbon_default_items_text_colors(): array
{
    return [
        ['id' => 'text_color_apply', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_color', 'gridSpan' => ['cols' => 2, 'rows' => 1]],
        ['id' => 'text_mark', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'text_mark_color', 'gridSpan' => ['cols' => 2, 'rows' => 1]],
        ['id' => 'text_mark_clear', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'row_separator'],
        ['id' => 'text_opacity', 'gridSpan' => ['cols' => 7, 'rows' => 1]],
    ];
}

/** Übergänge (4.0, vorher widget:slide_transition): je ein grosser Knopf. @return list<array<string,mixed>> */
function ribbon_default_items_transitions(): array
{
    $out = [];
    foreach (['none', 'fade', 'slide', 'convex', 'concave', 'zoom'] as $v) {
        $out[] = ['id' => 'transition_' . $v, 'gridSpan' => ['cols' => 2, 'rows' => 2]];
    }
    return $out;
}

/**
 * Einstellungen (4.0, vorher widget:settings_*): Titel · Breite/Rand über Höhe/Dauer · Folien-Set · Rechtschreibung.
 * Breite und Höhe stehen untereinander wie früher im Block «B×H», Rand und Dauer daneben.
 *
 * @return list<array<string,mixed>>
 */
function ribbon_default_items_settings(): array
{
    return [
        ['id' => 'settings_title', 'gridSpan' => ['cols' => 6, 'rows' => 2]],
        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'settings_width', 'gridSpan' => ['cols' => 3, 'rows' => 1]],
        ['id' => 'settings_margin', 'gridSpan' => ['cols' => 3, 'rows' => 1]],
        ['id' => 'row_separator'],
        ['id' => 'settings_height', 'gridSpan' => ['cols' => 3, 'rows' => 1]],
        ['id' => 'settings_duration', 'gridSpan' => ['cols' => 3, 'rows' => 1]],
        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'settings_layout_set', 'gridSpan' => ['cols' => 4, 'rows' => 2]],
        ['id' => 'separator', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'settings_spellcheck', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
    ];
}

/** Zoom (4.0, vorher widget:zoom). @return list<array<string,mixed>> */
function ribbon_default_items_zoom(): array
{
    return [
        ['id' => 'zoom_out', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'zoom_level', 'gridSpan' => ['cols' => 3, 'rows' => 1]],
        ['id' => 'zoom_in', 'gridSpan' => ['cols' => 1, 'rows' => 1]],
        ['id' => 'row_separator'],
        ['id' => 'zoom_fit', 'gridSpan' => ['cols' => 5, 'rows' => 1]],
    ];
}
