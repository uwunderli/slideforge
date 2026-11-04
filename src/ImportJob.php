<?php
/**
 * Datei-Import als CLI-Job (Upload unter data/jobs/{id}/upload).
 */
class ImportJob
{
    public static function cleanupDir(string $dir): void
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

    /** @param list<array<string,mixed>> $owned */
    public static function resolveSectionId(string $userId, array $owned, string $sectionId): string
    {
        $sectionId = trim($sectionId);
        $sections = Dashboard::ensureSections($userId, $owned);
        $ids = array_map(fn($s) => (string)$s['id'], $sections);
        if ($sectionId !== '' && in_array($sectionId, $ids, true)) {
            return $sectionId;
        }
        foreach ($sections as $s) {
            if (!empty($s['is_default'])) {
                return (string)$s['id'];
            }
        }
        return $sections[0]['id'] ?? '';
    }

    public static function assignSection(string $userId, string $presentationId, string $sectionId = ''): void
    {
        [$owned] = Presentation::listForUser($userId);
        $resolved = self::resolveSectionId($userId, $owned, $sectionId);
        if ($resolved === '') {
            return;
        }
        try {
            Dashboard::moveItem($userId, $presentationId, $resolved, 0);
        } catch (Throwable $e) {
            // Ungültiger Bereich — Präsentation bleibt beim nächsten Dashboard-Besuch im Standard-Bereich.
        }
    }

