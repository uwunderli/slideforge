<?php
/**
 * Kapselt das dateibasierte Speichern/Lesen von JSON-Daten.
 * Nutzt flock() um gleichzeitige Schreibzugriffe (Multiuser!) sauber zu serialisieren.
 */
class Storage
{
    /** Liest eine JSON-Datei und gibt ein assoziatives Array zurück (oder $default). */
    public static function read(string $path, $default = [])
    {
        if (!file_exists($path)) {
            return $default;
        }
        $fp = fopen($path, 'r');
        if (!$fp) {
            return $default;
        }
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($content, true);
        return $data === null ? $default : $data;
    }

    /** Schreibt Daten atomar als JSON in eine Datei (mit exklusivem Lock). */
    public static function write(string $path, $data): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }

        $fp = @fopen($path, 'c+');
        if (!$fp) {
            throw new RuntimeException(
                "Kann nicht in \"$path\" schreiben (Berechtigungsproblem). " .
                "Bitte prüfen, ob der PHP-Prozess Schreibrechte auf das data/-Verzeichnis hat."
            );
        }
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    /**
     * Führt eine Lese-Änder-Schreib-Operation atomar aus (verhindert Race Conditions
     * wenn zwei User gleichzeitig z.B. die ACL derselben Präsentation ändern).
     * $callback(array $data): array
     */
    public static function update(string $path, callable $callback, $default = [])
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        if (!file_exists($path)) {
            @file_put_contents($path, json_encode($default));
        }

        $fp = @fopen($path, 'c+');
        if (!$fp) {
            throw new RuntimeException(
                "Kann nicht in \"$path\" schreiben (Berechtigungsproblem). " .
                "Bitte prüfen, ob der PHP-Prozess Schreibrechte auf das data/-Verzeichnis hat."
            );
        }
        flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $data = json_decode($content, true);
        if ($data === null) {
            $data = $default;
        }

        $data = $callback($data);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $data;
    }

    /** Erzeugt eine kurze, URL-sichere, praktisch eindeutige ID. */
    public static function generateId(int $bytes = 8): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
