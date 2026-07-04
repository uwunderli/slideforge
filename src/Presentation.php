<?php
/**
 * Verwaltung von Präsentationen: Metadaten, Zugriffsrechte (ACL), öffentliche Links.
 * Jede Präsentation lebt in data/presentations/<id>/ mit meta.json, acl.json, slides.json
 */
class Presentation
{
    public static function dir(string $id): string
    {
        return PRESENTATIONS_PATH . '/' . $id;
    }

    public static function exists(string $id): bool
    {
        // ID validieren, um Path-Traversal zu verhindern (nur Hex erlaubt, siehe generateId)
        if (!preg_match('/^[a-f0-9]+$/', $id)) {
            return false;
        }
        return is_dir(self::dir($id));
    }

    public static function create(string $ownerId, string $title, int $width, int $height, bool $isTemplate = false): string
    {
        $id = Storage::generateId(8);
        $dir = self::dir($id);
        mkdir($dir, 0770, true);
        mkdir($dir . '/assets', 0770, true);

        Storage::write($dir . '/meta.json', [
            'id' => $id,
            'owner_id' => $ownerId,
            'title' => $title !== '' ? $title : 'Unbenannte Präsentation',
            'width' => $width,
            'height' => $height,
            'presentation_duration' => 30,
            'safe_margin' => 100,
            'timebar_stops' => [
                ['pct' => 0, 'color' => '#4caf6b'],
                ['pct' => 60, 'color' => '#d9c23a'],
                ['pct' => 90, 'color' => '#dd8a2e'],
                ['pct' => 100, 'color' => '#d9483a'],
            ],
            'show_progress' => true,
            'show_controls' => false,
            'is_template' => $isTemplate,
            'template_shared' => false,
            'template_order' => microtime(true),
            'archived' => false,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ]);

        Storage::write($dir . '/acl.json', [
            'shares' => [],
            'public' => [
                'enabled' => false,
                'token' => null,
                'permission' => 'view',
            ],
        ]);

        // Platzhalter für Phase 2 (Editor)
        Storage::write($dir . '/slides.json', [
            'slides' => [
                [
                    'id' => Storage::generateId(4),
                    'background' => ['type' => 'color', 'value' => '#111111'],
                    'transition' => 'slide',
                    'objects' => [],
                ],
            ],
        ]);

        return $id;
    }

    /** Erstellt eine Folienvorlage (technisch eine Präsentation mit genau einer Folie, is_template=true). */
    public static function createTemplate(string $ownerId, string $title): string
    {
        return self::create($ownerId, $title, DEFAULT_SLIDE_WIDTH, DEFAULT_SLIDE_HEIGHT, true);
    }

    public static function isTemplate(string $id): bool
    {
        $meta = self::getMeta($id);
        return $meta !== null && !empty($meta['is_template']);
    }

