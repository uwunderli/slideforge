<?php
declare(strict_types=1);

/**
 * Übernahme des Altbestands nach FileForge (H6, 27.11.2026; Entscheid Urs: automatisch in «Meine Ablage», ohne Freigaben).
 *
 * Quelle ist der Datenordner der alten FolienSchmiede (nur lesen; Biel: `/data/slideforge/data`). Jede Präsentation wird als .cfs in
 * die eigene Ablage der Besitzerin gelegt, unter `Präsentationen/<Abschnitt>/` (Abschnitt aus ihrem alten Dashboard, archivierte
 * unter `Präsentationen/Archiv/`, Folienvorlagen unter `Präsentationen/Vorlagen/` — diesen Ordner kann die Person in den Einstellungen als
 * Vorlagen-Ordner wählen). Abgelegt wird als die Person über FileForge (`create`, ersetzt nie). Freigaben werden nicht
 * übernommen, nur gezählt.
 *
 * Zuordnung alte Benutzer → Hub-Personenschlüssel: `ct_person_id` → `ct:<nr>`, sonst `u:<benutzername>`. Ob es die Person gibt, prüft
 * FileForge: ohne eigene Ablage scheitert `create` mit `no_access`, das steht dann im Protokoll als «nicht zuordenbar».
 *
 * Fortschritt und Ende im Task Manager des Hubs (Meldung, keine Häppchen); Stoppen im Task Manager beendet den Lauf nach der
 * laufenden Präsentation. Ein zweiter Lauf überspringt, was im Zustand `data/migration/state.json` schon als übernommen steht.
 */
final class Migration
{
    private const TASK_ID = 'slideforge.uebernahme';

    /** Ziel-Ablage für das Protokoll ('' = eigene Ablage der Person). */
    private static string $ablage = '';

