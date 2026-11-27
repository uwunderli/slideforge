<?php
declare(strict_types=1);

/**
 * Unterpunkte der Einstellungen — **eine Quelle** für die eigene Reiterleiste (`public/includes/settings_nav.php`) und für die
 * Hub-Seitenleiste (3.9.3, Urs 27.11.: «Mache hier noch die Unterpunkte in die Moduleinstellungen» / «Der Hub bietet eine
 * Schnittstelle und dann kommt das von SF»). Der Hub (≥ 2.34.1) nimmt die Liste über `api/module-nav.php` entgegen
 * (signierter Aufruf mit dem App-Schlüssel), Form siehe Hub `docs/DESIGN.md` › Seitenleiste.
 *
 * Gemeldet wird nur bei Änderung: Hash der gemeldeten Fassung in `data/cache/settings-nav-push.json`; Fehlschlag frühestens
 * nach einer Stunde erneut, eine Ablehnung (400) erst bei geänderter Liste. Angestossen vom Hub-Job (hooks/takt.php) und beim
 * Aufruf der Einstellungsseiten — nie bei jedem Seitenaufbau der übrigen App. Der tägliche Hub-Job meldet einmal ohne Hash-Vergleich
 * (`$force`), damit ein neu aufgesetzter oder aktualisierter Hub die Liste spätestens am nächsten Tag hat.
 * `admin` = nur Administratoren sehen den Eintrag; das Recht prüfen weiter die Seiten selbst.
 */
final class SettingsNav
{
    private const RETRY_SEC = 3600;

    /** Kennung => [Seite, Abfrage, Sprachschlüssel, nur Admin, Hub-Icon] */
    private const TABS = [
        'slides' => ['/templates.php', 'tab=slides', 'tpl.tab_slides', false, 'grid'],
        'texts' => ['/templates.php', 'tab=texts', 'tpl.tab_texts', false, 'pencil'],
        'vorlageelemente' => ['/templates.php', 'tab=vorlageelemente', 'tpl.tab_vorlageelemente', false, 'box'],
        'fonts' => ['/templates.php', 'tab=fonts', 'tpl.tab_fonts', false, 'tag'],
        'colors' => ['/templates.php', 'tab=colors', 'tpl.tab_colors', false, 'palette'],
        'general' => ['/admin_settings.php', 'tab=general', 'admin.tab_general', true, 'cog'],
        'spellcheck' => ['/admin_settings.php', 'tab=spellcheck', 'admin.tab_spellcheck', true, 'list'],
        'media' => ['/admin_settings.php', 'tab=media', 'admin.tab_media', true, 'files'],
    ];

    /**
     * Symbole der Reiter (3.12.47) — Abschrift der Pfade aus Hub `src/HubIcon.php` für die Namen oben, damit Reiter und
     * Hub-Seitenleiste dasselbe Symbol zeigen. Strich/Füllung setzt `.hub-list-tab__icon svg` (hub-kit.css, Rückfall style.css).
     */
    private const ICONS = [
        'grid' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.6"/>',
        'pencil' => '<path d="M4 20h4.2l9.1-9.1a2.3 2.3 0 0 0-3.2-3.2L5 16.8z"/><path d="m14.6 6.5 2.9 2.9"/>',
        'box' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3zM4 7.5 12 12l8-4.5M12 12v9"/>',
        'tag' => '<path d="M20.4 13.4 13.4 20.4a1.7 1.7 0 0 1-2.4 0L3.6 13V3.6H13l7.4 7.4a1.7 1.7 0 0 1 0 2.4z"/><circle cx="7.7" cy="7.7" r="1.2"/>',
        'palette' => '<path d="M12 21a9 9 0 1 1 9-9c0 2-1.6 2.6-3 2.6h-1.4c-1.3 0-2.1 1.5-1.3 2.5.7 1-.1 3.9-3.3 3.9z"/><circle cx="8" cy="10" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="15.8" cy="10" r="1"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.9 15a1.6 1.6 0 0 0 .3 1.8 2 2 0 1 1-2.8 2.8 1.6 1.6 0 0 0-2.7 1.1 2 2 0 1 1-4 0 1.6 1.6 0 0 0-2.8-1.1 2 2 0 1 1-2.8-2.8A1.6 1.6 0 0 0 4 12.3a2 2 0 1 1 0-4 1.6 1.6 0 0 0 1.1-2.8 2 2 0 1 1 2.8-2.8A1.6 1.6 0 0 0 10.6 4a2 2 0 1 1 4 0 1.6 1.6 0 0 0 2.8 1.1 2 2 0 1 1 2.8 2.8A1.6 1.6 0 0 0 21.3 11a2 2 0 1 1 0 4z"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'files' => '<path d="M4 6.6A1.6 1.6 0 0 1 5.6 5h3.1l1.8 2.2h7.9A1.6 1.6 0 0 1 20 8.8v9.6A1.6 1.6 0 0 1 18.4 20H5.6A1.6 1.6 0 0 1 4 18.4z"/>',
    ];

