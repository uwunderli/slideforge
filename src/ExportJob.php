<?php
/**
 * Präsentations-Export als CLI-Job (Ausgabe unter data/jobs/{id}/).
 */
class ExportJob
{
    private const FORMATS = ['html', 'zip', 'pptx', 'odp'];

    public static function isFormat(string $format): bool
    {
        return in_array($format, self::FORMATS, true);
    }

    /** @return array<string,mixed> */
    public static function enqueue(string $userId, string $presentationId, string $format): array
    {
        if (!self::isFormat($format)) {
            throw new InvalidArgumentException('Ungültiges Exportformat.');
        }
        if (!Presentation::canView($presentationId, $userId)) {
            throw new RuntimeException(t('export.no_access'));
        }
        $job = BackgroundJob::create('export', $userId, [
            'presentation_id' => $presentationId,
            'format' => $format,
        ]);
        $script = BASE_PATH . '/scripts/run-export.php';
        $log = BackgroundJob::jobDir($job['id']) . '/worker.log';
        if (!BackgroundJob::spawn($script, [$job['id']], $log)) {
            BackgroundJob::patch($job['id'], [
                'status' => 'failed',
                'finished_at' => gmdate('c'),
                'error' => 'CLI-Worker konnte nicht gestartet werden.',
            ]);
            throw new RuntimeException('Export-Worker konnte nicht gestartet werden.');
        }
        return $job;
    }

    public static function run(string $jobId): void
    {
        $job = BackgroundJob::read($jobId);
        if ($job === null || ($job['type'] ?? '') !== 'export') {
            throw new RuntimeException('Export-Job nicht gefunden.');
        }
        $lock = BackgroundJob::tryLock($jobId);
        if ($lock === false) {
            return;
        }
        try {
            if (($job['status'] ?? '') === 'done') {
                return;
            }
            BackgroundJob::patch($jobId, [
                'status' => 'running',
                'started_at' => gmdate('c'),
                'error' => null,
            ]);

            @ini_set('memory_limit', '1024M');
            @set_time_limit(600);

            $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
            $id = (string)($payload['presentation_id'] ?? '');
            $format = (string)($payload['format'] ?? '');
            $userId = (string)($job['user_id'] ?? '');
            if ($id === '' || !self::isFormat($format)) {
                throw new RuntimeException('Ungültige Job-Parameter.');
            }
            if (!Presentation::canView($id, $userId)) {
                throw new RuntimeException(t('export.no_access'));
            }

            $meta = Presentation::getMeta($id);
            if (!$meta) {
                throw new RuntimeException(t('export.no_access'));
            }
            $slidesData = Presentation::getSlides($id);
            $filenameBase = Exporter::sanitizeFilename($meta['title']);
            $outDir = BackgroundJob::jobDir($jobId);

            if ($format === 'pptx') {
                $built = PptxExporter::build($meta, $slidesData, $id);
                $outName = $filenameBase . '.pptx';
                $outPath = $outDir . '/' . $outName;
                if (!@rename($built, $outPath) && !@copy($built, $outPath)) {
                    throw new RuntimeException('PPTX konnte nicht gespeichert werden.');
                }
                @unlink($built);
                $mime = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            } elseif ($format === 'odp') {
                $built = OdpExporter::build($meta, $slidesData, $id);
                $outName = $filenameBase . '.odp';
                $outPath = $outDir . '/' . $outName;
                if (!@rename($built, $outPath) && !@copy($built, $outPath)) {
                    throw new RuntimeException('ODP konnte nicht gespeichert werden.');
                }
                @unlink($built);
                $mime = 'application/vnd.oasis.opendocument.presentation';
            } elseif ($format === 'zip') {
                $usedFiles = [];
                $resolved = Exporter::resolveAssets($slidesData, $id, 'zip', $usedFiles);
                $html = Exporter::buildStandaloneHtml($meta, $resolved);
                $tmpDir = $outDir . '/_ziptmp';
                if (!is_dir($tmpDir)) {
                    mkdir($tmpDir, 0770, true);
                }
                mkdir($tmpDir . '/assets', 0770, true);
                file_put_contents($tmpDir . '/index.html', $html);
                foreach ($usedFiles as $filename => $srcPath) {
                    copy($srcPath, $tmpDir . '/assets/' . $filename);
                }
                $outName = $filenameBase . '.zip';
                $outPath = $outDir . '/' . $outName;
                $zip = new ZipArchive();
                if ($zip->open($outPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException(t('export.zip_failed'));
                }
                $zip->addFile($tmpDir . '/index.html', 'index.html');
                foreach (glob($tmpDir . '/assets/*') ?: [] as $f) {
                    $zip->addFile($f, 'assets/' . basename($f));
                }
                $zip->close();
                self::cleanupDir($tmpDir);
                $mime = 'application/zip';
            } else {
                $resolved = Exporter::resolveAssets($slidesData, $id, 'inline');
                $html = Exporter::buildStandaloneHtml($meta, $resolved);
                $outName = $filenameBase . '.html';
                $outPath = $outDir . '/' . $outName;
                file_put_contents($outPath, $html);
                $mime = 'text/html; charset=utf-8';
            }

            BackgroundJob::patch($jobId, [
                'status' => 'done',
                'finished_at' => gmdate('c'),
                'error' => null,
                'result' => [
                    'filename' => $outName,
                    'mime' => $mime,
                    'size' => is_file($outPath) ? filesize($outPath) : 0,
                ],
            ]);
        } catch (Throwable $e) {
            BackgroundJob::patch($jobId, [
                'status' => 'failed',
                'finished_at' => gmdate('c'),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Absoluter Pfad der fertigen Datei oder null. */
    public static function outputPath(string $jobId): ?string
    {
        $job = BackgroundJob::read($jobId);
        if ($job === null || ($job['status'] ?? '') !== 'done') {
            return null;
        }
        $name = (string)(($job['result']['filename'] ?? ''));
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..')) {
            return null;
        }
        $path = BackgroundJob::jobDir($jobId) . '/' . $name;
        return is_file($path) ? $path : null;
    }

    private static function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
