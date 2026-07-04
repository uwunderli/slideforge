<?php
/**
 * Sammelt prüfbare Texte aus einer Präsentation und ruft LanguageTool auf.
 */
class SpellCheckService
{
    /** @return array{ok: bool, issues?: array, error?: string, issueCount?: int} */
    public static function checkPresentation(string $id, string $userLang): array
    {
        if (!Config::languageToolEnabled()) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $meta = Presentation::getMeta($id);
        $slidesData = Presentation::getSlides($id);
        $segments = [];

        $title = trim((string)($meta['title'] ?? ''));
        if ($title !== '') {
            $plain = SpellCheckPlain::fromMarkdown($title);
            $segments[] = self::segment('title', $title, $plain, null, null, 'title');
        }

        foreach ($slidesData['slides'] ?? [] as $index => $slide) {
            foreach ($slide['objects'] ?? [] as $obj) {
                if (($obj['type'] ?? '') !== 'text') {
                    continue;
                }
                $text = (string)($obj['text'] ?? '');
                if (trim($text) === '') {
                    continue;
                }
                $plain = SpellCheckPlain::fromMarkdown($text);
                $segments[] = self::segment(
                    'slide:' . $index . ':obj:' . ($obj['id'] ?? ''),
                    $text,
                    $plain,
                    $index,
                    $obj['id'] ?? null,
                    'object'
                );
            }
            $notes = (string)($slide['notes'] ?? '');
            if (trim($notes) !== '') {
                $plain = SpellCheckPlain::fromMarkdown($notes);
                $segments[] = self::segment(
                    'slide:' . $index . ':notes',
                    $notes,
                    $plain,
                    $index,
                    null,
                    'notes'
                );
            }
        }

        $langCode = SpellCheckPlain::languageCode($userLang);
        $result = LanguageToolClient::checkSegments($segments, $langCode);
        if (!$result['ok']) {
            return $result;
        }

        $issues = $result['issues'] ?? [];
        $issues = self::appendKnownWordTypos($segments, $issues, $langCode);
        foreach ($issues as &$issue) {
            $issue['slideIndex'] = self::parseSlideIndex($issue['segmentId']);
            $issue['kind'] = self::parseKind($issue['segmentId']);
        }
        unset($issue);

        return [
            'ok' => true,
            'issues' => $issues,
            'issueCount' => count($issues),
            'language' => $langCode,
        ];
    }

    /** @param array{plain: string, map: int[]} $plain */
    private static function segment(string $id, string $text, array $plain, ?int $slideIndex, ?string $objectId, string $kind): array
    {
        return [
            'id' => $id,
            'text' => $text,
            'plain' => $plain['plain'],
            'map' => $plain['map'],
            'slideIndex' => $slideIndex,
            'objectId' => $objectId,
            'kind' => $kind,
        ];
    }

    private static function parseSlideIndex(string $segmentId): ?int
    {
        if ($segmentId === 'title') {
            return null;
        }
        if (preg_match('/^slide:(\d+):/', $segmentId, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private static function parseKind(string $segmentId): string
    {
        if ($segmentId === 'title') {
            return 'title';
        }
        if (str_ends_with($segmentId, ':notes')) {
            return 'notes';
        }
        return 'object';
    }

    /** @param array<int, array<string, mixed>> $segments */
    private static function appendKnownWordTypos(array $segments, array $issues, string $languageCode): array
    {
        if (!str_starts_with($languageCode, 'de')) {
            return $issues;
        }

        $typos = ['Luckas' => 'Lukas'];

        foreach ($segments as $seg) {
            $plain = $seg['plain'] ?? '';
            $text = $seg['text'] ?? '';
            $map = $seg['map'] ?? [];
            if ($plain === '' || !$map) {
                continue;
            }

            foreach ($typos as $wrong => $suggestion) {
                $searchFrom = 0;
                $wrongLen = mb_strlen($wrong);
                while (($pos = mb_stripos($plain, $wrong, $searchFrom)) !== false) {
                    $before = $pos > 0 ? mb_substr($plain, $pos - 1, 1) : '';
                    $afterPos = $pos + $wrongLen;
                    $after = $afterPos < mb_strlen($plain) ? mb_substr($plain, $afterPos, 1) : '';
                    if ($before !== '' && preg_match('/\p{L}/u', $before)) {
                        $searchFrom = $pos + 1;
                        continue;
                    }
                    if ($after !== '' && preg_match('/\p{L}/u', $after)) {
                        $searchFrom = $pos + 1;
                        continue;
                    }

                    $duplicate = false;
                    foreach ($issues as $ex) {
                        if (($ex['segmentId'] ?? '') === ($seg['id'] ?? '')
                            && abs((int)($ex['offset'] ?? -999) - $pos) < $wrongLen) {
                            $duplicate = true;
                            break;
                        }
                    }
                    if ($duplicate) {
                        $searchFrom = $pos + $wrongLen;
                        continue;
                    }

                    $origStartChar = $map[$pos] ?? 0;
                    $lastIdx = min($pos + $wrongLen - 1, count($map) - 1);
                    $origEndChar = ($lastIdx + 1 < count($map))
                        ? $map[$lastIdx + 1]
                        : mb_strlen($text);

                    $issues[] = [
                        'segmentId' => $seg['id'],
                        'offset' => $pos,
                        'length' => $wrongLen,
                        'origOffset' => $origStartChar,
                        'origLength' => max(0, $origEndChar - $origStartChar),
                        'wrong' => mb_substr($text, $origStartChar, max(0, $origEndChar - $origStartChar)),
                        'plainWrong' => mb_substr($plain, $pos, $wrongLen),
                        'message' => t('spell.known_typo', ['word' => $wrong, 'suggestion' => $suggestion]),
                        'rule' => t('spell.known_typo_rule'),
                        'suggestions' => [$suggestion],
                    ];
                    $searchFrom = $pos + $wrongLen;
                }
            }
        }

        return $issues;
    }
}
