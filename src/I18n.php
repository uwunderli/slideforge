<?php
/**
 * Einfaches, dateibasiertes Mehrsprachigkeits-System (kein Composer/Framework nötig).
 * Sprachdateien liegen unter lang/<code>.php und geben ein flaches assoziatives
 * Array zurück ('key' => 'Übersetzter Text'). Deutsch (de) ist die Basissprache
 * und dient als Fallback, falls in einer anderen Sprache ein Key fehlt.
 */
class I18n
{
    private static ?array $strings = null;
    private static string $lang = 'de';

    public const SUPPORTED = [
        'de' => 'Deutsch',
        'en' => 'English',
        'fr' => 'Français',
        'it' => 'Italiano',
        'rm' => 'Rumantsch',
    ];

    public static function init(string $lang): void
    {
        self::$lang = isset(self::SUPPORTED[$lang]) ? $lang : 'de';
        $base = require LANG_PATH . '/de.php';
        if (self::$lang !== 'de') {
            $override = @include LANG_PATH . '/' . self::$lang . '.php';
            if (is_array($override)) {
                $base = array_merge($base, $override);
            }
        }
        self::$strings = $base;
    }

    public static function t(string $key, array $params = []): string
    {
        if (self::$strings === null) {
            self::init('de');
        }
        $text = self::$strings[$key] ?? $key;
        if ($params) {
            foreach ($params as $k => $v) {
                $text = str_replace('{' . $k . '}', (string)$v, $text);
            }
        }
        return $text;
    }

    public static function currentLang(): string
    {
        return self::$lang;
    }

    /** Flaggen-SVG für die Sprachauswahl in der Topbar (de = Schweizer Kreuz). */
    public static function flagSvg(string $code): string
    {
        return match ($code) {
            'en' => '<svg class="lang-flag-icon" viewBox="0 0 32 24" aria-hidden="true"><rect fill="#012169" width="32" height="24"/><path fill="#fff" d="M0 0l14 10H0V0zm32 0v10H18L32 0zM0 24l14-10H0v10zm32 0H18l14 10v-10z"/><path fill="#c8102e" d="M13 0h6v24h-6zM0 9v6h32V9z"/></svg>',
            'fr' => '<svg class="lang-flag-icon" viewBox="0 0 32 24" aria-hidden="true"><rect fill="#002395" width="10.67" height="24"/><rect fill="#fff" x="10.67" width="10.66" height="24"/><rect fill="#ed2939" x="21.33" width="10.67" height="24"/></svg>',
            'it' => '<svg class="lang-flag-icon" viewBox="0 0 32 24" aria-hidden="true"><rect fill="#009246" width="10.67" height="24"/><rect fill="#fff" x="10.67" width="10.66" height="24"/><rect fill="#ce2b37" x="21.33" width="10.67" height="24"/></svg>',
            'rm' => '<svg class="lang-flag-icon" viewBox="0 0 32 24" aria-hidden="true"><rect fill="#000" width="10.67" height="24"/><rect fill="#fff" x="10.67" width="10.66" height="24"/><rect fill="#0066cc" x="21.33" width="10.67" height="24"/></svg>',
            default => '<svg class="lang-flag-icon" viewBox="0 0 32 32" aria-hidden="true"><rect fill="#d52b1e" width="32" height="32"/><rect fill="#fff" x="13" y="6" width="6" height="20"/><rect fill="#fff" x="6" y="13" width="20" height="6"/></svg>',
        };
    }
}

/** Kurzform für Templates, wie h() für escaping. */
function t(string $key, array $params = []): string
{
    return I18n::t($key, $params);
}