    /**
     * Speichert Upload und startet Worker.
     *
     * @param array{name:string,tmp_name:string,error:int} $file
     * @return array<string,mixed>
     */
    public static function enqueueFromUpload(
        string $userId,
        array $file,
        string $sectionId = '',
        string $layoutSetId = '',
        array $logosOptions = []
    ): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new RuntimeException(t('import.no_file'));
        }
        $origName = (string)($file['name'] ?? 'upload.bin');
        $job = BackgroundJob::create('import', $userId, [
            'original_name' => $origName,
            'dashboard_section_id' => $sectionId,
            'layout_set_id' => $layoutSetId,
            'logos_options' => $logosOptions,
        ]);
        $dest = BackgroundJob::jobDir($job['id']) . '/upload.bin';
        if (!@move_uploaded_file($file['tmp_name'], $dest) && !@rename($file['tmp_name'], $dest) && !@copy($file['tmp_name'], $dest)) {
            BackgroundJob::patch($job['id'], [
                'status' => 'failed',
                'finished_at' => gmdate('c'),
                'error' => t('import.no_file'),
            ]);
            throw new RuntimeException(t('import.no_file'));
        }
        @chmod($dest, 0660);

        $script = BASE_PATH . '/scripts/run-import.php';
        $log = BackgroundJob::jobDir($job['id']) . '/worker.log';
        if (!BackgroundJob::spawn($script, [$job['id']], $log)) {
            BackgroundJob::patch($job['id'], [
                'status' => 'failed',
                'finished_at' => gmdate('c'),
                'error' => 'CLI-Worker konnte nicht gestartet werden.',
            ]);
            throw new RuntimeException('Import-Worker konnte nicht gestartet werden.');
        }
        return $job;
    }

    public static function run(string $jobId): void
    {
        $job = BackgroundJob::read($jobId);
        if ($job === null || ($job['type'] ?? '') !== 'import') {
            throw new RuntimeException('Import-Job nicht gefunden.');
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
            $userId = (string)($job['user_id'] ?? '');
            $uploadPath = BackgroundJob::jobDir($jobId) . '/upload.bin';
            $origName = strtolower((string)($payload['original_name'] ?? 'upload.bin'));
            $sectionId = (string)($payload['dashboard_section_id'] ?? '');
            $layoutSetId = trim((string)($payload['layout_set_id'] ?? ''));
            $logosOptions = is_array($payload['logos_options'] ?? null) ? $payload['logos_options'] : [];

            if ($userId === '' || !is_file($uploadPath)) {
                throw new RuntimeException(t('import.no_file'));
            }

            $user = Auth::findById($userId);
            if (!$user) {
                throw new RuntimeException(t('import.no_file'));
            }
            $logosImporterEnabled = Auth::logosImporterEnabled($user);
            $result = self::processFile(
                $userId,
                $uploadPath,
                $origName,
                $sectionId,
                $layoutSetId,
                $logosImporterEnabled,
                $logosOptions
            );

            BackgroundJob::patch($jobId, [
                'status' => 'done',
                'finished_at' => gmdate('c'),
                'error' => null,
                'result' => $result,
            ]);
            @unlink($uploadPath);
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

    /**
     * @return array{presentation_id:string,redirect:string,warnings:list<string>}
     */
    public static function processFile(
        string $userId,
        string $filePath,
        string $origName,
        string $sectionId,
        string $layoutSetId,
        bool $logosImporterEnabled,
        array $logosOptions = []
    ): array {
        $warnings = [];
        $htmlContent = null;
        $zipAssetsDir = null;
        $zipTmpDir = null;

        try {
            if (str_ends_with($origName, '.pptx')) {
                $newId = Presentation::create($userId, trim((string)pathinfo($origName, PATHINFO_FILENAME)) . t('import.title_suffix'), DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT);
                try {
                    $result = PptxImporter::import($filePath, $newId);
                } catch (Throwable $e) {
                    Presentation::delete($newId);
                    throw new RuntimeException(t('import.pptx_failed', ['error' => $e->getMessage()]));
                }
                Presentation::updateMeta($newId, ['width' => $result['width'], 'height' => $result['height']]);
                $slides = $result['slides'] ?: [Presentation::defaultSlide()];
                Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $slides]);
                if (!empty($result['warnings'])) {
                    $warnings = $result['warnings'];
                }
                self::assignSection($userId, $newId, $sectionId);
                return self::okResult($newId, $warnings);
            }

            if (str_ends_with($origName, '.xlsx')) {
                $newId = Presentation::create($userId, trim((string)pathinfo($origName, PATHINFO_FILENAME)) . t('import.title_suffix'), DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT);
                try {
                    $result = XlsxImporter::import($filePath, $newId);
                } catch (Throwable $e) {
                    Presentation::delete($newId);
                    throw new RuntimeException(t('import.xlsx_failed', ['error' => $e->getMessage()]));
                }
                Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $result['slides'] ?: [Presentation::defaultSlide()]]);
                self::assignSection($userId, $newId, $sectionId);
                return self::okResult($newId, $result['warnings']);
            }

            if (str_ends_with($origName, '.odp')) {
                $newId = Presentation::create($userId, trim((string)pathinfo($origName, PATHINFO_FILENAME)) . t('import.title_suffix'), DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT);
                try {
                    $result = OdpImporter::import($filePath, $newId);
                } catch (Throwable $e) {
                    Presentation::delete($newId);
                    throw new RuntimeException(t('import.odp_failed', ['error' => $e->getMessage()]));
                }
                Presentation::updateMeta($newId, ['width' => $result['width'], 'height' => $result['height']]);
                $slides = $result['slides'] ?: [Presentation::defaultSlide()];
                Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $slides]);
                if (!empty($result['warnings'])) {
                    $warnings = $result['warnings'];
                }
                self::assignSection($userId, $newId, $sectionId);
                return self::okResult($newId, $warnings);
            }

            if (str_ends_with($origName, '.pdf')) {
                if (!class_exists('Imagick')) {
                    throw new RuntimeException(t('import.pdf_no_imagick'));
                }
                $pdfTmpDir = sys_get_temp_dir() . '/sfimportpdf_' . bin2hex(random_bytes(6));
                mkdir($pdfTmpDir, 0770, true);

                try {
                    $probe = new Imagick();
                    $probe->pingImage($filePath);
                    $pageCount = max(1, $probe->getNumberImages());
                    $probe->clear();
                } catch (Throwable $e) {
                    self::cleanupDir($pdfTmpDir);
                    throw new RuntimeException(t('import.pdf_read_failed', ['error' => $e->getMessage()]));
                }
                if ($pageCount > 200) {
                    self::cleanupDir($pdfTmpDir);
                    throw new RuntimeException(t('import.pdf_too_many_pages'));
                }

                $pageImages = [];
                $pageWidth = DEFAULT_SLIDE_WIDTH;
                $pageHeight = DEFAULT_SLIDE_HEIGHT;
                for ($p = 0; $p < $pageCount; $p++) {
                    $im = new Imagick();
                    $im->setResolution(150, 150);
                    $im->readImage($filePath . '[' . $p . ']');
                    $im->setImageFormat('png');
                    $im->setImageBackgroundColor('white');
                    $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                    if ($p === 0) {
                        $pageWidth = $im->getImageWidth();
                        $pageHeight = $im->getImageHeight();
                    }
                    $pagePath = $pdfTmpDir . '/page' . ($p + 1) . '.png';
                    $im->writeImage($pagePath);
                    $im->clear();
                    $pageImages[] = $pagePath;
                }

                $title = trim((string)pathinfo($origName, PATHINFO_FILENAME)) . t('import.title_suffix');
                $newId = Presentation::create($userId, $title ?: t('import.default_title'), $pageWidth, $pageHeight);
                $assetsDir = Presentation::dir($newId) . '/assets';

                $slides = [];
                foreach ($pageImages as $i => $srcPath) {
                    $filename = 'pdfpage' . ($i + 1) . '_' . bin2hex(random_bytes(4)) . '.png';
                    copy($srcPath, $assetsDir . '/' . $filename);
                    $slides[] = [
                        'id' => 'slide' . bin2hex(random_bytes(4)),
                        'background' => ['type' => 'color', 'value' => '#ffffff'],
                        'objects' => [[
                            'id' => 'o' . bin2hex(random_bytes(4)),
                            'type' => 'image', 'x' => 0, 'y' => 0, 'w' => $pageWidth, 'h' => $pageHeight,
                            'rotation' => 0, 'opacity' => 1,
                            'src' => 'asset.php?id=' . urlencode($newId) . '&file=' . urlencode($filename),
                        ]],
                        'notes' => '', 'transition' => 'slide', 'autoAdvance' => 0,
                    ];
                }
                Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $slides]);
                self::cleanupDir($pdfTmpDir);
                self::assignSection($userId, $newId, $sectionId);
                return self::okResult($newId, $warnings);
            }

            if (str_ends_with($origName, '.chs') || LayoutSet::isLayoutSetArchiveFile($filePath)) {
                $newId = LayoutSet::importArchive($userId, $filePath);
                return self::okResult($newId, $warnings);
            }

            if (str_ends_with($origName, '.zip')) {
                $zip = new ZipArchive();
                if ($zip->open($filePath) !== true) {
                    throw new RuntimeException(t('import.zip_open_failed'));
                }
                $zipTmpDir = sys_get_temp_dir() . '/sfimport_' . bin2hex(random_bytes(6));
                mkdir($zipTmpDir, 0770, true);
                $zip->extractTo($zipTmpDir);
                $zip->close();
                $indexPath = $zipTmpDir . '/index.html';
                if (!is_file($indexPath)) {
                    throw new RuntimeException(t('import.no_index_html'));
                }
                $htmlContent = file_get_contents($indexPath);
                if (is_dir($zipTmpDir . '/assets')) {
                    $zipAssetsDir = $zipTmpDir . '/assets';
                }
            } elseif (str_ends_with($origName, '.html') || str_ends_with($origName, '.htm')) {
                $htmlContent = file_get_contents($filePath);
            } else {
                throw new RuntimeException(t('import.unsupported_type'));
            }

            if (LogosSermonImporter::isLogosExport($htmlContent)) {
                if (!$logosImporterEnabled) {
                    throw new RuntimeException(t('import.logos_disabled'));
                }
                if ($layoutSetId === '' || !LayoutSet::isLayoutSet($layoutSetId)) {
                    throw new RuntimeException(strip_tags(t('import.layout_set_required')));
                }
                $result = LogosSermonImporter::import($htmlContent, $layoutSetId, $logosOptions);
                $title = trim($result['title']) . t('import.title_suffix');
                $newId = Presentation::create($userId, $title ?: t('import.default_title'), $result['width'], $result['height']);
                if (!empty($result['layout_set_id'])) {
                    Presentation::updateMeta($newId, ['layout_set_id' => $result['layout_set_id']]);
                }
                if (!empty($result['footer_text'])) {
                    Presentation::updateMeta($newId, ['footer_text' => $result['footer_text']]);
                }
                $slides = $result['slides'] ?: [Presentation::defaultSlide()];
                Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $slides]);
                if (!empty($result['warnings'])) {
                    $warnings = $result['warnings'];
                }
                self::assignSection($userId, $newId, $sectionId);
                if ($zipTmpDir) {
                    self::cleanupDir($zipTmpDir);
                }
                return self::okResult($newId, $warnings);
            }

            if (!preg_match('/<script type="application\/json" id="slideforge-source-data">(.*?)<\/script>/s', $htmlContent, $m)) {
                throw new RuntimeException(t('import.no_source_data'));
            }
            $data = json_decode($m[1], true);
            if (!is_array($data) || empty($data['meta']) || !isset($data['slides'])) {
                throw new RuntimeException(t('import.corrupt_data'));
            }

            $meta = $data['meta'];
            $width = max(100, (int)($meta['width'] ?? DEFAULT_SLIDE_WIDTH));
            $height = max(100, (int)($meta['height'] ?? DEFAULT_SLIDE_HEIGHT));
            $title = trim((string)($meta['title'] ?? t('import.default_title'))) . t('import.title_suffix');

            $newId = Presentation::create($userId, $title, $width, $height);
            $restored = Exporter::metaFromReimport($meta, $userId);
            if ($restored['fields'] !== []) {
                Presentation::updateMeta($newId, $restored['fields']);
            }
            if ($restored['warnings'] !== []) {
                $warnings = array_merge($warnings, $restored['warnings']);
            }

            $slidesData = ['slides' => $data['slides']];
            $slidesData = Exporter::importAssets($slidesData, $newId, $zipAssetsDir);
            if (empty($slidesData['slides'])) {
                $slidesData['slides'] = [Presentation::defaultSlide()];
            }
            Storage::write(Presentation::dir($newId) . '/slides.json', $slidesData);

            if ($zipTmpDir) {
                self::cleanupDir($zipTmpDir);
            }

            self::assignSection($userId, $newId, $sectionId);
            return self::okResult($newId, $warnings);
        } catch (Throwable $e) {
            if ($zipTmpDir && is_dir($zipTmpDir)) {
                self::cleanupDir($zipTmpDir);
            }
            throw $e;
        }
    }

    /** @param list<string> $warnings @return array{presentation_id:string,redirect:string,warnings:list<string>} */
    private static function okResult(string $newId, array $warnings): array
    {
        return [
            'presentation_id' => $newId,
            'redirect' => 'editor.php?id=' . urlencode($newId),
            'warnings' => array_values($warnings),
        ];
    }
}
