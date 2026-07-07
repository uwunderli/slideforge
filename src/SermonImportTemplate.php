<?php
/**
 * Import-Vorlagen: legen fest, wohin Logos-HTML-Elemente auf Folien bzw. in Notizen kommen.
 */
class SermonImportTemplate
{
    public const ELEMENT_TYPES = [
        'document_title',
        'subtitle',
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

    public static function filePath(): string
    {
        return DATA_PATH . '/sermon_import_templates.json';
    }

    public static function listAll(): array
    {
        return Storage::read(self::filePath(), []);
    }

    public static function find(string $id): ?array
    {
        foreach (self::listAll() as $t) {
            if (($t['id'] ?? '') === $id) {
                return $t;
            }
        }
        return null;
    }

    public static function defaultId(): string
    {
        $all = self::listAll();
        return $all[0]['id'] ?? 'default';
    }

    public static function create(array $fields): array
    {
        $template = array_merge(self::defaultTemplateFields('Neue Import-Vorlage'), $fields);
        $template['id'] = Storage::generateId(6);
        Storage::update(self::filePath(), function ($list) use ($template) {
            $list[] = $template;
            return $list;
        }, []);
        return $template;
    }

    public static function update(string $id, array $fields): void
    {
        Storage::update(self::filePath(), function ($list) use ($id, $fields) {
            foreach ($list as &$t) {
                if (($t['id'] ?? '') === $id) {
                    $t = array_merge($t, $fields);
                }
            }
            return $list;
        }, []);
    }

    public static function delete(string $id): bool
    {
        $list = self::listAll();
        if (count($list) <= 1) {
            return false;
        }
        Storage::write(self::filePath(), array_values(array_filter($list, fn($t) => ($t['id'] ?? '') !== $id)));
        return true;
    }

    public static function duplicate(string $id): ?array
    {
        $source = self::find($id);
        if (!$source) {
            return null;
        }
        $copy = $source;
        $copy['id'] = Storage::generateId(6);
        $copy['name'] = ($source['name'] ?? '') . ' (Kopie)';
        Storage::update(self::filePath(), function ($list) use ($copy, $id) {
            $new = [];
            foreach ($list as $t) {
                $new[] = $t;
                if (($t['id'] ?? '') === $id) {
                    $new[] = $copy;
                }
            }
            return $new;
        }, []);
        return $copy;
    }

    /** @return array<string, array> */
    public static function elementMap(array $template): array
    {
        $defaults = self::defaultTemplateFields('')['elements'];
        $custom = $template['elements'] ?? [];
        $merged = [];
        foreach ($defaults as $key => $def) {
            $merged[$key] = array_merge($def, $custom[$key] ?? []);
        }
        return $merged;
    }

    public static function placementFor(array $template, string $type): array
    {
        return self::elementMap($template)[$type] ?? ['target' => 'skip'];
    }

    public static function ensureDefaults(): void
    {
        if (is_file(self::filePath())) {
            return;
        }
        Storage::write(self::filePath(), [self::defaultTemplateFields('Predigt Standard')]);
    }

    public static function defaultTemplateFields(string $name): array
    {
        return [
            'id' => 'default',
            'name' => $name !== '' ? $name : 'Predigt Standard',
            'background' => ['type' => 'color', 'value' => '#111111'],
            'slideBreakLevels' => [1, 2, 3, 4, 5],
            'createTitleSlide' => true,
            'elements' => [
                'document_title' => [
                    'target' => 'title_slide',
                    'x' => 100, 'y' => 380, 'w' => 1720, 'h' => 200,
                    'textTemplateId' => 'title',
                ],
                'subtitle' => [
                    'target' => 'title_slide',
                    'x' => 100, 'y' => 580, 'w' => 1720, 'h' => 100,
                    'textTemplateId' => 'subtitle',
                ],
                'heading1' => [
                    'target' => 'slide',
                    'x' => 100, 'y' => 120, 'w' => 1720, 'h' => 140,
                    'textTemplateId' => 'title',
                ],
                'heading2' => [
                    'target' => 'slide',
                    'x' => 100, 'y' => 100, 'w' => 1720, 'h' => 100,
                    'textTemplateId' => '0b3aec509d2e',
                ],
                'heading3' => [
                    'target' => 'slide',
                    'x' => 100, 'y' => 100, 'w' => 1720, 'h' => 90,
                    'textTemplateId' => '6ccca4e48029',
                ],
                'heading4' => [
                    'target' => 'slide',
                    'x' => 100, 'y' => 100, 'w' => 1720, 'h' => 80,
                    'textTemplateId' => '982874cf3eb0',
                ],
                'heading5' => [
                    'target' => 'slide',
                    'x' => 100, 'y' => 100, 'w' => 1720, 'h' => 70,
                    'textTemplateId' => '982874cf3eb0',
                ],
                'normal' => ['target' => 'notes'],
                'list_item' => ['target' => 'notes'],
                'lighttext' => [
                    'target' => 'slide',
                    'x' => 150, 'y' => 480, 'w' => 1620, 'h' => 220,
                    'fontFamily' => 'Open Sans',
                    'fontSize' => 48,
                    'fontWeight' => 'normal',
                    'italic' => true,
                    'color' => '#aaaaaa',
                    'align' => 'left',
                ],
                'prompt' => ['target' => 'skip'],
                'scripture_block' => [
                    'target' => 'slide',
                    'x' => 120, 'y' => 220, 'w' => 1680, 'h' => 520,
                    'textTemplateId' => 'standard',
                    'align' => 'center',
                ],
                'scripture_ref' => [
                    'target' => 'slide',
                    'x' => 100, 'y' => 160, 'w' => 1720, 'h' => 80,
                    'textTemplateId' => '982874cf3eb0',
                    'align' => 'center',
                ],
                'scripture_verse' => [
                    'target' => 'slide',
                    'x' => 100, 'y' => 280, 'w' => 1720, 'h' => 520,
                    'textTemplateId' => 'standard',
                    'align' => 'center',
                ],
                'scripture_inline' => ['target' => 'notes'],
                'meta' => ['target' => 'skip'],
            ],
        ];
    }
}
