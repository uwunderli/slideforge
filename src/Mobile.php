<?php

/**
 * Smartphone-Erkennung für die reduzierte Mobile-UI (v2.0.0).
 * Tablets/iPads erhalten weiterhin die Desktop-Oberfläche.
 */
class Mobile
{
    public const MODE_COOKIE = 'sf_ui_mode';

    public static function handleOverride(): void
    {
        if (isset($_GET['mobile'])) {
            $mode = $_GET['mobile'] === '1' ? 'mobile' : 'desktop';
            self::setModeCookie($mode);
        } elseif (isset($_GET['desktop'])) {
            $mode = $_GET['desktop'] === '1' ? 'desktop' : 'mobile';
            self::setModeCookie($mode);
        }
    }

    private static function setModeCookie(string $mode): void
    {
        if (!in_array($mode, ['mobile', 'desktop'], true)) {
            return;
        }
        setcookie(self::MODE_COOKIE, $mode, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::MODE_COOKIE] = $mode;
    }

    public static function isMobileClient(): bool
    {
        $cookie = $_COOKIE[self::MODE_COOKIE] ?? null;
        if ($cookie === 'mobile') {
            return true;
        }
        if ($cookie === 'desktop') {
            return false;
        }
        return self::detectMobileUserAgent();
    }

    private static function detectMobileUserAgent(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '') {
            return false;
        }
        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua)) {
            return false;
        }
        if (preg_match('/Android/i', $ua) && !preg_match('/Mobile/i', $ua)) {
            return false;
        }
        return (bool)preg_match(
            '/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i',
            $ua
        );
    }
}
