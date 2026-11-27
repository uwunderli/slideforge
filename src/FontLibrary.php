<?php
/**
 * Schriftarten der FolienSchmiede.
 *
 * Seit 3.9.0 kommen die Schriften **aus dem Hub** (Einstellungen › Hub › Palette › Schriften; Hub `api/fonts.php`, Urs 27.11.2026: «Man
 * könnte ja einen Ordner mit dem FF Picker angeben, wo die Schriftarten liegen. Die werden dann für die verschiedenen Programme verwendet»).
 * Die eigene Upload-Verwaltung ist weg. **Übergang:** früher hochgeladene Schriften (`custom_fonts` in data/config.json, Dateien in
 * public/uploads/fonts/) werden weiter unter ihrem bisherigen Namen ausgeliefert, damit bestehende Folien nicht brechen; zusätzlich unter
 * dem Familiennamen ohne Schnitt-Wort («Myriad Pro Regular» → «Myriad Pro»), solange der Hub diese Familie nicht selbst hat.
 * Übernahme der Dateien in den Hub-Ordner: scripts/schriften-in-hub.php.
 */
class FontLibrary
{
    /** Im Editor / Vorlagen wählbare Standard-Schriftarten (Google Fonts + Websafe). */
    public const SYSTEM_FONTS = [
        'Open Sans',
        'PT Sans',
        'Georgia',
        'Arial',
        'Times New Roman',
        'Courier New',
        'Verdana',
    ];

    private const FORMAT_CSS = [
        'woff2' => 'woff2',
        'woff' => 'woff',
        'ttf' => 'truetype',
        'otf' => 'opentype',
    ];

    public static function customFonts(): array
    {
        return Config::get('custom_fonts', []);
    }

    /** Alle wählbaren Schriftfamilien: zuoberst die des Hubs, dann die Standard-Schriften, dann alte eigene (Übergang). */
    public static function allFamilies(): array
    {
        $hub = self::hubFamilyNames();
        $legacy = [];
        foreach (self::customFonts() as $f) {
            $family = trim((string)($f['family'] ?? ''));
            if ($family !== '' && !self::inList($family, $hub)) {
                $legacy[] = $family;
            }
        }
        $out = [];
        foreach (array_merge($hub, self::SYSTEM_FONTS, $legacy) as $f) {
            if (!self::inList($f, $out)) {
                $out[] = $f;
            }
        }
        return $out;
    }

    private static function inList(string $family, array $list): bool
    {
        $k = mb_strtolower(trim($family));
        foreach ($list as $x) {
            if (mb_strtolower(trim((string)$x)) === $k) {
                return true;
            }
        }
        return false;
    }

    /** Adresse der @font-face-Datei des Hubs (öffentlich, CORS offen). */
    public static function hubCssUrl(): string
    {
        $base = function_exists('churchforge_hub_url') ? rtrim((string)churchforge_hub_url(), '/') : '';
        return $base . '/api/fonts.php?css=1';
    }

    /** Familiennamen der Hub-Schriften. @return list<string> */
    public static function hubFamilyNames(): array
    {
        return array_values(array_map(static fn (array $f): string => (string)$f['family'], self::hubFamilies()));
    }

    /**
     * Familien und Schnitte aus dem Hub (`api/fonts.php?liste=1`, signierter App-Aufruf), fünf Minuten zwischengespeichert in
     * data/cache/hub-fonts.json; antwortet der Hub nicht, gilt der letzte Stand.
     *
     * @return list<array{family:string,faces:list<array<string,mixed>>}>
     */
    public static function hubFamilies(): array
    {
        static $mem = null;
        if ($mem !== null) {
            return $mem;
        }
        $file = DATA_PATH . '/cache/hub-fonts.json';
        $d = is_readable($file) ? json_decode((string)@file_get_contents($file), true) : null;
        $d = is_array($d) ? $d : [];
        if ((time() - (int)($d['at'] ?? 0)) > 300) {
            $r = class_exists('HubApp') ? HubApp::hubGet('/api/fonts.php?liste=1', 6) : ['ok' => false];
            if (!empty($r['ok']) && is_array($r['json']['families'] ?? null)) {
                $d = ['at' => time(), 'families' => $r['json']['families']];
            } else {
                $d['at'] = time() - 240;   // in einer Minute wieder fragen
            }
            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0770, true);
            }
            @file_put_contents($file, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
        $out = [];
        foreach (is_array($d['families'] ?? null) ? $d['families'] : [] as $f) {
            $name = is_array($f) ? self::sanitizeFamily((string)($f['family'] ?? '')) : '';
            if ($name !== '') {
                $out[] = ['family' => $name, 'faces' => is_array($f['faces'] ?? null) ? $f['faces'] : []];
            }
        }
        return $mem = $out;
    }

