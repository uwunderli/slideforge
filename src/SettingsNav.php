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

    /** @return array<string,array{href:string,label:string,admin:bool}> für die Reiterleiste (Sprache der Person) */
    public static function tabs(): array
    {
        $out = [];
        foreach (self::TABS as $key => [$page, $query, $lang, $admin]) {
            $out[$key] = ['href' => ltrim($page, '/') . '?' . $query, 'label' => t($lang), 'admin' => $admin];
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
