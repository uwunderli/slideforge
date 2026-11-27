<?php
declare(strict_types=1);

/**
 * Arbeitskopien von FileForge-Dateien (Hub-Editor, 27.11.2026). Die Datei bleibt in FileForge; zum Bearbeiten wird sie in eine
 * Präsentation unter data/presentations/<id> ausgepackt (eine Arbeitskopie je Datei-Verweis, von allen geteilt, die sie öffnen).
 * Speichern packt die Arbeitskopie wieder zur .cfs und legt sie als die Person in FileForge ab (nur wenn FileForge noch die
 * geladene Fassung hat, sonst «Als Kopie speichern»). Rechte kommen nur aus FileForge (`stat`), nie aus SlideForge.
 *
 * Zustand je Arbeitskopie: data/workspaces/<schlüssel>.json; zuletzt geöffnet je Person: data/recent/<sha1>.json.
 * Aufräumen nur über den Hub-Takt (hooks/takt.php → cleanup()).
 */
final class Workspace
{
    public const OWNER = 'ff';
    private const RECENT_MAX = 20;
    private const RECHECK_SECONDS = 300;
    public const IMPORT_EXT = ['pptx', 'ppt', 'odp'];

    private static function dir(): string
    {
        $d = DATA_PATH . '/workspaces';
        if (!is_dir($d)) {
            @mkdir($d, 0770, true);
        }
        return $d;
    }

    private static function key(string $ref, string $share, bool $import = false): string
    {
        return ($import ? 'i' : ($ref !== '' ? 'r' : 's')) . sha1($ref !== '' ? $ref : $share);
    }

    private static function path(string $key): string
    {
        return self::dir() . '/' . $key . '.json';
    }

    /** @return array<string,mixed>|null */
    public static function get(string $key): ?array
    {
        if (preg_match('/^[ris][a-f0-9]{40}\z/', $key) !== 1) {
            return null;
        }
        $w = Storage::read(self::path($key), null);
        return is_array($w) && Presentation::exists((string)($w['id'] ?? '')) ? $w : null;
    }

    /** Arbeitskopie zur Präsentation (null = keine FileForge-Datei). @return array<string,mixed>|null */
    public static function forPresentation(string $id): ?array
    {
        $meta = Presentation::getMeta($id);
        $key = is_array($meta) && is_array($meta['ff'] ?? null) ? (string)($meta['ff']['key'] ?? '') : '';
        return $key !== '' ? self::get($key) : null;
    }

    /** @param array<string,mixed> $w */
    private static function put(array $w): void
    {
        Storage::write(self::path((string)$w['key']), $w);
        $meta = Presentation::getMeta((string)$w['id']);
        if ((string)($meta['ff']['key'] ?? '') !== (string)$w['key']) {
            Presentation::updateMeta((string)$w['id'], ['ff' => ['key' => (string)$w['key']]]);
        }
    }

    private static function title(string $name): string
    {
        $t = trim((string)preg_replace('/\.[A-Za-z0-9]{1,5}\z/', '', $name));
        return $t !== '' ? $t : 'Präsentation';
    }

    /** Fingerabdruck der Arbeitskopie (geändert seit dem letzten Laden/Speichern?). */
    public static function signature(string $id): string
    {
        $dir = Presentation::dir($id);
        $parts = [];
        foreach (array_merge([$dir . '/slides.json'], glob($dir . '/assets/*') ?: []) as $f) {
            clearstatcache(true, $f);
            $parts[] = basename($f) . ':' . (int)@filesize($f) . ':' . (int)@filemtime($f);
        }
        $meta = Storage::read($dir . '/meta.json', []);
        foreach (['ff', 'updated_at', 'owner_id'] as $k) {
            unset($meta[$k]);
        }
        $parts[] = md5((string)json_encode($meta));
        return sha1(implode('|', $parts));
    }

