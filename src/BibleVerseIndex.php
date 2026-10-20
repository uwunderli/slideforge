<?php
/**
 * Versgrenzen-Verzeichnis für den Logos-Import.
 *
 * Zweck: Ein langer Bibeltext aus einer Predigt soll versgenau auf mehrere Folien
 * verteilt werden ("Vers 14-16" / "Vers 17-19"), statt irgendwo im Satz zu brechen.
 * Der Logos-Export liefert den Wortlaut am Stück, ohne Versnummern - wo Vers 15
 * anfängt, steht nicht darin.
 *
 * Grundlage ist eine DRM-freie EPUB-Bibel des Benutzers. Bewusst wird daraus NICHT
 * der Bibeltext gespeichert, sondern je Vers nur ein kurzer, normalisierter
 * Erkennungsanfang (Kleinbuchstaben und Ziffern, ohne Satzzeichen). Der genügt, um
 * den Versanfang im Predigttext wiederzufinden, und ist keine lesbare Kopie der
 * Bibel. Der Wortlaut auf der Folie stammt weiterhin aus dem Predigt-Export.
 */
class BibleVerseIndex
{
    /** Zeichen des normalisierten Erkennungsanfangs je Vers. */
    private const FINGERPRINT_LENGTH = 40;

    /** Deutsche Buchnamen und gängige Kürzel auf die Dateikennungen der EPUB. */
    private const BOOKS = [
        'gen' => ['1. mose', '1 mose', 'genesis', '1. mo', '1mo'],
        'exo' => ['2. mose', '2 mose', 'exodus', '2. mo', '2mo'],
        'lev' => ['3. mose', '3 mose', 'levitikus', 'leviticus', '3. mo'],
        'num' => ['4. mose', '4 mose', 'numeri', '4. mo'],
        'deu' => ['5. mose', '5 mose', 'deuteronomium', '5. mo'],
        'jos' => ['josua', 'jos'],
        'jdg' => ['richter', 'ri'],
        'rut' => ['rut', 'ruth'],
        '1sa' => ['1. samuel', '1 samuel', '1. sam', '1sam'],
        '2sa' => ['2. samuel', '2 samuel', '2. sam', '2sam'],
        '1ki' => ['1. könige', '1 könige', '1. kön', '1kön', '1. kö'],
        '2ki' => ['2. könige', '2 könige', '2. kön', '2kön', '2. kö'],
        '1ch' => ['1. chronik', '1 chronik', '1. chr', '1chr'],
        '2ch' => ['2. chronik', '2 chronik', '2. chr', '2chr'],
        'ezr' => ['esra'],
        'neh' => ['nehemia', 'neh'],
        'est' => ['ester', 'esther'],
        'job' => ['hiob', 'ijob'],
        'psa' => ['psalm', 'psalmen', 'ps'],
        'pro' => ['sprüche', 'sprichwörter', 'spr'],
        'ecc' => ['prediger', 'kohelet', 'pred'],
        'sng' => ['hoheslied', 'hohelied', 'hld'],
        'isa' => ['jesaja', 'jes'],
        'jer' => ['jeremia', 'jer'],
        'lam' => ['klagelieder', 'klgl'],
        'ezk' => ['hesekiel', 'ezechiel', 'hes', 'hez'],
        'dan' => ['daniel', 'dan'],
        'hos' => ['hosea', 'hos'],
        'jol' => ['joel'],
        'amo' => ['amos'],
        'oba' => ['obadja', 'obd'],
        'jon' => ['jona'],
        'mic' => ['micha', 'mi'],
        'nam' => ['nahum', 'nah'],
        'hab' => ['habakuk', 'hab'],
        'zep' => ['zefanja', 'zeph'],
        'hag' => ['haggai', 'hag'],
        'zec' => ['sacharja', 'sach'],
        'mal' => ['maleachi', 'mal'],
        'mat' => ['matthäus', 'mt', 'matth'],
        'mrk' => ['markus', 'mk', 'mark'],
        'luk' => ['lukas', 'lk', 'luk'],
        'jhn' => ['johannes', 'joh', 'jh'],
        'act' => ['apostelgeschichte', 'apg'],
        'rom' => ['römer', 'röm'],
        '1co' => ['1. korinther', '1 korinther', '1. kor', '1kor'],
        '2co' => ['2. korinther', '2 korinther', '2. kor', '2kor'],
        'gal' => ['galater', 'gal'],
        'eph' => ['epheser', 'eph'],
        'php' => ['philipper', 'phil'],
        'col' => ['kolosser', 'kol'],
        '1th' => ['1. thessalonicher', '1. thess', '1thess'],
        '2th' => ['2. thessalonicher', '2. thess', '2thess'],
        '1ti' => ['1. timotheus', '1. tim', '1tim'],
        '2ti' => ['2. timotheus', '2. tim', '2tim'],
        'tit' => ['titus', 'tit'],
        'phm' => ['philemon', 'phlm'],
        'heb' => ['hebräer', 'hebr'],
        'jas' => ['jakobus', 'jak'],
        '1pe' => ['1. petrus', '1. petr', '1petr'],
        '2pe' => ['2. petrus', '2. petr', '2petr'],
        '1jn' => ['1. johannes', '1. joh', '1joh'],
        '2jn' => ['2. johannes', '2. joh', '2joh'],
        '3jn' => ['3. johannes', '3. joh', '3joh'],
        'jud' => ['judas'],
        'rev' => ['offenbarung', 'offb', 'apokalypse'],
    ];

