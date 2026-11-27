<?php
declare(strict_types=1);

/**
 * Dateien in FileForge als die angemeldete Person (FF-Haken `hooks/app-file.php`, FileForge ≥ 1.18.0, AppCall).
 * Ziel ist ein Verweis `ff:<hub>:<ablage>:<pfad>` oder eine Datei-Freigabe (`share`, 32 hex). Das Recht prüft FileForge.
 */
final class FfFile
{
    public const MAX_BYTES = 2147483648;   // wie FileForge (2 GiB)

    /** @return array{repo:string,path:string,dir:bool}|null */
    public static function parseRef(string $ref): ?array
    {
        $p = explode(':', $ref, 4);
        if (count($p) !== 4 || $p[0] !== 'ff' || $p[1] === '' || $p[2] === '' || $p[3] === '' || $p[3][0] !== '/'
            || strlen($ref) > 1000 || preg_match('/[\x00-\x1f\x7f]/', $ref) === 1) {
            return null;
        }
        return ['repo' => $p[2], 'path' => $p[3], 'dir' => str_ends_with($p[3], '/')];
    }

    public static function validShare(string $share): bool
    {
        return preg_match('/^[a-f0-9]{32}\z/', $share) === 1;
    }

    /** @return string Query-Teil `ref=…` bzw. `share=…` */
    private static function target(string $ref, string $share): string
    {
        return $ref !== '' ? 'ref=' . rawurlencode($ref) : 'share=' . rawurlencode($share);
    }

    /** @return array{ok:bool,error:string,status:int,name?:string,size?:int,oid?:string,canRead?:bool,canWrite?:bool,ref?:string,share?:string} */
    public static function stat(string $person, string $ref, string $share): array
    {
        $r = HubApp::call('fileforge', 'GET', '/hooks/app-file.php?action=stat&' . self::target($ref, $share), null, $person, 15);
        $j = is_array($r['json']) ? $r['json'] : [];
        if (empty($r['ok']) || empty($j['ok'])) {
            return ['ok' => false, 'error' => (string)($j['error'] ?? $r['error']), 'status' => (int)$r['status']];
        }
        return ['ok' => true, 'error' => '', 'status' => 200, 'name' => (string)($j['name'] ?? ''), 'size' => (int)($j['size'] ?? 0),
            'oid' => (string)($j['oid'] ?? ''), 'canRead' => !empty($j['canRead']), 'canWrite' => !empty($j['canWrite']),
            'ref' => (string)($j['ref'] ?? ''), 'share' => (string)($j['share'] ?? '')];
    }

    /** Datei in `$dest` laden. @return array{ok:bool,error:string,oid:string,name:string,size:int} */
    public static function read(string $person, string $ref, string $share, string $dest): array
    {
        $r = HubApp::download('fileforge', '/hooks/app-file.php?action=read&' . self::target($ref, $share), $dest, $person, self::MAX_BYTES);
        if (empty($r['ok'])) {
            return ['ok' => false, 'error' => $r['error'], 'oid' => '', 'name' => '', 'size' => 0];
        }
        return ['ok' => true, 'error' => '', 'oid' => (string)($r['headers']['x-ff-oid'] ?? ''),
            'name' => rawurldecode((string)($r['headers']['x-ff-name'] ?? '')), 'size' => (int)$r['size']];
    }

    /**
     * Datei aus dem FileForge-Dialog (`ref`) als die Person in eine Temp-Datei holen (Präsentation einfügen, Import, Traktanden,
     * Folien-Set, Logo). Die Temp-Datei wird am Ende der Anfrage gelöscht, sofern sie nicht verschoben wurde.
     * @return array{path:string,name:string,size:int} @throws RuntimeException mit lesbarem Text
     */
    public static function fetchTemp(string $person, string $ref, int $maxBytes = self::MAX_BYTES): array
    {
        $p = self::parseRef($ref);
        if ($p === null || $p['dir']) {
            throw new RuntimeException('Keine Datei gewählt.');
        }
        $tmp = (string)tempnam(sys_get_temp_dir(), 'sf-ff-');
        register_shutdown_function(static function () use ($tmp): void {
            if ($tmp !== '' && is_file($tmp)) {
                @unlink($tmp);
            }
        });
        $r = self::read($person, $ref, '', $tmp);
        $mb = (string)round($maxBytes / 1024 / 1024);
        if (!$r['ok']) {
            $msg = [
                'too_large' => 'Datei ist zu gross (max. ' . $mb . ' MB).',
                'no_access' => 'Kein Zugriff auf diese Datei.',
                'no_person' => 'Kein Zugriff auf diese Datei.',
                'busy' => 'FileForge ist gerade beschäftigt. Bitte gleich nochmals versuchen.',
            ];
            throw new RuntimeException($msg[$r['error']] ?? 'Die Datei liess sich aus FileForge nicht holen.');
        }
        $size = (int)@filesize($tmp);
        if ($size > $maxBytes) {
            throw new RuntimeException('Datei ist zu gross (max. ' . $mb . ' MB).');
        }
        $name = $r['name'] !== '' ? $r['name'] : basename($p['path']);
        return ['path' => $tmp, 'name' => $name, 'size' => $size];
    }