    /** Recht der Person in der Arbeitskopie eintragen (Präsentations-ACL; Quelle ist immer FileForge). */
    private static function grant(string $id, string $person, bool $canWrite): void
    {
        $user = Auth::findById($person);
        Storage::update(Presentation::dir($id) . '/acl.json', static function ($acl) use ($person, $canWrite, $user) {
            $acl = is_array($acl) ? $acl : [];
            $shares = [];
            foreach ((array)($acl['shares'] ?? []) as $s) {
                if (is_array($s) && (string)($s['user_id'] ?? '') !== $person) {
                    $shares[] = $s;
                }
            }
            $shares[] = ['user_id' => $person, 'username' => (string)($user['username'] ?? $person), 'permission' => $canWrite ? 'edit' : 'view'];
            $acl['shares'] = $shares;
            $acl['public'] = ['enabled' => false, 'token' => null, 'permission' => 'view'];
            return $acl;
        }, []);
    }

    private static function tmp(string $suffix): string
    {
        $d = DATA_PATH . '/cache/ff';
        if (!is_dir($d)) {
            @mkdir($d, 0770, true);
        }
        return $d . '/' . bin2hex(random_bytes(10)) . $suffix;
    }

    /**
     * Datei aus FileForge öffnen (Haken `open`, Dashboard, «Öffnen …»).
     *
     * @return array{ok:bool,error:string,id?:string,created?:bool,readOnly?:bool}
     */
    public static function open(string $person, string $ref, string $share): array
    {
        if ($person === '' || ($ref === '') === ($share === '') || ($ref !== '' && FfFile::parseRef($ref) === null) || ($share !== '' && !FfFile::validShare($share))) {
            return ['ok' => false, 'error' => 'bad_request'];
        }
        $st = FfFile::stat($person, $ref, $share);
        if (!$st['ok']) {
            return ['ok' => false, 'error' => $st['error'] !== '' ? $st['error'] : 'unreachable'];
        }
        if (empty($st['canRead'])) {
            return ['ok' => false, 'error' => 'no_access'];
        }
        $name = (string)$st['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $import = in_array($ext, self::IMPORT_EXT, true);
        if (!$import && $ext !== 'cfs') {
            return ['ok' => false, 'error' => 'bad_type'];
        }
        $key = self::key($ref, $share, $import);
        $lock = self::lock($key);
        try {
            $w = self::get($key);
            $created = false;
            if ($import && $w === null) {
                $r = self::importNew($person, $ref, $share, $name, $key);
                if (!$r['ok']) {
                    return $r;
                }
                $w = $r['ws'];
                $created = true;
            } elseif (!$import && ($w === null || ($w['oid'] !== $st['oid'] && self::signature((string)$w['id']) === (string)($w['sig'] ?? '')))) {
                // neu, oder in FileForge geändert und hier nichts Ungespeichertes: (neu) laden
                $id = $w !== null ? (string)$w['id'] : Presentation::create(self::OWNER, self::title($name), DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT);
                $r = self::load($person, $ref, $share, $id);
                if (!$r['ok']) {
                    if ($w === null) {
                        Presentation::delete($id);
                    }
                    return ['ok' => false, 'error' => $r['error']];
                }
                $created = $w === null;
                $w = ['key' => $key, 'id' => $id, 'ref' => $ref !== '' ? $ref : (string)$st['ref'], 'share' => $share, 'name' => $name,
                    'oid' => $r['oid'] !== '' ? $r['oid'] : (string)$st['oid'], 'external' => $r['external'], 'source' => 'cfs',
                    'sig' => self::signature($id), 'savedAt' => time(), 'savedBy' => ''];
            }
            $w['name'] = $import ? (string)$w['name'] : $name;
            $w['openedAt'] = time();
            $w['checked'][$person] = ['at' => time(), 'canWrite' => !empty($st['canWrite'])];
            self::put($w);
            self::grant((string)$w['id'], $person, !empty($st['canWrite']) || $import);
            self::touchRecent($person, $ref !== '' ? $ref : (string)$st['ref'], $share, $name);
            return ['ok' => true, 'error' => '', 'id' => (string)$w['id'], 'created' => $created, 'readOnly' => empty($st['canWrite']) && !$import];
        } finally {
            self::unlock($lock);
        }
    }

    /** .cfs aus FileForge in die Arbeitskopie laden. @return array{ok:bool,error:string,oid:string,external:array<string,string>} */
    private static function load(string $person, string $ref, string $share, string $id): array
    {
        $tmp = self::tmp('.cfs');
        try {
            $rd = FfFile::read($person, $ref, $share, $tmp);
            if (!$rd['ok']) {
                return ['ok' => false, 'error' => $rd['error'], 'oid' => '', 'external' => []];
            }
            $u = CfsFile::unpack($tmp, $id);
            if (!$u['ok']) {
                return ['ok' => false, 'error' => $u['error'], 'oid' => '', 'external' => []];
            }
            // Grosse Medien liegen als eigene Dateien in FileForge: als die Person dazuholen (ohne Recht bleibt das Medium leer)
            foreach ($u['external'] as $n => $eref) {
                $x = FfFile::read($person, $eref, '', Presentation::dir($id) . '/assets/' . $n);
                if (!$x['ok']) {
                    error_log('SlideForge: Medium nicht geladen (' . $x['error'] . ')');
                }
            }
            return ['ok' => true, 'error' => '', 'oid' => $rd['oid'], 'external' => $u['external']];
        } finally {
            @unlink($tmp);
        }
    }

    /** PowerPoint/ODP übernehmen: neue Arbeitskopie, gespeichert wird später als .cfs daneben. @return array{ok:bool,error:string,ws?:array<string,mixed>} */
    private static function importNew(string $person, string $ref, string $share, string $name, string $key): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'ppt') {
            return ['ok' => false, 'error' => 'ppt_unsupported'];
        }
        $tmp = self::tmp('.' . $ext);
        $id = Presentation::create(self::OWNER, self::title($name), DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT);
        try {
            $rd = FfFile::read($person, $ref, $share, $tmp);
            if (!$rd['ok']) {
                Presentation::delete($id);
                return ['ok' => false, 'error' => $rd['error']];
            }
            $res = $ext === 'odp' ? OdpImporter::import($tmp, $id) : PptxImporter::import($tmp, $id);
            Presentation::updateMeta($id, ['width' => (int)$res['width'], 'height' => (int)$res['height']]);
            Storage::write(Presentation::dir($id) . '/slides.json', ['slides' => $res['slides'] ?: [Presentation::defaultSlide()]]);
        } catch (Throwable $e) {
            Presentation::delete($id);
            error_log('SlideForge: Übernahme fehlgeschlagen: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'import_failed'];
        } finally {
            @unlink($tmp);
        }
        return ['ok' => true, 'error' => '', 'ws' => ['key' => $key, 'id' => $id, 'ref' => $ref, 'share' => $share, 'name' => self::title($name) . '.cfs',
            'oid' => '', 'external' => [], 'source' => 'import', 'sig' => '', 'savedAt' => 0, 'savedBy' => '']];
    }

