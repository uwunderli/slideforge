<?php
/**
 * Folien-Sets (Layout-Sets): mehrere Layout-Folien pro Vorlage, Logos-Platzhalter, Notizen-Reihenfolge.
 */
class LayoutSet
{
    public const LOGOS_ROLES = [
        'document_title',
        'heading1', 'heading2', 'heading3', 'heading4', 'heading5',
        'normal',
        'list_item',
        'lighttext',
        'prompt',
        'scripture_block',
        'scripture_ref',
        'scripture_verse',
        'scripture_inline',
        'meta',
    ];

    /** Standard-Elemente (immer sichtbar im Editor). */
    public const STANDARD_ELEMENT_ROLES = [
        'document_title',
        'subtitle',
        'heading1', 'heading2', 'heading3', 'heading4', 'heading5',
        'normal',
        'list_item',
    ];

    /** Aus Logos-HTML importiert — für Logos-Badge (Untertitel fehlt in Logos). */
    public const LOGOS_IMPORTED_ROLES = [
        'document_title',
        'heading1', 'heading2', 'heading3', 'heading4', 'heading5',
        'normal',
        'list_item',
        'lighttext',
        'prompt',
        'scripture_ref',
        'scripture_verse',
        'scripture_inline',
        'meta',
    ];

    /** Logos-Elemente, die im Set-Zuordnungsdialog per Zone aktiviert/deaktiviert werden können. */
    public const LOGOS_ZONE_ROLES = [
        'document_title',
        'heading1', 'heading2', 'heading3', 'heading4', 'heading5',
        'normal',
        'list_item',
        'lighttext',
        'prompt',
        'scripture_block',
        'scripture_ref',
        'scripture_verse',
        'scripture_inline',
        'meta',
    ];

    /** @deprecated use LOGOS_ZONE_ROLES */
    public const LOGOS_EXTRA_ROLES = self::LOGOS_ZONE_ROLES;

    /** Platzhalter-Rollen für Editor-Buttons aus der Zone „Folien“. */
    public static function slideInsertRolesFromZones(array $zones): array
    {
        $slides = $zones['slides'] ?? self::DEFAULT_ELEMENT_ZONES['slides'];
        $insert = [];
        foreach ($slides as $role) {
            if ($role === 'scripture_block') {
                $insert[] = 'scripture_ref';
                $insert[] = 'scripture_verse';
            } else {
                $insert[] = $role;
            }
        }
        return array_values(array_unique($insert));
    }

    public const ELEMENT_ZONES = ['slides', 'footer', 'custom', 'unused'];

    /** Zonen im Logos-Zuordnungsdialog (ohne Fußzeile). */
    public const ELEMENT_ZONE_UI_KEYS = ['slides', 'custom', 'unused'];

    /** @deprecated use STANDARD_ELEMENT_ROLES */
    public const SLIDE_ELEMENT_ROLES = [
        'document_title',
        'heading1', 'heading2', 'heading3', 'heading4', 'heading5',
        'lighttext',
        'scripture_block',
    ];

    /** @deprecated */
    public const NOTES_ELEMENT_ROLES = [
        'prompt',
        'normal',
        'list_item',
        'scripture_inline',
    ];

    /** Standard-Zuordnung Logos-Element → Layout-Schlüssel im Set. */
    public const DEFAULT_LAYOUT_MAP = [
        'document_title' => 'document_title',
        'subtitle' => 'subtitle',
        'heading1' => 'heading1',
        'heading2' => 'heading2',
        'heading3' => 'heading3',
        'heading4' => 'heading4',
        'heading5' => 'heading5',
        'lighttext' => 'lighttext',
        'scripture_block' => 'scripture_block',
        'scripture_ref' => 'scripture_block',
        'scripture_verse' => 'scripture_block',
    ];

    /** Standard-Reihenfolge für Sprechernotizen beim Import. */
    public const DEFAULT_NOTES_ORDER = [
        'normal',
        'list_item',
        'prompt',
        'scripture_inline',
    ];

    /** Standard-Zuordnung Logos-Element → Import-Zone. */
    public const DEFAULT_ELEMENT_ZONES = [
        'slides' => ['document_title', 'heading1', 'heading2', 'heading3', 'heading4', 'heading5', 'lighttext', 'scripture_block'],
        'footer' => [],
        'custom' => ['normal', 'list_item', 'prompt', 'scripture_inline'],
        'unused' => ['meta', 'scripture_ref', 'scripture_verse'],
    ];

    public const DEFAULT_LOGOS_IMPORT_SETTINGS = [
        'h1Opener' => 'always_separate',
        'scriptureHeading' => 'combine_if_layout_fits',
        'listGrouping' => 'layout',
        'textMaxCharacters' => 500,
    ];

    public static function isLayoutSet(string $id): bool
    {
        $meta = Presentation::getMeta($id);
        return $meta !== null && !empty($meta['is_template']) && !empty($meta['is_layout_set']);
    }

    public static function create(string $ownerId, string $title): string
    {
        $id = Presentation::create($ownerId, $title, DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT, true);
        Presentation::updateMeta($id, [
            'is_layout_set' => true,
            'logosLayoutMap' => self::DEFAULT_LAYOUT_MAP,
            'logosLayoutSlideIds' => [],
            'logosNotesOrder' => self::DEFAULT_NOTES_ORDER,
            'elementZones' => self::DEFAULT_ELEMENT_ZONES,
            'logosImportSettings' => self::DEFAULT_LOGOS_IMPORT_SETTINGS,
        ]);
        self::seedDefaultLayouts($id);
        return $id;
    }

