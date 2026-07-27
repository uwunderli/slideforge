<?php
require __DIR__ . '/../config.php';
// Bei JSON-Endpunkten dürfen keine HTML-Warnungen/Fehlerseiten in die Antwort rutschen.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
set_exception_handler(function (Throwable $e) {
    error_log('SlideForge api.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Unerwarteter Serverfehler.']);
});

function json_fail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function json_ok(array $data = []): void {
    echo json_encode(['ok' => true] + $data);
    exit;
}

function sf_public_view_url(?string $token): ?string
{
    $token = trim((string)$token);
    if ($token === '') {
        return null;
    }
    $scheme = current_scheme();
    $host = $_SERVER['HTTP_HOST'];
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return "$scheme://$host$base/view.php?token=" . $token;
}

/** @return array{shares:list,public:array,users:list} */
function sf_share_state(string $id, string $meId): array
{
    $acl = Presentation::getAcl($id);
    $sharedIds = array_column($acl['shares'] ?? [], 'user_id');
    $availableUsers = array_values(array_filter(Auth::listAll(), static fn($u) => ($u['id'] ?? '') !== $meId));
    usort($availableUsers, static fn($a, $b) => strcasecmp((string)$a['username'], (string)$b['username']));
    $enabled = !empty($acl['public']['enabled']);
    $token = (string)($acl['public']['token'] ?? '');
    return [
        'shares' => array_values($acl['shares'] ?? []),
        'public' => [
            'enabled' => $enabled,
            'url' => ($enabled && $token !== '') ? sf_public_view_url($token) : null,
        ],
        'users' => array_map(static function (array $u) use ($sharedIds): array {
            return [
                'id' => (string)$u['id'],
                'username' => (string)$u['username'],
                'email' => (string)($u['email'] ?? ''),
                'shared' => in_array($u['id'], $sharedIds, true),
            ];
        }, $availableUsers),
    ];
}

if (!Auth::isLoggedIn()) {
    json_fail('Nicht angemeldet.', 401);
}
$me = Auth::currentUser();

// Eingaben einlesen: entweder JSON-Body (POST) oder Query-String (GET)
$raw = file_get_contents('php://input');
$body = [];
if ($raw !== '' && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $body = json_decode($raw, true) ?? [];
}

$action = $_GET['action'] ?? $body['action'] ?? '';
$id = $_GET['id'] ?? $body['id'] ?? '';

if (!Presentation::exists($id)) {
    json_fail('Präsentation nicht gefunden.', 404);
}

$perm = Presentation::checkPermission($id, $me['id']);
if (!$perm) {
    json_fail('Kein Zugriff.', 403);
}
$canEdit = in_array($perm, ['owner', 'edit'], true);

// Alle verändernden Aktionen brauchen Edit-Recht + gültiges CSRF-Token
$mutating = ['save_slide', 'save_slide_label', 'add_slide', 'delete_slide', 'duplicate_slide', 'reorder_slides', 'apply_slide_template', 'apply_layout_from_set', 'import_slide_to_layout_set', 'save_layout_set_settings', 'save_logos_zones_accordion', 'toggle_public_link', 'set_display_options', 'save_meta', 'delete_media_asset', 'cleanup_unused_media', 'remove_image_background', 'add_share', 'remove_share', 'set_public_share', 'regenerate_public_token'];
if (in_array($action, $mutating, true)) {
    if (!$canEdit) {
        json_fail('Keine Bearbeitungsrechte.', 403);
    }
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_fail('Ungültiges CSRF-Token.', 403);
    }
}

// Folienvorlagen (nicht Folien-Sets) dürfen immer nur genau eine Folie haben.
$multiSlideActions = ['add_slide', 'delete_slide', 'duplicate_slide', 'reorder_slides'];
if (in_array($action, $multiSlideActions, true) && Presentation::isTemplate($id) && !LayoutSet::isLayoutSet($id)) {
    json_fail('Folienvorlagen bestehen bewusst aus nur einer Folie.', 400);
}