    /** «Myriad Pro Regular» → ['Myriad Pro', 400, 'normal']; ohne Schnitt-Wort null. @return array{0:string,1:int,2:string}|null */
    public static function legacyAlias(string $family): ?array
    {
        $w = ['thin' => 100, 'extralight' => 200, 'ultralight' => 200, 'light' => 300, 'regular' => 400, 'normal' => 400, 'book' => 400, 'roman' => 400,
            'medium' => 500, 'semibold' => 600, 'demibold' => 600, 'bold' => 700, 'extrabold' => 800, 'ultrabold' => 800, 'black' => 900, 'heavy' => 900];
        $teile = preg_split('/\s+/', trim($family)) ?: [];
        $weight = 400;
        $style = 'normal';
        $hit = false;
        while (count($teile) > 1) {
            $last = mb_strtolower((string)end($teile));
            if ($last === 'italic' || $last === 'oblique') {
                $style = 'italic';
            } elseif (isset($w[$last])) {
                $weight = $w[$last];
            } else {
                break;
            }
            array_pop($teile);
            $hit = true;
        }
        return $hit ? [implode(' ', $teile), $weight, $style] : null;
    }

    public static function findByFamily(string $family): ?array
    {
        foreach (self::customFonts() as $f) {
            if (($f['family'] ?? '') === $family) {
                return $f;
            }
        }
        return null;
    }

