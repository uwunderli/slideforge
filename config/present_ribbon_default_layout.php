<?php
/**
 * Standard-Layout für das Präsentationsmodus-Ribbon.
 *
 * @return array{version:int,tabs:list<array<string,mixed>>,prefs:array<string,mixed>}
 */
function present_ribbon_default_layout(): array
{
    return [
        'version' => 1,
        'prefs' => [
            'activeTab' => 'present',
            'collapsed' => false,
            'iconSize' => 'medium',
            'showLabels' => true,
        ],
        'tabs' => [
            [
                'id' => 'present',
                'labelKey' => 'present.section_present',
                'groups' => [
                    [
                        'id' => 'nav',
                        'labelKey' => 'ribbon.group_nav',
                        'items' => [
                            ['id' => 'back_to_editor'],
                        ],
                    ],
                    [
                        'id' => 'audience',
                        'labelKey' => 'present.menu_audience',
                        'items' => [
                            ['id' => 'show_progress'],
                            ['id' => 'show_controls'],
                            ['id' => 'widget:audience'],
                        ],
                    ],
                    [
                        'id' => 'remote',
                        'labelKey' => 'remote.qr_section',
                        'items' => [
                            ['id' => 'widget:remote'],
                        ],
                    ],
                    [
                        'id' => 'present',
                        'labelKey' => 'present.section_present',
                        'items' => [
                            ['id' => 'widget:local_present'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'settings',
                'labelKey' => 'ribbon.tab_view',
                'groups' => [
                    [
                        'id' => 'nav',
                        'labelKey' => 'ribbon.group_nav',
                        'items' => [
                            ['id' => 'back_to_editor'],
                        ],
                    ],
                    [
                        'id' => 'panels',
                        'labelKey' => 'present.panel_settings_title',
                        'items' => [
                            ['id' => 'panel_next'],
                            ['id' => 'panel_clock'],
                            ['id' => 'panel_timer'],
                            ['id' => 'panel_timebar'],
                            ['id' => 'panel_media'],
                            ['id' => 'panel_slides'],
                            ['id' => 'panel_ghost'],
                            ['id' => 'panel_laser'],
                        ],
                    ],
                    [
                        'id' => 'console',
                        'labelKey' => 'editor.settings_menu',
                        'items' => [
                            ['id' => 'settings_timebar'],
                            ['id' => 'settings_clock'],
                            ['id' => 'settings_laser'],
                            ['id' => 'ribbon_customize'],
                        ],
                    ],
                ],
            ],
        ],
    ];
}
