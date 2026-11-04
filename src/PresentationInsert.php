<?php

/**
 * Folien aus einer Datei in eine bestehende Präsentation einfügen:
 * PowerPoint (.pptx), OpenDocument (.odp), Folien-Set (.chs) und SlideForge-Export (.zip).
 * Bilder und Medien kommen in den Ordner der Zielpräsentation.
 */
class PresentationInsert
{
    public const EXTENSIONS = ['pptx', 'odp', 'chs', 'zip'];

    /** @return array{slides:list<array>,warnings:list<string>} */
    public static function slidesFromFile(string $targetId, string $path, string $origName): array
    {
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS, true)) {
            throw new RuntimeException(t('insertpres.unsupported'));
        }
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);
        if ($ext === 'pptx' || $ext === 'odp') {
            try {
                $res = $ext === 'pptx' ? PptxImporter::import($path, $targetId) : OdpImporter::import($path, $targetId);
            } catch (Throwable $e) {
                throw new RuntimeException(t($ext === 'pptx' ? 'import.pptx_failed' : 'import.odp_failed', ['error' => $e->getMessage()]));
            }
            return ['slides' => $res['slides'] ?? [], 'warnings' => array_values($res['warnings'] ?? [])];
        }
        return self::fromArchive($targetId, $path, $ext);
    }

    /** .chs und SlideForge-.zip: Folien lesen, Bilder in die Zielpräsentation kopieren. */
    private static function fromArchive(string $targetId, string $path, string $ext): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException(t('import.zip_open_failed'));
        }
        $tmp = sys_get_temp_dir() . '/sfinsert_' . bin2hex(random_bytes(6));
        mkdir($tmp, 0770, true);
        try {
            $zip->extractTo($tmp);
            $zip->close();
            $assetsDst = Presentation::dir($targetId) . '/assets';
            if (!is_dir($assetsDst)) {
                mkdir($assetsDst, 0770, true);
            }
            if (is_file($tmp . '/slides.json')) {
                $data = json_decode((string)file_get_contents($tmp . '/slides.json'), true);
                if (!is_array($data)) {
                    throw new RuntimeException(t('tpl.layout_set_archive_invalid'));
                }
                $json = (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $json = preg_replace('/asset\.php\?id=[^&]+&/u', 'asset.php?id=' . urlencode($targetId) . '&', $json) ?? $json;
                $data = json_decode($json, true) ?? ['slides' => []];
                foreach (glob($tmp . '/assets/*') ?: [] as $file) {
                    if (is_file($file)) {
                        copy($file, $assetsDst . '/' . basename($file));
                    }
                }
                $slides = array_values(array_filter($data['slides'] ?? [], 'is_array'));
                return ['slides' => $slides, 'warnings' => []];
            }
            if ($ext === 'zip' && is_file($tmp . '/index.html')) {
                $html = (string)file_get_contents($tmp . '/index.html');
                if (!preg_match('/<script type="application\/json" id="slideforge-source-data">(.*?)<\/script>/s', $html, $m)) {
                    throw new RuntimeException(t('import.no_source_data'));
                }
                $data = json_decode($m[1], true);
                if (!is_array($data) || !isset($data['slides'])) {
                    throw new RuntimeException(t('import.corrupt_data'));
                }
                $slidesData = Exporter::importAssets(['slides' => $data['slides']], $targetId, is_dir($tmp . '/assets') ? $tmp . '/assets' : null);
                return ['slides' => array_values($slidesData['slides'] ?? []), 'warnings' => []];
            }
            throw new RuntimeException(t('tpl.layout_set_archive_invalid'));
        } finally {
            ImportJob::cleanupDir($tmp);
        }
    }
}
