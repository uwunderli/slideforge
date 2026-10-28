<?php
// Identische Kopie von gemeinde/shared/src/HubPersonKey.php (Fallback, wenn ../shared fehlt). Aenderungen immer zuerst dort.

/**
 * Personen-Kennung ChurchForge (Urs, 28.10.2026): genau zwei Formen.
 *
 *   u:<name>  — ChurchForge-Login (lokaler Hub-Benutzer)
 *   ct:<id>   — ChurchTools-Login mit Personennummer (z. B. ct:216)
 *
 * Alte Daten tragen für CT-Personen die blosse Zahl («216»). Leser nehmen beide Formen an und vergleichen über
 * `equals()`; Schreiber schreiben nur noch die neue Form. Die Systemkennung `__hub_peer__` ist keine Person und
 * wird von `normalize()` nicht durchgelassen (Aufrufer prüfen sie vorher wörtlich).
 *
 * Diese Datei liegt zweimal vor und ist identisch: `gemeinde/shared/src/HubPersonKey.php` (Apps) und
 * `gemeinde/hub/src/HubPersonKey.php` (Hub). Änderungen immer an beiden.
 */
final class HubPersonKey
{
    /** Gültige, bereits normalisierte Kennung (für andere Module). Kein `:` im Namen. */
    public const PATTERN = '/^(ct:[1-9][0-9]{0,11}|u:[A-Za-z0-9._@+-]{1,128})\z/';

    /** Eingabe in die kanonische Form bringen; ungültig → ''. */
    public static function normalize(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 140) {
            return '';
        }
        if (preg_match('/^[1-9][0-9]{0,11}\z/', $key)) {
            return 'ct:' . $key;
        }
        return preg_match(self::PATTERN, $key) ? $key : '';
    }

    /** Gleiche Person? Beide Seiten müssen gültig sein; `u:`-Namen vergleichen ohne Grossschreibung. */
    public static function equals(string $a, string $b): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === '' || $b === '') {
            return false;
        }
        return $a === $b || ($a[0] === 'u' && strtolower($a) === strtolower($b));
    }

    public static function isCt(string $key): bool
    {
        return str_starts_with(self::normalize($key), 'ct:');
    }

    public static function isLocal(string $key): bool
    {
        return str_starts_with(self::normalize($key), 'u:');
    }

    /** CT-Personennummer, 0 wenn keine CT-Kennung. */
    public static function ctId(string $key): int
    {
        $n = self::normalize($key);
        return str_starts_with($n, 'ct:') ? (int)substr($n, 3) : 0;
    }

    /** Alte Zahlform für Kompatibilität (`ct:216` → `216`); `u:` unverändert; ungültig → ''. */
    public static function legacy(string $key): string
    {
        $n = self::normalize($key);
        return str_starts_with($n, 'ct:') ? substr($n, 3) : $n;
    }

    /** Kennung aus CT-Nummer. */
    public static function fromCt(int $id): string
    {
        return $id > 0 ? 'ct:' . $id : '';
    }

    /** Kennung aus lokalem Benutzernamen; '' bei ungültigem Namen. */
    public static function fromLocal(string $name): string
    {
        return self::normalize('u:' . trim($name));
    }

    /** Kennung aus Benutzersatz (person_id > 0 → ct:, sonst u:<username>). */
    public static function fromUser(array $user): string
    {
        $id = (int)($user['person_id'] ?? 0);
        return $id > 0 ? self::fromCt($id) : self::fromLocal((string)($user['username'] ?? ''));
    }

    /**
     * Schlüssel einer Zuordnung (Kennung → Daten) normalisieren. Gültige Kennungen werden zu `ct:`/`u:`;
     * andere Schlüssel (z. B. `__hub_peer__`) bleiben unverändert. Bei Doppelten gewinnt die neue Form.
     *
     * @param array<string,mixed> $map
     * @return array<string,mixed>
     */
    public static function normalizeKeys(array $map): array
    {
        $out = [];
        foreach ($map as $k => $v) {
            $n = self::normalize((string)$k);
            $nk = $n !== '' ? $n : (string)$k;
            if ($n !== '' && $nk !== (string)$k && array_key_exists($nk, $map)) {
                continue; // neue Form liegt schon vor und gewinnt
            }
            $out[$nk] = $v;
        }
        return $out;
    }

    /**
     * Liste von Kennungen normalisieren und Doppelte entfernen; Ungültiges bleibt unverändert.
     *
     * @param array<int,mixed> $list
     * @return list<string>
     */
    public static function normalizeList(array $list): array
    {
        $out = [];
        foreach ($list as $v) {
            $s = (string)$v;
            $n = self::normalize($s);
            $out[$n !== '' ? $n : $s] = true;
        }
        return array_map('strval', array_keys($out));
    }
}
