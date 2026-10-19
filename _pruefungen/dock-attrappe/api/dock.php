<?php
/**
 * Attrappe des Hub-Endpunkts `api/dock.php` — nur fuer den Pruefstand.
 * Der Fall steht in ../fall.json: {"fall": "...", "version": "..."}.
 */
$steuer = json_decode((string)@file_get_contents(__DIR__ . '/../fall.json'), true);
// Die Zeichen muss der Hub selbst ausliefern; das Modul verwirft fremde Herkunft.
$eigen = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8121');
$fall = is_array($steuer) ? (string)($steuer['fall'] ?? 'liste') : 'liste';

header('Content-Type: application/json; charset=utf-8');
$raus = static function (int $status, array $daten): void {
    http_response_code($status);
    echo json_encode(['api' => 1] + $daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

// Jeder Aufruf wird mitgeschrieben, damit der Prueffall zaehlen kann.
@file_put_contents(__DIR__ . '/../aufrufe.log',
    ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['QUERY_STRING'] ?? '')
    . ' auth=' . (isset($_SERVER['HTTP_AUTHORIZATION']) ? 'ja' : 'nein') . "\n", FILE_APPEND);

if ($fall === 'langsam') {
    sleep(3);   // laenger als das Zeitlimit des Moduls (2 s), aber nicht ewig
}
if ($fall === 'unauthorized') {
    $raus(401, ['ok' => false, 'error' => 'unauthorized']);
}
if ($fall === 'forbidden') {
    $raus(403, ['ok' => false, 'error' => 'forbidden']);
}
if ($fall === 'unknown') {
    $raus(404, ['ok' => false, 'error' => 'unknown_subject']);
}
if ($fall === 'lock_unknown') {
    // Vertrag Fassung 2: Sperrstand unlesbar -> wie «Hub nicht erreichbar».
    $raus(503, ['ok' => false, 'error' => 'lock_unknown']);
}
if ($fall === 'kaputt') {
    echo '{das ist kein json';
    exit;
}
if ($fall === 'leer') {
    $raus(200, ['ok' => true, 'version' => 'v-leer', 'subject' => 'u:eva', 'isAdmin' => false,
        'hub' => ['label' => 'Hub', 'href' => 'https://hub.test/', 'iconUrl' => $eigen . '/icons/hub.svg'],
        'items' => [], 'locked' => false, 'ttl' => 300]);
}
if ($fall === 'gesperrt') {
    $raus(200, ['ok' => true, 'version' => 'v-sperre', 'subject' => 'u:eva', 'isAdmin' => false,
        'hub' => ['label' => 'Hub', 'href' => 'https://hub.test/', 'iconUrl' => $eigen . '/icons/hub.svg'],
        'items' => [], 'locked' => true, 'ttl' => 300]);
}
if ($fall === 'gesperrt-mit-eintrag') {
    // Eine Antwort, die sich selbst widerspricht: gesperrt, aber mit Eintrag.
    $raus(200, ['ok' => true, 'version' => 'v-sperre2', 'subject' => 'u:eva', 'isAdmin' => true,
        'hub' => ['label' => 'Hub', 'href' => 'https://hub.test/', 'iconUrl' => $eigen . '/icons/hub.svg'],
        'items' => [['id' => 'planforge', 'label' => 'PlanSchmiede', 'href' => 'https://plan.test/',
                     'iconUrl' => $eigen . '/i/plan.svg', 'newTab' => false]],
        'locked' => true, 'ttl' => 300]);
}
if ($fall === 'boese') {
    // Adressen, die nicht ins Markup duerfen, und Unsinn in den Feldern.
    $raus(200, ['ok' => true, 'version' => 'v-boese', 'subject' => 'u:eva', 'isAdmin' => true,
        'hub' => ['label' => 'Hub', 'href' => 'javascript:alert(1)', 'iconUrl' => 'data:text/html,<script>'],
        'items' => [
            ['id' => 'js', 'label' => 'Boese', 'href' => 'javascript:alert(1)', 'iconUrl' => '', 'newTab' => false],
            ['id' => '', 'label' => 'Ohne Kennung', 'href' => 'https://a.test/', 'newTab' => false],
            ['id' => 'ok', 'label' => '<b>Mit "Anfuehrung"</b>', 'shortLabel' => '<b>Kurz</b>',
             'href' => 'https://ok.test/?a=1&b=2', 'iconUrl' => 'https://hub.test/i.svg', 'newTab' => true],
            'kein-feld',
        ], 'locked' => false, 'ttl' => 300]);
}

$version = is_array($steuer) ? (string)($steuer['version'] ?? 'v1') : 'v1';
if (($_GET['version'] ?? '') === $version) {
    $raus(200, ['ok' => true, 'version' => $version, 'unchanged' => true, 'ttl' => 300]);
}
$raus(200, [
    'ok' => true,
    'version' => $version,
    'subject' => (string)($_GET['for'] ?? ''),
    'isAdmin' => false,
    'hub' => ['label' => 'Hub', 'href' => 'https://hub.test/', 'iconUrl' => $eigen . '/icons/hub.svg'],
    'items' => [
        ['id' => 'planforge', 'label' => 'PlanSchmiede', 'shortLabel' => 'Plan',
         'href' => 'https://plan.test/', 'iconUrl' => $eigen . '/i/plan.svg', 'newTab' => false],
        ['id' => 'go', 'label' => 'Group-Office', 'shortLabel' => 'Group-Office',
         'href' => 'https://go.test/', 'iconUrl' => $eigen . '/i/go.svg', 'newTab' => true],
    ],
    'locked' => false,
    'ttl' => 300,
]);
