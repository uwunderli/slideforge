<?php
/**
 * Kapselt das dateibasierte Speichern/Lesen von JSON-Daten.
 * Nutzt flock() um gleichzeitige Schreibzugriffe (Multiuser!) sauber zu serialisieren.
 *
 * Zwei Fallen, die am 4.–6.11.2026 den Betrieb auf Biel lahmlegten (Selbst-Deadlock, 21 php-fpm-Kinder in flock):
 *  - flock ist an die offene Dateibeschreibung gebunden: öffnete derselbe Request eine Datei, die er schon mit LOCK_EX hielt
 *    (z. B. ein Lesezugriff im update()-Callback), ein zweites Mal und sperrte sie, wartete er ewig auf sich selbst.
 *    Darum merkt sich der Prozess die gehaltenen Dateien und liest/schreibt sie dann ohne zweite Sperre.
 *  - flock() ohne Grenze wartet endlos, wenn ein anderer Prozess hängt (und mit ihm die Sitzungsdatei der Browser-Sitzung).
 *    Darum wartet jede Sperre höchstens LOCK_WAIT Sekunden und bricht dann mit einer Ausnahme ab.
 */
class Storage
{
    /** Längste Wartezeit auf eine Dateisperre (Sekunden). */
    private const LOCK_WAIT = 15;

    /** @var array<string,true> Dateien, die dieser Prozess gerade mit LOCK_EX hält (Schlüssel: realpath) */
    private static array $held = [];

    private static function key(string $path): string
    {
        $real = @realpath($path);
        return $real !== false ? $real : $path;
    }

    /** Sperre mit Zeitlimit holen; wirft RuntimeException, wenn sie nicht frei wird. @param resource $fp */
    private static function lock($fp, int $op, string $path): void
    {
        $deadline = microtime(true) + self::LOCK_WAIT;
        do {
            $wouldBlock = 0;
            if (flock($fp, $op | LOCK_NB, $wouldBlock)) {
                return;
            }
            if (!$wouldBlock) {
                break; // echter Fehler, nicht «belegt»
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('Datei ist gesperrt (Zeitlimit ' . self::LOCK_WAIT . ' s): ' . basename($path));
    }

    /** Liest eine JSON-Datei und gibt ein assoziatives Array zurück (oder $default). */
    public static function read(string $path, $default = [])
    {
        if (!file_exists($path)) {
            return $default;
        }
        if (isset(self::$held[self::key($path)])) {
            // Diesen Pfad hält dieser Request schon exklusiv (update/write): ohne zweite Sperre lesen.
            $content = (string)@file_get_contents($path);
        } else {
            $fp = fopen($path, 'r');
            if (!$fp) {
                return $default;
            }
            try {
                self::lock($fp, LOCK_SH, $path);
                $content = stream_get_contents($fp);
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        $data = json_decode((string)$content, true);
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
        $key = self::key($path);
        $own = isset(self::$held[$key]); // schon von diesem Request gehalten (update() ruft write()): nicht noch einmal sperren
        try {
            if (!$own) {
                self::lock($fp, LOCK_EX, $path);
                self::$held[$key] = true;
            }
            ftruncate($fp, 0);
            rewind($fp);
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            fwrite($fp, $json);
            fflush($fp);
        } finally {
            if (!$own) {
                unset(self::$held[$key]);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
        return true;
    }

    /**
     * Führt eine Lese-Änder-Schreib-Operation atomar aus (verhindert Race Conditions
     * wenn zwei User gleichzeitig z.B. die ACL derselben Präsentation ändern).
     * $callback(array $data): array
     * Im Callback darf dieselbe Datei gelesen werden (liefert den Stand vor der Änderung), ohne dass sich der Request selbst sperrt.
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
        $key = self::key($path);
        $own = isset(self::$held[$key]); // verschachteltes update() derselben Datei im selben Request
        try {
            if (!$own) {
                self::lock($fp, LOCK_EX, $path);
                self::$held[$key] = true;
            }
            $content = stream_get_contents($fp);
            $data = json_decode((string)$content, true);
            if ($data === null) {
                $data = $default;
            }

            $data = $callback($data);

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        } finally {
            if (!$own) {
                unset(self::$held[$key]);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }

        return $data;
    }

    /** Erzeugt eine kurze, URL-sichere, praktisch eindeutige ID. */
    public static function generateId(int $bytes = 8): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