    /** Dupliziert eine Folienvorlage komplett (Folien + referenzierte Bilder) als neue, eigenständige Vorlage. */
    public static function duplicateTemplate(string $id, string $ownerId): string
    {
        $meta = self::getMeta($id);
        if (!$meta || empty($meta['is_template'])) {
            throw new RuntimeException('Keine Folienvorlage.');
        }
        $newId = self::create($ownerId, $meta['title'] . ' (Kopie)', (int)$meta['width'], (int)$meta['height'], true);

        // Bild-Dateien 1:1 in den neuen Vorlagen-Ordner kopieren
        $srcAssets = self::dir($id) . '/assets';
        $dstAssets = self::dir($newId) . '/assets';
        if (is_dir($srcAssets)) {
            foreach (glob($srcAssets . '/*') ?: [] as $file) {
                copy($file, $dstAssets . '/' . basename($file));
            }
        }

        // Folien kopieren, dabei alle Bild-URLs von der alten auf die neue Präsentations-ID umschreiben
        // (Dateinamen bleiben gleich, nur die id= im asset.php-Link muss sich ändern).
        $slidesData = Storage::read(self::dir($id) . '/slides.json', ['slides' => []]);
        $json = json_encode($slidesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json = str_replace('asset.php?id=' . urlencode($id) . '&', 'asset.php?id=' . urlencode($newId) . '&', $json);
        $slidesData = json_decode($json, true) ?? ['slides' => []];
        Storage::write(self::dir($newId) . '/slides.json', $slidesData);

        return $newId;
    }

    public static function setTemplateShared(string $id, bool $shared): void
    {
        self::updateMeta($id, ['template_shared' => $shared]);
    }

    /** Alle Folienvorlagen, die ein User verwenden darf: eigene + von anderen freigegebene. */
    public static function listTemplatesForUser(string $userId): array
    {
        $mine = [];
        $shared = [];
        if (!is_dir(PRESENTATIONS_PATH)) {
            return [$mine, $shared];
        }
        foreach (scandir(PRESENTATIONS_PATH) as $id) {
            if ($id === '.' || $id === '..') continue;
            $meta = self::getMeta($id);
            if (!$meta || empty($meta['is_template'])) continue;
            if ($meta['owner_id'] === $userId) {
                $mine[] = $meta;
            } elseif (!empty($meta['template_shared'])) {
                $shared[] = $meta;
            }
        }
        // Eigene Reihenfolge per Drag & Drop (template_order) hat Vorrang; ältere Vorlagen
        // ohne dieses Feld fallen auf das Änderungsdatum zurück (neueste zuerst).
        $sortFn = fn($a, $b) => ($a['template_order'] ?? 0) <=> ($b['template_order'] ?? 0)
            ?: strcmp($b['updated_at'], $a['updated_at']);
        usort($mine, $sortFn);
        usort($shared, $sortFn);
        return [$mine, $shared];
    }

    /** Ordnet die eigenen Folienvorlagen gemäss der übergebenen ID-Reihenfolge neu an (Drag & Drop). */
    public static function reorderTemplates(string $userId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            $meta = self::getMeta($id);
            if ($meta && !empty($meta['is_template']) && $meta['owner_id'] === $userId) {
                Storage::update(self::dir($id) . '/meta.json', function ($m) use ($index) {
                    $m['template_order'] = $index;
                    return $m;
                });
            }
        }
    }

    public static function getMeta(string $id): ?array
    {
        if (!self::exists($id)) {
            return null;
        }
        return Storage::read(self::dir($id) . '/meta.json', null);
    }

    public static function updateMeta(string $id, array $fields): void
    {
        Storage::update(self::dir($id) . '/meta.json', function ($meta) use ($fields) {
            foreach ($fields as $k => $v) {
                $meta[$k] = $v;
            }
            $meta['updated_at'] = date('c');
            return $meta;
        });
    }

    public static function getAcl(string $id): array
    {
        return Storage::read(self::dir($id) . '/acl.json', [
            'shares' => [],
            'public' => ['enabled' => false, 'token' => null, 'permission' => 'view'],
        ]);
    }