    /** @return array{ok:bool,error?:string,protocol?:string,counts?:array<string,int>} */
    public static function run(string $source, bool $dryRun, ?callable $echo = null, string $ablage = ''): array
    {
        // $ablage (Urs 27.11.: «Soll sie unter die Ablage BK - Office legen»): statt «Meine Ablage» in diese Ablage (per Name), als die Person;
        // fehlt ihr das Schreibrecht, ist das ein Fehler im Protokoll — kein Ausweichen auf eine andere Ablage.
        self::$ablage = trim($ablage);
        $echo ??= static function (string $line): void {
        };
        $source = rtrim($source, '/');
        $users = self::json($source . '/users.json');
        if (!is_dir($source . '/presentations') || !is_array($users)) {
            return ['ok' => false, 'error' => 'source_unreadable'];
        }
        $dir = DATA_PATH . '/migration';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $statePath = $dir . '/state.json';
        $state = Storage::read($statePath, []);
        $state = is_array($state) ? $state : [];
        $byId = [];
        foreach ($users as $u) {
            if (is_array($u) && (string)($u['id'] ?? '') !== '') {
                $byId[(string)$u['id']] = $u;
            }
        }
        $ids = [];
        foreach (scandir($source . '/presentations') ?: [] as $pid) {
            if (preg_match('/^[a-f0-9]+\z/', $pid) === 1 && is_file($source . '/presentations/' . $pid . '/meta.json')) {
                $ids[] = $pid;
            }
        }
        sort($ids);
        $counts = ['total' => count($ids), 'done' => 0, 'skipped' => 0, 'failed' => 0, 'unassigned' => 0, 'templates' => 0, 'sharesDropped' => 0];
        $rows = [];
        $perPerson = [];
        $title = 'FolienSchmiede: Übernahme nach FileForge' . ($dryRun ? ' (Probelauf)' : '');
        self::report(['id' => self::TASK_ID, 'title' => $title, 'state' => 'laeuft', 'progress' => 0, 'itemCount' => count($ids), 'reportOnly' => true]);
        $n = 0;
        foreach ($ids as $pid) {
            $n++;
            $src = $source . '/presentations/' . $pid;
            $meta = self::json($src . '/meta.json');
            $meta = is_array($meta) ? $meta : [];
            $acl = self::json($src . '/acl.json');
            $shares = is_array($acl) ? count((array)($acl['shares'] ?? [])) : 0;
            $counts['sharesDropped'] += $shares;
            $owner = (string)($meta['owner_id'] ?? '');
            $user = $byId[$owner] ?? null;
            $person = is_array($user) ? self::personKey($user) : '';
            $name = self::fileName((string)($meta['title'] ?? ''));
            $folder = 'Präsentationen/' . (!empty($meta['is_template']) ? 'Vorlagen' : (!empty($meta['archived']) ? 'Archiv' : self::sectionTitle($user, $pid)));
            $row = ['id' => $pid, 'person' => $person, 'user' => is_array($user) ? (string)($user['username'] ?? '') : '', 'dir' => $folder,
                'name' => $name, 'shares' => $shares, 'result' => ''];
            if (!empty($meta['is_template'])) {
                $counts['templates']++;
            }
            if (isset($state[$pid]['ref'])) {
                $counts['skipped']++;
                $row['result'] = 'schon übernommen';
            } elseif ($person === '') {
                $counts['unassigned']++;
                $row['result'] = 'nicht zuordenbar (Besitzer unbekannt)';
            } elseif ($dryRun) {
                $row['result'] = 'würde übernommen';
            } else {
                $r = self::one($src, $pid, $person, '/' . $folder, $name, self::$ablage !== '' ? 'name:' . self::$ablage : 'own');
                if ($r['ok']) {
                    $counts['done']++;
                    $state[$pid] = ['ref' => $r['ref'], 'person' => $person, 'at' => date('c')];
                    Storage::write($statePath, $state);
                    $row['result'] = 'übernommen als ' . $r['name'];
                } elseif (self::$ablage !== '' && in_array($r['error'], ['no_access', 'read_only', 'no_library', 'ambiguous'], true)) {
                    $counts['failed']++;
                    $row['result'] = 'Fehler: ' . ['no_access' => 'kein Zugang', 'read_only' => 'kein Schreibrecht', 'no_library' => 'Ablage nicht gefunden',
                        'ambiguous' => 'Ablagename mehrdeutig'][$r['error']] . ' («' . self::$ablage . '»)';
                } elseif ($r['error'] === 'no_access') {
                    $counts['unassigned']++;
                    $row['result'] = 'nicht zuordenbar (keine eigene Ablage in FileForge)';
                } else {
                    $counts['failed']++;
                    $row['result'] = 'Fehler: ' . $r['error'];
                }
            }
            $perPerson[$person !== '' ? $person : '(unbekannt)'] = ($perPerson[$person !== '' ? $person : '(unbekannt)'] ?? 0) + 1;
            $rows[] = $row;
            $echo($pid . ' · ' . ($person !== '' ? $person : '-') . ' · ' . $row['result']);
            if ($n % 5 === 0 || $n === count($ids)) {
                $rep = self::report(['id' => self::TASK_ID, 'title' => $title, 'state' => 'laeuft', 'progress' => (int)floor($n * 100 / max(1, count($ids))),
                    'itemsDone' => $n - $counts['failed'], 'itemsFailed' => $counts['failed']]);
                if (!empty($rep['json']['cancelRequested'])) {
                    self::report(['id' => self::TASK_ID, 'title' => $title, 'state' => 'abgebrochen']);
                    $proto = self::protocol($rows, $counts, $perPerson, $dryRun, true);
                    return ['ok' => false, 'error' => 'cancelled', 'protocol' => $proto, 'counts' => $counts];
                }
            }
        }
        $proto = self::protocol($rows, $counts, $perPerson, $dryRun, false);
        self::report(['id' => self::TASK_ID, 'title' => $title, 'state' => $counts['failed'] > 0 ? 'fehler' : 'fertig', 'progress' => 100,
            'detail' => sprintf('%d übernommen, %d übersprungen, %d nicht zuordenbar, %d Fehler', $counts['done'], $counts['skipped'], $counts['unassigned'], $counts['failed'])]);
        return ['ok' => true, 'protocol' => $proto, 'counts' => $counts];
    }

    /** Eine Präsentation packen und als die Person ablegen. @return array{ok:bool,error:string,ref?:string,name?:string} */
    private static function one(string $src, string $pid, string $person, string $dir, string $name, string $repo = 'own'): array
    {
        $made = [];
        try {
            // Arbeitsstand vorübergehend in den eigenen Datenordner holen (CfsFile packt aus PRESENTATIONS_PATH), samt Medien
            // anderer Präsentationen, auf die Folien verweisen (Folien-Sets).
            if (!Presentation::exists($pid)) {
                self::copyDir($src, Presentation::dir($pid));
                $made[] = $pid;
            }
            $raw = (string)@file_get_contents($src . '/slides.json') . (string)@file_get_contents($src . '/meta.json');
            preg_match_all('/asset\.php\?id=([a-f0-9]+)&/', $raw, $m);
            foreach (array_unique($m[1]) as $other) {
                $o = dirname($src) . '/' . $other;
                if ($other !== $pid && !Presentation::exists($other) && is_dir($o . '/assets')) {
                    self::copyDir($o . '/assets', Presentation::dir($other) . '/assets');
                    $made[] = $other;
                }
            }
            $tmp = DATA_PATH . '/cache/ff/' . bin2hex(random_bytes(10)) . '.cfs';
            @mkdir(dirname($tmp), 0770, true);
            try {
                $p = CfsFile::pack($pid, $tmp, null);
                if (!$p['ok']) {
                    return ['ok' => false, 'error' => $p['error']];
                }
                $c = FfFile::create($person, $repo, $dir, $name, $tmp, true);
            } finally {
                @unlink($tmp);
            }
            return $c['ok'] ? ['ok' => true, 'error' => '', 'ref' => $c['ref'], 'name' => $c['name']] : ['ok' => false, 'error' => $c['error'] !== '' ? $c['error'] : 'failed'];
        } finally {
            foreach ($made as $id) {
                Presentation::delete($id);
            }
        }
    }