    /**
     * Speichern (Menüband, Ctrl+S, alle 30 s, beim Schliessen). Ohne Änderung nichts tun.
     *
     * @return array{ok:bool,error:string,saved?:bool,oid?:string}
     */
    public static function save(string $id, string $person, bool $force = false): array
    {
        $w = self::forPresentation($id);
        if ($w === null) {
            return ['ok' => false, 'error' => self::local($id, $person) !== null ? 'need_copy' : 'not_ff'];
        }
        $lock = self::lock((string)$w['key']);
        try {
            $w = self::get((string)$w['key']) ?? $w;
            $sig = self::signature($id);
            if (!$force && $w['source'] === 'cfs' && $sig === (string)($w['sig'] ?? '')) {
                return ['ok' => true, 'error' => '', 'saved' => false, 'oid' => (string)$w['oid']];
            }
            if ($w['source'] === 'import') {
                // Übernommene PowerPoint/ODP: als neue .cfs neben dem Original (über eine Freigabe geht das nicht: Kopie wählen)
                $loc = FfFile::parseRef((string)$w['ref']);
                if ($loc === null) {
                    return ['ok' => false, 'error' => 'need_copy'];
                }
                $dirPath = rtrim(dirname($loc['path']), '/') . '/';
                return self::createFrom($w, $person, $loc['repo'], $dirPath, (string)$w['name'], $sig);
            }
            $tmp = self::tmp('.cfs');
            try {
                $p = CfsFile::pack($id, $tmp, self::externalFn($w, $person));
                if (!$p['ok']) {
                    return ['ok' => false, 'error' => $p['error']];
                }
                $r = FfFile::replace($person, (string)($w['share'] !== '' ? '' : $w['ref']), (string)$w['share'], $tmp, (string)$w['oid']);
            } finally {
                @unlink($tmp);
            }
            if (!$r['ok']) {
                return ['ok' => false, 'error' => $r['error']];
            }
            $w['oid'] = $r['oid'];
            $w['sig'] = $sig;
            $w['savedAt'] = time();
            $w['savedBy'] = $person;
            self::put($w);
            return ['ok' => true, 'error' => '', 'saved' => true, 'oid' => $r['oid']];
        } finally {
            self::unlock($lock);
        }
    }

