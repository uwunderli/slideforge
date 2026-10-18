<?php

/**
 * Favoriten des Hub-Kontos lesen — **nur lesen, nie schreiben**.
 *
 * Der Hub fuehrt die Favoriten pro Person in `user-prefs.json` seines
 * Datenordners (`UserPrefs::favorites()`). Die FolienSchmiede hat diese Klasse
 * nicht; sie kennt die Person aber aus dem SharedAuth-Cookie und liest denselben
 * Wert aus derselben Datei, wenn der Ordner erreichbar ist (Hub und Modul liegen
 * auf derselben Maschine und laufen unter derselben Kennung).
 *
 * Darum ausdruecklich nur lesend: `user-prefs.json` haelt die Einstellungen
 * **aller** Personen. Schreiben gehoert dem Hub (dort mit Sperre, `HubJsonStore`);
 * ein zweiter Schreiber koennte fremde Einstellungen verlieren.
 *
 * Rueckgabe von favorites():
 *   - `list<string>` — der Stand des Hubs. Auch die leere Liste gehoert dazu: die
 *     Person hat keine Favoriten **oder** ist im Hub-Stand noch nicht gefuehrt.
 *     Beides zeigt der Hub gleich an (nur das Hub-Zeichen), also zeigen wir es auch
 *     gleich an. (Review 18.10.: sonst kippt das Dock von "alles" auf "leer",
 *     sobald im Hub irgendeine andere Einstellung einen Eintrag anlegt.)
 *   - `null` — unbekannt: kein Hub-Login, oder die Datei fehlt / ist nicht lesbar /
 *     nicht lesbar als JSON. Dann faellt der Launcher auf seine alte Anzeige
 *     zurueck, statt leer zu sein.
 *
 * Vorgabe Urs 18.10.2026 (U50): «Launcher ja» — Dock mit Favoriten wie im Hub.
 */
class HubFavorites
{
    /** @var list<string>|null|false false = noch nicht geladen */
    private static $cache = false;

    /** @return list<string>|null */
    public static function favorites(): ?array
    {
        if (self::$cache !== false) {
            return self::$cache;
        }
        self::$cache = self::load();
        return self::$cache;
    }

    /** Nur fuer Prueffaelle: den gemerkten Stand vergessen. */
    public static function reset(): void
    {
        self::$cache = false;
    }

    /** @return list<string>|null */
    private static function load(): ?array
    {
        $key = self::userKey();
        if ($key === '') {
            return null;
        }
        $prefs = self::readJson(self::dataDir() . '/user-prefs.json');
        if ($prefs === null) {
            return null;
        }
        $key = self::canonicalKey($key);
        // Person im Hub-Stand nicht gefuehrt = keine Favoriten, genau wie dort
        // (`UserPrefs::get()` gibt fuer einen fehlenden Eintrag ein leeres Feld).
        $own = is_array($prefs[$key] ?? null) ? $prefs[$key] : [];
        $fav = $own['favorites'] ?? [];
        if (!is_array($fav)) {
            return [];
        }
        $out = [];
        foreach ($fav as $id) {
            // Verschachteltes nicht in Text zwingen — das gaebe eine PHP-Warnung
            // mitten im Seitenkopf (vor dem 18.10. auch sichtbar, seither nur im
            // Protokoll, siehe befund-fehleranzeige-prod-2026-10-18.md).
            if (!is_string($id) && !is_int($id)) {
                continue;
            }
            $id = trim((string)$id);
            if ($id !== '' && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * Dieselbe Kennung, die der Hub bildet: Personen-Nummer, sonst
     * `u:<Benutzername>` — wie `UserPrefs::userKey()`, aber **vor** der
     * Zweitkonto-Auflösung; die macht `canonicalKey()` beim Lesen.
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

    /**
     * Zweitanmeldung derselben Person zeigt im Hub auf ein Hauptkonto
     * (`IdentityLinks::canonical()`). Dieselbe Zuordnung, nur lesend.
     */
    private static function canonicalKey(string $key): string
    {
        $links = self::readJson(self::dataDir() . '/identity-links.json');
        if ($links === null) {
            return $key;
        }
        $all = is_array($links['links'] ?? null) ? $links['links'] : [];
        $to = trim((string)($all[$key]['to'] ?? ''));
        return $to !== '' ? $to : $key;
    }

    /**
     * Datenordner des Hubs. **Nicht** selbst herleiten, sondern denselben Ordner
     * nehmen, in dem `modules.json` liegt: dort schreibt der Hub auch
     * `user-prefs.json`. So gelten `CHURCHFORGE_MODULES_JSON`,
     * `CHURCHFORGE_HUB_DATA` und der Standort (gemeinde / feuerwehr / homeoffice)
     * genau wie beim Rest der Kette. Auflage aus dem Review, 18.10.
     */
    private static function dataDir(): string
    {
        if (function_exists('churchforge_modules_file')) {
            return dirname(churchforge_modules_file());
        }
        $env = trim((string)(getenv('CHURCHFORGE_HUB_DATA') ?: ''));
        if ($env !== '') {
            return rtrim($env, '/');
        }
        if (defined('CHURCHFORGE_SHARED_PATH')) {
            return dirname(CHURCHFORGE_SHARED_PATH) . '/hub/data/gemeinde';
        }
        if (defined('BASE_PATH')) {
            return dirname(BASE_PATH) . '/hub/data/gemeinde';
        }
        return '';
    }

    /** Mehr als das liest keine Einstellungsdatei — sonst bricht der Seitenaufbau. */
    private const MAX_BYTES = 4194304;

    /** @return array<string,mixed>|null */
    private static function readJson(string $file): ?array
    {
        if ($file === '' || !is_file($file) || !is_readable($file)) {
            return null;
        }
        $groesse = @filesize($file);
        if ($groesse === false || $groesse > self::MAX_BYTES) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
