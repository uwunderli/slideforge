<?php
/**
 * Leichte Job-Queue unter data/jobs/ + CLI-Spawn (kein schweres I/O im FPM).
 */
class BackgroundJob
{
    public static function rootDir(): string
    {
        $dir = DATA_PATH . '/jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        return $dir;
    }

    public static function jobDir(string $jobId): string
    {
        return self::rootDir() . '/' . $jobId;
    }

    public static function metaPath(string $jobId): string
    {
        return self::jobDir($jobId) . '/job.json';
    }

    public static function lockPath(string $jobId): string
    {
        return self::jobDir($jobId) . '/job.lock';
    }

    /** @return array<string,mixed> */
    public static function create(string $type, string $userId, array $payload = []): array
    {
        $jobId = bin2hex(random_bytes(16));
        $dir = self::jobDir($jobId);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Job-Verzeichnis konnte nicht angelegt werden.');
        }
        $job = [
            'id' => $jobId,
            'type' => $type,
            'user_id' => $userId,
            'status' => 'queued',
            'created_at' => gmdate('c'),
            'started_at' => null,
            'finished_at' => null,
            'error' => null,
            'payload' => $payload,
            'result' => null,
        ];
        self::write($jobId, $job);
        return $job;
    }

    /** @return array<string,mixed>|null */
    public static function read(string $jobId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            return null;
        }
        $path = self::metaPath($jobId);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    /** @param array<string,mixed> $job */
    public static function write(string $jobId, array $job): void
    {
        $path = self::metaPath($jobId);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        file_put_contents(
            $path,
            json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            LOCK_EX
        );
    }

    /** @param array<string,mixed> $patch */
    public static function patch(string $jobId, array $patch): ?array
    {
        $job = self::read($jobId);
        if ($job === null) {
            return null;
        }
        $job = array_merge($job, $patch);
        self::write($jobId, $job);
        return $job;
    }

    /**
     * Non-blocking Lock; false = anderer Worker hält den Job.
     * @return resource|false
     */
    public static function tryLock(string $jobId)
    {
        $path = self::lockPath($jobId);
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            return false;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return false;
        }
        return $fh;
    }

    public static function cliPhpBin(): string
    {
        $bin = (string)PHP_BINARY;
        if ($bin !== '' && is_executable($bin) && !str_contains(strtolower($bin), 'php-fpm')) {
            return $bin;
        }
        $candidates = [
            '/data/www/clipforge/runtime/bin/php',
            dirname(BASE_PATH) . '/clipforge/runtime/bin/php',
            '/usr/local/bin/php',
            '/usr/bin/php',
            'php',
        ];
        foreach ($candidates as $cand) {
            if ($cand === 'php') {
                return $cand;
            }
            if (is_executable($cand)) {
                return $cand;
            }
        }
        return 'php';
    }

    /** @param list<string> $args */
    public static function spawn(string $scriptAbs, array $args = [], ?string $logFile = null): bool
    {
        if (!is_file($scriptAbs)) {
            return false;
        }
        if (!function_exists('exec') || in_array('exec', array_map('strtolower', array_map('trim', explode(',', (string)ini_get('disable_functions')))), true)) {
            return false;
        }
        $php = self::cliPhpBin();
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($scriptAbs);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg((string)$a);
        }
        $log = $logFile !== null && $logFile !== ''
            ? $logFile
            : (self::rootDir() . '/' . basename($scriptAbs, '.php') . '.log');
        $logDir = dirname($log);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $cmd .= ' >> ' . escapeshellarg($log) . ' 2>&1 &';
        @exec($cmd);
        return true;
    }

    /** Öffentliche Status-Antwort ohne interne Payload-Secrets. */
    public static function publicStatus(array $job): array
    {
        return [
            'id' => $job['id'] ?? '',
            'type' => $job['type'] ?? '',
            'status' => $job['status'] ?? 'unknown',
            'error' => $job['error'] ?? null,
            'result' => $job['result'] ?? null,
            'created_at' => $job['created_at'] ?? null,
            'finished_at' => $job['finished_at'] ?? null,
        ];
    }
}
