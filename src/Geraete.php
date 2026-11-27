<?php

/**
 * **Auf Gerät zeigen** (Urs, 27.11.2026): SlideForge schickt eine Präsentation an ein Anzeige-Gerät des Hubs (`api/anzeige.php`,
 * Hub ≥ 2.33.0). Das Gerät öffnet `player.php` mit einem Einmal-Ticket des Hubs; SlideForge löst es ein und gibt dem Browser des
 * Geräts eine **Gerätesitzung nur für diese eine Präsentation** (Zuschauer-Ansicht, folgt dem Presenter/Tablet über `live.php`).
 * Das Gerät braucht kein Recht in FileForge und keine Person.
 */
final class Geraete
{
    private const SESSION = 'sf_geraet';

    /** Geräte der Person; `icons` = Icon-Liste des Hubs (≥ 2.34.3). @return array{ok:bool,geraete:list<array<string,mixed>>,icons:list<array<string,mixed>>,error:string} */
    public static function liste(string $person): array
    {
        $r = HubApp::hub('/api/anzeige.php?op=geraete', ['person' => $person]);
        $g = is_array($r['json']['geraete'] ?? null) ? $r['json']['geraete'] : [];
        $i = is_array($r['json']['icons'] ?? null) ? $r['json']['icons'] : [];
        return ['ok' => $r['ok'], 'geraete' => array_values($g), 'icons' => array_values($i), 'error' => $r['error']];
    }

    /**
     * Name oder Icon eines Geräts ändern (3.9.9, Hub ≥ 2.34.3 `op=umbenennen`/`op=icon`); Recht (Besitzer oder Admin) prüft der Hub.
     * @return array{ok:bool,error:string}
     */
    public static function aendern(string $person, string $geraet, string $feld, string $wert): array
    {
        $op = $feld === 'icon' ? 'icon' : 'umbenennen';
        $r = HubApp::hub('/api/anzeige.php?op=' . $op, ['person' => $person, 'geraet' => $geraet, $feld === 'icon' ? 'icon' : 'name' => $wert]);
        if ($r['ok']) {
            return ['ok' => true, 'error' => ''];
        }
        return ['ok' => false, 'error' => $r['status'] === 200 ? 'recht' : (string)($r['json']['error'] ?? $r['error'])];
    }

    /**
     * `$mit` (3.8.0, Mitlaufen): das Gerät folgt dem Editor statt dem Presenter und zeigt jede gespeicherte Änderung.
     * @return array{ok:bool,error:string}
     */
    public static function zeigen(string $person, string $geraet, string $id, string $titel, bool $mit = false): array
    {
        $url = $id === '' ? '' : self::basis() . '/player.php?p=' . rawurlencode($id) . ($mit ? '&mit=1' : '');
        $r = HubApp::hub('/api/anzeige.php?op=zeigen', ['person' => $person, 'geraet' => $geraet, 'url' => $url, 'titel' => $titel, 'inhalt' => $id]);
        return ['ok' => $r['ok'], 'error' => $r['ok'] ? '' : (string)($r['json']['error'] ?? $r['error'])];
    }

    /** Gerät die Seite neu laden lassen (3.6.6, Hub ≥ 2.33.11 `op=neuladen`); Recht prüft der Hub. @return array{ok:bool,error:string} */
    public static function neuladen(string $person, string $geraet): array
    {
        $r = HubApp::hub('/api/anzeige.php?op=neuladen', ['person' => $person, 'geraet' => $geraet]);
        return ['ok' => $r['ok'], 'error' => $r['ok'] ? '' : (string)($r['json']['error'] ?? $r['error'])];
    }

    /**
     * Gerät mit dem Code von `device.php` koppeln (3.4.0, Hub ≥ 2.33.8 `op=koppeln`). Wer koppeln darf, prüft der Hub.
     * @return array{ok:bool,error:string,geraet:array<string,mixed>}
     */
    public static function koppeln(string $person, string $code, string $name, string $dauer): array
    {
        $r = HubApp::hub('/api/anzeige.php?op=koppeln', ['person' => $person, 'code' => $code, 'name' => $name, 'dauer' => $dauer, 'freigabe' => 'ich']);
        $g = is_array($r['json']['geraet'] ?? null) ? $r['json']['geraet'] : [];
        return ['ok' => $r['ok'], 'error' => $r['ok'] ? '' : (string)($r['json']['error'] ?? $r['error']), 'geraet' => $g];
    }

    /** Einmal-Ticket des Hubs einlösen; bei Erfolg Gerätesitzung für genau diese Präsentation. */
    public static function einloesen(string $geraet, string $ticket, string $id): bool
    {
        if ($geraet === '' || $ticket === '' || !Presentation::exists($id)) {
            return false;
        }
        $r = HubApp::hub('/api/anzeige.php?op=pruefe', ['geraet' => $geraet, 'ticket' => $ticket]);
        if (!$r['ok'] || (string)($r['json']['inhalt'] ?? '') !== $id) {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION[self::SESSION] = ['geraet' => $geraet, 'name' => (string)($r['json']['geraet']['name'] ?? ''), 'p' => $id, 'seit' => time()];
        return true;
    }

    /** Darf der Browser als Gerät diese Präsentation ansehen (Zuschauer-Ansicht, live.php lesen, Medien)? */
    public static function darf(string $id): bool
    {
        $s = $_SESSION[self::SESSION] ?? null;
        return is_array($s) && $id !== '' && hash_equals((string)($s['p'] ?? ''), $id);
    }

    /** Pfad der App unter dem Hub, z. B. `/slideforge`. */
    private static function basis(): string
    {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('#^(/[a-z0-9_-]+)/public/#', $script, $m) === 1) {
            return $m[1];
        }
        return '/slideforge';
    }
}
