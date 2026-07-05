<?php

class RemoteQr
{
    public static function isAllowedRemoteUrl(string $data, string $host): bool
    {
        $parsed = parse_url($data);
        if (!$parsed || empty($parsed['host'])) {
            return false;
        }
        if (!str_contains($parsed['path'] ?? '', 'present_remote.php')) {
            return false;
        }
        $dataHost = strtolower($parsed['host']);
        $allowed = array_unique([
            strtolower(preg_replace('/:\d+$/', '', $host)),
            strtolower(preg_replace('/:\d+$/', '', http_request_host())),
        ]);
        return in_array($dataHost, $allowed, true);
    }

    public static function pngDataUri(string $data): ?string
    {
        if ($data === '' || !extension_loaded('gd')) {
            return null;
        }

        if (!class_exists('QRcode', false)) {
            if (!defined('QR_FIND_BEST_MASK')) {
                define('QR_FIND_BEST_MASK', false);
            }
            if (!defined('QR_CACHEABLE')) {
                define('QR_CACHEABLE', true);
            }
            require BASE_PATH . '/src/phpqrcode/qrlib.php';
        }

        ob_start();
        $prevLevel = error_reporting(E_ERROR);
        try {
            QRcode::png($data, false, QR_ECLEVEL_H, 10, 4);
        } finally {
            error_reporting($prevLevel);
        }
        $png = ob_get_clean();
        if ($png === false || strlen($png) < 64 || strncmp($png, "\x89PNG", 4) !== 0) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    public static function outputPng(string $data): bool
    {
        if ($data === '' || !extension_loaded('gd')) {
            return false;
        }

        if (!class_exists('QRcode', false)) {
            if (!defined('QR_FIND_BEST_MASK')) {
                define('QR_FIND_BEST_MASK', false);
            }
            if (!defined('QR_CACHEABLE')) {
                define('QR_CACHEABLE', true);
            }
            require BASE_PATH . '/src/phpqrcode/qrlib.php';
        }

        $prevLevel = error_reporting(E_ERROR);
        ob_start();
        try {
            QRcode::png($data, false, QR_ECLEVEL_H, 10, 4);
        } finally {
            error_reporting($prevLevel);
        }
        $png = ob_get_clean();
        if ($png === false || strlen($png) < 64 || strncmp($png, "\x89PNG", 4) !== 0) {
            return false;
        }

        header('Content-Type: image/png');
        header('Cache-Control: private, max-age=3600');
        echo $png;

        return true;
    }
}
