<?php
/**
 * Übersetzt Medien-Suchbegriffe für externe APIs (Pixabay, Icons, OpenClipart).
 */
class MediaTranslate
{
    public static function toEnglish(string $text, ?string $fromLang = null): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $fromLang = strtolower(substr($fromLang ?? I18n::currentLang(), 0, 2));
        if ($fromLang === 'en') {
            return $text;
        }

        $url = 'https://api.mymemory.translated.net/get?' . http_build_query([
            'q' => $text,
            'langpair' => $fromLang . '|en',
        ]);
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "User-Agent: SlideForge/1.0\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException(t('media.translate_failed'));
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException(t('media.translate_failed'));
        }
        $translated = trim((string)($data['responseData']['translatedText'] ?? ''));
        if ($translated === '' || strtoupper($translated) === strtoupper($text)) {
            if (!empty($data['responseStatus']) && (int)$data['responseStatus'] !== 200) {
                throw new RuntimeException(t('media.translate_failed'));
            }
        }
        return $translated !== '' ? $translated : $text;
    }
}
