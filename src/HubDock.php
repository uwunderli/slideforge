<?php

/**
 * Die App-Leiste holt der Hub, nicht wir.
 *
 * Wer welche App sehen darf, entscheidet **allein der Hub** (`ModuleAccess`: persoenliche App,
 * Administrator, `allowUsers`, `allowGroups`, `requireAnyGroup`, Personensperre). Dieses Modul
 * deutet davon nichts — es fragt `GET /api/dock.php?for=<kennung>` und zeigt die Liste.
 * Grundlage: Regel Urs 19.10.2026 «ACL's werden ueber die User definiert und stehen nicht fest
 * im Code. Wer was sehen darf, wird von Menschen gesagt und nicht von der Maschine.»
 *
 * Vertrag: `_berichte/hub/vertrag-dock-2026-10-19.md`
 * (sha256 `b9035dfe9574d115cecdbde7f7800e66d2f23296ac86b2f6089520bb95b1541a`).
 *
 * Verhalten, wenn keine Auskunft kommt — hier steht es an einer Stelle, damit nichts
 * «nach Gefuehl» passiert:
 *
 * | Lage                                   | Rueckgabe                                  |
 * |----------------------------------------|--------------------------------------------|
 * | 200 mit Liste                          | diese Liste                                |
 * | 200, Liste leer                        | leere Liste (Person hat keine Favoriten)   |
 * | 200 `unchanged`                        | der Zwischenspeicher, Frist neu            |
 * | 404 `unknown_subject`                  | `null` = unbekannt, **nicht** «darf nichts» |
 * | 401 / 403 (Token/Recht fehlt)          | `null`, Hinweis ins Protokoll              |
 * | Hub stumm, Zwischenspeicher frisch     | letzte Liste (bis TTL x 4)                 |
 * | Hub stumm, nichts im Speicher          | `null`                                     |
 *
 * `null` heisst fuer die Leiste: nur das Hub-Zeichen. Nie «vorsichtshalber alles zeigen».
 *
 * Das Modul-Token stellt ein Mensch im Hub aus (Einstellungen › Modul-Zugaenge). Sein Wert
 * wird **nie** protokolliert, gemeldet oder in eine Fehlermeldung geschrieben.
 */
class HubDock
{
    /** Kein Seitenaufbau wartet laenger als das auf den Hub. */
    private const TIMEOUT = 2;

    /** Schweigt der Hub, gilt die letzte Antwort noch so lange (TTL x dies). */
    private const GNADE = 4;

    private const TTL_VORGABE = 300;

    /** @var array<string,mixed>|null|false false = noch nicht geholt */
    private static $stand = false;

    /** @var string|null null = noch nicht gelesen. Der Wert wird nie protokolliert. */
    private static ?string $token = null;

    /**
     * @return array{hub:array<string,string>,items:list<array<string,mixed>>,isAdmin:bool,locked:bool|null,version:string}|null
     */
    public static function current(): ?array
    {
        if (self::$stand !== false) {
            return self::$stand;
        }
        self::$stand = self::laden();
        return self::$stand;
    }

    /** Nur fuer Prueffaelle. */
    public static function reset(): void
    {
        self::$stand = false;
        self::$token = null;
    }

    /**
     * Dieselbe Kennung, die der Hub bildet (`UserPrefs::userKey()`): Personen-Nummer, sonst
     * `u:<Benutzername>`. Die Zweitkonto-Auflösung macht der Hub.
     */
    public static function userKey(): string
    {
        if (!class_exists('SharedAuth')) {
            return '';
        }
        $user = SharedAuth::read();
        if (!is_array($user)) {
            return '';
        }
        foreach (['sub', 'ct_person_id', 'person_id'] as $feld) {
            $raw = trim((string)($user[$feld] ?? ''));
            if ($raw !== '' && ctype_digit($raw) && (int)$raw > 0) {
                return $raw;
            }
        }
        $name = trim((string)($user['username'] ?? ''));
        return $name !== '' ? 'u:' . $name : '';
    }