switch ($action) {
    case 'get_slides':
        $slides = Presentation::getSlides($id);
        $meta = Presentation::getMeta($id);
        json_ok(['slides' => $slides['slides'], 'meta' => $meta, 'permission' => $perm]);
        break;

    case 'slide_thumbnails':
        $slidesData = Presentation::getSlides($id);
        $meta = Presentation::getMeta($id) ?? [];
        $slideW = max(1, (int)($meta['width'] ?? 1920));
        $slideH = max(1, (int)($meta['height'] ?? 1080));
        $schematic = !empty($_GET['schematic']);
        $thumbnails = [];
        foreach ($slidesData['slides'] ?? [] as $slide) {
            $bg = $slide['background'] ?? null;
            $color = '#333333';
            if ($bg) {
                if (($bg['type'] ?? '') === 'color' || ($bg['type'] ?? '') === 'gradient') {
                    $color = $bg['value'] ?? '#333333';
                } elseif (($bg['type'] ?? '') === 'image') {
                    $color = '#222222';
                }
            }
            $thumbnails[] = [
                'id' => $slide['id'],
                'html' => $schematic
                    ? SlideRenderer::renderSlideSchematicThumbnailHtml($slide, $slideW, $slideH)
                    : SlideRenderer::renderSlideThumbnailHtml($slide, null),
                'color' => $color,
            ];
        }
        json_ok(['thumbnails' => $thumbnails]);
        break;

    case 'save_slide':
        $index = (int)($body['index'] ?? -1);
        $background = $body['background'] ?? ['type' => 'color', 'value' => '#111111'];
        $objects = $body['objects'] ?? [];
        $transition = $body['transition'] ?? null;
        $autoAdvance = isset($body['autoAdvance']) ? (int)$body['autoAdvance'] : null;
        $notes = isset($body['notes']) ? (string)$body['notes'] : null;
        $layoutKey = array_key_exists('layoutKey', $body) ? (string)$body['layoutKey'] : null;
        $label = array_key_exists('label', $body) ? (string)$body['label'] : null;
        $layoutSetSlideId = array_key_exists('layoutSetSlideId', $body) ? (string)$body['layoutSetSlideId'] : null;
        $result = Presentation::saveSlide($id, $index, $background, $objects, $transition, $autoAdvance, $notes, $layoutKey, $label, $layoutSetSlideId);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'save_slide_label':
        $index = (int)($body['index'] ?? -1);
        $label = (string)($body['label'] ?? '');
        $result = Presentation::saveSlideLabel($id, $index, $label);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'add_slide':
        $afterIndex = isset($body['after_index']) ? (int)$body['after_index'] : null;
        $result = Presentation::addSlide($id, $afterIndex);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'duplicate_slide':
        $index = (int)($body['index'] ?? -1);
        $result = Presentation::duplicateSlide($id, $index);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'delete_slide':
        $index = (int)($body['index'] ?? -1);
        $result = Presentation::deleteSlide($id, $index);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'toggle_slide_present_disabled':
        $index = (int)($body['index'] ?? -1);
        $result = Presentation::toggleSlidePresentDisabled($id, $index);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'reorder_slides':
        $order = $body['order'] ?? [];
        $result = Presentation::reorderSlides($id, $order);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'apply_transition_all':
        $transition = (string)($body['transition'] ?? 'slide');
        $autoAdvance = array_key_exists('autoAdvance', $body) ? max(0, (int)$body['autoAdvance']) : null;
        $result = Presentation::applyTransitionToAll($id, $transition, $autoAdvance);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'apply_transition_slides':
        $indices = array_values(array_filter(array_map('intval', $body['indices'] ?? []), fn($i) => $i >= 0));
        $transition = (string)($body['transition'] ?? 'slide');
        $autoAdvance = array_key_exists('autoAdvance', $body) ? max(0, (int)$body['autoAdvance']) : null;
        if ($indices === []) {
            json_fail('Keine Folien ausgewählt.', 400);
        }
        $result = Presentation::applyTransitionToSlides($id, $indices, $transition, $autoAdvance);
        json_ok(['slides' => $result['slides']]);
        break;

    case 'list_slide_templates':
        [$mine, $shared] = Presentation::listTemplatesForUser($me['id']);
        $enrichTemplateThumb = static function (array $meta): array {
            $slides = Presentation::getSlides($meta['id'])['slides'] ?? [];
            $slide = $slides[0] ?? null;
            if ($slide) {
                $bg = $slide['background'] ?? null;
                $color = '#333333';
                if ($bg) {
                    if (($bg['type'] ?? '') === 'color' || ($bg['type'] ?? '') === 'gradient') {
                        $color = $bg['value'] ?? '#333333';
                    } elseif (($bg['type'] ?? '') === 'image') {
                        $color = '#222222';
                    }
                }
                $slideW = max(100, (int)($meta['width'] ?? 1920));
                $slideH = max(100, (int)($meta['height'] ?? 1080));
                $meta['thumbHtml'] = SlideRenderer::renderSlideSchematicThumbnailHtml($slide, $slideW, $slideH);
                $meta['thumbColor'] = $color;
            }
            return $meta;
        };
        json_ok([
            'mine' => array_map($enrichTemplateThumb, $mine),
            'shared' => array_map($enrichTemplateThumb, $shared),
        ]);
        break;

    case 'list_layout_sets':
        [$mine, $shared] = LayoutSet::listForUser($me['id']);
        json_ok(['mine' => $mine, 'shared' => $shared]);
        break;

    case 'list_layout_set_layouts':
        $setId = $body['layout_set_id'] ?? ($_GET['layout_set_id'] ?? '');
        if (!LayoutSet::isLayoutSet($setId) || !Presentation::canUseTemplate($setId, $me['id'])) {
            json_fail('Keine Berechtigung für dieses Folien-Set.', 403);
        }
        $setMeta = Presentation::getMeta($setId);
        $slideW = max(100, (int)($setMeta['width'] ?? 1920));
        $slideH = max(100, (int)($setMeta['height'] ?? 1080));
        $layouts = [];
        foreach (Presentation::getSlides($setId)['slides'] ?? [] as $slide) {
            $key = LayoutSet::resolveSlideLayoutKey($slide);
            if ($key === '') {
                continue;
            }
            $bg = $slide['background'] ?? null;
            $color = '#333333';
            if ($bg) {
                if (($bg['type'] ?? '') === 'color' || ($bg['type'] ?? '') === 'gradient') {
                    $color = $bg['value'] ?? '#333333';
                } elseif (($bg['type'] ?? '') === 'image') {
                    $color = '#222222';
                }
            }
            $layouts[] = [
                'slideId' => (string)($slide['id'] ?? ''),
                'layoutKey' => $key,
                'title' => LayoutSet::slideLabel($slide),
                'slide' => $slide,
                'thumbHtml' => SlideRenderer::renderSlideThumbnailHtml($slide, null),
                'thumbColor' => $color,
            ];
        }
        json_ok(['layouts' => $layouts, 'layout_set_id' => $setId]);
        break;

    case 'apply_layout_from_set':
        $index = (int)($body['index'] ?? -1);
        $setId = $body['layout_set_id'] ?? '';
        $layoutKey = (string)($body['layout_key'] ?? '');
        $layoutSlideId = trim((string)($body['layout_slide_id'] ?? ''));
        if (!LayoutSet::isLayoutSet($setId) || !Presentation::canUseTemplate($setId, $me['id'])) {
            json_fail('Keine Berechtigung für dieses Folien-Set.', 403);
        }
        $layoutSlide = $layoutSlideId !== ''
            ? LayoutSet::findLayout($setId, $layoutKey, $layoutSlideId)
            : LayoutSet::findLayout($setId, $layoutKey);
        if (!$layoutSlide) {
            json_fail('Layout nicht gefunden.', 404);
        }
        $slidesData = Presentation::getSlides($id);
        $current = $slidesData['slides'][$index] ?? null;
        if (!$current) {
            json_fail('Folie nicht gefunden.', 404);
        }
        $merged = LayoutSet::applyLayoutToSlide($current, $layoutSlide);
        $layoutLabel = trim((string)($layoutSlide['label'] ?? ''));
        if ($layoutLabel === '') {
            $layoutLabel = LayoutSet::slideLabel($layoutSlide);
        }
        if ($layoutLabel !== '') {
            $merged['label'] = $layoutLabel;
        }
        $setSlideId = (string)($layoutSlide['id'] ?? $layoutSlideId);
        if ($setSlideId !== '') {
            $merged['layoutSetSlideId'] = $setSlideId;
        }
        $result = Presentation::saveSlide(
            $id,
            $index,
            $merged['background'],
            $merged['objects'],
            $merged['transition'] ?? null,
            $merged['autoAdvance'] ?? null,
            $merged['notes'] ?? null,
            $merged['layoutKey'] ?? $layoutKey,
            $merged['label'] ?? null,
            $merged['layoutSetSlideId'] ?? null
        );
        if (!empty($merged['label']) && empty($result['slides'][$index]['label'])) {
            $result = Presentation::saveSlideLabel($id, $index, (string)$merged['label']);
        }
        json_ok(['slides' => $result['slides']]);
        break;

    case 'import_slide_to_layout_set':
        $index = (int)($body['index'] ?? -1);
        $setId = trim((string)($body['layout_set_id'] ?? ''));
        $layoutKey = trim((string)($body['layout_key'] ?? ''));
        if (!LayoutSet::isLayoutSet($setId)) {
            json_fail(t('tpl.layout_set_invalid'), 400);
        }
        $setMeta = Presentation::getMeta($setId);
        $canEditSet = $setMeta
            && ($setMeta['owner_id'] === $me['id'] || Auth::isAdmin());
        if (!$canEditSet) {
            json_fail(t('tpl.no_permission'), 403);
        }
        if ($layoutKey === '') {
            $slidesData = Presentation::getSlides($id);
            $slide = $slidesData['slides'][$index] ?? null;
            $layoutKey = $slide ? LayoutSet::layoutKeyFromTitle(LayoutSet::slideLabel($slide)) : '';
        }
        try {
            LayoutSet::importPresentationSlide($setId, $id, $index, $layoutKey);
        } catch (Throwable $e) {
            json_fail($e->getMessage(), 400);
        }
        json_ok(['message' => t('editor.import_slide_to_set_done')]);
        break;

    case 'save_layout_set_settings':
        if (!LayoutSet::isLayoutSet($id) || !Presentation::canUseTemplate($id, $me['id'])) {
            json_fail('Keine Berechtigung.', 403);
        }
        $fields = [];
        if (isset($body['logosNotesOrder']) && is_array($body['logosNotesOrder'])) {
            $fields['logosNotesOrder'] = array_values(array_filter(
                array_map('strval', $body['logosNotesOrder']),
                fn($r) => in_array($r, LayoutSet::LOGOS_ROLES, true)
            ));
        }
        if (isset($body['elementZones']) && is_array($body['elementZones'])) {
            $zones = [];
            foreach (LayoutSet::ELEMENT_ZONES as $zone) {
                $roles = $body['elementZones'][$zone] ?? [];
                if (!is_array($roles)) {
                    continue;
                }
                $zones[$zone] = array_values(array_filter(
                    array_map('strval', $roles),
                    fn($r) => in_array($r, LayoutSet::LOGOS_ZONE_ROLES, true)
                ));
            }
            $fields['elementZones'] = $zones;
        }
        if (isset($body['elementTextLinks']) && is_array($body['elementTextLinks'])) {
            $links = [];
            foreach (ElementLink::allRoles() as $role) {
                if (!array_key_exists($role, $body['elementTextLinks'])) {
                    continue;
                }
                $val = $body['elementTextLinks'][$role];
                $links[$role] = ($val !== null && $val !== '') ? (string)$val : null;
            }
            $fields['elementTextLinks'] = $links;
        }
        if (isset($body['logosImportSettings']) && is_array($body['logosImportSettings'])) {
            $fields['logosImportSettings'] = LayoutSet::normalizeLogosImportSettings($body['logosImportSettings']);
        }
        if (!empty($fields)) {
            Presentation::updateMeta($id, $fields);
        }
        $meta = Presentation::getMeta($id) ?? [];
        json_ok([
            'meta' => $meta,
            'elementZones' => LayoutSet::elementZones($meta),
            'elementTextLinks' => LayoutSet::elementTextLinks($meta),
            'logosImportSettings' => LayoutSet::logosImportSettings($meta),
        ]);
        break;

    case 'save_logos_zones_accordion':
        Auth::setLogosZonesAccordionOpen($me['id'], !empty($body['open']));
        json_ok(['open' => Auth::logosZonesAccordionOpen(Auth::currentUser())]);
        break;

    case 'apply_slide_template':
        $index = (int)($body['index'] ?? -1);
        $templateId = $body['template_id'] ?? '';
        if (!Presentation::canUseTemplate($templateId, $me['id'])) {
            json_fail('Keine Berechtigung für diese Vorlage.', 403);
        }
        $slide = Presentation::getTemplateSlideContent($templateId);
        if (!$slide) {
            json_fail('Vorlage ist leer oder ungültig.', 404);
        }
        $result = Presentation::saveSlide(
            $id, $index,
            $slide['background'] ?? ['type' => 'color', 'value' => '#111111'],
            $slide['objects'] ?? [],
            $slide['transition'] ?? 'slide'
        );
        json_ok(['slides' => $result['slides']]);
        break;

    case 'toggle_public_link':
        if ($perm !== 'owner') {
            json_fail(t('share.owner_only'), 403);
        }
        $enabled = !empty($body['enabled']);
        $public = Presentation::setPublic($id, $enabled, 'view');
        $publicUrl = $enabled ? sf_public_view_url($public['token'] ?? null) : null;
        json_ok(['enabled' => $enabled, 'url' => $publicUrl]);
        break;

    case 'get_share':
        if ($perm !== 'owner') {
            json_fail(t('share.owner_only'), 403);
        }
        json_ok(sf_share_state($id, $me['id']));
        break;

    case 'add_share':
        if ($perm !== 'owner') {
            json_fail(t('share.owner_only'), 403);
        }
        if (Presentation::isTemplate($id)) {
            json_fail(t('share.owner_only'), 400);
        }
        $username = trim((string)($body['username'] ?? ''));
        $permission = (($body['permission'] ?? 'view') === 'edit') ? 'edit' : 'view';
        $target = Auth::findByUsername($username);
        if (!$target) {
            json_fail(t('share.user_not_found'));
        }
        if (($target['id'] ?? '') === $me['id']) {
            json_fail(t('share.cannot_share_self'));
        }
        $meta = Presentation::getMeta($id) ?? [];
        Presentation::addShare($id, $target['id'], $target['username'], $permission);
        $mail = ShareNotification::send($target, $me, $meta, $permission);
        $message = t('share.saved_for', ['user' => $target['username']]);
        $warning = null;
        if (!empty($mail['ok'])) {
            $message = t('share.saved_and_mailed', ['user' => $target['username'], 'email' => $target['email']]);
        } elseif (($mail['error'] ?? '') === 'no_smtp') {
            $warning = t('share.mail_no_smtp');
        } elseif (($mail['error'] ?? '') === 'invalid_email') {
            $warning = t('share.mail_invalid_email', ['user' => $target['username']]);
        } else {
            $warning = t('share.mail_send_failed', ['error' => $mail['error'] ?? t('admin.test_mail_unknown_error')]);
        }
        json_ok(sf_share_state($id, $me['id']) + ['message' => $message, 'warning' => $warning]);
        break;

    case 'remove_share':
        if ($perm !== 'owner') {
            json_fail(t('share.owner_only'), 403);
        }
        Presentation::removeShare($id, (string)($body['user_id'] ?? ''));
        json_ok(sf_share_state($id, $me['id']) + ['message' => t('share.removed')]);
        break;

    case 'set_public_share':
        if ($perm !== 'owner') {
            json_fail(t('share.owner_only'), 403);
        }
        $enabled = !empty($body['enabled']);
        Presentation::setPublic($id, $enabled, 'view');
        $state = sf_share_state($id, $me['id']);
        json_ok($state + [
            'message' => $enabled ? t('share.public_enabled') : t('share.public_disabled'),
        ]);
        break;

    case 'regenerate_public_token':
        if ($perm !== 'owner') {
            json_fail(t('share.owner_only'), 403);
        }
        Presentation::regeneratePublicToken($id);
        json_ok(sf_share_state($id, $me['id']) + ['message' => t('share.token_regenerated')]);
        break;

    case 'set_display_options':
        $fields = [];
        if (array_key_exists('show_progress', $body)) {
            $fields['show_progress'] = !empty($body['show_progress']);
        }
        if (array_key_exists('show_controls', $body)) {
            $fields['show_controls'] = !empty($body['show_controls']);
        }
        Presentation::updateMeta($id, $fields);
        json_ok(['meta' => Presentation::getMeta($id)]);
        break;

    case 'save_meta':
        $meta = Presentation::getMeta($id) ?? [];
        $fields = [];
        if (array_key_exists('title', $body)) {
            $title = trim((string)$body['title']);
            if ($title !== '') {
                $fields['title'] = $title;
            }
        }
        if (array_key_exists('width', $body)) {
            $fields['width'] = max(100, (int)$body['width']);
        }
        if (array_key_exists('height', $body)) {
            $fields['height'] = max(100, (int)$body['height']);
        }
        if (array_key_exists('safe_margin', $body)) {
            $fields['safe_margin'] = max(0, (int)$body['safe_margin']);
        }
        if (empty($meta['is_template'])) {
            if (array_key_exists('presentation_duration', $body)) {
                $fields['presentation_duration'] = max(1, (int)$body['presentation_duration']);
            }
            if (array_key_exists('layout_set_id', $body)) {
                $fields['layout_set_id'] = trim((string)$body['layout_set_id']);
            }
        }
        if ($fields) {
            Presentation::updateMeta($id, $fields);
        }
        json_ok(['meta' => Presentation::getMeta($id)]);
        break;

    case 'list_media':
        json_ok(['items' => Presentation::listMediaAssets($id)]);
        break;

    case 'delete_media_asset':
        $filename = basename((string)($body['filename'] ?? ''));
        $result = Presentation::deleteMediaAsset($id, $filename);
        if (!$result['ok']) {
            $messages = [
                'invalid_filename' => 'Ungültiger Dateiname.',
                'in_use' => 'Datei wird noch auf mindestens einer Folie verwendet.',
                'not_found' => 'Datei nicht gefunden.',
                'delete_failed' => 'Löschen fehlgeschlagen.',
            ];
            json_fail($messages[$result['error']] ?? 'Löschen fehlgeschlagen.', 400);
        }
        json_ok(['items' => Presentation::listMediaAssets($id)]);
        break;

    case 'cleanup_unused_media':
        $result = Presentation::cleanupUnusedMediaAssets($id);
        if (!$result['ok']) {
            $messages = [
                'delete_failed' => 'Einige Dateien konnten nicht gelöscht werden.',
            ];
            json_fail($messages[$result['error']] ?? 'Aufräumen fehlgeschlagen.', 400);
        }
        json_ok([
            'items' => Presentation::listMediaAssets($id),
            'deleted' => $result['deleted'] ?? [],
            'count' => (int)($result['count'] ?? 0),
        ]);
        break;

    case 'remove_image_background':
        $filename = basename((string)($body['filename'] ?? ''));
        $result = ImageHelper::removeBackgroundFromAsset($id, $filename);
        if (!$result['ok']) {
            $messages = [
                'invalid_filename' => t('props.remove_background_invalid'),
                'not_found' => t('props.remove_background_not_found'),
                'unsupported_type' => t('props.remove_background_unsupported'),
                'process_failed' => t('props.remove_background_failed'),
                'save_failed' => t('props.remove_background_failed'),
            ];
            json_fail($messages[$result['error'] ?? ''] ?? t('props.remove_background_failed'), 400);
        }
        json_ok([
            'url' => $result['url'],
            'filename' => $result['filename'],
        ]);
        break;

    default:
        json_fail('Unbekannte Aktion.', 400);
}
