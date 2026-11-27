<?php

/**
 * Adressen aus Eingabefeldern richtigstellen, **bevor** sie geprüft werden (Urs, 27.11.2026: «wenn ich hub.bkbiel.ch eingebe dann kommt
 * automatisch ein https:// vorne hin, wenn ich das falsch schreibe schreibst du es richtig. Das ist so ein Standart»).
 *
 * Kopie aus dem Hub (src/HubUrl.php, Stand Hub 2.33.11) für die FolienSchmiede (3.8.3); Gegenstück im Browser: Hub `assets/js/hub-url.js` (`HubUrl.normalize`, gleiche Regeln). Jedes URL-Feld im Hub nimmt diesen Baustein
 * (DESIGN.md, «Adressfelder»). Die normale Prüfung bleibt danach bestehen: was ungültig ist, bleibt ungültig.
 *
 * Regeln: Leerzeichen raus; fehlt das Schema → `https://`; Tippfehler am Schema (`hhttps://`, `htps://`, `https//`, `https:/`, `https:///`,
 * `http//`, `Https://` …) richtigstellen; Host klein. Ein ausdrückliches `http://` bleibt `http://`. Leere Eingabe bleibt leer.
 */
final class HubUrl
{
    public static function normalize(string $url): string
    {
        $u = preg_replace('/\s+/u', '', $url) ?? '';
        if ($u === '') {
            return '';
        }
        // Pfad auf dem eigenen Hub (`/api/…`) oder Platzhalter (`{hub}/…`): nicht anfassen.
        if (preg_match('#^(/[^/]|\{)#', $u)) {
            return $u;
        }
        // Schema mit Tippfehlern: ein Wort aus h/t/p/s (mind. «htp»/«ttp»), gefolgt von beliebig vielen «:» und «/».
        if (preg_match('#^([a-z]{2,8})(:*)(/*)(.*)$#is', $u, $m) && ($m[2] !== '' || $m[3] !== '')) {
            $schema = strtolower($m[1]);
            if (preg_match('/^h*t+p+s+$/', $schema) || preg_match('/^h*t+p+$/', $schema) || in_array($schema, ['htps', 'htts', 'hhtps', 'ttps'], true)) {
                $u = (str_ends_with($schema, 's') ? 'https' : 'http') . '://' . $m[4];
            } elseif ($m[2] !== '' && $m[3] === '//') {
                return $u;   // anderes, bewusst gesetztes Schema (z. B. ftp://) nicht anfassen
            }
        } elseif (preg_match('#^//#', $u)) {
            $u = 'https:' . $u;
        }
        if (!preg_match('#^https?://#i', $u)) {
            $u = 'https://' . ltrim($u, ':/');
        }
        // Schema und Host klein, Pfad unverändert.
        if (preg_match('#^(https?)://([^/?\#]*)(.*)$#is', $u, $m)) {
            $u = strtolower($m[1]) . '://' . strtolower($m[2]) . $m[3];
        }
        return $u;
    }

    /** Die üblichen Adressfelder eines Formulars richtigstellen (nur nicht-leere Zeichenketten). */
    public static function normalizePost(array $felder = ['url', 'baseUrl', 'hubUrl', 'ablage']): void
    {
        foreach ($felder as $k) {
            if (isset($_POST[$k]) && is_string($_POST[$k]) && trim($_POST[$k]) !== '') {
                $_POST[$k] = self::normalize($_POST[$k]);
            }
        }
    }
}
