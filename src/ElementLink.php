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
        $def = SermonImportTemplate::defaultTemplateFields('')['elements'];
        $out = [];
        foreach (self::allRoles() as $role) {
            $out[$role] = $def[$role]['textTemplateId'] ?? null;
        }
        return $out;
    }

    public static function ensureDefaults(): void
    {
        if (!is_file(self::filePath())) {
            Storage::write(self::filePath(), []);
        }
    }
}
