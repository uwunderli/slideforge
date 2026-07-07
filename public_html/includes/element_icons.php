<?php
/**
 * Inline-SVG-Icons für Vorlageelemente (stroke=currentColor).
 */
function sf_element_icon(string $role): string
{
    $common = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    return match ($role) {
        'document_title' => '<svg ' . $common . '><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
        'subtitle' => '<svg ' . $common . '><path d="M4 6h16"/><path d="M4 10h12"/><path d="M4 14h8"/></svg>',
        'heading1' => '<svg ' . $common . '><path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17 10l3 2-3 2v-4z"/></svg>',
        'heading2' => '<svg ' . $common . '><path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17 12h5"/></svg>',
        'heading3' => '<svg ' . $common . '><path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17 14h4"/></svg>',
        'heading4' => '<svg ' . $common . '><path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17 16h3"/></svg>',
        'heading5' => '<svg ' . $common . '><path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17 18h2"/></svg>',
        'normal' => '<svg ' . $common . '><path d="M4 6h16"/><path d="M4 12h12"/><path d="M4 18h8"/></svg>',
        'list_item' => '<svg ' . $common . '><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><circle cx="4" cy="6" r="1" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1" fill="currentColor" stroke="none"/></svg>',
        'lighttext' => '<svg ' . $common . '><path d="M3 21c3-3 6-8 6-13a6 6 0 1 1 12 0c0 5 3 10 6 13"/><path d="M12 8v4"/></svg>',
        'prompt' => '<svg ' . $common . '><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'scripture_block' => '<svg ' . $common . '><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        'scripture_ref' => '<svg ' . $common . '><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 7h8M8 11h5"/></svg>',
        'scripture_verse' => '<svg ' . $common . '><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 12h8M8 16h8M8 8h6"/></svg>',
        'scripture_inline' => '<svg ' . $common . '><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        'meta' => '<svg ' . $common . '><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
        default => '<svg ' . $common . '><rect x="3" y="3" width="18" height="18" rx="2"/></svg>',
    };
}

function sf_logos_badge(): string
{
    return '<img src="assets/icons/logos-symbol.svg?v=' . (int)ASSET_VERSION . '" alt="" class="element-row-logos-badge" width="16" height="16">';
}