    /** Kopie eines Folien-Sets (eigenes oder freigegebenes) — die Kopie ist immer privat. */
    public static function duplicate(string $sourceId, string $ownerId): string
    {
        if (!self::isLayoutSet($sourceId)) {
            throw new RuntimeException(t('tpl.layout_set_invalid'));
        }
        $meta = Presentation::getMeta($sourceId);
        if (!$meta) {
            throw new RuntimeException(t('tpl.layout_set_invalid'));
        }

        $title = trim((string)($meta['title'] ?? ''));
        if ($title === '') {
            $title = t('tpl.default_layout_set_title');
        }

        $newId = Presentation::create(
            $ownerId,
            $title . ' (Kopie)',
            (int)($meta['width'] ?? DEFAULT_SLIDE_WIDTH),
            (int)($meta['height'] ?? DEFAULT_SLIDE_HEIGHT),
            true
        );

        $copyMeta = [
            'is_layout_set' => true,
            'template_shared' => false,
        ];
        foreach (['logosLayoutMap', 'logosLayoutSlideIds', 'logosNotesOrder', 'elementZones', 'elementTextLinks', 'logosImportSettings', 'safe_margin'] as $key) {
            if (array_key_exists($key, $meta)) {
                $copyMeta[$key] = $meta[$key];
            }
        }
        Presentation::updateMeta($newId, $copyMeta);

        $srcAssets = Presentation::dir($sourceId) . '/assets';
        $dstAssets = Presentation::dir($newId) . '/assets';
        if (is_dir($srcAssets)) {
            foreach (glob($srcAssets . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    copy($file, $dstAssets . '/' . basename($file));
                }
            }
        }

        $slidesData = Storage::read(Presentation::dir($sourceId) . '/slides.json', ['slides' => []]);
        $json = json_encode($slidesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json = str_replace(
            'asset.php?id=' . urlencode($sourceId) . '&',
            'asset.php?id=' . urlencode($newId) . '&',
            $json
        );
        $slidesData = json_decode($json, true) ?? ['slides' => []];
        Storage::write(Presentation::dir($newId) . '/slides.json', $slidesData);

        return $newId;
    }

    public static function seedDefaultLayouts(string $setId): void
    {
        $slides = [];
        $defs = [
            ['layoutKey' => 'document_title', 'setRole' => 'document_title', 'y' => 380, 'h' => 200, 'textTemplateId' => 'title'],
            ['layoutKey' => 'subtitle', 'setRole' => 'subtitle', 'y' => 580, 'h' => 100, 'textTemplateId' => 'subtitle'],
            ['layoutKey' => 'heading1', 'setRole' => 'heading1', 'y' => 120, 'h' => 140, 'textTemplateId' => 'title'],
            ['layoutKey' => 'heading2', 'setRole' => 'heading2', 'y' => 100, 'h' => 100, 'textTemplateId' => '0b3aec509d2e'],
            ['layoutKey' => 'heading3', 'setRole' => 'heading3', 'y' => 100, 'h' => 90, 'textTemplateId' => '6ccca4e48029'],
            ['layoutKey' => 'heading4', 'setRole' => 'heading4', 'y' => 100, 'h' => 80, 'textTemplateId' => '982874cf3eb0'],
            ['layoutKey' => 'heading5', 'setRole' => 'heading5', 'y' => 100, 'h' => 70, 'textTemplateId' => '982874cf3eb0'],
            ['layoutKey' => 'lighttext', 'setRole' => 'lighttext', 'y' => 420, 'h' => 220, 'italic' => true, 'color' => '#aaaaaa'],
        ];
        foreach ($defs as $def) {
            $slides[] = self::makeLayoutSlide($def);
        }
        $slides[] = self::makeScriptureBlockLayoutSlide();
        Storage::write(Presentation::dir($setId) . '/slides.json', ['slides' => $slides]);
    }

    /** @return array<string, array> layoutKey => slide (erste Folie bei doppeltem Schlüssel) */
    public static function layoutsByKey(string $setId): array
    {
        $map = [];
        foreach (Presentation::getSlides($setId)['slides'] ?? [] as $slide) {
            $key = $slide['layoutKey'] ?? '';
            if ($key !== '' && !isset($map[$key])) {
                $map[$key] = $slide;
            }
        }
        return $map;
    }

    public static function findLayout(string $setId, string $layoutKey, ?string $slideId = null): ?array
    {
        if ($slideId !== null && $slideId !== '') {
            $slide = self::findLayoutBySlideId($setId, $slideId);
            return $slide !== null ? self::prepareLayoutSlide($slide) : null;
        }
        $slide = self::layoutsByKey($setId)[$layoutKey] ?? null;
        if (!$slide) {
            return null;
        }
        return self::prepareLayoutSlide($slide);
    }

    /** Layout-Folie im Set eindeutig über slide.id (unabhängig vom layoutKey). */
    public static function findLayoutBySlideId(string $setId, string $slideId): ?array
    {
        $slideId = trim($slideId);
        if ($slideId === '') {
            return null;
        }
        foreach (Presentation::getSlides($setId)['slides'] ?? [] as $slide) {
            if ((string)($slide['id'] ?? '') === $slideId) {
                return $slide;
            }
        }
        return null;
    }

    /** Layout-Folien in Filmstreifen-Reihenfolge (oben → unten). */
    public static function layoutsInOrder(string $setId): array
    {
        if (!self::isLayoutSet($setId)) {
            return [];
        }
        return Presentation::getSlides($setId)['slides'] ?? [];
    }

    /** Findet die passende Layout-Folie für eine Logos-Rolle (Filmstreifen + eindeutige slide.id). */
    public static function findLayoutForRole(string $setId, string $role, ?array $setMeta = null): ?array
    {
        return self::findLayoutSlideForRole($setId, $role, $setMeta);
    }

    /**
     * Sucht die erste Layout-Folie im Filmstreifen mit passendem Platzhalter-Feld.
     * Gibt layoutKey zurück (legacy); Auflösung läuft über findLayoutSlideForRole().
     */
    public static function resolveLayoutKeyForRole(string $setId, string $role, ?array $setMeta = null): string
    {
        $slide = self::findLayoutSlideForRole($setId, $role, $setMeta);
        if ($slide !== null) {
            return (string)($slide['layoutKey'] ?? self::canonicalLogosRole($role));
        }
        $role = self::canonicalLogosRole($role);
        if (isset(self::layoutsByKey($setId)[$role])) {
            return $role;
        }
        return $role;
    }

    /** @return array<string, string> role => slide.id */
    public static function layoutSlideIdMap(array $setMeta): array
    {
        $stored = $setMeta['logosLayoutSlideIds'] ?? [];
        return is_array($stored) ? $stored : [];
    }

    /**
     * Erste passende Layout-Folie im Filmstreifen (oben → unten), eindeutig über slide.id.
     * Bei doppeltem layoutKey wird nicht die letzte Folie genommen, sondern die zuerst passende.
     */
    public static function findLayoutSlideForRole(string $setId, string $role, ?array $setMeta = null): ?array
    {
        $setMeta ??= Presentation::getMeta($setId) ?? [];
        $role = self::canonicalLogosRole($role);
        $slideIds = self::layoutSlideIdMap($setMeta);
        if (!empty($slideIds[$role])) {
            $cached = self::findLayoutSlideById($setId, (string)$slideIds[$role], $role);
            if ($cached !== null) {
                return $cached;
            }
        }
        return self::scanLayoutSlideForRole($setId, $role);
    }

    private static function scanLayoutSlideForRole(string $setId, string $role): ?array
    {
        $role = self::canonicalLogosRole($role);
        foreach (self::layoutSearchRoles($role) as $searchRole) {
            foreach (self::layoutsInOrder($setId) as $slide) {
                $prepared = self::prepareLayoutSlide($slide);
                if (self::slideHasObjectRole($prepared, $searchRole)) {
                    return $prepared;
                }
            }
        }
        return null;
    }

    private static function findLayoutSlideById(string $setId, string $slideId, string $role): ?array
    {
        foreach (self::layoutsInOrder($setId) as $slide) {
            if ((string)($slide['id'] ?? '') !== $slideId) {
                continue;
            }
            $prepared = self::prepareLayoutSlide($slide);
            foreach (self::layoutSearchRoles($role) as $searchRole) {
                if (self::slideHasObjectRole($prepared, $searchRole)) {
                    return $prepared;
                }
            }
            return null;
        }
        return null;
    }

    /** @return list<string> */
    private static function layoutSearchRoles(string $role): array
    {
        return match ($role) {
            'scripture_block' => ['scripture_block', 'scripture_ref', 'scripture_verse'],
            'scripture_ref', 'scripture_verse' => ['scripture_ref', 'scripture_verse', 'scripture_block'],
            default => [$role],
        };
    }

    private static function slideHasObjectRole(array $slide, string $role): bool
    {
        $role = self::canonicalLogosRole($role);
        foreach ($slide['objects'] ?? [] as $obj) {
            if (self::readSetRole($obj) === $role) {
                return true;
            }
        }
        return false;
    }

    /** Liest setRole (legacy: logosRole). */
    private static function readSetRole(array $obj): string
    {
        return self::canonicalLogosRole((string)($obj['setRole'] ?? $obj['logosRole'] ?? ''));
    }

    /** @param array<string, mixed> $obj */
    private static function normalizeSetRoleOnObject(array $obj): array
    {
        if (!empty($obj['logosRole']) && empty($obj['setRole'])) {
            $obj['setRole'] = $obj['logosRole'];
        }
        unset($obj['logosRole']);
        if (!empty($obj['setRole'])) {
            $obj['setRole'] = self::canonicalLogosRole((string)$obj['setRole']);
        }
        return $obj;
    }

    /** Ergänzt fehlende setRole anhand von Platzhaltertext (nicht Folienname). */
    public static function prepareLayoutSlide(array $slide): array
    {
        $key = (string)($slide['layoutKey'] ?? '');
        $slide['objects'] = array_map(
            fn($obj) => self::normalizeSetRoleOnObject($obj),
            self::dedupeLayoutObjects(
                self::resolveLayoutObjectRoles(
                    self::adaptImportedTemplateObjects($slide['objects'] ?? [], $key),
                    $key
                ),
                $key
            )
        );
        return $slide;
    }

    /**
     * Folienvorlagen (z. B. „Titel“ mit Text „Titel“/„Untertitel“) → Logos-Platzhalter.
     * Position, Schrift und Ausrichtung bleiben erhalten.
     *
     * @param list<array> $objects
     * @return list<array>
     */
    private static function adaptImportedTemplateObjects(array $objects, string $layoutKey): array
    {
        $assignedRoles = [];
        foreach ($objects as $obj) {
            $role = self::readSetRole($obj);
            if ($role !== '') {
                $assignedRoles[$role] = true;
            }
        }

        $out = [];
        foreach ($objects as $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                $out[] = $obj;
                continue;
            }

            $role = self::readSetRole($obj);
            $text = trim((string)($obj['text'] ?? ''));
            if ($role !== '') {
                $obj['setRole'] = $role;
                if (!self::isPlaceholderText($text)) {
                    $obj['text'] = self::rolePlaceholderText($role);
                }
                $out[] = $obj;
                continue;
            }

            $inferred = self::inferRoleFromText($text);
            if ($inferred !== null && empty($assignedRoles[$inferred])) {
                $obj['setRole'] = $inferred;
                if (!self::isPlaceholderText($text)) {
                    $obj['text'] = '«' . t('logos.role_' . $inferred) . '»';
                }
                $assignedRoles[$inferred] = true;
                $out[] = $obj;
                continue;
            }

            if (self::isTemplateSampleLabel(self::normalizePlaceholderText($text), $assignedRoles)) {
                continue;
            }

            $out[] = $obj;
        }

        return $out;
    }

    /** @param array<string, bool> $assignedRoles */
    private static function isTemplateSampleLabel(string $normalized, array $assignedRoles): bool
    {
        if (in_array($normalized, ['titel', 'title'], true)) {
            return !empty($assignedRoles['document_title'])
                || !empty($assignedRoles['heading1'])
                || !empty($assignedRoles['heading2'])
                || !empty($assignedRoles['heading3'])
                || !empty($assignedRoles['heading4'])
                || !empty($assignedRoles['heading5']);
        }
        if (in_array($normalized, ['untertitel', 'subtitle'], true)) {
            return !empty($assignedRoles['subtitle']);
        }
        return false;
    }

    /** Einheitliche Logos-Rollen (layoutKey-Aliase, Schreibweisen). */
    public static function canonicalLogosRole(string $role): string
    {
        $role = strtolower(trim($role));
        if ($role === '') {
            return '';
        }
        static $aliases = [
            'ueberschrift' => 'heading1',
            'ueberschrift_1' => 'heading1',
            'ueberschrift1' => 'heading1',
            'heading_1' => 'heading1',
            'ueberschrift_2' => 'heading2',
            'heading_2' => 'heading2',
            'ueberschrift_3' => 'heading3',
            'heading_3' => 'heading3',
            'ueberschrift_4' => 'heading4',
            'heading_4' => 'heading4',
            'ueberschrift_5' => 'heading5',
            'heading_5' => 'heading5',
            'title' => 'document_title',
            'titelfolie' => 'document_title',
            'html_vorlage' => 'document_title',
            'untertitel' => 'subtitle',
        ];
        if (isset($aliases[$role])) {
            return $aliases[$role];
        }
        if (preg_match('/^ueberschrift_(\d)$/', $role, $m)) {
            return 'heading' . $m[1];
        }
        return $role;
    }