    /**
     * «Als Kopie speichern» (nach 409, ohne Schreibrecht, oder von Hand): neue .cfs im gewählten Ordner; die Arbeitskopie
     * gehört danach zur neuen Datei.
     *
     * @return array{ok:bool,error:string,saved?:bool,oid?:string,ref?:string,name?:string}
     */
    public static function saveCopy(string $id, string $person, string $dirRef, string $name): array
    {
        $w = self::forPresentation($id) ?? self::local($id, $person);
        $loc = FfFile::parseRef($dirRef);
        if ($w === null || $loc === null || !$loc['dir']) {
            return ['ok' => false, 'error' => 'bad_request'];
        }
        $lock = self::lock((string)$w['key']);
        try {
            return self::createFrom($w, $person, $loc['repo'], $loc['path'], $name, self::signature($id));
        } finally {
            self::unlock($lock);
        }
    }

    /** @param array<string,mixed> $w @return array{ok:bool,error:string,saved?:bool,oid?:string,ref?:string,name?:string} */
    private static function createFrom(array $w, string $person, string $repo, string $dir, string $name, string $sig): array
    {
        $name = self::cfsName($name);
        $id = (string)$w['id'];
        $tmp = self::tmp('.cfs');
        try {
            $p = CfsFile::pack($id, $tmp, null);
            if (!$p['ok']) {
                return ['ok' => false, 'error' => $p['error']];
            }
            $c = FfFile::create($person, $repo, $dir, $name, $tmp);
        } finally {
            @unlink($tmp);
        }
        if (!$c['ok']) {
            return ['ok' => false, 'error' => $c['error']];
        }
        @unlink(self::path((string)$w['key']));
        $nw = ['key' => self::key($c['ref'], ''), 'id' => $id, 'ref' => $c['ref'], 'share' => '', 'name' => $c['name'], 'oid' => $c['oid'],
            'external' => [], 'source' => 'cfs', 'sig' => $sig, 'savedAt' => time(), 'savedBy' => $person, 'openedAt' => time(),
            'checked' => [$person => ['at' => time(), 'canWrite' => true]]];
        self::put($nw);
        self::grant($id, $person, true);
        self::touchRecent($person, $c['ref'], '', $c['name']);
        return ['ok' => true, 'error' => '', 'saved' => true, 'oid' => $c['oid'], 'ref' => $c['ref'], 'name' => $c['name']];
    }

    /**
     * Neue, leere Präsentation direkt als .cfs im gewählten Ordner anlegen (oder aus einer Vorlage-.cfs).
     *
     * @return array{ok:bool,error:string,id?:string}
     */
    public static function createNew(string $person, string $dirRef, string $name, string $templateRef = ''): array
    {
        $loc = FfFile::parseRef($dirRef);
        if ($loc === null || !$loc['dir']) {
            return ['ok' => false, 'error' => 'bad_request'];
        }
        $id = Presentation::create(self::OWNER, self::title($name), DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT);
        if ($templateRef !== '') {
            $r = self::load($person, $templateRef, '', $id);
            if (!$r['ok']) {
                Presentation::delete($id);
                return ['ok' => false, 'error' => $r['error']];
            }
        }
        Presentation::updateMeta($id, ['title' => self::title($name)]);
        $tmp = self::tmp('.cfs');
        try {
            $p = CfsFile::pack($id, $tmp, null);
            $c = $p['ok'] ? FfFile::create($person, $loc['repo'], $loc['path'], self::cfsName($name), $tmp) : ['ok' => false, 'error' => $p['error']];
        } finally {
            @unlink($tmp);
        }
        if (empty($c['ok'])) {
            Presentation::delete($id);
            return ['ok' => false, 'error' => (string)$c['error']];
        }
        $w = ['key' => self::key((string)$c['ref'], ''), 'id' => $id, 'ref' => (string)$c['ref'], 'share' => '', 'name' => (string)$c['name'],
            'oid' => (string)$c['oid'], 'external' => [], 'source' => 'cfs', 'sig' => self::signature($id), 'savedAt' => time(),
            'savedBy' => $person, 'openedAt' => time(), 'checked' => [$person => ['at' => time(), 'canWrite' => true]]];
        self::put($w);
        self::grant($id, $person, true);
        self::touchRecent($person, (string)$c['ref'], '', (string)$c['name']);
        return ['ok' => true, 'error' => '', 'id' => $id];
    }

