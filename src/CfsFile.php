<?php
declare(strict_types=1);

/**
 * Dateiformat `.cfs` (ChurchForge Slides, Fassung 1): ein ZIP mit
 *   meta.json    {cfs:1, app, version, title, width, height, … Präsentations-Einstellungen, external?:{datei: ff-Verweis}}
 *   slides.json  {slides:[…]} — Bilder/Videos als `asset:<datei>` (URL-kodiert wie in der Folie)
 *   assets/…     Medien, unkomprimiert gespeichert (Bilder/Videos sind schon gepackt)
 *   thumb.png    optional
 * Medien über 100 MB liegen nicht in der Datei, sondern als FileForge-Verweis in `meta.external` (Entscheid Urs 27.11.).
 * In der Arbeitskopie (data/presentations/<id>) stehen die Medien wie bisher als `asset.php?id=<id>&file=<datei>`.
 */
final class CfsFile
{
    public const VERSION = 1;
    public const EMBED_MAX = 100 * 1024 * 1024;
    private const MAX_ENTRIES = 5000;
    private const MAX_TOTAL = 2147483648;
    /** Felder der Arbeitskopie, die nicht in die Datei gehören (Besitz, Rechte, Ablage). */
    private const META_DROP = ['id', 'owner_id', 'is_template', 'template_shared', 'template_order', 'archived', 'ff', 'created_at', 'updated_at'];