    /** @param array<string, string|array> $contentByRole
     * @return array<string, string>
     */
    private static function normalizeContentByRole(array $contentByRole): array
    {
        $out = [];
        foreach ($contentByRole as $role => $value) {
            $canonical = self::canonicalLogosRole((string)$role);
            if ($canonical === '') {
                continue;
            }
            $text = is_array($value) ? trim((string)($value['text'] ?? '')) : trim((string)$value);
            if ($text === '') {
                continue;
            }
            $out[$canonical] = $text;
        }
        return $out;
    }

    /**
     * Entfernt Beispiel-/Doppeltext aus importierten Layouts (z. B. «Überschrift 1» + Überschrift 1).
     *
     * @param list<array> $objects
     * @return list<array>
     */
    private static function dedupeLayoutObjects(array $objects, string $layoutKey): array
    {
        $byRole = [];
        $unroledText = [];
        $nonText = [];

        foreach ($objects as $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                $nonText[] = $obj;
                continue;
            }
            $role = self::readSetRole($obj);
            if ($role !== '') {
                $obj['setRole'] = $role;
                if (!isset($byRole[$role]) || self::preferLayoutTextObject($obj, $byRole[$role])) {
                    $byRole[$role] = $obj;
                }
                continue;
            }
            $unroledText[] = $obj;
        }

        if (!$byRole && !$unroledText) {
            return $objects;
        }

        $rolesPresent = array_fill_keys(array_keys($byRole), true);
        $out = array_values($byRole);
        foreach ($unroledText as $obj) {
            $text = trim((string)($obj['text'] ?? ''));
            if ($text === '') {
                $out[] = $obj;
                continue;
            }
            if (self::isRedundantLayoutSampleText($text, $rolesPresent, $layoutKey)) {
                continue;
            }
            if (self::matchesLayoutRoleText($text, $byRole)) {
                continue;
            }
            $out[] = $obj;
        }

