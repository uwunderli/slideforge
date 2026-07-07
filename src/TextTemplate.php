<?php
/**
 * Frei definierbare Textvorlagen (Name, Schriftart/-grösse/-schnitt, Farbe, Ausrichtung).
 * Erscheinen im Editor-Tab "Texte" anstelle der früher fest einprogrammierten
 * Titel-/Untertitel-Buttons.
 */
class TextTemplate
{
    public const FALLBACK_ID = 'standard';

    public static function isFallback(string $id): bool
    {
        return $id === self::FALLBACK_ID;
    }

    /** @return array<string, mixed> */
    public static function fallbackDefaults(): array
    {
        return [
            'id' => self::FALLBACK_ID,
            'name' => 'Text',
            'fontFamily' => 'Open Sans',
            'fontSize' => 65,
            'fontWeight' => 'normal',
            'italic' => false,
            'underline' => false,
            'strikethrough' => false,
            'uppercase' => false,
            'smallCaps' => false,
            'color' => '#ffffff',
            'align' => 'left',
            'w' => 599,
            'h' => 70,
            'isFallback' => true,
        ];
    }

    public static function ensureFallback(): void
    {
        Storage::update(TEXT_TEMPLATES_FILE, function ($list) {
            foreach ($list as &$t) {
                if (($t['id'] ?? '') === self::FALLBACK_ID) {
                    $t['isFallback'] = true;
                    return $list;
                }
            }
            array_unshift($list, self::fallbackDefaults());
            return $list;
        }, []);
    }

    public static function listAll(): array
    {
        $list = Storage::read(TEXT_TEMPLATES_FILE, []);
        usort($list, function ($a, $b) {
            $aFallback = self::isFallback((string)($a['id'] ?? ''));
            $bFallback = self::isFallback((string)($b['id'] ?? ''));
            if ($aFallback !== $bFallback) {
                return $aFallback ? -1 : 1;
            }
            return 0;
        });
        return $list;
    }

    public static function find(string $id): ?array
    {
        foreach (Storage::read(TEXT_TEMPLATES_FILE, []) as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    public static function resolve(?string $id): ?array
    {
        if ($id !== null && $id !== '') {
            $tpl = self::find($id);
            if ($tpl) {
                return $tpl;
            }
        }
        return self::find(self::FALLBACK_ID);
    }

    public static function create(array $fields): array
    {
        $template = array_merge([
            'id' => Storage::generateId(6),
            'name' => 'Neue Vorlage',
            'fontFamily' => 'Open Sans',
            'fontSize' => 32,
            'fontWeight' => 'normal',
            'italic' => false,
            'underline' => false,
            'strikethrough' => false,
            'uppercase' => false,
            'smallCaps' => false,
            'color' => '#ffffff',
            'align' => 'left',
            'w' => 500,
            'h' => 60,
        ], $fields);
        Storage::update(TEXT_TEMPLATES_FILE, function ($list) use ($template) {
            $list[] = $template;
            return $list;
        }, []);
        return $template;
    }

    public static function update(string $id, array $fields): void
    {
        Storage::update(TEXT_TEMPLATES_FILE, function ($list) use ($id, $fields) {
            foreach ($list as &$t) {
                if ($t['id'] === $id) {
                    $t = array_merge($t, $fields);
                }
            }
            return $list;
        }, []);
    }

    public static function delete(string $id): void
    {
        if (self::isFallback($id)) {
            return;
        }
        Storage::update(TEXT_TEMPLATES_FILE, function ($list) use ($id) {
            return array_values(array_filter($list, fn($t) => $t['id'] !== $id));
        }, []);
    }

    /** Dupliziert eine Textvorlage direkt neben dem Original in der Liste. */
    public static function duplicate(string $id): ?array
    {
        if (self::isFallback($id)) {
            return null;
        }
        $source = self::find($id);
        if (!$source) return null;
        $copy = $source;
        $copy['id'] = Storage::generateId(6);
        $copy['name'] = $source['name'] . ' (Kopie)';
        Storage::update(TEXT_TEMPLATES_FILE, function ($list) use ($copy, $id) {
            $newList = [];
            foreach ($list as $t) {
                $newList[] = $t;
                if ($t['id'] === $id) {
                    $newList[] = $copy; // direkt hinter dem Original einfügen
                }
            }
            return $newList;
        }, []);
        return $copy;
    }

    /** Ordnet die Liste gemäss der übergebenen ID-Reihenfolge neu an (per Drag & Drop im UI). */
    public static function reorder(array $orderedIds): void
    {
        Storage::update(TEXT_TEMPLATES_FILE, function ($list) use ($orderedIds) {
            $byId = [];
            foreach ($list as $t) {
                $byId[$t['id']] = $t;
            }
            $new = [];
            $ids = array_values(array_unique(array_merge(
                [self::FALLBACK_ID],
                array_filter(array_map('strval', $orderedIds), fn($id) => $id !== self::FALLBACK_ID)
            )));
            foreach ($ids as $id) {
                if (isset($byId[$id])) {
                    $new[] = $byId[$id];
                    unset($byId[$id]);
                }
            }
            foreach ($byId as $t) {
                $new[] = $t;
            }
            return $new;
        }, []);
    }
}