    /**
     * Arbeitskopie zu einer .cfs packen.
     *
     * @param callable(string $name, string $path): ?string $external Medium über EMBED_MAX: liefert den FileForge-Verweis
     *        (dann nicht eingebettet) oder null (dann doch eingebettet)
     * @return array{ok:bool,error:string}
     */
    public static function pack(string $id, string $dest, ?callable $external = null): array
    {
        $dir = Presentation::dir($id);
        $meta = Storage::read($dir . '/meta.json', null);
        $slidesRaw = is_file($dir . '/slides.json') ? (string)file_get_contents($dir . '/slides.json') : '';
        if (!is_array($meta) || $slidesRaw === '') {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $assets = [];       // Name in der Datei => Pfad auf der Platte
        $rewrite = static function (string $json) use ($id, &$assets): string {
            return (string)preg_replace_callback('/asset\.php\?id=([a-f0-9]+)&(?:amp;)?file=([^"&\\\\\s]+)/u', static function (array $m) use ($id, &$assets): string {
                $plain = rawurldecode(urldecode($m[2]));
                if (basename($plain) !== $plain || $plain === '' || !Presentation::exists($m[1])) {
                    return $m[0];
                }
                $src = Presentation::dir($m[1]) . '/assets/' . $plain;
                if (!is_file($src) || is_link($src)) {
                    return $m[0];
                }
                $name = $m[1] === $id ? $plain : 'x' . $m[1] . '_' . $plain;   // Medium einer anderen Präsentation (Folien-Set): mitnehmen
                $assets[$name] = $src;
                return 'asset:' . rawurlencode($name);
            }, $json);
        };
        $slidesOut = $rewrite($slidesRaw);
        foreach (self::META_DROP as $k) {
            unset($meta[$k]);
        }
        $metaJson = $rewrite((string)json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $meta = json_decode($metaJson, true) ?: [];
        // Medien der Präsentation, die (noch) keine Folie nennt, kommen mit (Medienbibliothek der Präsentation)
        foreach (glob($dir . '/assets/*') ?: [] as $f) {
            $n = basename($f);
            if (is_file($f) && !is_link($f) && !isset($assets[$n])) {
                $assets[$n] = $f;
            }
        }
        $ext = [];
        if ($external !== null) {
            foreach ($assets as $n => $p) {
                if ((int)@filesize($p) > self::EMBED_MAX) {
                    $ref = $external($n, $p);
                    if (is_string($ref) && $ref !== '') {
                        $ext[$n] = $ref;
                        unset($assets[$n]);
                    }
                }
            }
        }
        $meta = ['cfs' => self::VERSION, 'app' => 'slideforge', 'version' => trim((string)@file_get_contents(BASE_PATH . '/VERSION'))] + $meta;
        if ($ext !== []) {
            $meta['external'] = $ext;
        }
        $tmp = $dest . '.part';
        @unlink($tmp);
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'error' => 'not_writable'];
        }
        $zip->addFromString('meta.json', (string)json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('slides.json', $slidesOut);
        foreach ($assets as $n => $p) {
            $zip->addFile($p, 'assets/' . $n);
            $zip->setCompressionName('assets/' . $n, ZipArchive::CM_STORE);
        }
        $thumb = $dir . '/thumb.png';
        if (is_file($thumb) && !is_link($thumb)) {
            $zip->addFile($thumb, 'thumb.png');
            $zip->setCompressionName('thumb.png', ZipArchive::CM_STORE);
        }
        if (!$zip->close() || !@rename($tmp, $dest)) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'not_writable'];
        }
        return ['ok' => true, 'error' => ''];
    }

    /**
     * .cfs in eine (leere oder zu ersetzende) Arbeitskopie auspacken. Behält `id`, `owner_id` und die übrigen Felder der
     * Arbeitskopie; alles andere kommt aus der Datei.
     *
     * @return array{ok:bool,error:string,external:array<string,string>}
     */
    public static function unpack(string $file, string $id): array
    {
        $fail = static fn(string $e): array => ['ok' => false, 'error' => $e, 'external' => []];
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            return $fail('not_cfs');
        }
        try {
            $meta = json_decode((string)$zip->getFromName('meta.json'), true);
            $slides = $zip->getFromName('slides.json');
            if (!is_array($meta) || (int)($meta['cfs'] ?? 0) < 1 || !is_string($slides) || !is_array(json_decode($slides, true))) {
                return $fail('not_cfs');
            }
            if ((int)$meta['cfs'] > self::VERSION) {
                return $fail('cfs_too_new');
            }
            if ($zip->numFiles > self::MAX_ENTRIES) {
                return $fail('too_large');
            }
            $total = 0;
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $n = is_array($st) ? (string)$st['name'] : '';
                $total += is_array($st) ? (int)$st['size'] : 0;
                if (str_starts_with($n, 'assets/')) {
                    $b = substr($n, 7);
                    if ($b !== '' && basename($b) === $b && $b[0] !== '.' && preg_match('/[\x00-\x1f\x7f]/', $b) !== 1) {
                        $entries[$i] = $b;
                    }
                }
            }
            if ($total > self::MAX_TOTAL) {
                return $fail('too_large');
            }
            $dir = Presentation::dir($id);
            $assetDir = $dir . '/assets';
            if (!is_dir($assetDir)) {
                @mkdir($assetDir, 0770, true);
            }
            foreach (glob($assetDir . '/*') ?: [] as $old) {
                if (is_file($old) || is_link($old)) {
                    @unlink($old);
                }
            }
            foreach ($entries as $i => $b) {
                $in = $zip->getStream($zip->getNameIndex($i));
                $out = @fopen($assetDir . '/' . $b, 'wb');
                if ($in === false || $out === false) {
                    return $fail('not_writable');
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
            }
            $thumb = $zip->getFromName('thumb.png');
            if (is_string($thumb) && $thumb !== '') {
                file_put_contents($dir . '/thumb.png', $thumb);
            } else {
                @unlink($dir . '/thumb.png');
            }
        } finally {
            $zip->close();
        }
        $back = static fn(string $json): string => (string)preg_replace('/(?<![A-Za-z0-9_.\/-])asset:([^"&\\\\\s]+)/u', 'asset.php?id=' . $id . '&file=$1', $json);
        $external = [];
        foreach ((array)($meta['external'] ?? []) as $n => $ref) {
            if (is_string($n) && basename($n) === $n && is_string($ref) && str_starts_with($ref, 'ff:')) {
                $external[$n] = $ref;
            }
        }
        foreach (['cfs', 'app', 'version', 'external'] as $k) {
            unset($meta[$k]);
        }
        foreach (self::META_DROP as $k) {
            unset($meta[$k]);
        }
        $meta = json_decode($back((string)json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), true) ?: [];
        file_put_contents($dir . '/slides.json', $back($slides), LOCK_EX);
        Storage::update($dir . '/meta.json', static function ($old) use ($meta) {
            $old = is_array($old) ? $old : [];
            $keep = array_intersect_key($old, array_flip(self::META_DROP));
            return array_merge($meta, $keep, ['updated_at' => date('c')]);
        }, []);
        return ['ok' => true, 'error' => '', 'external' => $external];
    }
}
