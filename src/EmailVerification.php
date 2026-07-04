<?php
/**
 * E-Mail-Bestätigung bei der Registrierung.
 */
class EmailVerification
{
    private const TOKEN_TTL_SECONDS = 48 * 3600;

    public static function issueToken(string $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + self::TOKEN_TTL_SECONDS;
        Storage::update(USERS_FILE, function ($users) use ($userId, $token, $expires) {
            foreach ($users as &$u) {
                if ($u['id'] === $userId) {
                    $u['email_verify_token'] = $token;
                    $u['email_verify_expires'] = $expires;
                }
            }
            return $users;
        }, []);
        return $token;
    }

    public static function findUserByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        foreach (Storage::read(USERS_FILE, []) as $u) {
            if (($u['email_verify_token'] ?? '') === $token) {
                return $u;
            }
        }
        return null;
    }

    /** @return array{0: bool, 1: string, 2: ?array} */
    public static function verify(string $token): array
    {
        $user = self::findUserByToken($token);
        if (!$user) {
            return [false, 'invalid', null];
        }
        if (!empty($user['email_verified'])) {
            return [true, 'already', $user];
        }
        $expires = (int)($user['email_verify_expires'] ?? 0);
        if ($expires > 0 && time() > $expires) {
            return [false, 'expired', $user];
        }

        Storage::update(USERS_FILE, function ($users) use ($user) {
            foreach ($users as &$u) {
                if ($u['id'] === $user['id']) {
                    $u['email_verified'] = true;
                    unset($u['email_verify_token'], $u['email_verify_expires']);
                }
            }
            return $users;
        }, []);

        return [true, 'ok', Auth::findById($user['id'])];
    }

    public static function verifyUrl(string $token): string
    {
        $scheme = current_scheme();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        return $scheme . '://' . $host . $base . '/verify_email.php?token=' . urlencode($token);
    }

    /** @return array{ok: bool, error?: string} */
    public static function sendMail(array $user): array
    {
        $smtp = Config::smtp();
        if (empty($smtp['host'])) {
            return ['ok' => false, 'error' => 'no_smtp'];
        }
        if (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid_email'];
        }

        $token = self::issueToken($user['id']);
        $url = self::verifyUrl($token);
        $siteTitle = Config::siteTitle();
        $lang = $user['language'] ?? I18n::currentLang();
        $prevLang = I18n::currentLang();
        I18n::init($lang);

        $result = SmtpMailer::send(
            $smtp,
            $user['email'],
            t('auth.verify_mail_subject', ['site' => $siteTitle]),
            t('auth.verify_mail_body', ['site' => $siteTitle, 'url' => $url])
        );

        I18n::init($prevLang);
        return $result;
    }

    /** @return array{0: bool, 1: string} */
    public static function resendForEmail(string $email): array
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'invalid_email'];
        }

        $user = null;
        foreach (Storage::read(USERS_FILE, []) as $u) {
            if (strcasecmp($u['email'] ?? '', $email) === 0) {
                $user = $u;
                break;
            }
        }

        if (!$user) {
            // Kein Hinweis, ob die Adresse existiert (Enumeration vermeiden).
            return [true, 'sent'];
        }
        if (!empty($user['email_verified'])) {
            return [true, 'already_verified'];
        }

        $mail = self::sendMail($user);
        if (!$mail['ok']) {
            return [false, $mail['error'] ?? 'send_failed'];
        }
        return [true, 'sent'];
    }

    public static function isVerified(array $user): bool
    {
        return !empty($user['email_verified']);
    }
}