    public static function delete(string $id): void
    {
        $dir = self::dir($id);
        if (!is_dir($dir)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }

    /**
     * Ermittelt die Berechtigung eines Users auf eine Präsentation.
     * Rückgabe: 'owner' | 'edit' | 'view' | null (kein Zugriff)
     */
    public static function checkPermission(string $id, ?string $userId): ?string
    {
        $meta = self::getMeta($id);
        if (!$meta) {
            return null;
        }
        if ($userId !== null && $meta['owner_id'] === $userId) {
            return 'owner';
        }
        if ($userId === null) {
            return null;
        }
        $acl = self::getAcl($id);
        foreach ($acl['shares'] as $share) {
            if ($share['user_id'] === $userId) {
                return $share['permission']; // 'edit' oder 'view'
            }
        }
        return null;
    }

    public static function canView(string $id, ?string $userId): bool
    {
        return in_array(self::checkPermission($id, $userId), ['owner', 'edit', 'view'], true);
    }

    public static function canEdit(string $id, ?string $userId): bool
    {
        return in_array(self::checkPermission($id, $userId), ['owner', 'edit'], true);
    }

    /** Liste aller Präsentationen, aufgeteilt in eigene und geteilte, für ein Dashboard. */
    public static function listForUser(string $userId): array
    {
        $owned = [];
        $shared = [];

        if (!is_dir(PRESENTATIONS_PATH)) {
            return [$owned, $shared];
        }

        foreach (scandir(PRESENTATIONS_PATH) as $id) {
            if ($id === '.' || $id === '..') continue;
            $meta = self::getMeta($id);
            if (!$meta || !empty($meta['is_template'])) continue;

            if ($meta['owner_id'] === $userId) {
                $owned[] = $meta;
            } else {
                $acl = self::getAcl($id);
                foreach ($acl['shares'] as $share) {
                    if ($share['user_id'] === $userId) {
                        $meta['my_permission'] = $share['permission'];
                        $shared[] = $meta;
                        break;
                    }
                }
            }
        }

        usort($owned, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
        usort($shared, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

        return [$owned, $shared];
    }

    public static function addShare(string $id, string $targetUserId, string $targetUsername, string $permission): void
    {
        $permission = in_array($permission, ['view', 'edit'], true) ? $permission : 'view';
        Storage::update(self::dir($id) . '/acl.json', function ($acl) use ($targetUserId, $targetUsername, $permission) {
            // Bestehenden Eintrag entfernen (falls Update der Berechtigung)
            $acl['shares'] = array_values(array_filter($acl['shares'], fn($s) => $s['user_id'] !== $targetUserId));
            $acl['shares'][] = [
                'user_id' => $targetUserId,
                'username' => $targetUsername,
                'permission' => $permission,
            ];
            return $acl;
        });
    }

    public static function removeShare(string $id, string $targetUserId): void
    {
        Storage::update(self::dir($id) . '/acl.json', function ($acl) use ($targetUserId) {
            $acl['shares'] = array_values(array_filter($acl['shares'], fn($s) => $s['user_id'] !== $targetUserId));
            return $acl;
        });
    }

    public static function setPublic(string $id, bool $enabled, string $permission = 'view'): array
    {
        return Storage::update(self::dir($id) . '/acl.json', function ($acl) use ($enabled, $permission) {
            $acl['public']['enabled'] = $enabled;
            $acl['public']['permission'] = $permission;
            if ($enabled && empty($acl['public']['token'])) {
                $acl['public']['token'] = Storage::generateId(12);
            }
            if (!$enabled) {
                // Token bleibt bestehen (falls User später wieder aktiviert), Link ist aber inaktiv
            }
            return $acl;
        })['public'];
    }

    public static function regeneratePublicToken(string $id): string
    {
        $acl = Storage::update(self::dir($id) . '/acl.json', function ($acl) {
            $acl['public']['token'] = Storage::generateId(12);
            return $acl;
        });
        return $acl['public']['token'];
    }

    /** Findet eine Präsentations-ID anhand des öffentlichen Tokens. */
    public static function getSlides(string $id): array
    {
        return Storage::read(self::dir($id) . '/slides.json', ['slides' => []]);
    }

    public static function defaultSlide(): array
    {
        return [
            'id' => Storage::generateId(4),
            'background' => ['type' => 'color', 'value' => '#111111'],
            'transition' => 'slide',
            'objects' => [],
        ];
    }

    /** Ersetzt eine einzelne Folie (Hintergrund + Objekte) an gegebenem Index. */
    public static function saveSlide(string $id, int $index, array $background, array $objects, ?string $transition = null, $autoAdvance = null, ?string $notes = null): array
    {
        $result = Storage::update(self::dir($id) . '/slides.json', function ($data) use ($index, $background, $objects, $transition, $autoAdvance, $notes) {
            if (!isset($data['slides'][$index])) {
                return $data;
            }
            $data['slides'][$index]['background'] = $background;
            $data['slides'][$index]['objects'] = $objects;
            if ($transition !== null) {
                $data['slides'][$index]['transition'] = $transition;
            }
            if ($autoAdvance !== null) {
                $data['slides'][$index]['autoAdvance'] = max(0, (int)$autoAdvance);
            }
            if ($notes !== null) {
                $data['slides'][$index]['notes'] = $notes;
            }
            return $data;
        }, ['slides' => []]);
        self::updateMeta($id, []);
        return $result;
    }

    /** Setzt den Übergang für ALLE Folien auf den gleichen Wert (Editor-Button "Für alle Folien übernehmen"). */
    public static function applyTransitionToAll(string $id, string $transition): array
    {
        $result = Storage::update(self::dir($id) . '/slides.json', function ($data) use ($transition) {
            foreach ($data['slides'] as &$slide) {
                $slide['transition'] = $transition;
            }
            return $data;
        }, ['slides' => []]);
        self::updateMeta($id, []);
        return $result;
    }

    // ---------- Live-Position (für Präsentationsmodus + folgenden öffentlichen Link) ----------

    /** Reihenfolge der Uhr-Anzeigen im Präsentationsmodus (Klick wechselt zur nächsten). */
    public static function defaultClockOrder(): array
    {
        return ['analog', 'digital', 'studio'];
    }

    public static function normalizeClockOrder($order): array
    {
        $allowed = self::defaultClockOrder();
        if (!is_array($order)) {
            return $allowed;
        }
        $result = [];
        foreach ($order as $item) {
            if (is_string($item) && in_array($item, $allowed, true) && !in_array($item, $result, true)) {
                $result[] = $item;
            }
        }
        foreach ($allowed as $item) {
            if (!in_array($item, $result, true)) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public static function defaultTimebarStops(): array
    {
        return [
            ['pct' => 0, 'color' => '#4caf6b'],
            ['pct' => 60, 'color' => '#d9c23a'],
            ['pct' => 90, 'color' => '#dd8a2e'],
            ['pct' => 100, 'color' => '#d9483a'],
        ];
    }

    public static function normalizeTimebarStops($stops): array
    {
        $defaults = self::defaultTimebarStops();
        if (!is_array($stops) || empty($stops)) {
            return $defaults;
        }
        $parsed = [];
        foreach ($stops as $s) {
            if (!is_array($s) || !isset($s['color']) || !preg_match('/^#[0-9a-fA-F]{6}$/', $s['color'])) {
                continue;
            }
            $parsed[] = [
                'pct' => max(0, min(100, (float)($s['pct'] ?? 0))),
                'color' => $s['color'],
            ];
        }
        if (count($parsed) < 2) {
            return $defaults;
        }
        usort($parsed, fn($a, $b) => $a['pct'] <=> $b['pct']);
        $parsed[0]['pct'] = 0;
        $parsed[count($parsed) - 1]['pct'] = 100;
        return $parsed;
    }

    /**
     * Legt die mitgelieferten Standard-Folienvorlagen an (aus seed/templates/) - wird einmalig
     * aufgerufen, wenn der allererste Benutzer (automatisch Admin) registriert wird, damit eine
     * frische Installation direkt mit sinnvollen, öffentlich freigegebenen Vorlagen startet.
     */
    public static function seedDefaultTemplates(string $ownerId): void
    {
        $seedDir = BASE_PATH . '/seed/templates';
        if (!is_dir($seedDir)) {
            return;
        }

        $entries = [];
        foreach (scandir($seedDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $metaPath = $seedDir . '/' . $entry . '/meta.json';
            $slidesPath = $seedDir . '/' . $entry . '/slides.json';
            if (!is_file($metaPath) || !is_file($slidesPath)) {
                continue;
            }
            $seedMeta = json_decode((string)file_get_contents($metaPath), true);
            if (!is_array($seedMeta)) {
                continue;
            }
            $entries[] = ['id' => $entry, 'meta' => $seedMeta, 'slidesPath' => $slidesPath];
        }

        usort($entries, fn($a, $b) => ($a['meta']['template_order'] ?? 0) <=> ($b['meta']['template_order'] ?? 0));

        foreach ($entries as $entry) {
            self::importSeedTemplate($ownerId, $entry['id'], $entry['meta'], $entry['slidesPath']);
        }
    }

    /**
     * Schreibt alle öffentlich freigegebenen Folienvorlagen aus data/presentations/ nach seed/templates/.
     * @return int Anzahl exportierter Vorlagen
     */
    public static function exportSharedTemplatesToSeed(): int
    {
        $seedDir = BASE_PATH . '/seed/templates';
        if (!is_dir($seedDir)) {
            mkdir($seedDir, 0770, true);
        }

        $shared = [];
        if (is_dir(PRESENTATIONS_PATH)) {
            foreach (scandir(PRESENTATIONS_PATH) as $id) {
                if ($id === '.' || $id === '..') {
                    continue;
                }
                $meta = self::getMeta($id);
                if ($meta && !empty($meta['is_template']) && !empty($meta['template_shared'])) {
                    $shared[] = $meta;
                }
            }
        }

        usort($shared, fn($a, $b) => ($a['template_order'] ?? 0) <=> ($b['template_order'] ?? 0));

        foreach (scandir($seedDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $seedDir . '/' . $entry;
            if (is_dir($path)) {
                self::removeDir($path);
            }
        }

        $count = 0;
        foreach ($shared as $meta) {
            $id = $meta['id'];
            $target = $seedDir . '/' . $id;
            mkdir($target, 0770, true);

            $seedMeta = [
                'title' => $meta['title'] ?? 'Vorlage',
                'width' => (int)($meta['width'] ?? DEFAULT_SLIDE_WIDTH),
                'height' => (int)($meta['height'] ?? DEFAULT_SLIDE_HEIGHT),
                'template_order' => (int)($meta['template_order'] ?? $count),
            ];
            file_put_contents(
                $target . '/meta.json',
                json_encode($seedMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );

            $slidesPath = self::dir($id) . '/slides.json';
            if (is_file($slidesPath)) {
                copy($slidesPath, $target . '/slides.json');
            }

            $srcAssets = self::dir($id) . '/assets';
            if (is_dir($srcAssets)) {
                $dstAssets = $target . '/assets';
                mkdir($dstAssets, 0770, true);
                foreach (glob($srcAssets . '/*') ?: [] as $file) {
                    if (is_file($file)) {
                        copy($file, $dstAssets . '/' . basename($file));
                    }
                }
            }

            $count++;
        }

        return $count;
    }

    private static function importSeedTemplate(string $ownerId, string $seedId, array $seedMeta, string $slidesPath): void
    {
        $slidesData = json_decode((string)file_get_contents($slidesPath), true);
        if (!is_array($slidesData)) {
            return;
        }

        $newId = self::create(
            $ownerId,
            $seedMeta['title'] ?? 'Vorlage',
            (int)($seedMeta['width'] ?? DEFAULT_SLIDE_WIDTH),
            (int)($seedMeta['height'] ?? DEFAULT_SLIDE_HEIGHT),
            true
        );

        $seedAssets = BASE_PATH . '/seed/templates/' . $seedId . '/assets';
        $dstAssets = self::dir($newId) . '/assets';
        if (is_dir($seedAssets)) {
            foreach (glob($seedAssets . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    copy($file, $dstAssets . '/' . basename($file));
                }
            }
        }

        $json = json_encode($slidesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json = str_replace('asset.php?id=' . urlencode($seedId) . '&', 'asset.php?id=' . urlencode($newId) . '&', $json);
        $slidesData = json_decode($json, true) ?? ['slides' => []];
        Storage::write(self::dir($newId) . '/slides.json', $slidesData);

        self::updateMeta($newId, [
            'template_shared' => true,
            'template_order' => $seedMeta['template_order'] ?? microtime(true),
        ]);
    }

    private static function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? self::removeDir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    public static function setLivePosition(string $id, int $index, ?int $frag = null, string $channel = 'present'): void
    {
        if (!in_array($channel, ['editor', 'present'], true)) {
            $channel = 'present';
        }
        Storage::update(self::dir($id) . '/live.json', function ($data) use ($index, $frag, $channel) {
            if (!isset($data[$channel]) || !is_array($data[$channel])) {
                $data[$channel] = [];
            }
            $data[$channel]['index'] = $index;
            $data[$channel]['frag'] = $frag;
            $data[$channel]['ts'] = time();
            return $data;
        }, ['media' => null]);
    }

    public static function setLiveMediaCommand(string $id, string $mediaId, string $mediaAction): void
    {
        Storage::update(self::dir($id) . '/live.json', function ($data) use ($mediaId, $mediaAction) {
            $data['media'] = ['id' => $mediaId, 'action' => $mediaAction, 'cmd_ts' => microtime(true)];
            $data['ts'] = time();
            return $data;
        }, ['index' => 0]);
    }

    public static function setLiveLaser(string $id, bool $active, ?float $x, ?float $y, int $slideIndex, string $color, int $size, bool $trail = false): void
    {
        if ($active) {
            $x = max(0.0, min(1.0, $x ?? 0.0));
            $y = max(0.0, min(1.0, $y ?? 0.0));
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#ff0000';
        } else {
            $color = strtolower($color);
        }
        $size = max(8, min(64, $size));
        Storage::update(self::dir($id) . '/live.json', function ($data) use ($active, $x, $y, $slideIndex, $color, $size, $trail) {
            $data['laser'] = [
                'active' => $active,
                'x' => $active ? $x : null,
                'y' => $active ? $y : null,
                'slideIndex' => $slideIndex,
                'color' => $color,
                'size' => $size,
                'trail' => $trail,
                'ts' => microtime(true),
            ];
            return $data;
        }, []);
    }

    public static function getLivePosition(string $id, string $channel = 'present'): ?array
    {
        if (!in_array($channel, ['editor', 'present'], true)) {
            $channel = 'present';
        }
        $data = Storage::read(self::dir($id) . '/live.json', null);
        if (!$data) {
            return null;
        }

        // Altes Ein-Kanal-Format (nur Präsentationsmodus)
        if (isset($data['index'], $data['ts']) && !isset($data['present']) && !isset($data['editor'])) {
            $data['present'] = [
                'index' => $data['index'],
                'frag' => $data['frag'] ?? null,
                'ts' => $data['ts'],
            ];
        }

        $ch = $data[$channel] ?? null;
        if (!$ch || !isset($ch['index'], $ch['ts'])) {
            return null;
        }
        if (time() - (int)$ch['ts'] > 20) {
            return null;
        }

        $result = [
            'index' => (int)$ch['index'],
            'frag' => isset($ch['frag']) && $ch['frag'] !== null ? (int)$ch['frag'] : null,
            'ts' => (int)$ch['ts'],
        ];
        if (!empty($data['media'])) {
            $result['media'] = $data['media'];
        }
        if (!empty($data['laser']) && is_array($data['laser'])) {
            $lz = $data['laser'];
            $age = microtime(true) - (float)($lz['ts'] ?? 0);
            if (!empty($lz['active']) && $age < 1.5 && isset($lz['x'], $lz['y'])) {
                $result['laser'] = [
                    'active' => true,
                    'x' => (float)$lz['x'],
                    'y' => (float)$lz['y'],
                    'slideIndex' => (int)($lz['slideIndex'] ?? 0),
                    'color' => is_string($lz['color'] ?? null) ? $lz['color'] : '#ff0000',
                    'size' => (int)($lz['size'] ?? 24),
                    'trail' => !empty($lz['trail']),
                ];
            } elseif (empty($lz['active']) && $age < 0.4) {
                $result['laser'] = ['active' => false];
            }
        }
        return $result;
    }

    public static function clearLivePosition(string $id): void
    {
        $path = self::dir($id) . '/live.json';
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public static function addSlide(string $id, ?int $afterIndex = null): array
    {
        $newSlide = self::defaultSlide();
        $data = Storage::update(self::dir($id) . '/slides.json', function ($data) use ($afterIndex, $newSlide) {
            $slides = $data['slides'] ?? [];
            if ($afterIndex === null || $afterIndex >= count($slides) - 1) {
                $slides[] = $newSlide;
            } else {
                array_splice($slides, $afterIndex + 1, 0, [$newSlide]);
            }
            $data['slides'] = $slides;
            return $data;
        }, ['slides' => []]);
        self::updateMeta($id, []);
        return $data;
    }

    public static function duplicateSlide(string $id, int $index): array
    {
        $result = Storage::update(self::dir($id) . '/slides.json', function ($data) use ($index) {
            if (!isset($data['slides'][$index])) {
                return $data;
            }
            $copy = $data['slides'][$index];
            $copy['id'] = Storage::generateId(4);
            foreach ($copy['objects'] as &$obj) {
                $obj['id'] = Storage::generateId(4);
            }
            array_splice($data['slides'], $index + 1, 0, [$copy]);
            return $data;
        }, ['slides' => []]);
        self::updateMeta($id, []);
        return $result;
    }

    public static function deleteSlide(string $id, int $index): array
    {
        $result = Storage::update(self::dir($id) . '/slides.json', function ($data) use ($index) {
            if (count($data['slides']) <= 1) {
                return $data; // mindestens eine Folie muss übrig bleiben
            }
            array_splice($data['slides'], $index, 1);
            return $data;
        }, ['slides' => []]);
        self::updateMeta($id, []);
        return $result;
    }

    /** $orderedIds = Array von Folien-IDs in der gewünschten neuen Reihenfolge. */
    public static function reorderSlides(string $id, array $orderedIds): array
    {
        $result = Storage::update(self::dir($id) . '/slides.json', function ($data) use ($orderedIds) {
            $byId = [];
            foreach ($data['slides'] as $s) {
                $byId[$s['id']] = $s;
            }
            $new = [];
            foreach ($orderedIds as $sid) {
                if (isset($byId[$sid])) {
                    $new[] = $byId[$sid];
                    unset($byId[$sid]);
                }
            }
            // übrig gebliebene (sollte nicht vorkommen) hinten anhängen
            foreach ($byId as $s) {
                $new[] = $s;
            }
            $data['slides'] = $new;
            return $data;
        }, ['slides' => []]);
        self::updateMeta($id, []);
        return $result;
    }

    public static function canUseTemplate(string $id, string $userId): bool
    {
        $meta = self::getMeta($id);
        if (!$meta || empty($meta['is_template'])) {
            return false;
        }
        return $meta['owner_id'] === $userId || !empty($meta['template_shared']);
    }

    public static function getTemplateSlideContent(string $id): ?array
    {
        $data = self::getSlides($id);
        return $data['slides'][0] ?? null;
    }

    public static function setArchived(string $id, bool $archived): void
    {
        self::updateMeta($id, ['archived' => $archived]);
    }

    public static function findByPublicToken(string $token): ?string
    {
        if (!is_dir(PRESENTATIONS_PATH)) {
            return null;
        }
        foreach (scandir(PRESENTATIONS_PATH) as $id) {
            if ($id === '.' || $id === '..') continue;
            $acl = self::getAcl($id);
            if (!empty($acl['public']['enabled']) && ($acl['public']['token'] ?? null) === $token) {
                return $id;
            }
        }
        return null;
    }

    /** Dateiname aus asset.php-URL extrahieren. */
    public static function assetFilenameFromUrl(string $url): ?string
    {
        if (preg_match('/(?:[?&])file=([^&#]+)/', $url, $m)) {
            return basename(urldecode($m[1]));
        }
        return null;
    }

    /** Sammelt Medien-Referenzen: Dateiname => Folienindizes (0-basiert). */
    public static function collectMediaReferences(string $id): array
    {
        $slides = self::getSlides($id)['slides'] ?? [];
        $refs = [];
        foreach ($slides as $i => $slide) {
            $bg = $slide['background'] ?? [];
            $bgType = $bg['type'] ?? '';
            if (in_array($bgType, ['image', 'video'], true) && !empty($bg['value'])) {
                $fn = self::assetFilenameFromUrl((string)$bg['value']);
                if ($fn) {
                    $refs[$fn][] = $i;
                }
            }
            foreach ($slide['objects'] ?? [] as $obj) {
                self::collectObjectMediaRefs($obj, $i, $refs);
            }
        }
        foreach ($refs as &$indices) {
            $indices = array_values(array_unique($indices));
            sort($indices);
        }
        unset($indices);
        return $refs;
    }

    private static function collectObjectMediaRefs(array $obj, int $slideIndex, array &$refs): void
    {
        $type = $obj['type'] ?? '';
        if (in_array($type, ['image', 'video', 'audio'], true) && !empty($obj['src'])) {
            $fn = self::assetFilenameFromUrl((string)$obj['src']);
            if ($fn) {
                $refs[$fn][] = $slideIndex;
            }
        }
        if ($type === 'group') {
            foreach ($obj['children'] ?? [] as $child) {
                self::collectObjectMediaRefs($child, $slideIndex, $refs);
            }
        }
    }

    /** Alle Mediendateien im assets/-Ordner inkl. Verwendung auflisten. */
    public static function listMediaAssets(string $id): array
    {
        $refs = self::collectMediaReferences($id);
        $assetsDir = self::dir($id) . '/assets';
        $items = [];
        if (!is_dir($assetsDir)) {
            return [];
        }
        foreach (glob($assetsDir . '/*') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $name = basename($path);
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'webm'], true)) {
                $kind = 'video';
            } elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'm4a'], true)) {
                $kind = 'audio';
            } else {
                $kind = 'image';
            }
            $usedOn = array_values(array_unique($refs[$name] ?? []));
            sort($usedOn);
            $items[] = [
                'filename' => $name,
                'size' => filesize($path) ?: 0,
                'url' => 'asset.php?id=' . urlencode($id) . '&file=' . urlencode($name),
                'kind' => $kind,
                'usedOn' => $usedOn,
            ];
        }
        usort($items, function (array $a, array $b): int {
            $aUnused = empty($a['usedOn']);
            $bUnused = empty($b['usedOn']);
            if ($aUnused !== $bUnused) {
                return $aUnused ? 1 : -1;
            }
            return strcasecmp($a['filename'], $b['filename']);
        });
        return $items;
    }

    /** Unbenutzte Mediendatei löschen. */
    public static function deleteMediaAsset(string $id, string $filename): array
    {
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            return ['ok' => false, 'error' => 'invalid_filename'];
        }
        $refs = self::collectMediaReferences($id);
        if (!empty($refs[$filename])) {
            return ['ok' => false, 'error' => 'in_use'];
        }
        $path = self::dir($id) . '/assets/' . $filename;
        if (!is_file($path)) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (!@unlink($path)) {
            return ['ok' => false, 'error' => 'delete_failed'];
        }
        return ['ok' => true];
    }

    /** Alle unbenutzten Mediendateien löschen. */
    public static function cleanupUnusedMediaAssets(string $id): array
    {
        $refs = self::collectMediaReferences($id);
        $assetsDir = self::dir($id) . '/assets';
        $deleted = [];
        $failed = [];
        if (!is_dir($assetsDir)) {
            return ['ok' => true, 'deleted' => [], 'count' => 0];
        }
        foreach (glob($assetsDir . '/*') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $name = basename($path);
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
                continue;
            }
            if (!empty($refs[$name])) {
                continue;
            }
            if (@unlink($path)) {
                $deleted[] = $name;
            } else {
                $failed[] = $name;
            }
        }
        if ($failed !== []) {
            return ['ok' => false, 'error' => 'delete_failed', 'deleted' => $deleted, 'failed' => $failed];
        }
        return ['ok' => true, 'deleted' => $deleted, 'count' => count($deleted)];
    }
}