    /** @param array<string,mixed> $user */
    private static function personKey(array $user): string
    {
        $ct = (int)($user['ct_person_id'] ?? 0);
        if ($ct > 0) {
            return 'ct:' . $ct;
        }
        $name = trim((string)($user['username'] ?? ''));
        return preg_match('/^[A-Za-z0-9._@+-]{1,128}\z/', $name) === 1 ? 'u:' . $name : '';
    }

    /** Abschnitt des alten Dashboards, in dem die Präsentation lag («Allgemein», wenn keiner). @param array<string,mixed>|null $user */
    private static function sectionTitle(?array $user, string $pid): string
    {
        foreach (is_array($user['dashboard_sections'] ?? null) ? $user['dashboard_sections'] : [] as $s) {
            if (is_array($s) && empty($s['is_shared_inbox']) && in_array($pid, (array)($s['items'] ?? []), true)) {
                $t = self::clean((string)($s['title'] ?? ''));
                return $t !== '' ? $t : 'Allgemein';
            }
        }
        return 'Allgemein';
    }

    private static function fileName(string $title): string
    {
        $t = self::clean($title);
        return ($t !== '' ? $t : 'Präsentation') . '.cfs';
    }

    /** Für Datei- und Ordnernamen: keine Schrägstriche, Steuerzeichen oder führenden Punkte, höchstens 120 Zeichen. */
    private static function clean(string $s): string
    {
        $s = trim((string)preg_replace('/[\/\\\\\x00-\x1f\x7f:*?"<>|]+/u', ' ', $s));
        $s = ltrim((string)preg_replace('/\s+/u', ' ', $s), '.');
        return mb_substr($s, 0, 120);
    }

    private static function copyDir(string $from, string $to): void
    {
        @mkdir($to, 0770, true);
        foreach (scandir($from) ?: [] as $f) {
            if ($f === '.' || $f === '..' || is_link($from . '/' . $f)) {
                continue;
            }
            is_dir($from . '/' . $f) ? self::copyDir($from . '/' . $f, $to . '/' . $f) : @copy($from . '/' . $f, $to . '/' . $f);
        }
    }

    /** @return mixed */
    private static function json(string $file)
    {
        return is_file($file) && !is_link($file) ? json_decode((string)file_get_contents($file), true) : null;
    }

    /** @param array<string,mixed> $body @return array<string,mixed> */
    private static function report(array $body): array
    {
        try {
            return HubApp::task($body);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Protokoll `data/migration/protokoll-<zeit>.md`: Zahlen, je Person, jede Präsentation (Kennung, Person, Ziel, Ergebnis). Keine Inhalte.
     *
     * @param list<array<string,mixed>> $rows
     * @param array<string,int> $counts
     * @param array<string,int> $perPerson
     */
    private static function protocol(array $rows, array $counts, array $perPerson, bool $dryRun, bool $cancelled): string
    {
        $f = DATA_PATH . '/migration/protokoll-' . date('Ymd-His') . ($dryRun ? '-probe' : '') . '.md';
        $o = '# Übernahme nach FileForge ' . date('d.m.Y H:i') . ($dryRun ? ' (Probelauf, nichts abgelegt)' : '') . ($cancelled ? ' — abgebrochen' : '') . "\n\n"
            . 'Ziel: ' . (self::$ablage !== '' ? 'Ablage «' . self::$ablage . '»' : 'eigene Ablage der Person') . "\n\n";
        $o .= sprintf("Präsentationen %d · übernommen %d · schon übernommen %d · nicht zuordenbar %d · Fehler %d · davon Vorlagen (nach Präsentationen/Vorlagen) %d · Freigaben weggelassen %d\n\n",
            $counts['total'], $counts['done'], $counts['skipped'], $counts['unassigned'], $counts['failed'], $counts['templates'], $counts['sharesDropped']);
        $o .= "## Je Person\n\n";
        ksort($perPerson);
        foreach ($perPerson as $p => $c) {
            $o .= '- ' . $p . ': ' . $c . "\n";
        }
        $o .= "\n## Einzeln\n\n| Präsentation | Alter Benutzer | Person | Ziel | Freigaben | Ergebnis |\n|---|---|---|---|---|---|\n";
        foreach ($rows as $r) {
            $o .= '| ' . $r['id'] . ' | ' . str_replace('|', '/', (string)$r['user']) . ' | ' . ($r['person'] !== '' ? $r['person'] : '–') . ' | '
                . str_replace('|', '/', $r['dir'] . '/' . $r['name']) . ' | ' . $r['shares'] . ' | ' . $r['result'] . " |\n";
        }
        file_put_contents($f, $o);
        return $f;
    }
}
