<?php
/**
 * Verschlüsselung sensibler User-Daten (z. B. WebDAV-Passwörter) at rest.
 */
class CryptoHelper
{
    private const CIPHER = 'aes-256-gcm';

    public static function appSecret(): string
    {
        $file = DATA_PATH . '/.app_secret';
        if (is_readable($file)) {
            $secret = trim((string)file_get_contents($file));
            if ($secret !== '') {
                return $secret;
            }
        }
        $secret = bin2hex(random_bytes(32));
        if (!is_dir(DATA_PATH)) {
            mkdir(DATA_PATH, 0770, true);
        }
        file_put_contents($file, $secret, LOCK_EX);
        @chmod($file, 0660);
        return $secret;
    }

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key = hash('sha256', self::appSecret(), true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): ?string
    {
        if ($payload === '') {
            return '';
        }
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $key = hash('sha256', self::appSecret(), true);
        $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }
}
