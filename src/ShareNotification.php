<?php
/**
 * E-Mail-Benachrichtigung, wenn eine Präsentation mit einem Benutzer geteilt wird.
 */
class ShareNotification
{
    public static function presentationUrl(string $presentationId): string
    {
        $scheme = current_scheme();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        return $scheme . '://' . $host . $base . '/editor.php?id=' . urlencode($presentationId);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function send(array $recipient, array $sharer, array $presentationMeta, string $permission): array
    {
        $smtp = Config::smtp();
        if (empty($smtp['host'])) {
            return ['ok' => false, 'error' => 'no_smtp'];
        }
        if (empty($recipient['email']) || !filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid_email'];
        }

        $lang = $recipient['language'] ?? 'de';
        if (!isset(I18n::SUPPORTED[$lang])) {
            $lang = 'de';
        }
        $prevLang = I18n::currentLang();
        I18n::init($lang);

        $siteTitle = Config::siteTitle();
        $sharerName = $sharer['username'] ?? '';
        $title = $presentationMeta['title'] ?? 'Präsentation';
        $permKey = $permission === 'edit' ? 'share.mail_permission_edit' : 'share.mail_permission_view';
        $permissionLabel = t($permKey);
        $url = self::presentationUrl($presentationMeta['id'] ?? '');

        $result = SmtpMailer::send(
            $smtp,
            $recipient['email'],
            t('share.mail_subject', ['sharer' => $sharerName, 'title' => $title]),
            t('share.mail_body', [
                'sharer' => $sharerName,
                'title' => $title,
                'permission' => $permissionLabel,
                'url' => $url,
                'site' => $siteTitle,
            ])
        );

        I18n::init($prevLang);
        return $result;
    }
}