    public static function indexPath(string $translation = 'lu17'): string
    {
        return DATA_PATH . '/bibles/' . preg_replace('/[^a-z0-9_-]/', '', strtolower($translation)) . '.json';
    }

    public static function available(string $translation = 'lu17'): bool
    {
        return is_file(self::indexPath($translation));
    }

    /**
     * Normalisiert Text auf Buchstaben und Ziffern und merkt sich zu jedem behaltenen
     * Zeichen den Versatz im Ursprungstext. Nur so lässt sich eine Fundstelle später
     * wieder auf den unveränderten Predigttext abbilden.
     *
     * @return array{0: string, 1: list<int>}
     */
    public static function normalize(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        $map = [];
        foreach ($chars as $i => $c) {
            $lower = mb_strtolower($c);
            if (preg_match('/[\p{L}\p{N}]/u', $lower)) {
                $out .= $lower;
                $map[] = $i;
            }
        }
        return [$out, $map];
    }

    /** Dateikennung zu einem deutschen Buchnamen, oder null. */
    public static function bookCode(string $name): ?string
    {
        $needle = mb_strtolower(trim($name));
        $needle = preg_replace('/\s+/u', ' ', $needle) ?? $needle;
        $needle = rtrim($needle, '.');
        foreach (self::BOOKS as $code => $names) {
            foreach ($names as $candidate) {
                if ($needle === rtrim($candidate, '.')) {
                    return $code;
                }
            }
        }
        return null;
    }

    /**
     * Zerlegt eine Stellenangabe wie „2. Könige 19,14–19" oder „Johannes 3,14-15".
     *
     * @return array{book: string, chapter: int, from: int, to: int}|null
     */
    public static function parseReference(string $ref): ?array
    {
        $ref = trim(preg_replace('/\s+/u', ' ', $ref) ?? $ref);
        // Buchname, Kapitel, dann Vers oder Versspanne (Bindestrich oder Gedankenstrich).
        if (!preg_match('/^(.+?)\s+(\d+)\s*[,:]\s*(\d+)(?:\s*[-\x{2010}-\x{2015}]\s*(\d+))?/u', $ref, $m)) {
            return null;
        }
        $code = self::bookCode($m[1]);
        if ($code === null) {
            return null;
        }
        $from = (int)$m[3];
        $to = isset($m[4]) && $m[4] !== '' ? (int)$m[4] : $from;
        if ($to < $from) {
            return null;
        }
        return ['book' => $code, 'chapter' => (int)$m[2], 'from' => $from, 'to' => $to];
    }