    /** @return array<string,mixed>|null */
    private static function laden(): ?array
    {
        $key = self::userKey();
        if ($key === '' || self::token() === '') {
            return null;
        }
        $speicher = self::lese($key);
        $jetzt = time();
        $ttl = isset($speicher['ttl']) ? max(30, (int)$speicher['ttl']) : self::TTL_VORGABE;
        $gespeichert = is_array($speicher['stand'] ?? null) ? self::sauber($speicher['stand']) : null;

        // Frisch genug: gar nicht fragen.
        if ($gespeichert !== null && isset($speicher['geholt'])
            && $jetzt - (int)$speicher['geholt'] < $ttl) {
            return $gespeichert;
        }

        $antwort = self::frage($key, is_array($speicher) ? (string)($speicher['version'] ?? '') : '');

        switch ($antwort['art']) {
            case 'liste':
                self::schreibe($key, [
                    'version' => (string)$antwort['version'],
                    'ttl' => (int)$antwort['ttl'],
                    'geholt' => $jetzt,
                    'stand' => $antwort['stand'],
                ]);
                return $antwort['stand'];

            case 'unchanged':
                $speicher['geholt'] = $jetzt;
                self::schreibe($key, $speicher);
                return $gespeichert;

            case 'unbekannt':
                // Der Hub kennt diese Person nicht (404) — dann gilt auch ein alter Stand
                // nicht mehr. Er wird weggeworfen, damit niemand eine Liste behaelt, die
                // ihm nicht mehr zusteht.
                self::vergiss($key);
                return null;

            case 'verweigert':
                // Token oder Zusatzrecht weg (401/403): kein Recht, also keine Liste —
                // auch nicht aus dem Zwischenspeicher. Einrichtungsfehler, nicht Ausfall.
                return null;

            case 'stumm':
            default:
                // Hub schweigt (Netz, Zeitlimit, kaputte Antwort, 503 unlesbarer Sperrstand):
                // letzte Antwort noch eine Weile gelten lassen, dann nichts. Nichts
                // herabsetzen, nichts raten.
                if ($gespeichert !== null && isset($speicher['geholt'])
                    && $jetzt - (int)$speicher['geholt'] < $ttl * self::GNADE) {
                    return $gespeichert;
                }
                return null;
        }
    }