    /** @return array<string,array{href:string,label:string,admin:bool,icon:string}> für die Reiterleiste (Sprache der Person) */
    public static function tabs(): array
    {
        $out = [];
        foreach (self::TABS as $key => [$page, $query, $lang, $admin, $icon]) {
            $out[$key] = ['href' => ltrim($page, '/') . '?' . $query, 'label' => t($lang), 'admin' => $admin,
                'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true">' . (self::ICONS[$icon] ?? self::ICONS['box']) . '</svg>'];
        }
        return $out;
    }

    /** Name des Unterpunkts (Seitentitel); unbekannt → «Einstellungen». */
    public static function label(string $key): string
    {
        return isset(self::TABS[$key]) ? t(self::TABS[$key][2]) : t('admin.heading');
    }

    /** @return array{api:int,sections:list<array<string,mixed>>} Meldung an den Hub, immer Deutsch (eine Fassung für alle) */
    public static function manifest(): array
    {
        $de = require LANG_PATH . '/de.php';
        $sections = [];
        foreach (self::TABS as [$page, $query, $lang, $admin, $icon]) {
            $e = ['path' => $page, 'query' => $query, 'label' => (string)($de[$lang] ?? $lang), 'icon' => $icon];
            if ($admin) {
                $e['admin'] = true;
            }
            $sections[] = $e;
        }
        return ['api' => 1, 'sections' => $sections];
    }

    /** @return array{action:string,error?:string} unchanged | backoff | pushed | failed | rejected */
    public static function syncIfChanged(bool $force = false): array
    {
        $manifest = self::manifest();
        $hash = hash('sha256', (string)json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $file = DATA_PATH . '/cache/settings-nav-push.json';
        $st = json_decode((string)@file_get_contents($file), true);
        $st = is_array($st) ? $st : [];
        if (!$force && ($st['hash'] ?? '') === $hash) {
            if (!empty($st['ok']) || (int)($st['status'] ?? 0) === 400) {
                return ['action' => 'unchanged'];
            }
            if (time() - (int)($st['triedAt'] ?? 0) < self::RETRY_SEC) {
                return ['action' => 'backoff'];
            }
        }
        $r = HubApp::hub('/api/module-nav.php', $manifest, 6);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        @file_put_contents($file, (string)json_encode(['hash' => $hash, 'ok' => !empty($r['ok']), 'status' => (int)$r['status'],
            'error' => (string)$r['error'], 'triedAt' => time()]), LOCK_EX);
        if (!empty($r['ok'])) {
            return ['action' => 'pushed'];
        }
        error_log('SlideForge: Einstellungs-Unterpunkte nicht beim Hub gemeldet (' . (string)$r['error'] . ')');
        return ['action' => (int)$r['status'] === 400 ? 'rejected' : 'failed', 'error' => (string)$r['error']];
    }
}
