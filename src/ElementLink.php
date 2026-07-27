<?php
/**
 * Globale Verknüpfung Vorlageelement → Textvorlage (unter Vorlagen → Vorlageelemente).
 */
class ElementLink
{
    public static function filePath(): string
    {
        return DATA_PATH . '/element_text_links.json';
    }

    /** @return array<string, string|null> role => textTemplateId */
    public static function map(): array
    {
        $stored = Storage::read(self::filePath(), []);
        $out = [];
        foreach (self::allRoles() as $role) {
            $out[$role] = isset($stored[$role]) && $stored[$role] !== ''
                ? (string)$stored[$role]
                : (self::defaults()[$role] ?? null);
        }
        return $out;
    }

    public static function textTemplateId(string $role): ?string
    {
        $id = self::map()[$role] ?? null;
        return ($id !== null && $id !== '') ? $id : null;
    }

    /** @param array<string, string|null> $links */
    public static function save(array $links): void
    {
        $clean = [];
        foreach (self::allRoles() as $role) {
            if (!array_key_exists($role, $links)) {
                continue;
            }
            $val = $links[$role];
            $clean[$role] = ($val !== null && $val !== '') ? (string)$val : null;
        }
        Storage::write(self::filePath(), $clean);
    }

    /** @return list<string> */
    public static function allRoles(): array
    {
        return array_values(array_unique(array_merge(
            LayoutSet::STANDARD_ELEMENT_ROLES,
            LayoutSet::LOGOS_EXTRA_ROLES,
            ['scripture_ref', 'scripture_verse']
        )));
    }

    /** @return array<string, string|null> */
    public static function defaults(): array
    {
        return [
            'document_title' => 'title',
            'subtitle' => 'subtitle',
            'heading1' => 'title',
            'heading2' => '0b3aec509d2e',
            'heading3' => '6ccca4e48029',
            'heading4' => '982874cf3eb0',
            'heading5' => '982874cf3eb0',
            'normal' => null,
            'list_item' => null,
            'lighttext' => null,
            'prompt' => null,
            'scripture_block' => 'standard',
            'scripture_ref' => '982874cf3eb0',
            'scripture_verse' => 'standard',
            'scripture_inline' => null,
            'meta' => null,
        ];
    }

    public static function ensureDefaults(): void
    {
        if (!is_file(self::filePath())) {
            Storage::write(self::filePath(), []);
        }
    }
}