    /**
     * Ein Aufruf. `art` sagt, was daraus folgt:
     * `liste` (mit `stand`/`version`/`ttl`), `unchanged`, `unbekannt` (404/400),
     * `verweigert` (401/403), `stumm` (Netz, Zeitlimit, kaputte Antwort, 503).
     *
     * @return array<string,mixed>
     */
    private static function frage(string $key, string $version): array
    {
        $url = rtrim(churchforge_hub_url(), '/') . '/api/dock.php?for=' . rawurlencode($key);
        if ($version !== '') {
            $url .= '&version=' . rawurlencode($version);
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return ['art' => 'stumm'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . self::token(),
                'Accept: application/json',
            ],
        ]);
        $rumpf = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($rumpf) || $status === 0) {
            return ['art' => 'stumm'];
        }
        if ($status === 401 || $status === 403) {
            // Einrichtungsfehler, nicht Rechtelage: der Hub kennt unser Token nicht oder die
            // App hat das Zusatzrecht nicht. Ohne Wert, ohne Kopf — nur der Hinweis.
            error_log('SlideForge: Hub-Leiste abgewiesen (HTTP ' . $status . '). Modul-Token oder Zusatzrecht pruefen.');
            return ['art' => 'verweigert'];
        }
        if ($status === 404 || $status === 400) {
            // 404 unknown_subject: der Hub kennt die Person nicht. Das ist **nicht**
            // «darf nichts» (das waere 200 mit leerer Liste). 400 kann hier nicht
            // auftreten (wir senden immer `for`) und gilt gleich.
            return ['art' => 'unbekannt'];
        }
        if ($status !== 200) {
            // 503 lock_unknown (Sperrstand im Hub unlesbar) und alles sonst: wie
            // «Hub nicht erreichbar» behandeln — Vertrag Fassung 2.
            return ['art' => 'stumm'];
        }
        $daten = json_decode($rumpf, true);
        if (!is_array($daten) || empty($daten['ok'])) {
            return ['art' => 'stumm'];
        }
        if (!empty($daten['unchanged'])) {
            return ['art' => 'unchanged'];
        }
        return [
            'art' => 'liste',
            'version' => (string)($daten['version'] ?? ''),
            'ttl' => (int)($daten['ttl'] ?? self::TTL_VORGABE),
            'stand' => self::sauber($daten),
        ];
    }

    /**
     * Werte aus der Antwort auf das Erlaubte beschneiden.
     *
     * Dasselbe laeuft **auch beim Lesen aus dem Zwischenspeicher**: der liegt in `data/cache/`,
     * und solange dieser Ordner weltschreibbar ist (gemessen 19.10.: `0777`), koennte eine
     * untergeschobene Datei sonst eine Adresse ins Markup bringen. Tiefenstaffelung, keine
     * Rechtefrage.
     *
     * @param array<string,mixed> $daten
     * @return array{hub:array<string,string>,items:list<array<string,mixed>>,isAdmin:bool,locked:bool|null,version:string}
     */
    private static function sauber(array $daten): array
    {
        return [
            'hub' => self::saubereHub($daten['hub'] ?? null),
            'items' => self::saubereItems($daten['items'] ?? null),
            'isAdmin' => !empty($daten['isAdmin']),
            // Laut Vertrag Fassung 2 ist das Feld immer da; fehlt es (aeltere Fassung),
            // gilt der Sperrstand als unbekannt und die Liste trotzdem.
            'locked' => array_key_exists('locked', $daten) ? (bool)$daten['locked'] : null,
            'version' => (string)($daten['version'] ?? ''),
        ];
    }

    /** @return array<string,string> */
    private static function saubereHub($roh): array
    {
        $roh = is_array($roh) ? $roh : [];
        return [
            'label' => self::text($roh['label'] ?? 'Hub', 60),
            'href' => self::adresse($roh['href'] ?? ''),
            'iconUrl' => self::adresse($roh['iconUrl'] ?? ''),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function saubereItems($roh): array
    {
        if (!is_array($roh)) {
            return [];
        }
        $out = [];
        foreach ($roh as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }
            $id = self::text($eintrag['id'] ?? '', 64);
            $href = self::adresse($eintrag['href'] ?? '');
            if ($id === '' || $href === '') {
                continue;
            }
            $label = self::text($eintrag['label'] ?? $id, 120);
            $out[] = [
                'id' => $id,
                'label' => $label,
                'shortLabel' => self::text($eintrag['shortLabel'] ?? $label, 120),
                'href' => $href,
                'iconUrl' => self::adresse($eintrag['iconUrl'] ?? ''),
                'newTab' => !empty($eintrag['newTab']),
            ];
        }
        return $out;
    }

    private static function text($roh, int $max): string
    {
        if (!is_string($roh) && !is_int($roh)) {
            return '';
        }
        $s = trim((string)$roh);
        return $s === '' ? '' : mb_substr($s, 0, $max);
    }

    /**
     * Nur `https:`/`http:`-Adressen aus der Antwort uebernehmen. Das ist keine Rechtefrage,
     * sondern eine harte Grenze: ein `javascript:`-Verweis im Markup waere ein Einfallstor,
     * auch wenn die Antwort vom eigenen Hub kommt.
     */
    private static function adresse($roh): string
    {
        if (!is_string($roh)) {
            return '';
        }
        $s = trim($roh);
        if ($s === '' || mb_strlen($s) > 2048) {
            return '';
        }
        return preg_match('#^https?://#i', $s) === 1 ? $s : '';
    }

    /** Der Wert wird nie protokolliert oder gemeldet. */
    private static function token(): string
    {
        if (self::$token !== null) {
            return self::$token;
        }
        $token = trim((string)(getenv('SLIDEFORGE_HUB_TOKEN') ?: ''));
        if ($token === '') {
            $datei = DATA_PATH . '/.hub-token';
            if (is_file($datei) && is_readable($datei)) {
                $token = trim((string)@file_get_contents($datei));
            }
        }
        self::$token = $token;
        return self::$token;
    }

    private static function speicherDatei(string $key): string
    {
        return rtrim(EXPORT_CACHE_PATH, '/') . '/hub-dock-' . sha1($key) . '.json';
    }

    /** @return array<string,mixed>|null */
    private static function lese(string $key): ?array
    {
        $datei = self::speicherDatei($key);
        if (!is_file($datei) || !is_readable($datei)) {
            return null;
        }
        $daten = json_decode((string)@file_get_contents($datei), true);
        return is_array($daten) ? $daten : null;
    }

    private static function vergiss(string $key): void
    {
        $datei = self::speicherDatei($key);
        if (is_file($datei)) {
            @unlink($datei);
        }
    }

    /** @param array<string,mixed> $daten */
    private static function schreibe(string $key, array $daten): void
    {
        $ordner = rtrim(EXPORT_CACHE_PATH, '/');
        if (!is_dir($ordner)) {
            @mkdir($ordner, 0770, true);
        }
        $datei = self::speicherDatei($key);
        $roh = json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($roh === false) {
            return;
        }
        // Erst daneben schreiben, dann umbenennen: ein halber Zwischenspeicher waere schlimmer
        // als keiner.
        $tmp = $datei . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $roh, LOCK_EX) === false) {
            return;
        }
        if (!@rename($tmp, $datei)) {
            @unlink($tmp);
        }
    }
}
