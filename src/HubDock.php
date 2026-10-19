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
 * Vertrag: `_berichte/hub/vertrag-dock-2026-10-19.md`, **Fassung 2**
 * (sha256 `a60de895e9dae9244ac9e85f2ef57586170eb6cb724116153c9676cf3ca69d10`).
 * Fassung 2 bringt: `isAdmin` wie in `acl.php` (eine Admin-Rolle allein zaehlt nicht),
 * `locked` immer in der Antwort, und `503 lock_unknown` = wie «Hub nicht erreichbar».
 *
 * Verhalten, wenn keine Auskunft kommt — hier steht es an einer Stelle, damit nichts
 * «nach Gefuehl» passiert:
 *
 * | Lage                                     | Rueckgabe                                     |
 * |------------------------------------------|-----------------------------------------------|
 * | 200 mit Liste                            | diese Liste                                   |
 * | 200, Liste leer                          | leere Liste (Person hat keine Favoriten)      |
 * | 200 `unchanged`                          | der Zwischenspeicher, Frist neu               |
 * | 200 mit `locked: true`                   | leere Liste (die Sperre gilt, auch wenn die Antwort Eintraege nennt) |
 * | 404 `unknown_subject`, 400               | `null` = unbekannt, **nicht** «darf nichts»; ein alter Stand wird **geloescht** |
 * | 401 / 403 (Token/Recht fehlt)            | `null`, Hinweis ins Protokoll; der Speicher wird **nicht** benutzt, bleibt aber liegen |
 * | 503 `lock_unknown`, 405, kaputte Antwort | wie «Hub stumm»                               |
 * | Hub stumm, Zwischenspeicher frisch       | letzte Liste (bis TTL x 4)                    |
 * | Hub stumm, nichts im Speicher            | `null`                                        |
 * | keine Hub-Adresse gesetzt, kein Token    | gar kein Aufruf, `null`                       |
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

    /** Grenzen fuer die Frist aus der Antwort — eine fremde Zahl darf sie nicht sprengen. */
    private const TTL_MIN = 30;
    private const TTL_MAX = 600;

    /**
     * Hat der Hub eben nicht geantwortet, wird so lange nicht erneut gewartet. Sonst
     * kostet ein toter Hub bei jedem Seitenaufbau das Zeitlimit (Auflage Review 19.10.).
     */
    private const STUMM_PAUSE = 60;

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
        $ttl = self::frist($speicher['ttl'] ?? null);
        $gespeichert = is_array($speicher['stand'] ?? null) ? self::sauber($speicher['stand']) : null;

        // Frisch genug: gar nicht fragen.
        if ($gespeichert !== null && isset($speicher['geholt'])
            && $jetzt - (int)$speicher['geholt'] < $ttl) {
            return $gespeichert;
        }

        // Negativ-Marke: der Hub hat eben nicht geantwortet. Dann nicht erneut ins
        // Zeitlimit laufen — sonst wartet jeder Seitenaufbau 2 s auf einen toten Hub.
        if (isset($speicher['stummSeit']) && $jetzt - (int)$speicher['stummSeit'] < self::STUMM_PAUSE) {
            $antwort = ['art' => 'stumm'];
        } else {
            // Nur fragen mit einer Version, zu der auch ein brauchbarer Stand liegt: sonst
            // antwortet der Hub `unchanged` und die Leiste bliebe fuer immer leer
            // (Auflage Review 19.10.).
            $version = ($gespeichert !== null && is_array($speicher)) ? (string)($speicher['version'] ?? '') : '';
            $antwort = self::frage($key, $version);
        }

        switch ($antwort['art']) {
            case 'liste':
                // Die Negativ-Marke faellt mit der neuen Antwort weg.
                self::schreibe($key, [
                    'version' => (string)$antwort['version'],
                    'ttl' => self::frist($antwort['ttl']),
                    'geholt' => $jetzt,
                    'stand' => $antwort['stand'],
                ]);
                return $antwort['stand'];

            case 'unchanged':
                $speicher['geholt'] = $jetzt;
                unset($speicher['stummSeit']);
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
                // herabsetzen, nichts raten. Die Marke verhindert, dass der naechste
                // Seitenaufbau wieder ins Zeitlimit laeuft.
                $speicher = is_array($speicher) ? $speicher : [];
                $speicher['stummSeit'] = $jetzt;
                self::schreibe($key, $speicher);
                if ($gespeichert !== null && isset($speicher['geholt'])
                    && $jetzt - (int)$speicher['geholt'] < $ttl * self::GNADE) {
                    return $gespeichert;
                }
                return null;
        }
    }

    /** Frist aus der Antwort, in Grenzen gehalten. */
    private static function frist($roh): int
    {
        $ttl = is_numeric($roh) ? (int)$roh : self::TTL_VORGABE;
        return max(self::TTL_MIN, min(self::TTL_MAX, $ttl));
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
        $hub = self::hubAdresse();
        if ($hub === '') {
            error_log('SlideForge: Hub-Leiste nicht abgefragt, CHURCHFORGE_HUB_URL ist nicht gesetzt.');
            return ['art' => 'stumm'];
        }
        if (!self::verschluesselt($hub)) {
            // Das Token geht nur ueber eine verschluesselte Verbindung hinaus. Ausnahme ist
            // allein der eigene Rechner (Pruefstand) -- Auflage Review 19.10.
            error_log('SlideForge: Hub-Leiste nicht abgefragt, Hub-Adresse ist nicht https.');
            return ['art' => 'stumm'];
        }
        $url = $hub . '/api/dock.php?for=' . rawurlencode($key);
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
        // Gesperrte Person: die Sperre wird **hier** durchgesetzt, nicht nur gemerkt.
        // Heute liefert der Hub bei einer Sperre ohnehin keine Eintraege; verlassen wir
        // uns darauf, haengt die Sperre allein an der Gegenseite (Auflage Review 19.10.).
        $gesperrt = !empty($daten['locked']);
        return [
            'hub' => self::saubereHub($daten['hub'] ?? null),
            'items' => $gesperrt ? [] : self::saubereItems($daten['items'] ?? null),
            'isAdmin' => !$gesperrt && !empty($daten['isAdmin']),
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
            'iconUrl' => self::zeichenAdresse($roh['iconUrl'] ?? ''),
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
                'iconUrl' => self::zeichenAdresse($eintrag['iconUrl'] ?? ''),
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
     *
     * **Warum die Ziele der Eintraege nicht auf den Hub-Host beschraenkt sind** (Frage aus dem
     * Review): die Apps liegen auf eigenen Hosts — Group-Office, Nextcloud, die FolienSchmiede
     * selbst. Eine Bindung an den Hub-Host wuerde die Leiste leerraeumen. Beschraenkt wird
     * darum das, was dort hingehoert: die **Zeichen** (`zeichenAdresse()`).
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

    /**
     * Zeichen (Icons) liefert laut Vertrag der Hub. Darum muss die Adresse beim Hub liegen —
     * sonst holt der Browser jeder Person ein Bild von einem fremden Host (Auflage Review
     * 19.10.). Passt sie nicht, bleibt das Zeichen leer; die Leiste steht trotzdem.
     */
    private static function zeichenAdresse($roh): string
    {
        $s = self::adresse($roh);
        if ($s === '') {
            return '';
        }
        $eigen = self::herkunft(self::hubAdresse());
        $fremd = self::herkunft($s);
        return ($eigen !== '' && $fremd === $eigen) ? $s : '';
    }

    /**
     * **Die Adresse, an die das Token geht, darf nicht am Anfragekopf hängen.**
     *
     * `churchforge_hub_url()` leitet den Hub notfalls aus `HTTP_HOST` ab (shared/bootstrap.php) —
     * fuer Stile und Zeichen ist das richtig, fuer ein Geheimnis nicht: ein angemeldeter Mensch
     * koennte mit `Host: hub.wunder.li` unser bkbiel-Token an einen anderen Hub ausleiten
     * (gemessen im Review 19.10.). Darum gilt hier **nur** die ausdruecklich gesetzte
     * Umgebungsvariable. Fehlt sie, geht kein Aufruf hinaus und die Leiste zeigt das
     * Hub-Zeichen — fail-closed.
     */
    private static function hubAdresse(): string
    {
        return rtrim(trim((string)(getenv('CHURCHFORGE_HUB_URL') ?: '')), '/');
    }

    /** https, oder http nur auf dem eigenen Rechner (Pruefstand). */
    private static function verschluesselt(string $url): bool
    {
        $t = parse_url($url);
        $schema = strtolower((string)($t['scheme'] ?? ''));
        $host = strtolower((string)($t['host'] ?? ''));
        if ($schema === 'https') {
            return true;
        }
        return $schema === 'http' && in_array($host, ['127.0.0.1', '::1', 'localhost'], true);
    }

    /** Schema + Host + Port einer Adresse, klein geschrieben. */
    private static function herkunft(string $url): string
    {
        $t = parse_url($url);
        if (!is_array($t) || ($t['scheme'] ?? '') === '' || ($t['host'] ?? '') === '') {
            return '';
        }
        return strtolower($t['scheme']) . '://' . strtolower($t['host'])
            . (isset($t['port']) ? ':' . (int)$t['port'] : '');
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
        // Form pruefen: ein Zeilenumbruch oder Leerzeichen im Wert wuerde sich in die
        // Kopfzeilen der Anfrage schreiben (Auflage Review 19.10.). Lieber kein Token.
        if ($token !== '' && preg_match('/^[A-Za-z0-9_\-.]{8,512}$/', $token) !== 1) {
            error_log('SlideForge: Hub-Token hat eine unerwartete Form und wird nicht benutzt.');
            $token = '';
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