    /**
     * Versanfänge im Predigttext bestimmen.
     *
     * @return list<array{verse: int, offset: int}> leer, wenn nichts zugeordnet werden konnte
     */
    public static function verseOffsets(string $text, array $parsed, string $translation = 'lu17'): array
    {
        $index = self::load($translation);
        $book = $index['books'][$parsed['book']][(string)$parsed['chapter']] ?? null;
        if (!is_array($book)) {
            return [];
        }

        [$haystack, $map] = self::normalize($text);
        $found = [];
        $searchFrom = 0;
        for ($v = $parsed['from']; $v <= $parsed['to']; $v++) {
            $fingerprint = (string)($book[(string)$v] ?? '');
            if ($fingerprint === '') {
                continue;
            }
            $pos = mb_strpos($haystack, $fingerprint, $searchFrom);
            if ($pos === false) {
                // Kürzeren Anfang versuchen: Predigten kürzen Verse gelegentlich.
                $short = mb_substr($fingerprint, 0, 18);
                $pos = mb_strlen($short) >= 12 ? mb_strpos($haystack, $short, $searchFrom) : false;
            }
            if ($pos === false) {
                continue;
            }
            $found[] = ['verse' => $v, 'offset' => $map[$pos] ?? 0];
            $searchFrom = $pos + 1;
        }
        return $found;
    }

    /** @return array{translation: string, books: array<string, array<string, array<string, string>>>} */
    private static function load(string $translation): array
    {
        static $cache = [];
        if (isset($cache[$translation])) {
            return $cache[$translation];
        }
        $path = self::indexPath($translation);
        $data = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
        $cache[$translation] = is_array($data) ? $data : ['translation' => $translation, 'books' => []];
        return $cache[$translation];
    }

    /**
     * Baut das Verzeichnis aus einer DRM-freien EPUB-Bibel.
     *
     * @return array{books: int, verses: int, path: string}
     */
    public static function buildFromEpub(string $epubPath, string $translation = 'lu17'): array
    {
        if (!is_file($epubPath)) {
            throw new RuntimeException('EPUB nicht gefunden: ' . $epubPath);
        }
        $zip = new ZipArchive();
        if ($zip->open($epubPath) !== true) {
            throw new RuntimeException('EPUB liess sich nicht öffnen.');
        }
        if ($zip->locateName('META-INF/encryption.xml') !== false) {
            $zip->close();
            throw new RuntimeException('Die Datei ist kopiergeschützt; ein Kopierschutz wird nicht umgangen.');
        }

        $books = [];
        $verseCount = 0;
        foreach (array_keys(self::BOOKS) as $code) {
            $raw = $zip->getFromName('OPS/' . $code . '.xhtml');
            if ($raw === false) {
                continue;
            }
            $chapters = self::fingerprintsFromBook($raw);
            if ($chapters === []) {
                continue;
            }
            $books[$code] = $chapters;
            foreach ($chapters as $verses) {
                $verseCount += count($verses);
            }
        }
        $zip->close();

        $path = self::indexPath($translation);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Verzeichnisordner liess sich nicht anlegen: ' . $dir);
        }
        $json = json_encode(
            ['translation' => $translation, 'books' => $books],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false || file_put_contents($path, $json) === false) {
            throw new RuntimeException('Verzeichnis liess sich nicht schreiben.');
        }

        return ['books' => count($books), 'verses' => $verseCount, 'path' => $path];
    }

    /** @return array<string, array<string, string>> Kapitel => Vers => Erkennungsanfang */
    private static function fingerprintsFromBook(string $xhtml): array
    {
        // Fussnotenzeichen zuerst entfernen, sonst stünde ihre Nummer im Verstext.
        $xhtml = preg_replace('/<a[^>]*epub:type="noteref"[^>]*>.*?<\/a>/su', '', $xhtml) ?? $xhtml;
        if (!preg_match_all(
            '/<span id="vref-(\d+)-(\d+)"[^>]*>.*?<\/span>/su',
            $xhtml,
            $m,
            PREG_OFFSET_CAPTURE
        )) {
            return [];
        }

        $out = [];
        $count = count($m[0]);
        for ($i = 0; $i < $count; $i++) {
            $start = $m[0][$i][1] + strlen($m[0][$i][0]);
            $end = ($i + 1 < $count) ? $m[0][$i + 1][1] : strlen($xhtml);
            $text = html_entity_decode(
                strip_tags(substr($xhtml, $start, $end - $start)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            [$normalized] = self::normalize($text);
            if ($normalized === '') {
                continue;
            }
            $out[$m[1][$i][0]][$m[2][$i][0]] = mb_substr($normalized, 0, self::FINGERPRINT_LENGTH);
        }
        return $out;
    }
}