    private static function cfsName(string $name): string
    {
        $name = trim(str_replace(['/', '\\', "\0"], '-', $name));
        $name = (string)preg_replace('/\.(cfs|pptx?|odp)\z/i', '', $name);
        return ($name !== '' ? mb_substr($name, 0, 200) : 'Präsentation') . '.cfs';
    }

    /** Medien über 100 MB als eigene Datei neben der Präsentation ablegen (einmal je Medium). @param array<string,mixed> $w */
    private static function externalFn(array &$w, string $person): ?callable
    {
        $loc = FfFile::parseRef((string)$w['ref']);
        if ($loc === null) {
            return null;   // über eine Freigabe: einbetten
        }
        $dir = rtrim(dirname($loc['path']), '/') . '/';
        $stem = self::title((string)$w['name']);
        return static function (string $n, string $path) use (&$w, $person, $loc, $dir, $stem): ?string {
            if (isset($w['external'][$n])) {
                return (string)$w['external'][$n];
            }
            $c = FfFile::create($person, $loc['repo'], $dir, $stem . ' – ' . $n, $path);
            if (!$c['ok']) {
                return null;
            }
            $w['external'][$n] = $c['ref'];
            return $c['ref'];
        };
    }

    /**
     * Recht der Person neu bei FileForge holen, wenn die letzte Prüfung älter als 5 Minuten ist (Editor, Präsentieren).
     * @return string|null 'edit' | 'view' | null (kein Recht mehr); unverändert, wenn FileForge nicht antwortet
     */
    public static function recheck(string $id, string $person): ?string
    {
        $w = self::forPresentation($id);
        if ($w === null) {
            return Presentation::checkPermission($id, $person);
        }
        $c = is_array($w['checked'][$person] ?? null) ? $w['checked'][$person] : null;
        if ($c !== null && time() - (int)$c['at'] < self::RECHECK_SECONDS) {
            return Presentation::checkPermission($id, $person);
        }
        $st = FfFile::stat($person, (string)($w['share'] !== '' ? '' : $w['ref']), (string)$w['share']);
        if (!$st['ok']) {
            return Presentation::checkPermission($id, $person);
        }
        if (empty($st['canRead'])) {
            Storage::update(Presentation::dir($id) . '/acl.json', static function ($acl) use ($person) {
                $acl = is_array($acl) ? $acl : [];
                $acl['shares'] = array_values(array_filter((array)($acl['shares'] ?? []), static fn($s) => (string)($s['user_id'] ?? '') !== $person));
                return $acl;
            }, []);
            return null;
        }
        $can = !empty($st['canWrite']) || $w['source'] === 'import';
        $w['checked'][$person] = ['at' => time(), 'canWrite' => $can];
        Storage::write(self::path((string)$w['key']), $w);
        self::grant($id, $person, $can);
        return $can ? 'edit' : 'view';
    }

    /**
     * Präsentation, die noch nicht in FileForge liegt (Logos-/Agenda-Import, Übernahme): nur die Besitzerin darf sie mit
     * «Speichern unter …» als .cfs ablegen; danach ist sie eine gewöhnliche FileForge-Datei. @return array<string,mixed>|null
     */
    private static function local(string $id, string $person): ?array
    {
        if (Presentation::checkPermission($id, $person) !== 'owner') {
            return null;
        }
        $meta = Presentation::getMeta($id);
        return ['key' => 'l' . sha1($id), 'id' => $id, 'ref' => '', 'share' => '', 'name' => self::title((string)($meta['title'] ?? '')) . '.cfs',
            'oid' => '', 'external' => [], 'source' => 'local', 'sig' => '', 'savedAt' => 0, 'savedBy' => ''];
    }

