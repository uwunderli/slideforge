<?php
/**
 * System- und benutzerdefinierte Schriftarten (Upload in public_html/uploads/fonts/).
 * Metadaten in data/config.json unter custom_fonts.
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

    /** Alle wählbaren Schriftfamilien (System + hochgeladen). */
    public static function allFamilies(): array
    {
        $custom = [];
        foreach (self::customFonts() as $f) {
            $family = trim((string)($f['family'] ?? ''));
            if ($family !== '') {
                $custom[] = $family;
            }
        }
        return array_values(array_unique(array_merge(self::SYSTEM_FONTS, $custom)));
    }

    public static function findById(string $id): ?array
    {
        foreach (self::customFonts() as $f) {
            if (($f['id'] ?? '') === $id) {
                return $f;
            }
        }
        return null;
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

    /** @return list<array<string, mixed>> */
    public static function normalizeUploadedFiles(array $filesField): array
    {
        if (($filesField['name'] ?? '') === '' && ($filesField['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        if (!is_array($filesField['name'] ?? null)) {
            return [$filesField];
        }

        $out = [];
        foreach ($filesField['name'] as $i => $name) {
            $out[] = [
                'name' => $name,
                'type' => $filesField['type'][$i] ?? '',
                'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                'error' => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesField['size'][$i] ?? 0,
            ];
        }
        return $out;
    }

    /** @return array{font: array, error?: string} */
    public static function upload(array $file, string $displayName = '', string $family = ''): array
    {
        $result = self::uploadBatch([[
            'file' => $file,
            'displayName' => $displayName,
            'family' => $family,
        ]]);
        if (!empty($result['fonts'])) {
            return ['font' => $result['fonts'][0]];
        }
        return ['font' => [], 'error' => $result['errors'][0]['error'] ?? 'Upload fehlgeschlagen.'];
    }

    /**
     * @param list<array{file: array, displayName?: string, family?: string}> $items
     * @return array{fonts: list<array>, errors: list<array{file: string, error: string}>}
     */
    public static function uploadBatch(array $items): array
    {
        if (!is_dir(FONTS_UPLOAD_PATH)) {
            mkdir(FONTS_UPLOAD_PATH, 0770, true);
        }

        $uploaded = [];
        $errors = [];
        $newEntries = [];
        $reservedFamilies = [];
        foreach (self::customFonts() as $f) {
            $family = trim((string)($f['family'] ?? ''));
            if ($family !== '') {
                $reservedFamilies[$family] = true;
            }
        }

        foreach ($items as $item) {
            $file = $item['file'] ?? [];
            $fileLabel = (string)($file['name'] ?? 'Datei');
            $entry = self::prepareUploadEntry(
                $file,
                (string)($item['displayName'] ?? ''),
                (string)($item['family'] ?? ''),
                $reservedFamilies
            );
            if (!empty($entry['error'])) {
                $errors[] = ['file' => $fileLabel, 'error' => $entry['error']];
                continue;
            }

            $newEntries[] = $entry['font'];
            $uploaded[] = $entry['font'];
            $reservedFamilies[$entry['font']['family']] = true;
        }

        if ($newEntries) {
            Config::update(['custom_fonts' => array_merge(self::customFonts(), $newEntries)]);
        }

        return ['fonts' => $uploaded, 'errors' => $errors];
    }

    /** @param array<string, true> $reservedFamilies */
    private static function prepareUploadEntry(array $file, string $displayName, string $family, array $reservedFamilies): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['error' => 'Upload fehlgeschlagen.'];
        }
        if (($file['size'] ?? 0) > MAX_FONT_SIZE) {
            return ['error' => 'Datei ist zu gross (max. ' . round(MAX_FONT_SIZE / 1024 / 1024) . ' MB).'];
        }

        $baseName = pathinfo($file['name'] ?? '', PATHINFO_FILENAME);
        $displayName = trim($displayName) !== '' ? trim($displayName) : $baseName;
        $family = self::sanitizeFamily($family !== '' ? $family : $displayName);
        if ($family === '') {
            return ['error' => 'Ungültiger Schriftname.'];
        }
        if (in_array($family, self::SYSTEM_FONTS, true)) {
            return ['error' => 'Name ist für eine Standard-Schrift reserviert.'];
        }
        if (isset($reservedFamilies[$family])) {
            return ['error' => 'Eine Schrift mit diesem Namen existiert bereits.'];
        }

        $ext = self::detectExtension($file);
        if ($ext === null) {
            return ['error' => 'Nicht unterstütztes Format. Erlaubt: WOFF2, WOFF, TTF, OTF.'];
        }

        $filename = Storage::generateId(12) . '.' . $ext;
        $destination = FONTS_UPLOAD_PATH . '/' . $filename;
        if (!@move_uploaded_file($file['tmp_name'], $destination)) {
            return ['error' => 'Datei konnte nicht gespeichert werden.'];
        }

        return ['font' => [
            'id' => Storage::generateId(12),
            'name' => mb_substr($displayName, 0, 80),
            'family' => $family,
            'file' => $filename,
            'format' => $ext,
            'uploaded_at' => time(),
        ]];
    }

    public static function delete(string $id): bool
    {
        $fonts = self::customFonts();
        $found = null;
        foreach ($fonts as $i => $f) {
            if (($f['id'] ?? '') === $id) {
                $found = $i;
                break;
            }
        }
        if ($found === null) {
            return false;
        }

        $file = $fonts[$found]['file'] ?? '';
        if ($file !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            $path = FONTS_UPLOAD_PATH . '/' . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        array_splice($fonts, $found, 1);
        Config::update(['custom_fonts' => $fonts]);
        return true;
    }

    /** Google-Fonts-Link für Standard-Schriftarten. */
    public static function googleFontsLinkTag(): string
    {
        return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
            . '<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700;800&family=PT+Sans:wght@400;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">';
    }

    /** CSS mit @font-face für hochgeladene Schriften. */
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
        }
        return $css;
    }

    /** Markup für Seiten-Kopf: Google Fonts + eigene Schriften. */
    public static function headMarkup(string $customCssHref = 'fonts.css.php', ?array $slidesData = null, bool $embedForExport = false): string
    {
        $html = self::googleFontsLinkTag() . "\n";
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

    private static function detectExtension(array $file): ?string
    {
        $allowed = [
            'font/woff2' => 'woff2',
            'font/woff' => 'woff',
            'font/ttf' => 'ttf',
            'font/otf' => 'otf',
            'application/font-woff' => 'woff',
            'application/font-woff2' => 'woff2',
            'application/x-font-woff' => 'woff',
            'application/x-font-ttf' => 'ttf',
            'application/x-font-truetype' => 'ttf',
            'application/x-font-otf' => 'otf',
            'application/vnd.ms-opentype' => 'otf',
            'application/octet-stream' => null,
        ];

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? @finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mime && isset($allowed[$mime]) && $allowed[$mime] !== null) {
            return $allowed[$mime];
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $byExt = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'ttf', 'otf' => 'otf'];
        return $byExt[$ext] ?? null;
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
