<?php
/**
 * Katalog der Befehle/Widgets für das Präsentationsmodus-Ribbon.
 *
 * @return list<array<string,mixed>>
 */
function present_ribbon_command_catalog(): array
{
    return [
        ['id' => 'back_to_editor', 'kind' => 'link', 'category' => 'nav', 'labelKey' => 'ribbon.back_editor_short', 'icon' => 'back_editor', 'domId' => 'editorBackLinkRibbon', 'hrefKey' => 'editor', 'gridSpan' => ['cols' => 2, 'rows' => 2]],

        ['id' => 'show_progress', 'kind' => 'command', 'category' => 'present', 'labelKey' => 'ribbon.present_progress_short', 'icon' => 'progress_bar', 'domId' => 'showProgressToggle', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'show_controls', 'kind' => 'command', 'category' => 'present', 'labelKey' => 'ribbon.present_controls_short', 'icon' => 'navigation', 'domId' => 'showControlsToggle', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'widget:audience', 'kind' => 'widget', 'category' => 'present', 'labelKey' => 'ribbon.present_public_short', 'templateId' => 'widget-present-audience', 'visibleWhen' => ['canBroadcast', 'isOwner'], 'gridSpan' => ['cols' => 4, 'rows' => 2]],
        ['id' => 'widget:remote', 'kind' => 'widget', 'category' => 'present', 'labelKey' => 'remote.qr_section', 'templateId' => 'widget-present-remote', 'visibleWhen' => ['canBroadcast', 'hasRemote'], 'gridSpan' => ['cols' => 6, 'rows' => 2]],
        ['id' => 'widget:local_present', 'kind' => 'widget', 'category' => 'present', 'labelKey' => 'present.local_present', 'templateId' => 'widget-present-local', 'visibleWhen' => ['canBroadcast'], 'gridSpan' => ['cols' => 5, 'rows' => 2]],

        ['id' => 'panel_next', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'ribbon.panel_next_short', 'icon' => 'present_local', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'panel_clock', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'present.clock_section', 'icon' => 'clock', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'panel_timer', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'present.timer_section', 'icon' => 'presentation', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'panel_timebar', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'ribbon.panel_timebar_short', 'icon' => 'timebar', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'panel_media', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'ribbon.panel_media_short', 'icon' => 'media_video', 'visibleWhen' => ['canBroadcast'], 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'panel_slides', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'ribbon.panel_slides_short', 'icon' => 'slides', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'panel_ghost', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'ribbon.panel_ghost_short', 'icon' => 'ghost', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'panel_laser', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'ribbon.panel_laser_short', 'icon' => 'laser', 'gridSpan' => ['cols' => 2, 'rows' => 2]],

        ['id' => 'settings_timebar', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'present.settings_submenu_timebar', 'icon' => 'timebar', 'domId' => 'presentTimebarSettingsBtn', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'settings_clock', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'present.clock_section', 'icon' => 'clock', 'domId' => 'presentClockSettingsBtn', 'gridSpan' => ['cols' => 1, 'rows' => 2]],
        ['id' => 'settings_laser', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'present.settings_submenu_laser', 'icon' => 'laser', 'domId' => 'presentLaserSettingsBtn', 'gridSpan' => ['cols' => 2, 'rows' => 2]],
        ['id' => 'ribbon_customize', 'kind' => 'command', 'category' => 'settings', 'labelKey' => 'ribbon.customize_short', 'icon' => 'ribbon_customize', 'domId' => 'presentRibbonCustomizeBtn', 'gridSpan' => ['cols' => 2, 'rows' => 2]],

        ['id' => 'separator', 'kind' => 'separator', 'category' => 'layout', 'labelKey' => 'ribbon.separator'],
        ['id' => 'row_separator', 'kind' => 'row_separator', 'category' => 'layout', 'labelKey' => 'ribbon.row_separator'],
    ];
}
