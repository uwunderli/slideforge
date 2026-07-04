<?php
/**
 * Wandelt Markdown-Text in Klartext für Rechtschreibprüfung um.
 * map[i] = Zeichen-Index (nicht Byte!) im Original für Klartext-Zeichen i.
 */
class SpellCheckPlain
{
    /** @return array{plain: string, map: int[]} */
    public static function fromMarkdown(string $raw): array
    {
        $plain = '';
        $map = [];
        if ($raw === '') {
            return ['plain' => '', 'map' => []];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = explode("\n", $normalized);
        $charPos = 0;

        foreach ($lines as $lineIdx => $lineText) {
            $lineCharLen = mb_strlen($lineText);
            $content = $lineText;
            $contentStartChar = $charPos;

            if (preg_match('/^(#{1,6})(\s*)(.*)$/u', $lineText, $m)) {
                $contentStartChar += mb_strlen($m[1]) + mb_strlen($m[2]);
                $content = $m[3];
            } elseif (preg_match('/^(\s*[-*]\s+)(.*)$/u', $lineText, $m)) {
                $contentStartChar += mb_strlen($m[1]);
                $content = $m[2];
            }

            self::appendPlainMapped($content, $contentStartChar, $plain, $map);

            $charPos += $lineCharLen;
            if ($lineIdx < count($lines) - 1) {
                $plain .= "\n";
                $map[] = $charPos;
                $charPos += 1;
            }
        }

        return ['plain' => $plain, 'map' => $map];
    }

    private static function appendPlainMapped(string $line, int $baseCharOffset, string &$plain, array &$map): void
    {
        $lineLen = mb_strlen($line);
        $pos = 0;
        while ($pos < $lineLen) {
            $chunk = self::nextVisibleChunk($line, $pos);
            if ($chunk === null) {
                break;
            }
            [$text, $relStart, $relEnd] = $chunk;
            $pos = $relEnd;
            if ($text === '') {
                continue;
            }
            $plain .= $text;
            $textLen = mb_strlen($text);
            for ($i = 0; $i < $textLen; $i++) {
                $map[] = $baseCharOffset + $relStart + $i;
            }
        }
    }

    /** @return array{0: string, 1: int, 2: int}|null Zeichen-Offsets in $line */
    private static function nextVisibleChunk(string $line, int $pos): ?array
    {
        $lineLen = mb_strlen($line);
        if ($pos >= $lineLen) {
            return null;
        }

        $rest = mb_substr($line, $pos);
        $rules = [
            '/^\[([^\]]+)\]\((?:https?:\/\/[^\s)]+|mailto:[^\s)]+)\)/u',
            '/^\[(?:color|mark)=#[0-9a-fA-F]{6}\](.+?)\[\/(?:color|mark)\]/us',
            '/^\[(?:upper|sc)\](.+?)\[\/(?:upper|sc)\]/us',
            '/^\*\*(.+?)\*\*/us',
            '/^\*(.+?)\*/us',
            '/^`([^`]+)`/u',
            '/^\+\+(.+?)\+\+/us',
            '/^~~(.+?)~~/us',
        ];

        foreach ($rules as $pattern) {
            if (!preg_match($pattern, $rest, $m)) {
                continue;
            }
            $fullLen = mb_strlen($m[0]);
            $inner = $m[1];
            $prefix = mb_substr($m[0], 0, mb_strpos($m[0], $inner));
            $innerStart = $pos + mb_strlen($prefix);
            return [$inner, $innerStart, $pos + $fullLen];
        }

        return [mb_substr($line, $pos, 1), $pos, $pos + 1];
    }

    public static function languageCode(string $lang): string
    {
        return match ($lang) {
            'en' => 'en-US',
            'fr' => 'fr',
            'it' => 'it',
            'rm' => 'rm',
            'de-CH' => 'de-CH',
            default => 'de-DE',
        };
    }
}