    /** Für den Editor. @return array<string,mixed>|null */
    public static function status(string $id, string $person): ?array
    {
        $w = self::forPresentation($id) ?? self::local($id, $person);
        if ($w === null) {
            return null;
        }
        return ['name' => (string)$w['name'], 'source' => (string)$w['source'], 'viaShare' => (string)$w['share'] !== '',
            'dirty' => $w['source'] === 'import' || $w['source'] === 'local' || self::signature($id) !== (string)($w['sig'] ?? ''),
            'canWrite' => !empty($w['checked'][$person]['canWrite']), 'savedAt' => (int)($w['savedAt'] ?? 0)];
    }

    // --- zuletzt geöffnet ---

    private static function recentPath(string $person): string
    {
        $d = DATA_PATH . '/recent';
        if (!is_dir($d)) {
            @mkdir($d, 0770, true);
        }
        return $d . '/' . sha1($person) . '.json';
    }

    public static function touchRecent(string $person, string $ref, string $share, string $name): void
    {
        if ($ref === '' && $share === '') {
            return;
        }
        Storage::update(self::recentPath($person), static function ($list) use ($ref, $share, $name) {
            $out = [['ref' => $ref, 'share' => $share, 'name' => $name, 'at' => time()]];
            foreach (is_array($list) ? $list : [] as $it) {
                if (is_array($it) && ((string)($it['ref'] ?? '') !== $ref || (string)($it['share'] ?? '') !== $share) && count($out) < self::RECENT_MAX) {
                    $out[] = $it;
                }
            }
            return $out;
        }, []);
    }

    /** @return list<array{ref:string,share:string,name:string,at:int}> */
    public static function recent(string $person): array
    {
        $list = Storage::read(self::recentPath($person), []);
        return array_values(array_filter(is_array($list) ? $list : [], 'is_array'));
    }

    public static function forgetRecent(string $person, string $ref, string $share): void
    {
        Storage::update(self::recentPath($person), static fn($list) => array_values(array_filter(is_array($list) ? $list : [],
            static fn($it) => is_array($it) && ((string)($it['ref'] ?? '') !== $ref || (string)($it['share'] ?? '') !== $share))), []);
    }

    // --- Aufräumen (Hub-Takt) ---

    /** Arbeitskopien, die seit `$days` Tagen niemand geöffnet hat, entfernen (Ungespeichertes erst nach der doppelten Zeit). */
    public static function cleanup(int $days = 14): array
    {
        $removed = 0;
        $kept = 0;
        foreach (glob(self::dir() . '/*.json') ?: [] as $f) {
            $w = Storage::read($f, null);
            if (!is_array($w)) {
                continue;
            }
            $id = (string)($w['id'] ?? '');
            $age = time() - (int)($w['openedAt'] ?? 0);
            $dirty = Presentation::exists($id) && ($w['source'] ?? '') === 'cfs' && self::signature($id) !== (string)($w['sig'] ?? '');
            if ($age < $days * 86400 || ($dirty && $age < 2 * $days * 86400)) {
                $kept++;
                continue;
            }
            $lock = self::lock((string)$w['key']);
            try {
                if (Presentation::exists($id)) {
                    Presentation::delete($id);
                }
                @unlink($f);
                $removed++;
            } finally {
                self::unlock($lock);
            }
        }
        foreach (glob(DATA_PATH . '/cache/ff/*') ?: [] as $t) {
            if (is_file($t) && time() - (int)@filemtime($t) > 86400) {
                @unlink($t);
            }
        }
        return ['removed' => $removed, 'kept' => $kept];
    }

    /** @return resource|null */
    private static function lock(string $key)
    {
        $fp = @fopen(self::dir() . '/' . preg_replace('/[^a-z0-9]/', '', $key) . '.lock', 'c');
        if ($fp !== false) {
            flock($fp, LOCK_EX);
            return $fp;
        }
        return null;
    }

    /** @param resource|null $fp */
    private static function unlock($fp): void
    {
        if (is_resource($fp)) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
