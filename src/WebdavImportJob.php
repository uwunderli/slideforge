<?php
/**
 * WebDAV-Medienimport als CLI-Job.
 */
class WebdavImportJob
{
    /** @return array<string,mixed> */
    public static function enqueue(
        string $userId,
        string $presentationId,
        string $driveId,
        string $path,
        int $declaredSize = 0
    ): array {
        if (!Presentation::exists($presentationId)) {
            throw new RuntimeException(t('webdav.error_not_found'));
        }
        $perm = Presentation::checkPermission($presentationId, $userId);
        if (!$perm || !in_array($perm, ['owner', 'edit'], true)) {
            throw new RuntimeException(t('webdav.error_edit'));
        }
        $drive = Auth::getWebdavDriveCredentials($userId, $driveId);
        if (!$drive) {
            throw new RuntimeException(t('webdav.error_drive_not_found'));
        }
        $path = WebdavClient::normalizeBrowsePath($path);
        if ($path === '') {
            throw new RuntimeException(t('webdav.error_invalid_path'));
        }

        $job = BackgroundJob::create('webdav_import', $userId, [
            'presentation_id' => $presentationId,
            'drive_id' => $driveId,
            'path' => $path,
            'size' => max(0, $declaredSize),
        ]);
        $script = BASE_PATH . '/scripts/run-webdav-import.php';
        $log = BackgroundJob::jobDir($job['id']) . '/worker.log';
        if (!BackgroundJob::spawn($script, [$job['id']], $log)) {
            BackgroundJob::patch($job['id'], [
                'status' => 'failed',
                'finished_at' => gmdate('c'),
                'error' => 'CLI-Worker konnte nicht gestartet werden.',
            ]);
            throw new RuntimeException('WebDAV-Worker konnte nicht gestartet werden.');
        }
        return $job;
    }

    public static function run(string $jobId): void
    {
        $job = BackgroundJob::read($jobId);
        if ($job === null || ($job['type'] ?? '') !== 'webdav_import') {
            throw new RuntimeException('WebDAV-Import-Job nicht gefunden.');
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

            @set_time_limit(600);

            $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
            $userId = (string)($job['user_id'] ?? '');
            $id = (string)($payload['presentation_id'] ?? '');
            $driveId = (string)($payload['drive_id'] ?? '');
            $path = WebdavClient::normalizeBrowsePath((string)($payload['path'] ?? ''));
            $declaredSize = max(0, (int)($payload['size'] ?? 0));

            $drive = Auth::getWebdavDriveCredentials($userId, $driveId);
            if (!$drive || $path === '') {
                throw new RuntimeException(t('webdav.error_drive_not_found'));
            }

            $result = WebdavClient::importToPresentation($id, $drive, $path, $declaredSize);
            if (!$result['ok']) {
                $map = [
                    'invalid_path' => t('webdav.error_invalid_path'),
                    'unsupported_type' => t('webdav.error_type'),
                    'file_too_large' => t('webdav.error_too_large'),
                    'download_failed' => t('webdav.error_download'),
                    'auth_failed' => t('webdav.error_auth_failed'),
                    'not_found' => t('webdav.error_not_found'),
                    'save_failed' => t('webdav.error_save'),
                    'https_required' => t('webdav.error_https_required'),
                    'blocked_host' => t('webdav.error_blocked_host'),
                ];
                throw new RuntimeException($map[$result['error'] ?? ''] ?? t('webdav.error_generic'));
            }

            BackgroundJob::patch($jobId, [
                'status' => 'done',
                'finished_at' => gmdate('c'),
                'error' => null,
                'result' => [
                    'url' => $result['url'],
                    'filename' => $result['filename'],
                    'kind' => $result['kind'],
                    'name' => $result['name'] ?? '',
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
}
