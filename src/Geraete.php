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

    /** @return array{ok:bool,geraete:list<array<string,mixed>>,error:string} */
    public static function liste(string $person): array
    {
        $r = HubApp::hub('/api/anzeige.php?op=geraete', ['person' => $person]);
        $g = is_array($r['json']['geraete'] ?? null) ? $r['json']['geraete'] : [];
        return ['ok' => $r['ok'], 'geraete' => array_values($g), 'error' => $r['error']];
    }

    /** @return array{ok:bool,error:string} */
    public static function zeigen(string $person, string $geraet, string $id, string $titel): array
    {
        $url = $id === '' ? '' : self::basis() . '/player.php?p=' . rawurlencode($id);
        $r = HubApp::hub('/api/anzeige.php?op=zeigen', ['person' => $person, 'geraet' => $geraet, 'url' => $url, 'titel' => $titel, 'inhalt' => $id]);
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
