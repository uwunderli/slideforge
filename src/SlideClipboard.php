<?php
/**
 * Benutzereigene Zwischenablage für ganze Folien.
 *
 * Zweck: Folien aus einer Präsentation in eine andere übernehmen. Der Inhalt liegt je
 * Benutzer in einer Datei; Bilder werden erst beim Einfügen in die Zielpräsentation
 * kopiert, damit die Ablage klein bleibt und nichts doppelt herumliegt.
 */
class SlideClipboard
{
    /** Mehr als das hebt niemand auf; Ältestes fällt heraus. */
    private const MAX_ENTRIES = 60;

    private static function dir(): string
    {
        $dir = DATA_PATH . '/clipboard';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private static function path(string $userId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
        return self::dir() . '/' . $safe . '.json';
    }

    /** @return list<array{id: string, title: string, source_id: string, source_title: string, created_at: string, slide: array}> */
    public static function all(string $userId): array
    {
        $data = Storage::read(self::path($userId), ['entries' => []]);
        $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
        return array_values(array_filter($entries, fn($e) => is_array($e) && isset($e['slide'])));
    }

    /**
     * Kurzfassung für die Anzeige — ohne die vollständigen Folien, die Liste wird sonst
     * unnötig gross.
     *
     * @return list<array{id: string, title: string, source_title: string, created_at: string}>
     */
    public static function summary(string $userId): array
    {
        return array_map(fn(array $e) => [
            'id' => (string)($e['id'] ?? ''),
            'title' => (string)($e['title'] ?? ''),
            'source_title' => (string)($e['source_title'] ?? ''),
            'created_at' => (string)($e['created_at'] ?? ''),
            'width' => (int)($e['width'] ?? DEFAULT_SLIDE_WIDTH),
            'height' => (int)($e['height'] ?? DEFAULT_SLIDE_HEIGHT),
            /* Miniatur erst hier erzeugen, nicht beim Kopieren: Die Ablage bleibt so
               klein, und die Liste wird ohnehin nur beim Öffnen des Reiters geholt. */
            'thumb' => SlideRenderer::renderSlideThumbnailHtml($e['slide'], null),
        ], self::all($userId));
    }

    /**
     * Legt Folien in die Ablage. Zuletzt kopiertes steht oben.
     *
     * @param list<array> $slides
     */
    public static function add(string $userId, string $sourceId, string $sourceTitle, array $slides): int
    {
        $entries = self::all($userId);
        // Folienmass der Quelle merken, damit die Miniatur das richtige Seitenverhältnis hat.
        $meta = Presentation::getMeta($sourceId) ?? [];
        $width = max(100, (int)($meta['width'] ?? DEFAULT_SLIDE_WIDTH));
        $height = max(100, (int)($meta['height'] ?? DEFAULT_SLIDE_HEIGHT));
        $neu = [];
        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $neu[] = [
                'id' => Storage::generateId(6),
                'title' => self::slideTitle($slide),
                'width' => $width,
                'height' => $height,
                'source_id' => $sourceId,
                'source_title' => $sourceTitle,
                'created_at' => gmdate('c'),
                'slide' => $slide,
            ];
        }
        if ($neu === []) {
            return 0;
        }
        $entries = array_slice(array_merge($neu, $entries), 0, self::MAX_ENTRIES);
        Storage::write(self::path($userId), ['entries' => $entries]);
        return count($neu);
    }

    public static function remove(string $userId, array $ids): void
    {
        $weg = array_flip(array_map('strval', $ids));
        $entries = array_values(array_filter(
            self::all($userId),
            fn(array $e) => !isset($weg[(string)($e['id'] ?? '')])
        ));
        Storage::write(self::path($userId), ['entries' => $entries]);
    }

    public static function clear(string $userId): void
    {
        Storage::write(self::path($userId), ['entries' => []]);
    }

    /**
     * Holt Folien zum Einfügen in eine Zielpräsentation: kopiert die benötigten
     * Bilddateien mit und schreibt die Verweise auf die Ziel-Kennung um.
     *
     * @param list<string> $ids leere Liste = alle
     * @return array{slides: list<array>, warnings: list<string>}
     */
    public static function fetchFor(string $userId, array $ids, string $targetId): array
    {
        $wanted = $ids === [] ? null : array_flip(array_map('strval', $ids));
        $slides = [];
        $warnings = [];
        foreach (self::all($userId) as $entry) {
            $entryId = (string)($entry['id'] ?? '');
            if ($wanted !== null && !isset($wanted[$entryId])) {
                continue;
            }
            $sourceId = (string)($entry['source_id'] ?? '');
            $slide = $entry['slide'];
            if ($sourceId !== '' && $sourceId !== $targetId) {
                [$slide, $fehlend] = self::rehostAssets($slide, $sourceId, $targetId);
                foreach ($fehlend as $datei) {
                    $warnings[] = sprintf(
                        'Bild „%s" aus „%s" wurde nicht gefunden.',
                        $datei,
                        (string)($entry['source_title'] ?? $sourceId)
                    );
                }
            }
            // Eigene Kennung, sonst kollidiert die Folie mit ihrem Original.
            $slide['id'] = Storage::generateId(4);
            $slides[] = $slide;
        }
        return ['slides' => $slides, 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * Kopiert die in der Folie verwendeten Bilder in die Zielpräsentation und schreibt
     * die Verweise um. Gleiches Vorgehen wie beim Duplizieren einer Vorlage: Dateinamen
     * bleiben, nur die `id=` im asset.php-Verweis wechselt.
     *
     * @return array{0: array, 1: list<string>}
     */
    private static function rehostAssets(array $slide, string $sourceId, string $targetId): array
    {
        $json = json_encode($slide, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [$slide, []];
        }

        $srcDir = Presentation::dir($sourceId) . '/assets';
        $dstDir = Presentation::dir($targetId) . '/assets';
        if (!is_dir($dstDir)) {
            @mkdir($dstDir, 0777, true);
        }

        $fehlend = [];
        $muster = '/asset\.php\?id=' . preg_quote(urlencode($sourceId), '/') . '&(?:amp;)?file=([A-Za-z0-9_.-]+)/';
        if (preg_match_all($muster, $json, $treffer)) {
            foreach (array_unique($treffer[1]) as $datei) {
                $von = $srcDir . '/' . $datei;
                $nach = $dstDir . '/' . $datei;
                if (is_file($nach)) {
                    continue;
                }
                if (is_file($von)) {
                    @copy($von, $nach);
                } else {
                    $fehlend[] = $datei;
                }
            }
        }

        $json = str_replace(
            'asset.php?id=' . urlencode($sourceId) . '&',
            'asset.php?id=' . urlencode($targetId) . '&',
            $json
        );
        $neu = json_decode($json, true);
        return [is_array($neu) ? $neu : $slide, $fehlend];
    }

    /** Beschriftung für die Liste: erster Text auf der Folie, sonst das Layout. */
    private static function slideTitle(array $slide): string
    {
        foreach ($slide['objects'] ?? [] as $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                continue;
            }
            $text = trim((string)($obj['text'] ?? ''));
            if ($text !== '') {
                return mb_substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, 70);
            }
        }
        $label = trim((string)($slide['label'] ?? ''));
        return $label !== '' ? $label : (string)($slide['layoutKey'] ?? 'Folie');
    }
}
