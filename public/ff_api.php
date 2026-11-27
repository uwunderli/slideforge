<?php
declare(strict_types=1);

/**
 * Dateien in FileForge für die Oberfläche (Startseite und Editor). POST JSON mit csrf_token; GET nur zum Lesen.
 *
 *   GET  ?action=home                                  → {recent:[…], forme:[…], templateDir:{ref,label}|null}
 *   GET  ?action=templates                             → {items:[{name, ref}]}  .cfs im Vorlagen-Ordner der Person
 *   POST {action:open, ref|share}                      → {ok, id}
 *   POST {action:create, dir, name, template?}         → {ok, id}           neue .cfs im Ordner (dir = ff-Verweis eines Ordners)
 *   POST {action:save, id}                             → {ok, saved, error?} error: changed | read_only | need_copy | …
 *   POST {action:save_copy, id, dir, name}             → {ok, name}
 *   POST {action:save_template, id, name}              → {ok, name}         Kopie in den Vorlagen-Ordner der Person
 *   POST {action:status, id}                           → {ok, status}
 *   POST {action:template_dir, ref, label}             → {ok}               Vorlagen-Ordner der Person ('' = keiner)
 *   POST {action:forget, ref|share}                    → {ok}               aus «Zuletzt geöffnet» nehmen
 * Das Recht an jeder Datei prüft FileForge als die angemeldete Person.
 */
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$out = static function (array $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
if (!Auth::isLoggedIn() || ($me = Auth::currentUser()) === null) {
    $out(['ok' => false, 'error' => 'login'], 401);
}
$person = (string)$me['id'];
$messages = [
    'changed' => 'Die Datei wurde inzwischen in FileForge geändert. Speichere deine Fassung als Kopie.',
    'read_only' => 'Du darfst diese Datei nur lesen. Speichere deine Fassung als Kopie.',
    'no_access' => 'Kein Zugriff auf diese Datei oder diesen Ordner.',
    'need_copy' => 'Wähle einen Ordner für die neue Datei.',
    'resting' => 'Die Freigabe ruht.',
    'quota' => 'Der Speicherplatz in der Ablage ist voll.',
    'too_large' => 'Die Datei ist zu gross.',
    'busy' => 'FileForge ist gerade beschäftigt. Bitte gleich nochmals versuchen.',
    'not_cfs' => 'Die Datei ist keine gültige Präsentation.',
    'cfs_too_new' => 'Die Datei stammt aus einer neueren FolienSchmiede.',
    'ppt_unsupported' => 'Alte PowerPoint-Dateien (.ppt) gehen nicht. Bitte als .pptx speichern.',
    'import_failed' => 'Die Datei liess sich nicht übernehmen.',
    'bad_type' => 'Diese Datei kann die FolienSchmiede nicht öffnen.',
    'no_template_dir' => 'Lege zuerst in den Einstellungen einen Ordner für Vorlagen fest.',
];
$fail = static function (string $error, int $code = 200) use ($out, $messages): never {
    $out(['ok' => false, 'error' => $error, 'message' => $messages[$error] ?? 'FileForge ist nicht erreichbar.'], $code);
};

$templateDir = static function () use ($me): ?array {
    $t = is_array($me['sf_template_dir'] ?? null) ? $me['sf_template_dir'] : null;
    return $t !== null && FfFile::parseRef((string)($t['ref'] ?? '')) !== null ? ['ref' => (string)$t['ref'], 'label' => (string)($t['label'] ?? '')] : null;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (($_GET['action'] ?? '') === 'templates') {
        $t = $templateDir();
        if ($t === null) {
            $fail('no_template_dir');
        }
        $l = FfFile::listing($person, $t['ref'], ['cfs']);
        if (!$l['ok']) {
            $fail($l['error'] === 'no_access' ? 'no_access' : 'unreachable');
        }
        $items = array_values(array_filter($l['items'], static fn(array $i): bool => !$i['isDir']));
        $out(['ok' => true, 'items' => array_map(static fn(array $i): array => ['name' => $i['name'], 'ref' => $i['ref']], $items)]);
    }
    if (($_GET['action'] ?? '') !== 'home') {
        $fail('bad_request', 400);
    }
    $forme = FfFile::forMe($person, ['cfs', 'pptx', 'odp']);
    $out(['ok' => true, 'recent' => Workspace::recent($person), 'forme' => $forme, 'templateDir' => $templateDir()]);
}

$body = json_decode((string)file_get_contents('php://input', false, null, 0, 65536), true);
$body = is_array($body) ? $body : [];
$token = (string)($body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
    $out(['ok' => false, 'error' => 'csrf'], 403);
}
$s = static fn(string $k): string => is_string($body[$k] ?? null) ? trim((string)$body[$k]) : '';
$action = $s('action');
@set_time_limit(300);

/** Recht auf die Arbeitskopie (Editor-Aktionen): nur wer sie über FileForge geöffnet hat. */
$needPresentation = static function (bool $write) use ($s, $person, $fail): string {
    $id = $s('id');
    $perm = Presentation::exists($id) ? Workspace::recheck($id, $person) : null;
    if ($perm === null || ($write && !in_array($perm, ['owner', 'edit'], true))) {
        $fail('no_access', 403);
    }
    return $id;
};

switch ($action) {
    case 'open':
        $r = Workspace::open($person, $s('ref'), $s('share'));
        $r['ok'] ? $out(['ok' => true, 'id' => $r['id'], 'readOnly' => !empty($r['readOnly'])]) : $fail($r['error']);

    case 'create':
        $tpl = $s('template');
        $r = Workspace::createNew($person, $s('dir'), $s('name'), FfFile::parseRef($tpl) !== null ? $tpl : '');
        $r['ok'] ? $out(['ok' => true, 'id' => $r['id']]) : $fail($r['error']);

    case 'save':
        $id = $needPresentation(true);
        $r = Workspace::save($id, $person);
        $r['ok'] ? $out(['ok' => true, 'saved' => !empty($r['saved']), 'status' => Workspace::status($id, $person)]) : $fail($r['error']);

    case 'save_copy':
        $id = $needPresentation(false);
        $r = Workspace::saveCopy($id, $person, $s('dir'), $s('name'));
        $r['ok'] ? $out(['ok' => true, 'name' => $r['name'], 'status' => Workspace::status($id, $person)]) : $fail($r['error']);

    case 'save_template':
        $id = $needPresentation(false);
        $t = $templateDir();
        if ($t === null) {
            $fail('no_template_dir');
        }
        $tmp = DATA_PATH . '/cache/ff/' . bin2hex(random_bytes(10)) . '.cfs';
        @mkdir(dirname($tmp), 0770, true);
        $p = CfsFile::pack($id, $tmp, null);
        $loc = FfFile::parseRef($t['ref']);
        $c = $p['ok'] && $loc !== null ? FfFile::create($person, $loc['repo'], $loc['path'], (string)preg_replace('/\.cfs\z/i', '', $s('name') !== '' ? $s('name') : 'Vorlage') . '.cfs', $tmp) : ['ok' => false, 'error' => $p['error'] ?: 'bad_request'];
        @unlink($tmp);
        !empty($c['ok']) ? $out(['ok' => true, 'name' => $c['name']]) : $fail((string)$c['error']);

    case 'status':
        $id = $needPresentation(false);
        $out(['ok' => true, 'status' => Workspace::status($id, $person)]);

    case 'template_dir':
        $ref = $s('ref');
        $loc = $ref === '' ? null : FfFile::parseRef($ref);
        if ($ref !== '' && ($loc === null || !$loc['dir'])) {
            $fail('bad_request', 400);
        }
        $label = mb_substr($s('label'), 0, 300);
        Storage::update(USERS_FILE, static function ($users) use ($person, $ref, $label) {
            foreach ($users as &$u) {
                if ((string)($u['id'] ?? '') === $person) {
                    if ($ref === '') {
                        unset($u['sf_template_dir']);
                    } else {
                        $u['sf_template_dir'] = ['ref' => $ref, 'label' => $label];
                    }
                }
            }
            unset($u);
            return $users;
        }, []);
        $out(['ok' => true]);

    case 'forget':
        Workspace::forgetRecent($person, $s('ref'), $s('share'));
        $out(['ok' => true]);
}
$fail('bad_request', 400);