    public static function sanitizeFamily(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $name));
        $name = str_replace(['"', "'", ';', '\\'], '', $name);
        return mb_substr($name, 0, 80);
    }

    /** Google-Fonts-Link für Standard-Schriftarten. */
    public static function googleFontsLinkTag(): string
    {
        return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
            . '<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700;800&family=PT+Sans:wght@400;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">';
    }

    /** CSS mit @font-face für früher hochgeladene Schriften (Übergang bis sie im Hub liegen). */
    public static function cssBlock(string $urlPrefix = 'uploads/fonts/', ?array $onlyFamilies = null, bool $embedBase64 = false): string
    {
        $css = '';
        foreach (self::customFonts() as $font) {
            $family = $font['family'] ?? '';
            if ($family === '') {
                continue;
            }
            if ($onlyFamilies !== null && !in_array($family, $onlyFamilies, true)) {
                continue;
            }
            $file = $font['file'] ?? '';
            $format = $font['format'] ?? '';
            if ($file === '' || !isset(self::FORMAT_CSS[$format])) {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
                continue;
            }

            $path = FONTS_UPLOAD_PATH . '/' . $file;
            if (!is_file($path)) {
                continue;
            }

            if ($embedBase64) {
                $mime = self::mimeForFormat($format);
                $b64 = base64_encode((string)file_get_contents($path));
                $src = "url('data:{$mime};base64,{$b64}') format('" . self::FORMAT_CSS[$format] . "')";
            } else {
                $src = "url('" . $urlPrefix . rawurlencode($file) . "') format('" . self::FORMAT_CSS[$format] . "')";
            }

            $css .= "@font-face{font-family:'" . str_replace("'", "\\'", $family) . "';"
                . "src:{$src};font-weight:400;font-style:normal;font-display:swap;}\n";
            // Übergang (3.9.0): auch unter der Familie ohne Schnitt-Wort, solange der Hub sie nicht hat.
            $alias = self::legacyAlias($family);
            if ($alias !== null && !self::inList($alias[0], self::hubFamilyNames()) && !self::inList($alias[0], array_column(self::customFonts(), 'family'))) {
                $css .= "@font-face{font-family:'" . str_replace("'", "\\'", $alias[0]) . "';"
                    . "src:{$src};font-weight:{$alias[1]};font-style:{$alias[2]};font-display:swap;}\n";
            }
        }
        return $css;
    }

    /** Markup für Seiten-Kopf: Google Fonts, Schriften des Hubs, alte eigene Schriften (Übergang). */
    public static function headMarkup(string $customCssHref = 'fonts.css.php', ?array $slidesData = null, bool $embedForExport = false): string
    {
        $html = self::googleFontsLinkTag() . "\n";
        // Schriften des Hubs (3.9.0) in jeder Ansicht; im Export als Verweis (online).
        $html .= '<link rel="stylesheet" href="' . h(self::hubCssUrl()) . '" crossorigin="anonymous">' . "\n";
        if ($embedForExport) {
            $families = $slidesData ? self::collectFamiliesFromSlides($slidesData) : null;
            $css = self::cssBlock('', $families, true);
            if ($css !== '') {
                $html .= "<style>\n" . $css . "</style>\n";
            }
        } elseif ($customCssHref !== '') {
            $html .= '<link rel="stylesheet" href="' . h($customCssHref) . '?v=' . ASSET_VERSION . '">' . "\n";
        }
        return $html;
    }

    /** Schriftfamilien, die in Folien-Objekten vorkommen. */
    public static function collectFamiliesFromSlides(array $slidesData): array
    {
        $families = [];
        $walk = function ($obj) use (&$walk, &$families) {
            if (!is_array($obj)) {
                return;
            }
            if (($obj['type'] ?? '') === 'group' && !empty($obj['children'])) {
                foreach ($obj['children'] as $child) {
                    $walk($child);
                }
                return;
            }
            if (($obj['type'] ?? '') === 'text' && !empty($obj['fontFamily'])) {
                $families[] = $obj['fontFamily'];
            }
        };
        foreach ($slidesData['slides'] ?? [] as $slide) {
            foreach ($slide['objects'] ?? [] as $obj) {
                $walk($obj);
            }
        }
        return array_values(array_unique($families));
    }

    /** Hochgeladene Schriften, die in der Präsentation verwendet werden. */
    public static function customFontsForSlides(array $slidesData): array
    {
        $fonts = [];
        foreach (self::collectFamiliesFromSlides($slidesData) as $family) {
            $font = self::findByFamily($family);
            if ($font) {
                $fonts[] = $font;
            }
        }
        return $fonts;
    }

    public static function filePath(array $font): ?string
    {
        $file = $font['file'] ?? '';
        if ($file === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            return null;
        }
        $path = FONTS_UPLOAD_PATH . '/' . $file;
        return is_file($path) ? $path : null;
    }

    public static function canEmbedInPptx(string $format): bool
    {
        return in_array($format, ['ttf', 'otf'], true);
    }

    public static function canEmbedInOdp(string $format): bool
    {
        return isset(self::FORMAT_CSS[$format]);
    }

    public static function generateGuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return '{' . strtoupper(vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4)
        )) . '}';
    }

    /** Obfuscation für PPTX (ODTTF, erste 32 Bytes). */
    public static function obfuscateOdttf(string $fontData, string $guid): string
    {
        $key = self::guidToXorKey($guid);
        $out = $fontData;
        for ($i = 0; $i < 32 && $i < strlen($out); $i++) {
            $out[$i] = chr(ord($out[$i]) ^ ord($key[$i % 16]));
        }
        return $out;
    }

    /** ODF font-face Deklaration für styles.xml. */
    public static function odfFontFaceXml(array $font, string $href): string
    {
        $family = h($font['family'] ?? '');
        $format = match ($font['format'] ?? 'ttf') {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'otf' => 'opentype',
            default => 'truetype',
        };
        return '<style:font-face style:name="' . $family . '" svg:font-family="' . $family . '">'
            . '<svg:font-face-src><svg:font-face-uri xlink:href="' . h($href) . '" xlink:type="simple" xlink:actuate="onLoad">'
            . '<svg:font-face-format svg:string="' . $format . '"/>'
            . '</svg:font-face-uri></svg:font-face-src></style:font-face>';
    }

    public static function odfFontMime(string $format): string
    {
        return match ($format) {
            'woff2' => 'application/font-woff2',
            'woff' => 'application/font-woff',
            'otf' => 'application/vnd.oasis.opendocument.opentype',
            default => 'application/x-font-ttf',
        };
    }

    private static function guidToXorKey(string $guid): string
    {
        $hex = strtoupper(str_replace(['{', '}', '-'], '', $guid));
        $bytes = array_values(unpack('C*', pack('H*', $hex)));
        $guidMem = array_merge(
            array_reverse(array_slice($bytes, 0, 4)),
            array_reverse(array_slice($bytes, 4, 2)),
            array_reverse(array_slice($bytes, 6, 2)),
            array_slice($bytes, 8, 8)
        );
        return pack('C*', ...array_reverse($guidMem));
    }

    private static function mimeForFormat(string $format): string
    {
        return match ($format) {
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'otf' => 'font/otf',
            default => 'font/ttf',
        };
    }
}
