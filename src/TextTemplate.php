<?php
/**
 * Frei definierbare Textvorlagen (Name, Schriftart/-grösse/-schnitt, Farbe, Ausrichtung).
 * Erscheinen im Editor-Tab "Texte" anstelle der früher fest einprogrammierten
 * Titel-/Untertitel-Buttons.
 */
class TextTemplate
{
    public static function listAll(): array
    {
        return Storage::read(TEXT_TEMPLATES_FILE, []);
    }

    public static function find(string $id): ?array
    {
        foreach (self::listAll() as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }
        return null;
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
        Storage::update(TEXT_TEMPLATES_FILE, function ($list) use ($id) {
            return array_values(array_filter($list, fn($t) => $t['id'] !== $id));
        }, []);
    }

    /** Dupliziert eine Textvorlage direkt neben dem Original in der Liste. */
    public static function duplicate(string $id): ?array
    {
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
            foreach ($orderedIds as $id) {
                if (isset($byId[$id])) {
                    $new[] = $byId[$id];
                    unset($byId[$id]);
                }
            }
            // Falls IDs fehlen sollten (z.B. gleichzeitig gelöscht), Rest anhängen statt zu verlieren
            foreach ($byId as $t) {
                $new[] = $t;
            }
            return $new;
        }, []);
    }
}