    /** Dateifeld für Formulare: versteckter `ref`, Knopf «Aus Dateien wählen …» (FileForge-Dialog, sf-ff.js), Dateiname, Entfernen. */
    public static function fieldHtml(string $name, string $id, array $accept, string $title, string $value = ''): string
    {
        $value = self::parseRef($value) !== null ? $value : '';
        $shown = $value !== '' ? basename((string)self::parseRef($value)['path']) : '';
        return '<div class="sf-ff-field" data-sf-ff-field data-accept="' . h(implode(',', $accept)) . '" data-title="' . h($title) . '">'
            . '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '" data-name="' . h($shown) . '">'
            . '<button type="button" class="hub-btn hub-btn--sm" id="' . h($id) . '" data-sf-ff-pick>' . h(t('ff.pick_file')) . '</button>'
            . '<span class="sf-ff-field-name" data-sf-ff-name data-empty="' . h(t('ff.no_file')) . '" aria-live="polite"></span>'
            . '<button type="button" class="hub-btn hub-btn--ghost hub-btn--sm" data-sf-ff-clear hidden aria-label="' . h(t('ff.clear_file')) . '">' . h(t('ff.clear_file')) . '</button>'
            . '</div>';
    }

    /** Neue Fassung ablegen, nur wenn FileForge noch `$expectOid` hat. 409 → `changed`. @return array{ok:bool,error:string,oid:string} */
    public static function replace(string $person, string $ref, string $share, string $file, string $expectOid): array
    {
        $r = HubApp::stream('fileforge', '/hooks/app-file.php?action=replace&' . self::target($ref, $share) . '&expectOid=' . rawurlencode($expectOid), $file, $person, 1800);
        $j = is_array($r['json']) ? $r['json'] : [];
        if (empty($r['ok']) || empty($j['ok'])) {
            $e = (string)($j['error'] ?? '');
            return ['ok' => false, 'error' => $e !== '' ? $e : ((int)$r['status'] === 409 ? 'changed' : $r['error']), 'oid' => ''];
        }
        return ['ok' => true, 'error' => '', 'oid' => (string)($j['oid'] ?? '')];
    }

    /** Neue Datei anlegen (ersetzt nie; belegt → «Name (1).ext»). @return array{ok:bool,error:string,ref:string,name:string,oid:string} */
    public static function create(string $person, string $repo, string $dir, string $name, string $file, bool $mkdirs = false): array
    {
        // repo 'own' = eigene Ablage der Person; mkdirs legt fehlende Ordner an (FileForge ≥ 1.18.1)
        $q = 'action=create&repo=' . rawurlencode($repo) . '&dir=' . rawurlencode($dir) . '&name=' . rawurlencode($name) . ($mkdirs ? '&mkdirs=1' : '');
        $r = HubApp::stream('fileforge', '/hooks/app-file.php?' . $q, $file, $person, 1800);
        $j = is_array($r['json']) ? $r['json'] : [];
        if (empty($r['ok']) || empty($j['ok'])) {
            return ['ok' => false, 'error' => (string)($j['error'] ?? $r['error']), 'ref' => '', 'name' => '', 'oid' => ''];
        }
        return ['ok' => true, 'error' => '', 'ref' => (string)($j['ref'] ?? ''), 'name' => (string)($j['name'] ?? $name), 'oid' => (string)($j['oid'] ?? '')];
    }

    /** Datei-Freigaben an die Person. @return list<array{share:string,name:string,from:string,perm:string,changed:string}> */
    public static function forMe(string $person, array $exts): array
    {
        $ext = implode(',', array_filter($exts, static fn($x) => preg_match('/^[a-z0-9]{1,10}\z/', (string)$x) === 1));
        $r = HubApp::call('fileforge', 'GET', '/hooks/app-file.php?action=forme' . ($ext !== '' ? '&ext=' . $ext : ''), null, $person, 15);
        $j = is_array($r['json']) ? $r['json'] : [];
        $out = [];
        foreach ((array)($j['items'] ?? []) as $it) {
            if (is_array($it) && self::validShare((string)($it['share'] ?? ''))) {
                $out[] = ['share' => (string)$it['share'], 'name' => (string)($it['name'] ?? ''), 'from' => (string)($it['from'] ?? ''),
                    'perm' => (string)($it['perm'] ?? ''), 'changed' => (string)($it['changed'] ?? '')];
            }
        }
        return $out;
    }

    /**
     * Ordnerinhalt als die Person (FileForge ≥ 1.18.1, Aktion `list`), Dateien nur mit diesen Endungen.
     *
     * @param list<string> $exts
     * @return array{ok:bool,error:string,items:list<array{name:string,ref:string,isDir:bool,mtime:int}>}
     */
    public static function listing(string $person, string $dirRef, array $exts): array
    {
        $ext = implode(',', array_filter($exts, static fn($x) => preg_match('/^[a-z0-9]{1,10}\z/', (string)$x) === 1));
        $r = HubApp::call('fileforge', 'GET', '/hooks/app-file.php?action=list&ref=' . rawurlencode($dirRef) . ($ext !== '' ? '&ext=' . $ext : ''), null, $person, 15);
        $j = is_array($r['json']) ? $r['json'] : [];
        if (empty($r['ok']) || empty($j['ok'])) {
            return ['ok' => false, 'error' => (string)($j['error'] ?? $r['error']), 'items' => []];
        }
        $out = [];
        foreach ((array)($j['items'] ?? []) as $it) {
            if (is_array($it) && self::parseRef((string)($it['ref'] ?? '')) !== null) {
                $out[] = ['name' => (string)($it['name'] ?? ''), 'ref' => (string)$it['ref'], 'isDir' => !empty($it['isDir']), 'mtime' => (int)($it['mtime'] ?? 0)];
            }
        }
        return ['ok' => true, 'error' => '', 'items' => $out];
    }
}
