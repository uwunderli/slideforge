<?php
declare(strict_types=1);

/**
 * Einstellungen aus der alten FolienSchmiede nachholen (3.8.3, Urs 27.11.: «Bei den Einstellungen müsste ich nun aber auch die
 * Einstellungen machen können, die ich zuvor gemacht habe»). Aufruf: `php scripts/migrate-to-ff.php --source=<alt> --settings [--run]`.
 *
 * Füllt nur, was in der neuen Installation noch leer oder unverändert ab Werk ist; was jemand hier schon gesetzt hat, bleibt.
 * Idempotent: ein zweiter Lauf findet nichts mehr. Vor dem Schreiben eine Sicherung `<datei>.vor-settings-<zeit>` neben der Datei.
 * Ausgabe nennt nur Schlüssel, nie Werte (API-Schlüssel). SMTP und Benutzer/Einladungen nicht (Hub-Sache, 3.8.3 entfernt);
 * Schriften holt Migration::fonts (3.8.2). Markenfarben nicht: sie kommen nur noch aus der Hub-Palette (3.9.2).
 */
final class SettingsMigration
{
    /** @return array{ok:bool,error?:string,taken:list<string>,kept:list<string>} */
    public static function run(string $source, bool $dryRun, callable $echo): array
    {
        $source = rtrim($source, '/');
        $old = self::json($source . '/config.json');
        if (!is_array($old)) {
            return ['ok' => false, 'error' => 'source_unreadable', 'taken' => [], 'kept' => []];
        }
        $new = Storage::read(SITE_CONFIG_FILE, []);
        $new = is_array($new) ? $new : [];
        $set = [];
        $taken = [];
        $kept = [];
        $note = static function (string $key, bool $take) use (&$taken, &$kept): void {
            if ($take) {
                $taken[] = $key;
            } else {
                $kept[] = $key;
            }
        };

        // Seitentitel: neu leer oder Werkseinstellung
        $oldTitle = trim((string)($old['site_title'] ?? ''));
        if ($oldTitle !== '' && $oldTitle !== APP_NAME) {
            $newTitle = trim((string)($new['site_title'] ?? ''));
            $take = $newTitle === '' || $newTitle === APP_NAME;
            if ($take) {
                $set['site_title'] = $oldTitle;
            }
            $note('site_title', $take);
        }

        // Logo: Datei aus dem alten uploads/ kopieren (nie ersetzen)
        $oldLogo = (string)($old['logo'] ?? '');
        if ($oldLogo !== '' && preg_match('/^[a-zA-Z0-9._-]+\z/', $oldLogo) === 1) {
            $base = dirname($source);
            $from = is_file($base . '/public_html/uploads/' . $oldLogo) ? $base . '/public_html/uploads/' . $oldLogo : $base . '/public/uploads/' . $oldLogo;
            $take = (string)($new['logo'] ?? '') === '' && is_file($from);
            if ($take && !$dryRun) {
                $to = PUBLIC_UPLOADS_PATH . '/' . $oldLogo;
                if (!is_file($to) && !@copy($from, $to)) {
                    $echo('Logo nicht kopiert.');
                    $take = false;
                } else {
                    @chmod($to, 0644);
                }
            }
            if ($take) {
                $set['logo'] = $oldLogo;
            }
            $note('logo', $take);
        }

        // LanguageTool: fehlt der Abschnitt → ganz; sonst leere Textfelder einzeln
        if (is_array($old['languagetool'] ?? null)) {
            if (!is_array($new['languagetool'] ?? null)) {
                $set['languagetool'] = $old['languagetool'];
                $note('languagetool', true);
            } else {
                $lt = $new['languagetool'];
                foreach (['api_url', 'api_username', 'api_key'] as $k) {
                    if (trim((string)($old['languagetool'][$k] ?? '')) === '') {
                        continue;
                    }
                    $take = trim((string)($lt[$k] ?? '')) === '';
                    if ($take) {
                        $lt[$k] = $old['languagetool'][$k];
                        $set['languagetool'] = $lt;
                    }
                    $note('languagetool.' . $k, $take);
                }
            }
        }

        // Pixabay: ohne neuen Schlüssel den alten Abschnitt (Schalter + Schlüssel)
        if (is_array($old['pixabay'] ?? null) && trim((string)($old['pixabay']['api_key'] ?? '')) !== '') {
            $take = trim((string)($new['pixabay']['api_key'] ?? '')) === '';
            if ($take) {
                $set['pixabay'] = ['enabled' => !empty($old['pixabay']['enabled']), 'api_key' => (string)$old['pixabay']['api_key']];
            }
            $note('pixabay.api_key', $take);
        }

        // Iconify, Openclipart: nur der Schalter, wenn neu noch nie gesetzt
        foreach (['iconify', 'openclipart'] as $sec) {
            if (is_array($old[$sec] ?? null)) {
                $take = !is_array($new[$sec] ?? null);
                if ($take) {
                    $set[$sec] = ['enabled' => !empty($old[$sec]['enabled'])];
                }
                $note($sec, $take);
            }
        }

        if ($set !== [] && !$dryRun) {
            self::backup(SITE_CONFIG_FILE);
            Config::update($set);
        }

        // Textvorlagen: neu ab Werk → alte Liste
        $oldTt = self::json($source . '/text_templates.json');
        if (is_array($oldTt) && $oldTt !== [] && self::strip($oldTt) !== self::strip(TextTemplate::seedDefaults())) {
            $take = self::strip(Storage::read(TEXT_TEMPLATES_FILE, [])) === self::strip(TextTemplate::seedDefaults());
            if ($take && !$dryRun) {
                self::backup(TEXT_TEMPLATES_FILE);
                Storage::write(TEXT_TEMPLATES_FILE, array_values($oldTt));
                TextTemplate::ensureFallback();
            }
            $note('text_templates', $take);
        }

        // Vorlageelemente und Logos-Importvorlagen: Datei fehlt neu → kopieren
        foreach (['element_text_links.json', 'sermon_import_templates.json'] as $file) {
            $data = self::json($source . '/' . $file);
            if (!is_array($data) || $data === []) {
                continue;
            }
            $take = !is_file(DATA_PATH . '/' . $file) || Storage::read(DATA_PATH . '/' . $file, []) === [];
            if ($take && !$dryRun) {
                Storage::write(DATA_PATH . '/' . $file, $data);
            }
            $note(basename($file, '.json'), $take);
        }

        $echo(($dryRun ? 'Probelauf, würde übernehmen: ' : 'Übernommen: ') . ($taken !== [] ? implode(', ', $taken) : 'nichts'));
        $echo('Schon gesetzt, bleibt: ' . ($kept !== [] ? implode(', ', $kept) : 'nichts'));
        return ['ok' => true, 'taken' => $taken, 'kept' => $kept];
    }

    /** Textvorlagen ohne Fallback-Kennzeichen vergleichen (ensureFallback setzt es nachträglich). */
    private static function strip(mixed $list): array
    {
        if (!is_array($list)) {
            return [];
        }
        return array_map(static function ($t) {
            if (is_array($t)) {
                unset($t['isFallback']);
                ksort($t);
            }
            return $t;
        }, array_values($list));
    }

    private static function backup(string $file): void
    {
        if (is_file($file)) {
            @copy($file, $file . '.vor-settings-' . date('Ymd-His'));
        }
    }

    private static function json(string $file): mixed
    {
        return is_file($file) && !is_link($file) ? json_decode((string)file_get_contents($file), true) : null;
    }
}