        return array_merge($out, $nonText);
    }

    private static function preferLayoutTextObject(array $a, array $b): bool
    {
        $score = static function (array $obj): int {
            $text = trim((string)($obj['text'] ?? ''));
            $points = 0;
            if (self::isPlaceholderText($text)) {
                $points += 2;
            }
            if ((string)($obj['setRole'] ?? '') !== '') {
                $points += 1;
            }
            return $points;
        };
        return $score($a) >= $score($b);
    }

    /** @param array<string, array> $byRole */
    private static function matchesLayoutRoleText(string $text, array $byRole): bool
    {
        $norm = self::normalizePlaceholderText($text);
        if ($norm === '') {
            return false;
        }
        foreach ($byRole as $role => $obj) {
            $objNorm = self::normalizePlaceholderText((string)($obj['text'] ?? ''));
            if ($norm === $objNorm) {
                return true;
            }
            if ($norm === self::normalizePlaceholderText(t('logos.role_' . $role))) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, bool> $rolesPresent */
    private static function isRedundantLayoutSampleText(string $text, array $rolesPresent, string $layoutKey): bool
    {
        $normalized = self::normalizePlaceholderText($text);
        if ($normalized === '') {
            return false;
        }
        foreach (array_keys($rolesPresent) as $role) {
            if ($normalized === self::normalizePlaceholderText(t('logos.role_' . $role))) {
                return true;
            }
        }
        if (preg_match('/^überschrift\s*(\d)$/u', $normalized, $m)) {
            return !empty($rolesPresent['heading' . $m[1]]);
        }
        if (self::isTemplateSampleLabel($normalized, $rolesPresent)) {
            return true;
        }
        return false;
    }

    public static function primaryLogosRole(array $slide): ?string
    {
        foreach ($slide['objects'] ?? [] as $obj) {
            $role = self::readSetRole($obj);
            if ($role !== '') {
                return $role;
            }
        }
        return null;
    }

    public static function humanizeLayoutKey(string $key): string
    {
        static $words = [
            'ueberschrift' => 'Überschrift',
            'und' => 'und',
            'inhalt' => 'Inhalt',
            'inhalte' => 'Inhalte',
            'zwei' => 'zwei',
            'vergleich' => 'Vergleich',
            'text' => 'Text',
            'document' => 'Dokument',
            'title' => 'Titel',
            'subtitle' => 'Untertitel',
            'heading' => 'Überschrift',
            'scripture' => 'Bibelstelle',
            'block' => 'Block',
            'lighttext' => 'Blockzitat',
        ];
        $parts = array_filter(explode('_', strtolower(trim($key))));
        if (!$parts) {
            return $key;
        }
        return implode(' ', array_map(
            fn($p) => $words[$p] ?? (preg_match('/^\d+$/', $p) ? $p : mb_convert_case($p, MB_CASE_TITLE, 'UTF-8')),
            $parts
        ));
    }

    /** Anzeigename einer Layout-Folie (gespeichertes label oder sinnvoller Fallback). */
    public static function slideLabel(array $slide): string
    {
        $label = trim((string)($slide['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $key = (string)($slide['layoutKey'] ?? '');
        if ($key !== '') {
            $tKey = 'logos.role_' . $key;
            $translated = t($tKey);
            if ($translated !== $tKey) {
                return $translated;
            }
            return self::humanizeLayoutKey($key);
        }
        $role = self::primaryLogosRole($slide);
        if ($role !== null) {
            $tKey = 'logos.role_' . $role;
            $translated = t($tKey);
            if ($translated !== $tKey) {
                return $translated;
            }
        }
        return t('editor.unnamed_slide');
    }

    public static function defaultLabelForLayout(array $def): string
    {
        $role = (string)($def['setRole'] ?? '');
        if ($role !== '') {
            $tKey = 'logos.role_' . $role;
            $translated = t($tKey);
            if ($translated !== $tKey) {
                return $translated;
            }
        }
        $key = (string)($def['layoutKey'] ?? '');
        return $key !== '' ? self::humanizeLayoutKey($key) : t('editor.unnamed_slide');
    }

    /**
     * Übernimmt eine Einzel-Folienvorlage als Layout-Folie in ein Folien-Set (Kopie der Assets inkl.).
     * Existiert layoutKey bereits im Set, wird die Folie ersetzt.
     */
    public static function importSlideTemplate(string $setId, string $templateId, string $layoutKey): void
    {
        $meta = Presentation::getMeta($templateId);
        if (!$meta || empty($meta['is_template']) || !empty($meta['is_layout_set'])) {
            throw new RuntimeException(t('tpl.import_template_invalid'));
        }
        $slide = Presentation::getTemplateSlideContent($templateId);
        if (!$slide) {
            throw new RuntimeException(t('tpl.import_template_empty'));
        }
        $importTitle = trim((string)($meta['title'] ?? ''));
        self::importSlideIntoSet($setId, $templateId, $slide, $layoutKey, $importTitle);
    }

    /**
     * Übernimmt eine Folie aus einer Präsentation als Layout-Folie in ein Folien-Set.
     */
    public static function importPresentationSlide(string $setId, string $presentationId, int $slideIndex, string $layoutKey): void
    {
        $meta = Presentation::getMeta($presentationId);
        if (!$meta || !empty($meta['is_template'])) {
            throw new RuntimeException(t('editor.import_slide_to_set_invalid'));
        }
        $slidesData = Presentation::getSlides($presentationId);
        $slide = $slidesData['slides'][$slideIndex] ?? null;
        if (!$slide) {
            throw new RuntimeException(t('editor.import_slide_to_set_empty'));
        }
        $importTitle = trim((string)($slide['label'] ?? ''));
        if ($importTitle === '') {
            $importTitle = self::slideLabel($slide);
        }
        self::importSlideIntoSet($setId, $presentationId, $slide, $layoutKey, $importTitle);
    }

    /** @param array<string, mixed> $slide */
    private static function importSlideIntoSet(
        string $setId,
        string $sourceId,
        array $slide,
        string $layoutKey,
        string $importTitle = ''
    ): void {
        if (!self::isLayoutSet($setId)) {
            throw new RuntimeException(t('tpl.layout_set_invalid'));
        }
        $layoutKey = preg_replace('/[^a-z0-9_\-]/i', '_', trim($layoutKey));
        if ($layoutKey === '') {
            throw new RuntimeException(t('tpl.layout_key_required'));
        }

        $srcAssets = Presentation::dir($sourceId) . '/assets';
        $dstAssets = Presentation::dir($setId) . '/assets';
        if (is_dir($srcAssets)) {
            if (!is_dir($dstAssets)) {
                mkdir($dstAssets, 0770, true);
            }
            foreach (glob($srcAssets . '/*') ?: [] as $file) {
                $dst = $dstAssets . '/' . basename($file);
                if (!is_file($dst)) {
                    copy($file, $dst);
                }
            }
        }

        $json = json_encode($slide, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json = str_replace(
            'asset.php?id=' . urlencode($sourceId) . '&',
            'asset.php?id=' . urlencode($setId) . '&',
            $json
        );
        $slide = json_decode($json, true) ?? [];
        $slide['id'] = Storage::generateId(4);
        $slide['layoutKey'] = $layoutKey;
        if ($importTitle !== '') {
            $slide['label'] = $importTitle;
        } elseif (empty($slide['label'])) {
            $slide['label'] = self::slideLabel($slide);
        }
        $slide = self::prepareLayoutSlide($slide);

        Storage::update(Presentation::dir($setId) . '/slides.json', function ($data) use ($slide, $layoutKey) {
            $replaced = false;
            foreach ($data['slides'] as $i => $s) {
                if (($s['layoutKey'] ?? '') === $layoutKey) {
                    $data['slides'][$i] = $slide;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                $data['slides'][] = $slide;
            }
            return $data;
        }, ['slides' => []]);

        self::syncLayoutMap($setId);
    }

    /** Exportiert ein Folien-Set als ZIP-Archiv (Dateiendung kann frei gewählt werden, z. B. .chs). */
    public static function isZipArchiveFile(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return false;
        }
        $magic = fread($fh, 4);
        fclose($fh);
        return in_array($magic, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }

    public static function isAllowedArchiveUpload(string $path, string $originalName = ''): bool
    {
        $name = strtolower(trim($originalName));
        if ($name !== '' && (str_ends_with($name, '.zip') || str_ends_with($name, '.chs'))) {
            return true;
        }
        return self::isZipArchiveFile($path);
    }

    public static function isLayoutSetArchiveFile(string $path): bool
    {
        if (!self::isZipArchiveFile($path) || !class_exists('ZipArchive')) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('meta.json') !== false && $zip->locateName('slides.json') !== false;
        $zip->close();
        return $ok;
    }

    public static function exportArchive(string $setId, string $archivePath): void
    {
        if (!self::isLayoutSet($setId)) {
            throw new RuntimeException(t('tpl.layout_set_invalid'));
        }
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(t('tpl.layout_set_zip_unavailable'));
        }
        $meta = Presentation::getMeta($setId);
        if (!$meta) {
            throw new RuntimeException(t('tpl.layout_set_invalid'));
        }

        $tmpDir = sys_get_temp_dir() . '/sf_layoutset_export_' . bin2hex(random_bytes(6));
        mkdir($tmpDir, 0770, true);
        mkdir($tmpDir . '/assets', 0770, true);
        try {
            $exportMeta = [
                'title' => (string)($meta['title'] ?? t('tpl.default_layout_set_title')),
                'width' => (int)($meta['width'] ?? DEFAULT_SLIDE_WIDTH),
                'height' => (int)($meta['height'] ?? DEFAULT_SLIDE_HEIGHT),
                'template_order' => (int)($meta['template_order'] ?? 0),
                'is_layout_set' => true,
                'template_shared' => !empty($meta['template_shared']),
                'safe_margin' => (int)($meta['safe_margin'] ?? 100),
            ];
            foreach (['logosLayoutMap', 'logosLayoutSlideIds', 'logosNotesOrder', 'elementZones', 'elementTextLinks', 'logosImportSettings'] as $key) {
                if (array_key_exists($key, $meta)) {
                    $exportMeta[$key] = $meta[$key];
                }
            }
            file_put_contents(
                $tmpDir . '/meta.json',
                json_encode($exportMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );

            $slidesData = Storage::read(Presentation::dir($setId) . '/slides.json', ['slides' => []]);
            file_put_contents(
                $tmpDir . '/slides.json',
                json_encode($slidesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );

            $srcAssets = Presentation::dir($setId) . '/assets';
            if (is_dir($srcAssets)) {
                foreach (glob($srcAssets . '/*') ?: [] as $file) {
                    if (is_file($file)) {
                        copy($file, $tmpDir . '/assets/' . basename($file));
                    }
                }
            }

            $zip = new ZipArchive();
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException(t('tpl.layout_set_zip_failed'));
            }
            $zip->addFile($tmpDir . '/meta.json', 'meta.json');
            $zip->addFile($tmpDir . '/slides.json', 'slides.json');
            foreach (glob($tmpDir . '/assets/*') ?: [] as $file) {
                if (is_file($file)) {
                    $zip->addFile($file, 'assets/' . basename($file));
                }
            }
            $zip->close();
        } finally {
            self::removeDirRecursive($tmpDir);
        }
    }

    /** Importiert ein Folien-Set aus ZIP/.chs und legt eine private Kopie an. */
    public static function importArchive(string $ownerId, string $archivePath): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(t('tpl.layout_set_zip_unavailable'));
        }
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException(t('tpl.layout_set_zip_open_failed'));
        }
        $tmpDir = sys_get_temp_dir() . '/sf_layoutset_import_' . bin2hex(random_bytes(6));
        mkdir($tmpDir, 0770, true);
        try {
            $zip->extractTo($tmpDir);
            $zip->close();

            $metaPath = $tmpDir . '/meta.json';
            $slidesPath = $tmpDir . '/slides.json';
            if (!is_file($metaPath) || !is_file($slidesPath)) {
                throw new RuntimeException(t('tpl.layout_set_archive_invalid'));
            }
            $importMeta = json_decode((string)file_get_contents($metaPath), true);
            $slidesData = json_decode((string)file_get_contents($slidesPath), true);
            if (!is_array($importMeta) || !is_array($slidesData)) {
                throw new RuntimeException(t('tpl.layout_set_archive_invalid'));
            }

            $newId = Presentation::create(
                $ownerId,
                trim((string)($importMeta['title'] ?? '')) !== '' ? (string)$importMeta['title'] : t('tpl.default_layout_set_title'),
                (int)($importMeta['width'] ?? DEFAULT_SLIDE_WIDTH),
                (int)($importMeta['height'] ?? DEFAULT_SLIDE_HEIGHT),
                true
            );

            $json = json_encode($slidesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $json = preg_replace('/asset\.php\?id=[^&]+&/u', 'asset.php?id=' . urlencode($newId) . '&', (string)$json);
            $slidesData = json_decode((string)$json, true) ?? ['slides' => []];
            $preparedSlides = [];
            foreach ($slidesData['slides'] ?? [] as $slide) {
                if (is_array($slide)) {
                    $preparedSlides[] = self::prepareLayoutSlide($slide);
                }
            }
            Storage::write(Presentation::dir($newId) . '/slides.json', ['slides' => $preparedSlides]);

            $srcAssets = $tmpDir . '/assets';
            $dstAssets = Presentation::dir($newId) . '/assets';
            if (is_dir($srcAssets)) {
                foreach (glob($srcAssets . '/*') ?: [] as $file) {
                    if (is_file($file)) {
                        copy($file, $dstAssets . '/' . basename($file));
                    }
                }
            }

            $metaUpdates = [
                'is_layout_set' => true,
                'template_shared' => false,
                'template_order' => microtime(true),
            ];
            foreach (['logosLayoutMap', 'logosLayoutSlideIds', 'logosNotesOrder', 'elementZones', 'elementTextLinks', 'logosImportSettings', 'safe_margin'] as $key) {
                if (array_key_exists($key, $importMeta)) {
                    $metaUpdates[$key] = $importMeta[$key];
                }
            }
            Presentation::updateMeta($newId, $metaUpdates);
            self::syncLayoutMap($newId);
            return $newId;
        } finally {
            self::removeDirRecursive($tmpDir);
        }
    }

    private static function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_dir($f)) {
                self::removeDirRecursive($f);
            } else {
                @unlink($f);
            }
        }
        @rmdir($dir);
    }

    public static function layoutKeyFromTitle(string $title): string
    {
        $key = strtolower(trim($title));
        $key = preg_replace('/[^a-z0-9]+/u', '_', $key) ?? '';
        $key = trim($key, '_');
        return $key !== '' ? $key : 'layout';
    }

    /** Effektiver Layout-Schlüssel (gespeichert, aus Label oder slide.id). */
    public static function resolveSlideLayoutKey(array $slide): string
    {
        $key = trim((string)($slide['layoutKey'] ?? ''));
        if ($key !== '') {
            return $key;
        }
        $label = trim((string)($slide['label'] ?? ''));
        if ($label !== '') {
            return self::layoutKeyFromTitle($label);
        }
        $id = trim((string)($slide['id'] ?? ''));
        return $id !== '' ? 'slide_' . $id : '';
    }

    /** Eindeutigen layoutKey für eine neue Layout-Folie vergeben. */
    public static function assignLayoutKeyForSlide(string $setId, array $slide, ?string $preferredKey = null): string
    {
        $existing = [];
        $slideId = (string)($slide['id'] ?? '');
        foreach (Presentation::getSlides($setId)['slides'] ?? [] as $s) {
            if ($slideId !== '' && (string)($s['id'] ?? '') === $slideId) {
                continue;
            }
            $key = self::resolveSlideLayoutKey($s);
            if ($key !== '') {
                $existing[] = $key;
            }
        }
        $base = trim((string)($preferredKey ?? ''));
        if ($base === '') {
            $label = trim((string)($slide['label'] ?? ''));
            $base = $label !== '' ? self::layoutKeyFromTitle($label) : '';
        }
        if ($base === '') {
            $id = trim((string)($slide['id'] ?? ''));
            $base = $id !== '' ? 'slide_' . $id : 'layout';
        }
        $key = $base;
        $suffix = 2;
        while (in_array($key, $existing, true)) {
            $key = $base . '_' . $suffix;
            $suffix++;
        }
        return $key;
    }

    public static function layoutMap(array $setMeta): array
    {
        return array_merge(self::DEFAULT_LAYOUT_MAP, $setMeta['logosLayoutMap'] ?? []);
    }

    /** Baut logosLayoutMap und logosLayoutSlideIds aus Platzhalter-Feldern im Filmstreifen. */
    public static function syncLayoutMap(string $setId): void
    {
        if (!self::isLayoutSet($setId)) {
            return;
        }
        if (!self::layoutsInOrder($setId)) {
            return;
        }
        $roles = [
            'document_title', 'subtitle',
            'heading1', 'heading2', 'heading3', 'heading4', 'heading5',
            'lighttext', 'scripture_block', 'scripture_ref', 'scripture_verse',
        ];
        $map = [];
        $slideIds = [];
        foreach ($roles as $role) {
            $slide = self::scanLayoutSlideForRole($setId, $role);
            if ($slide === null) {
                continue;
            }
            $key = (string)($slide['layoutKey'] ?? '');
            $id = (string)($slide['id'] ?? '');
            if ($key !== '') {
                $map[$role] = $key;
            }
            if ($id !== '') {
                $slideIds[$role] = $id;
            }
        }
        Presentation::updateMeta($setId, [
            'logosLayoutMap' => $map,
            'logosLayoutSlideIds' => $slideIds,
        ]);
    }

    public static function notesOrder(array $setMeta): array
    {
        $order = $setMeta['logosNotesOrder'] ?? self::DEFAULT_NOTES_ORDER;
        return array_values(array_filter($order, fn($t) => in_array($t, self::LOGOS_ROLES, true)));
    }

    /** @return array{slides: list<string>, footer: list<string>, custom: list<string>, unused: list<string>} */
    public static function elementZones(array $setMeta): array
    {
        $defaults = self::DEFAULT_ELEMENT_ZONES;
        $stored = $setMeta['elementZones'] ?? [];
        $result = [];
        foreach (self::ELEMENT_ZONES as $zone) {
            $roles = $stored[$zone] ?? $defaults[$zone] ?? [];
            if (!is_array($roles)) {
                $roles = [];
            }
            $result[$zone] = array_values(array_unique(array_filter(
                array_map('strval', $roles),
                fn($r) => in_array($r, self::LOGOS_ZONE_ROLES, true)
            )));
        }
        $assigned = [];
        foreach ($result as $roles) {
            foreach ($roles as $r) {
                $assigned[$r] = true;
            }
        }
        foreach (self::LOGOS_ZONE_ROLES as $role) {
            if (!isset($assigned[$role])) {
                $result['unused'][] = $role;
            }
        }
        if (!empty($result['footer'])) {
            foreach ($result['footer'] as $role) {
                if (!in_array($role, $result['unused'], true)) {
                    $result['unused'][] = $role;
                }
            }
            $result['footer'] = [];
        }
        return $result;
    }

    /** @return array{h1Opener: string, scriptureHeading: string, listGrouping: string|int, textMaxCharacters: string|int} */
    public static function logosImportSettings(array $setMeta): array
    {
        $stored = $setMeta['logosImportSettings'] ?? [];
        return self::normalizeLogosImportSettings(is_array($stored) ? $stored : []);
    }

    /** @param array<string, mixed> $raw */
    public static function normalizeLogosImportSettings(array $raw): array
    {
        $out = self::DEFAULT_LOGOS_IMPORT_SETTINGS;
        $h1 = (string)($raw['h1Opener'] ?? $out['h1Opener']);
        if (in_array($h1, ['always_separate', 'combine_with_first'], true)) {
            $out['h1Opener'] = $h1;
        }
        $sh = (string)($raw['scriptureHeading'] ?? $out['scriptureHeading']);
        if (in_array($sh, ['scripture_always_separate', 'combine_if_layout_fits', 'always_combined'], true)) {
            $out['scriptureHeading'] = $sh;
        }
        $lg = $raw['listGrouping'] ?? $out['listGrouping'];
        if ($lg === 'layout' || $lg === 0 || $lg === '0') {
            $out['listGrouping'] = $lg === 0 || $lg === '0' ? 0 : 'layout';
        } elseif (in_array((int)$lg, [1, 3, 5], true)) {
            $out['listGrouping'] = (int)$lg;
        }
        $tc = $raw['textMaxCharacters'] ?? $out['textMaxCharacters'];
        if ($tc === 'layout' || $tc === 0 || $tc === '0') {
            $out['textMaxCharacters'] = $tc === 0 || $tc === '0' ? 0 : 'layout';
        } elseif (is_numeric($tc) && (int)$tc > 0) {
            $out['textMaxCharacters'] = (int)$tc;
        }
        return $out;
    }

    public static function zoneForRole(array $setMeta, string $role): string
    {
        if (!in_array($role, self::LOGOS_ZONE_ROLES, true)) {
            return 'unused';
        }
        foreach (self::elementZones($setMeta) as $zone => $roles) {
            if (in_array($role, $roles, true)) {
                return $zone;
            }
        }
        return 'unused';
    }

    /** @return array<string, string|null> role => textTemplateId (Set-spezifisch, Fallback: global) */
    public static function elementTextLinks(array $setMeta): array
    {
        $out = ElementLink::map();
        $stored = $setMeta['elementTextLinks'] ?? null;
        if (!is_array($stored)) {
            return $out;
        }
        foreach (ElementLink::allRoles() as $role) {
            if (!array_key_exists($role, $stored)) {
                continue;
            }
            $val = $stored[$role];
            $out[$role] = ($val !== null && $val !== '') ? (string)$val : null;
        }
        return $out;
    }

    public static function textTemplateIdForRole(string $role, ?array $setMeta = null): ?string
    {
        if ($setMeta !== null) {
            $id = self::elementTextLinks($setMeta)[$role] ?? null;
            if ($id !== null && $id !== '') {
                return $id;
            }
        }
        return ElementLink::textTemplateId($role);
    }

    public static function styleForRole(string $role, array $placement = [], ?array $setMeta = null): array
    {
        $style = $placement;
        $tpl = TextTemplate::resolve(self::textTemplateIdForRole($role, $setMeta));
        if ($tpl) {
            $style = array_merge($tpl, $placement);
        }
        return $style;
    }

    /**
     * Wendet ein Layout aus einem Set auf eine Folie an — Inhalt (setRole-Objekte) bleibt erhalten.
     */
    public static function applyLayoutToSlide(array $currentSlide, array $layoutSlide): array
    {
        $layoutObjects = self::resolveLayoutObjectRoles(
            $layoutSlide['objects'] ?? [],
            (string)($layoutSlide['layoutKey'] ?? '')
        );
        $currentObjects = $currentSlide['objects'] ?? [];

        $contentByRole = [];
        $extraByRole = [];
        $freeObjects = [];
        foreach ($currentObjects as $obj) {
            $role = self::readSetRole($obj);
            if ($role !== '') {
                $obj['setRole'] = $role;
                if (!isset($contentByRole[$role])) {
                    $contentByRole[$role] = $obj;
                } else {
                    $extraByRole[$role][] = $obj;
                }
            } else {
                $freeObjects[] = $obj;
            }
        }

        self::assignFreeContentToRoles(
            $contentByRole,
            $extraByRole,
            $freeObjects,
            $layoutObjects,
            (string)($layoutSlide['layoutKey'] ?? '')
        );

        $merged = [];
        $filledRoles = [];
        $placedTexts = [];

        foreach ($layoutObjects as $layoutObj) {
            $role = self::readSetRole($layoutObj);
            if ($role !== '') {
                if (!empty($filledRoles[$role])) {
                    continue;
                }
                $content = $contentByRole[$role] ?? null;
                $merged[] = self::mergePlaceholderObject($layoutObj, $content);
                if ($content !== null) {
                    $contentText = self::contentTextFromRoleMap($contentByRole, $role);
                    if ($contentText !== '') {
                        $placedTexts[] = self::normalizePlaceholderText($contentText);
                    }
                }
                unset($contentByRole[$role]);
                $filledRoles[$role] = true;
            } else {
                if (self::shouldSkipLayoutObject($layoutObj, $filledRoles, $contentByRole, (string)($layoutSlide['layoutKey'] ?? ''), $placedTexts)) {
                    continue;
                }
                if (($layoutObj['type'] ?? '') === 'text') {
                    continue;
                }
                $merged[] = $layoutObj;
            }
        }

        foreach ($contentByRole as $obj) {
            $merged[] = $obj;
        }
        foreach ($extraByRole as $objs) {
            foreach ($objs as $obj) {
                $merged[] = $obj;
            }
        }
        foreach ($freeObjects as $obj) {
            if (self::isDuplicateOfMergedText($obj, $merged)) {
                continue;
            }
            $merged[] = $obj;
        }

        $merged = self::stripDuplicateTextObjects($merged);

        return [
            'id' => $currentSlide['id'] ?? Storage::generateId(4),
            'background' => $layoutSlide['background'] ?? ($currentSlide['background'] ?? ['type' => 'color', 'value' => '#111111']),
            'transition' => $currentSlide['transition'] ?? ($layoutSlide['transition'] ?? 'slide'),
            'autoAdvance' => $currentSlide['autoAdvance'] ?? ($layoutSlide['autoAdvance'] ?? 0),
            'notes' => $currentSlide['notes'] ?? '',
            'layoutKey' => $layoutSlide['layoutKey'] ?? ($currentSlide['layoutKey'] ?? ''),
            'layoutSetSlideId' => (string)($layoutSlide['id'] ?? ($currentSlide['layoutSetSlideId'] ?? '')),
            'label' => $currentSlide['label'] ?? ($layoutSlide['label'] ?? null),
            'objects' => $merged,
            'presentDisabled' => $currentSlide['presentDisabled'] ?? false,
        ];
    }

    /** @param list<array> $layoutObjects */
    private static function resolveLayoutObjectRoles(array $layoutObjects, string $layoutKey): array
    {
        $resolved = [];
        foreach ($layoutObjects as $layoutObj) {
            $next = $layoutObj;
            if (($next['setRole'] ?? '') !== '') {
                $next['setRole'] = self::canonicalLogosRole((string)$next['setRole']);
            } elseif (($next['type'] ?? '') === 'text') {
                $inferred = self::inferRoleFromText((string)($next['text'] ?? ''));
                if ($inferred !== null) {
                    $next['setRole'] = self::canonicalLogosRole($inferred);
                }
            }
            $resolved[] = $next;
        }
        return $resolved;
    }

    /**
     * Ordnet Text ohne setRole Platzhalter-Rollen zu (z. B. manuell eingegebene Überschrift).
     *
     * @param array<string, array> $contentByRole
     * @param array<string, list<array>> $extraByRole
     * @param list<array> $freeObjects
     * @param list<array> $layoutObjects
     */
    private static function assignFreeContentToRoles(
        array &$contentByRole,
        array &$extraByRole,
        array &$freeObjects,
        array $layoutObjects,
        string $layoutKey = ''
    ): void
    {
        $neededRoles = [];
        foreach ($layoutObjects as $layoutObj) {
            $role = (string)($layoutObj['setRole'] ?? '');
            if ($role !== '' && !isset($contentByRole[$role])) {
                $neededRoles[] = $role;
            }
        }
        if (!$neededRoles || !$freeObjects) {
            return;
        }

        foreach ($neededRoles as $role) {
            if (isset($contentByRole[$role])) {
                continue;
            }
            $layoutObj = self::findLayoutObjectForRole($layoutObjects, $role);
            if (!$layoutObj) {
                continue;
            }
            $want = self::normalizePlaceholderText((string)($layoutObj['text'] ?? ''));
            $roleLabel = self::normalizePlaceholderText(t('logos.role_' . $role));

            foreach ($freeObjects as $idx => $freeObj) {
                if (($freeObj['type'] ?? '') !== 'text') {
                    continue;
                }
                $have = self::normalizePlaceholderText((string)($freeObj['text'] ?? ''));
                if ($have === '' || self::isPlaceholderText((string)($freeObj['text'] ?? ''))) {
                    continue;
                }
                if ($have === $want || $have === $roleLabel || self::textMatchesRole($have, $role)) {
                    $matched = $freeObj;
                    self::assignContentObjectToRole($contentByRole, $extraByRole, $role, $matched);
                    unset($freeObjects[$idx]);
                    break;
                }
            }
        }

        $freeTexts = array_values(array_filter(
            $freeObjects,
            fn($obj) => ($obj['type'] ?? '') === 'text' && trim((string)($obj['text'] ?? '')) !== ''
                && !self::isPlaceholderText((string)($obj['text'] ?? ''))
        ));
        $stillNeeded = array_values(array_filter(
            $neededRoles,
            fn($role) => !isset($contentByRole[$role])
        ));
        if (!$stillNeeded || !$freeTexts) {
            return;
        }

        $primaryRole = self::primaryLogosRole(['objects' => $layoutObjects]);
        if ($primaryRole !== null && in_array($primaryRole, $stillNeeded, true)) {
            $stillNeeded = array_values(array_merge(
                [$primaryRole],
                array_values(array_filter($stillNeeded, fn($r) => $r !== $primaryRole))
            ));
        }

        while ($stillNeeded !== [] && $freeTexts !== []) {
            $role = array_shift($stillNeeded);
            $matched = array_shift($freeTexts);
            self::assignContentObjectToRole($contentByRole, $extraByRole, $role, $matched);
            $matchedId = $matched['id'] ?? null;
            foreach ($freeObjects as $idx => $obj) {
                if ($matchedId !== null && ($obj['id'] ?? null) === $matchedId) {
                    unset($freeObjects[$idx]);
                    break;
                }
            }
        }
    }

    /** @param array<string, array> $contentByRole @param array<string, list<array>> $extraByRole */
    private static function assignContentObjectToRole(array &$contentByRole, array &$extraByRole, string $role, array $obj): void
    {
        $role = self::canonicalLogosRole($role);
        $obj['setRole'] = $role;
        if (!isset($contentByRole[$role])) {
            $contentByRole[$role] = $obj;
            return;
        }
        $extraByRole[$role][] = $obj;
    }

    private static function textMatchesRole(string $normalizedText, string $role): bool
    {
        if (preg_match('/^überschrift\s*(\d)$/u', $normalizedText, $m)) {
            return $role === 'heading' . $m[1];
        }
        return false;
    }

    private static function findLayoutObjectForRole(array $layoutObjects, string $role): ?array
    {
        $role = self::canonicalLogosRole($role);
        foreach ($layoutObjects as $layoutObj) {
            if (self::canonicalLogosRole((string)($layoutObj['setRole'] ?? '')) === $role) {
                return $layoutObj;
            }
        }
        return null;
    }

    /** @param list<array> $layoutObjects */
    private static function findUnroledContentTextObject(array $layoutObjects): ?array
    {
        foreach ($layoutObjects as $layoutObj) {
            if (($layoutObj['type'] ?? '') !== 'text') {
                continue;
            }
            if (self::readSetRole($layoutObj) !== '') {
                continue;
            }
            return $layoutObj;
        }
        return null;
    }

    public static function rolePlaceholderText(string $role): string
    {
        $role = self::canonicalLogosRole($role);
        return '«' . t('logos.role_' . $role) . '»';
    }

    private static function isPlaceholderText(string $text): bool
    {
        $text = trim($text);
        return $text !== '' && preg_match('/^«.+»$/u', $text) === 1;
    }

    /**
     * Wählt die Layout-Folie mit dem besten Rollen-Match für mehrere Inhaltsrollen (Layout-Scoring).
     *
     * @param array<string, string> $contentByRole
     */
    public static function bestLayoutForContent(string $setId, array $contentByRole, ?array $setMeta = null): ?array
    {
        $roles = [];
        foreach ($contentByRole as $role => $text) {
            if (trim((string)$text) === '') {
                continue;
            }
            $canonical = self::canonicalLogosRole((string)$role);
            if ($canonical !== '') {
                $roles[] = $canonical;
            }
        }
        $roles = array_values(array_unique($roles));
        if ($roles === []) {
            return null;
        }

        $best = null;
        $bestScore = PHP_INT_MIN;
        foreach (self::layoutsInOrder($setId) as $slide) {
            $prepared = self::prepareLayoutSlide($slide);
            $score = self::scoreLayoutForRoles($prepared, $roles);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $prepared;
            }
        }
        if ($best === null || $bestScore < count($roles) * 10) {
            return null;
        }
        return $best;
    }

    /** @param list<string> $roles */
    private static function scoreLayoutForRoles(array $layoutSlide, array $roles): int
    {
        $layoutRoles = [];
        foreach ($layoutSlide['objects'] ?? [] as $obj) {
            $role = self::readSetRole($obj);
            if ($role !== '') {
                $layoutRoles[$role] = true;
            }
        }
        $layoutKey = (string)($layoutSlide['layoutKey'] ?? '');
        foreach (self::rolesForLayoutKey($layoutKey) ?? [] as $expectedRole) {
            if ($expectedRole !== 'subtitle') {
                $layoutRoles[self::canonicalLogosRole($expectedRole)] = true;
            }
        }
        $score = 0;
        foreach ($roles as $role) {
            if (!empty($layoutRoles[$role])) {
                $score += 10;
            } elseif ($role === 'list_item' && !empty($layoutRoles['normal'])) {
                $score += 10;
            } elseif ($role === 'normal' && !empty($layoutRoles['list_item'])) {
                $score += 10;
            } else {
                return PHP_INT_MIN;
            }
        }
        $matchedRoles = [];
        foreach ($roles as $role) {
            $matchedRoles[$role] = true;
            if ($role === 'list_item') {
                $matchedRoles['normal'] = true;
            }
            if ($role === 'normal') {
                $matchedRoles['list_item'] = true;
            }
        }
        $extra = 0;
        foreach (array_keys($layoutRoles) as $layoutRole) {
            if (empty($matchedRoles[$layoutRole]) && $layoutRole !== 'subtitle') {
                $extra++;
            }
        }
        if ($extra > 0) {
            $score -= $extra * 2;
        }
        return $score;
    }

    /**
     * Layout-Objekt überspringen, wenn Inhalt für dieselbe Rolle bereits gesetzt ist
     * (z. B. «Überschrift 1» + Überschrift 1 als Doppel im importierten Layout).
     *
     * @param array<string, string|array> $contentByRole
     * @param list<string> $placedNormalizedTexts
     */
    private static function shouldSkipLayoutObject(
        array $layoutObj,
        array $filledRoles,
        array $contentByRole,
        string $layoutKey,
        array $placedNormalizedTexts = []
    ): bool {
        if (($layoutObj['type'] ?? '') !== 'text') {
            return false;
        }
        $text = trim((string)($layoutObj['text'] ?? ''));
        if ($text === '') {
            return false;
        }
        $normalized = self::normalizePlaceholderText($text);

        foreach ($contentByRole as $role => $_) {
            $contentText = self::contentTextFromRoleMap($contentByRole, (string)$role);
            if ($contentText !== '' && self::normalizePlaceholderText($contentText) === $normalized) {
                return true;
            }
        }

        if (self::isPlaceholderText($text)) {
            $inferred = self::inferRoleFromPlaceholderText($text, $layoutKey);
            if ($inferred !== null && (
                !empty($filledRoles[$inferred])
                || self::contentTextFromRoleMap($contentByRole, $inferred) !== ''
            )) {
                return true;
            }
        }

        if (preg_match('/^überschrift\s*(\d)$/u', $normalized, $m)) {
            $candidate = 'heading' . $m[1];
            if (!empty($filledRoles[$candidate]) || self::contentTextFromRoleMap($contentByRole, $candidate) !== '') {
                return true;
            }
        }

        foreach ($filledRoles as $role => $_) {
            $contentText = self::contentTextFromRoleMap($contentByRole, (string)$role);
            if ($contentText !== '' && self::normalizePlaceholderText($contentText) === $normalized) {
                return true;
            }
        }
        foreach ($placedNormalizedTexts as $placed) {
            if ($placed !== '' && $placed === $normalized) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string|array> $contentByRole */
    private static function contentTextFromRoleMap(array $contentByRole, string $role): string
    {
        if (array_key_exists($role, $contentByRole)) {
            $value = $contentByRole[$role];
            if (is_array($value)) {
                return trim((string)($value['text'] ?? ''));
            }
            return trim((string)$value);
        }
        if ($role === 'normal' && array_key_exists('list_item', $contentByRole)) {
            return trim((string)$contentByRole['list_item']);
        }
        if ($role === 'list_item' && array_key_exists('normal', $contentByRole)) {
            return trim((string)$contentByRole['normal']);
        }
        if (str_starts_with($role, 'heading')) {
            foreach ($contentByRole as $contentRole => $value) {
                if (!str_starts_with((string)$contentRole, 'heading')) {
                    continue;
                }
                if (is_array($value)) {
                    return trim((string)($value['text'] ?? ''));
                }
                return trim((string)$value);
            }
        }
        return '';
    }

    /**
     * Entfernt doppelte Textobjekte nach Merge (Platzhalter + gleicher Inhalt).
     *
     * @param list<array> $objects
     * @return list<array>
     */
    private static function compactDuplicateTextObjects(array $objects): array
    {
        $kept = [];
        $drop = [];
        foreach ($objects as $idx => $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                continue;
            }
            $text = trim((string)($obj['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $norm = self::normalizePlaceholderText($text);
            $role = (string)($obj['setRole'] ?? '');
            $key = $role !== '' ? ('role:' . $role . ':text:' . $norm) : ('text:' . $norm);
            if (!isset($kept[$key])) {
                $kept[$key] = $idx;
                continue;
            }
            $prevIdx = $kept[$key];
            $prev = $objects[$prevIdx];
            $prevText = trim((string)($prev['text'] ?? ''));
            $keepIdx = self::preferTextObject($prev, $obj) ? $prevIdx : $idx;
            $dropIdx = $keepIdx === $prevIdx ? $idx : $prevIdx;
            $kept[$key] = $keepIdx;
            $drop[$dropIdx] = true;
            if ($norm !== '' && $role === '') {
                $kept['text:' . $norm] = $keepIdx;
            }
        }

        foreach ($objects as $idx => $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                continue;
            }
            if (self::readSetRole($obj) !== '') {
                continue;
            }
            $text = trim((string)($obj['text'] ?? ''));
            if ($text === '' || isset($drop[$idx])) {
                continue;
            }
            $norm = self::normalizePlaceholderText($text);
            if ($norm === '') {
                continue;
            }
            foreach ($objects as $otherIdx => $other) {
                if ($otherIdx === $idx || isset($drop[$otherIdx]) || ($other['type'] ?? '') !== 'text') {
                    continue;
                }
                if (self::readSetRole($other) !== '') {
                    continue;
                }
                $otherNorm = self::normalizePlaceholderText((string)($other['text'] ?? ''));
                if ($otherNorm !== $norm) {
                    continue;
                }
                $keepIdx = self::preferTextObject($objects[$idx], $objects[$otherIdx]) ? $idx : $otherIdx;
                $dropIdx = $keepIdx === $idx ? $otherIdx : $idx;
                $drop[$dropIdx] = true;
            }
        }

        $out = [];
        foreach ($objects as $idx => $obj) {
            if (!isset($drop[$idx])) {
                $out[] = $obj;
            }
        }
        return $out;
    }

    private static function preferTextObject(array $a, array $b): bool
    {
        $score = static function (array $obj): int {
            $text = trim((string)($obj['text'] ?? ''));
            $points = 0;
            if ((string)($obj['setRole'] ?? '') !== '') {
                $points += 4;
            }
            if ($text !== '' && !self::isPlaceholderText($text)) {
                $points += 2;
            }
            return $points;
        };
        return $score($a) >= $score($b);
    }

    /**
     * Entfernt Platzhalter und doppelte Texte nach dem Layout-Merge.
     *
     * @param list<array> $objects
     * @return list<array>
     */
    private static function stripDuplicateTextObjects(array $objects): array
    {
        $objects = self::compactDuplicateTextObjects($objects);

        $realNorms = [];
        foreach ($objects as $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                continue;
            }
            $text = trim((string)($obj['text'] ?? ''));
            if ($text === '' || self::isPlaceholderText($text)) {
                continue;
            }
            $realNorms[self::normalizePlaceholderText($text)] = true;
        }

        $out = [];
        foreach ($objects as $obj) {
            if (($obj['type'] ?? '') !== 'text') {
                $out[] = $obj;
                continue;
            }
            $text = trim((string)($obj['text'] ?? ''));
            if ($text !== '' && self::isPlaceholderText($text)) {
                $norm = self::normalizePlaceholderText($text);
                if (!empty($realNorms[$norm])) {
                    continue;
                }
            }
            $out[] = $obj;
        }

        return $out;
    }

    private static function normalizePlaceholderText(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^«(.+)»$/u', $text, $m)) {
            $text = trim($m[1]);
        }
        return mb_strtolower($text, 'UTF-8');
    }

    private static function inferRoleFromText(string $text): ?string
    {
        $norm = self::normalizePlaceholderText($text);
        if ($norm === '') {
            return null;
        }
        static $short = [
            'titel' => 'document_title',
            'title' => 'document_title',
            'untertitel' => 'subtitle',
            'subtitle' => 'subtitle',
        ];
        if (isset($short[$norm])) {
            return $short[$norm];
        }
        foreach (self::LOGOS_ROLES as $role) {
            if ($norm === self::normalizePlaceholderText(t('logos.role_' . $role))) {
                return $role;
            }
        }
        if (preg_match('/^überschrift\s*(\d)$/u', $norm, $m)) {
            $candidate = 'heading' . $m[1];
            if (in_array($candidate, self::LOGOS_ROLES, true)) {
                return $candidate;
            }
        }
        return null;
    }

    private static function inferRoleFromPlaceholderText(string $text, string $layoutKey = ''): ?string
    {
        if (!self::isPlaceholderText($text)) {
            return null;
        }
        return self::inferRoleFromText($text);
    }

    /** Welche Rollen auf einem Layout-Schlüssel sinnvoll sind (null = alle). */
    private static function rolesForLayoutKey(string $layoutKey): ?array
    {
        if ($layoutKey === '') {
            return null;
        }
        static $map = [
            'document_title' => ['document_title', 'subtitle'],
            'title' => ['document_title', 'subtitle'],
            'titelfolie' => ['document_title', 'subtitle'],
            'html_vorlage' => ['document_title', 'subtitle'],
            'subtitle' => ['subtitle'],
            'abschnitt' => ['heading1', 'subtitle'],
            'heading1' => ['heading1', 'subtitle', 'normal'],
            'heading2' => ['heading2', 'subtitle', 'normal'],
            'heading3' => ['heading3', 'subtitle', 'normal'],
            'heading4' => ['heading4', 'subtitle', 'normal'],
            'heading5' => ['heading5', 'subtitle', 'normal'],
            'ueberschrift' => ['heading2', 'subtitle'],
            'ueberschrift_2' => ['heading2', 'subtitle'],
            'ueberschrift_und_inhalt' => ['heading3', 'normal'],
            'ueberschrift_und_zwei_inhalte' => ['heading4', 'normal'],
            'scripture_block' => ['scripture_ref', 'scripture_verse', 'scripture_block'],
            'lighttext' => ['lighttext'],
        ];
        if (isset($map[$layoutKey])) {
            return $map[$layoutKey];
        }
        if (str_starts_with($layoutKey, 'heading')) {
            return [$layoutKey, 'subtitle', 'normal'];
        }
        $primary = self::primaryRoleForLayoutKey($layoutKey);
        return $primary !== null ? [$primary, 'subtitle'] : null;
    }

    private static function primaryRoleForLayoutKey(string $layoutKey): ?string
    {
        if ($layoutKey === '') {
            return null;
        }
        if (in_array($layoutKey, self::LOGOS_ROLES, true)) {
            return $layoutKey;
        }
        static $aliases = [
            'document_title' => 'document_title',
            'title' => 'document_title',
            'titelfolie' => 'document_title',
            'html_vorlage' => 'document_title',
            'abschnitt' => 'heading1',
            'ueberschrift' => 'heading2',
            'ueberschrift_2' => 'heading2',
            'ueberschrift_und_inhalt' => 'heading3',
            'ueberschrift_und_zwei_inhalte' => 'heading4',
            'vergleich' => 'heading1',
            'text' => 'normal',
        ];
        return $aliases[$layoutKey] ?? null;
    }

    /** @param list<array> $merged */
    private static function isDuplicateOfMergedText(array $obj, array $merged): bool
    {
        if (($obj['type'] ?? '') !== 'text') {
            return false;
        }
        if (self::readSetRole($obj) !== '') {
            return false;
        }
        $text = self::normalizePlaceholderText((string)($obj['text'] ?? ''));
        if ($text === '') {
            return false;
        }
        foreach ($merged as $existing) {
            if (($existing['type'] ?? '') !== 'text') {
                continue;
            }
            if (self::normalizePlaceholderText((string)($existing['text'] ?? '')) === $text) {
                return true;
            }
        }
        return false;
    }

    public static function fillSlideFromLayout(array $layoutSlide, array $contentByRole, string $notes = '', ?array $setMeta = null): array
    {
        $layoutSlide = self::prepareLayoutSlide($layoutSlide);
        $layoutKey = (string)($layoutSlide['layoutKey'] ?? '');
        $layoutObjects = $layoutSlide['objects'] ?? [];
        $contentByRole = self::normalizeContentByRole($contentByRole);

        $objects = [];
        $filledRoles = [];
        $placedTexts = [];
        foreach ($layoutObjects as $layoutObj) {
            $role = self::readSetRole($layoutObj);
            if ($role !== '') {
                if (!empty($filledRoles[$role])) {
                    continue;
                }
                $text = self::contentTextFromRoleMap($contentByRole, $role);
                if ($text === '') {
                    $objects[] = self::mergePlaceholderObject($layoutObj, null);
                } else {
                    $objects[] = self::mergePlaceholderObject($layoutObj, ['text' => $text, 'setRole' => $role]);
                    $placedTexts[] = self::normalizePlaceholderText($text);
                }
                $filledRoles[$role] = true;
            } else {
                if (self::shouldSkipLayoutObject($layoutObj, $filledRoles, $contentByRole, $layoutKey, $placedTexts)) {
                    continue;
                }
                if (($layoutObj['type'] ?? '') === 'text') {
                    continue;
                }
                $objects[] = $layoutObj;
            }
        }
        foreach ($contentByRole as $role => $text) {
            $role = self::canonicalLogosRole((string)$role);
            $text = trim((string)$text);
            if ($text === '' || !empty($filledRoles[$role])) {
                continue;
            }
            $layoutObj = self::findLayoutObjectForRole($layoutObjects, $role);
            if ($layoutObj === null && in_array($role, ['normal', 'list_item'], true)) {
                $layoutObj = self::findUnroledContentTextObject($layoutObjects);
            }
            if ($layoutObj) {
                $objects[] = self::mergePlaceholderObject($layoutObj, ['text' => $text, 'setRole' => $role]);
            } else {
                $objects[] = self::makeRoleTextObject($role, $text, [], $setMeta);
            }
            $filledRoles[$role] = true;
        }
        $objects = self::stripDuplicateTextObjects($objects);
        return [
            'id' => Storage::generateId(4),
            'background' => $layoutSlide['background'] ?? ['type' => 'color', 'value' => '#111111'],
            'transition' => 'slide',
            'autoAdvance' => 0,
            'notes' => $notes,
            'layoutKey' => $layoutKey,
            'label' => $layoutSlide['label'] ?? null,
            'objects' => $objects,
        ];
    }

    public static function listForUser(string $userId): array
    {
        [$mine, $shared] = Presentation::listTemplatesForUser($userId);
        $filter = fn($m) => !empty($m['is_layout_set']);
        return [array_values(array_filter($mine, $filter)), array_values(array_filter($shared, $filter))];
    }

    public static function appendRoleContent(array &$slide, array $layoutSlide, string $role, string $text, ?array $setMeta = null): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        foreach ($layoutSlide['objects'] ?? [] as $layoutObj) {
            if (($layoutObj['setRole'] ?? '') === $role) {
                $slide['objects'][] = self::mergePlaceholderObject($layoutObj, ['text' => $text, 'setRole' => $role]);
                return;
            }
        }
        $slide['objects'][] = self::makeRoleTextObject($role, $text, [], $setMeta);
    }

    public static function makeRoleTextObject(string $role, string $text, array $placement = [], ?array $setMeta = null): array
    {
        $text = trim($text);
        $style = self::styleForRole($role, array_merge([
            'x' => 100,
            'y' => 100,
            'w' => 1720,
            'h' => 100,
        ], $placement), $setMeta);
        return [
            'id' => 'o' . bin2hex(random_bytes(4)),
            'type' => 'text',
            'setRole' => $role,
            'rotation' => 0,
            'opacity' => 1,
            'animType' => 'none',
            'animOrder' => 1,
            'x' => (int)($style['x'] ?? 100),
            'y' => (int)($style['y'] ?? 100),
            'w' => (int)($style['w'] ?? 1720),
            'h' => (int)($style['h'] ?? 100),
            'text' => $text,
            'fontFamily' => $style['fontFamily'] ?? 'Open Sans',
            'fontSize' => (int)($style['fontSize'] ?? 48),
            'fontWeight' => ($style['fontWeight'] ?? '') === 'bold' ? 'bold' : 'normal',
            'italic' => !empty($style['italic']),
            'underline' => !empty($style['underline']),
            'strikethrough' => !empty($style['strikethrough']),
            'uppercase' => !empty($style['uppercase']),
            'smallCaps' => !empty($style['smallCaps']),
            'color' => $style['color'] ?? '#ffffff',
            'align' => in_array($style['align'] ?? '', ['left', 'center', 'right'], true) ? $style['align'] : 'left',
        ];
    }

    public static function mergePlaceholderObject(array $layoutObj, ?array $contentObj): array
    {
        $obj = $layoutObj;
        $obj['id'] = Storage::generateId(4);
        if ($contentObj === null) {
            return $obj;
        }
        $contentText = trim((string)($contentObj['text'] ?? ''));
        if ($contentText !== '') {
            $obj['text'] = $contentText;
        }
        if (!empty($contentObj['setRole'])) {
            $obj['setRole'] = $contentObj['setRole'];
        }
        return $obj;
    }

    public static function layoutHasRole(array $layoutSlide, string $role): bool
    {
        $layoutSlide = self::prepareLayoutSlide($layoutSlide);
        foreach (self::layoutSearchRoles($role) as $searchRole) {
            if (self::slideHasObjectRole($layoutSlide, $searchRole)) {
                return true;
            }
        }
        return false;
    }

    private static function makeScriptureBlockLayoutSlide(): array
    {
        return [
            'id' => Storage::generateId(4),
            'layoutKey' => 'scripture_block',
            'label' => t('logos.role_scripture_block'),
            'background' => ['type' => 'color', 'value' => '#111111'],
            'transition' => 'slide',
            'autoAdvance' => 0,
            'notes' => '',
            'objects' => [
                self::makePlaceholderTextObject('scripture_ref', [
                    'y' => 160,
                    'h' => 80,
                    'textTemplateId' => '982874cf3eb0',
                    'align' => 'center',
                ]),
                self::makePlaceholderTextObject('scripture_verse', [
                    'y' => 280,
                    'h' => 520,
                    'textTemplateId' => 'standard',
                    'align' => 'center',
                ]),
            ],
        ];
    }

    private static function makePlaceholderTextObject(string $role, array $def = []): array
    {
        $placement = [
            'x' => (int)($def['x'] ?? 100),
            'y' => (int)($def['y'] ?? 100),
            'w' => (int)($def['w'] ?? 1720),
            'h' => (int)($def['h'] ?? 100),
        ];
        $style = $placement;
        $tpl = TextTemplate::resolve($def['textTemplateId'] ?? ElementLink::textTemplateId($role));
        if ($tpl) {
            $style = array_merge($tpl, $placement);
        }
        if (!empty($def['italic'])) {
            $style['italic'] = true;
        }
        if (!empty($def['color'])) {
            $style['color'] = $def['color'];
        }
        if (!empty($def['align'])) {
            $style['align'] = $def['align'];
        }
        $label = t('logos.role_' . $role);
        return [
            'id' => 'o' . bin2hex(random_bytes(4)),
            'type' => 'text',
            'setRole' => $role,
            'rotation' => 0,
            'opacity' => 1,
            'animType' => 'none',
            'animOrder' => 1,
            'x' => (int)$style['x'],
            'y' => (int)$style['y'],
            'w' => (int)($style['w'] ?? 1720),
            'h' => (int)($style['h'] ?? 100),
            'text' => '«' . $label . '»',
            'fontFamily' => $style['fontFamily'] ?? 'Open Sans',
            'fontSize' => (int)($style['fontSize'] ?? 65),
            'fontWeight' => ($style['fontWeight'] ?? '') === 'bold' ? 'bold' : 'normal',
            'italic' => !empty($style['italic']),
            'underline' => false,
            'strikethrough' => false,
            'uppercase' => false,
            'smallCaps' => !empty($style['smallCaps']),
            'color' => $style['color'] ?? '#ffffff',
            'align' => $style['align'] ?? 'left',
        ];
    }

    private static function makeLayoutSlide(array $def): array
    {
        $role = $def['setRole'];
        $slide = [
            'id' => Storage::generateId(4),
            'layoutKey' => $def['layoutKey'],
            'label' => self::defaultLabelForLayout($def),
            'background' => ['type' => 'color', 'value' => '#111111'],
            'transition' => 'slide',
            'autoAdvance' => 0,
            'notes' => '',
            'objects' => [self::makePlaceholderTextObject($role, $def)],
        ];
        return $slide;
    }
}
