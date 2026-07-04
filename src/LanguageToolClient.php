<?php
/**
 * Client für die LanguageTool-API (Rechtschreibung & Grammatik).
 */
class LanguageToolClient
{
    /** @param array<int, array{id: string, plain: string, text: string, map: int[]}> $segments */
    public static function checkSegments(array $segments, string $languageCode, ?array $config = null): array
    {
        $config = $config ?? Config::languageTool();
        if (empty($config['enabled'])) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $parts = [];
        $ranges = [];
        $offset = 0;
        foreach ($segments as $seg) {
            $plain = $seg['plain'] ?? '';
            if (trim($plain) === '') {
                continue;
            }
            if ($parts) {
                $parts[] = "\n\n";
                $offset += 2;
            }
            $start = $offset;
            $parts[] = $plain;
            $offset += mb_strlen($plain);
            $ranges[] = [
                'id' => $seg['id'],
                'text' => $seg['text'],
                'map' => $seg['map'],
                'plain' => $plain,
                'globalStart' => $start,
                'globalEnd' => $offset,
            ];
        }

        if (!$parts) {
            return ['ok' => true, 'issues' => []];
        }

        $combined = implode('', $parts);
        $response = self::request($combined, $languageCode, $config);
        if (!$response['ok']) {
            return $response;
        }

        $issues = [];
        foreach ($response['matches'] as $match) {
            $globalOffset = (int)($match['offset'] ?? 0);
            $length = (int)($match['length'] ?? 0);
            $segment = null;
            foreach ($ranges as $range) {
                if ($globalOffset >= $range['globalStart'] && $globalOffset < $range['globalEnd']) {
                    $segment = $range;
                    break;
                }
            }
            if (!$segment) {
                continue;
            }

            $localOffset = $globalOffset - $segment['globalStart'];
            $map = $segment['map'];
            $text = $segment['text'];
            $textLen = mb_strlen($text);

            $origStartChar = $map[$localOffset] ?? 0;
            $lastPlainIdx = min($localOffset + max(0, $length - 1), count($map) - 1);
            if ($lastPlainIdx < 0) {
                continue;
            }
            $origEndChar = ($lastPlainIdx + 1 < count($map))
                ? $map[$lastPlainIdx + 1]
                : $textLen;

            $wrong = mb_substr($text, $origStartChar, max(0, $origEndChar - $origStartChar));
            $plainWrong = mb_substr($segment['plain'], $localOffset, $length);
            if (self::isMarkdownCapitalizationFalsePositive($segment['plain'], $localOffset, $plainWrong, $match)) {
                continue;
            }
            $suggestions = [];
            foreach ($match['replacements'] ?? [] as $rep) {
                if (!empty($rep['value'])) {
                    $suggestions[] = $rep['value'];
                }
            }

            $issues[] = [
                'segmentId' => $segment['id'],
                'offset' => $localOffset,
                'length' => $length,
                'origOffset' => $origStartChar,
                'origLength' => max(0, $origEndChar - $origStartChar),
                'wrong' => $wrong,
                'plainWrong' => $plainWrong,
                'message' => (string)($match['message'] ?? ''),
                'rule' => (string)($match['rule']['description'] ?? $match['rule']['id'] ?? ''),
                'suggestions' => array_slice($suggestions, 0, 5),
            ];
        }

        return ['ok' => true, 'issues' => self::tuneIssuesForLocale($issues, $languageCode)];
    }

    /** @param array<int, array<string, mixed>> $issues */
    private static function tuneIssuesForLocale(array $issues, string $languageCode): array
    {
        if ($languageCode !== 'de-CH') {
            return $issues;
        }

        $filtered = [];
        foreach ($issues as $issue) {
            $wrong = mb_strtolower($issue['wrong'] ?? '');
            if ($wrong === 'luckas') {
                $issue['suggestions'] = array_values(array_unique(array_merge(
                    ['Lukas'],
                    $issue['suggestions'] ?? []
                )));
                $issue['suggestions'] = array_slice($issue['suggestions'], 0, 5);
            } elseif (preg_match('/^luc?kas$/u', $wrong)) {
                $suggestions = array_values(array_unique(array_merge(
                    ['Lukas'],
                    $issue['suggestions'] ?? []
                )));
                $suggestions = array_values(array_filter(
                    $suggestions,
                    static fn(string $s): bool => mb_strtolower($s) !== 'luckas' && mb_strtolower($s) !== 'lucas'
                ));
                $issue['suggestions'] = array_slice($suggestions, 0, 5);
            }
            $filtered[] = $issue;
        }
        return $filtered;
    }

    /** Grossschreibung nach Markdown-Zeilenanfang, Überschrift oder Komma-Liste – kein Fehler. */
    private static function isMarkdownCapitalizationFalsePositive(string $plain, int $offset, string $plainWrong, array $match): bool
    {
        if (!self::isAllowedCapitalizationPosition($plain, $offset)) {
            return false;
        }
        if ($plainWrong === '' || !preg_match('/^\p{Lu}/u', $plainWrong)) {
            return false;
        }
        $message = (string)($match['message'] ?? '');
        $ruleId = (string)($match['rule']['id'] ?? '');
        if (preg_match('/UPPERCASE_SENTENCE_START/i', $ruleId)) {
            return false;
        }
        if (preg_match('/klein\s*geschrieben|Satzanfang|Nomen und Eigennamen|sentence\s+start|at the beginning|minuscule|minuscol|minuscula|minuscul|Großgeschrieben/i', $message)) {
            return true;
        }
        if (preg_match('/^(DE_|FR_|IT_|EN_|RM_).*(CASE|CAPITAL|GROSS|MINUS)/i', $ruleId)) {
            return true;
        }
        return false;
    }

    private static function isAllowedCapitalizationPosition(string $plain, int $offset): bool
    {
        if ($offset <= 0) {
            return true;
        }
        if (($plain[$offset - 1] ?? '') === "\n") {
            return true;
        }
        if ($offset >= 2 && substr($plain, $offset - 2, 2) === ', ') {
            return true;
        }
        if ($offset >= 2 && substr($plain, $offset - 2, 2) === '; ') {
            return true;
        }
        return false;
    }

    /** @return array{ok: bool, matches?: array, error?: string} */
    private static function request(string $text, string $languageCode, array $config): array
    {
        $url = rtrim(trim((string)($config['api_url'] ?? '')), '/');
        if ($url === '') {
            $url = 'https://api.languagetool.org/v2/check';
        } elseif (!str_contains($url, '/check')) {
            $url .= '/v2/check';
        }

        $post = [
            'text' => $text,
            'language' => $languageCode,
            'enabledOnly' => 'false',
        ];
        // preferredVariants gilt nur mit language=auto; bei de-CH reicht language=de-CH.
        if (!empty($config['api_username'])) {
            $post['username'] = $config['api_username'];
        }
        if (!empty($config['api_key'])) {
            $post['apiKey'] = $config['api_key'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init_failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => $curlErr !== '' ? $curlErr : 'network_error'];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'invalid_response'];
        }
        if ($code >= 400) {
            return ['ok' => false, 'error' => (string)($data['message'] ?? 'api_error_' . $code)];
        }

        return ['ok' => true, 'matches' => $data['matches'] ?? []];
    }
}
