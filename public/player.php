<?php
/**
 * **Einstieg des Anzeige-Geräts** (Urs, 27.11.2026). Der Hub (`device.php`) lädt diese Seite im Vollbild-Rahmen mit
 * `?p=<Präsentation>&hub_geraet=<Gerät>&hub_gt=<Einmal-Ticket>`. SlideForge löst das Ticket beim Hub ein (`Geraete::einloesen`)
 * und öffnet die Zuschauer-Ansicht, die dem Presenter bzw. der Tablet-Fernsteuerung folgt. Ohne gültiges Ticket: nichts.
 */
require __DIR__ . '/../config.php';

header('Cache-Control: no-store');
$id = (string)($_GET['p'] ?? '');
$mit = ($_GET['mit'] ?? '') === '1';   // Mitlaufen (3.8.0): folgt dem Editor statt dem Presenter
$ok = Geraete::darf($id)
    || Geraete::einloesen((string)($_GET['hub_geraet'] ?? ''), (string)($_GET['hub_gt'] ?? ''), $id);
// Wechsel ohne Neuladen (Urs 27.11.: ein Tippen gibt Vollbild und Ton frei, auch in Safari): die laufende Zuschauer-Ansicht
// löst das Ticket per fetch ein (`json=1`) und tauscht die Folien selbst aus — die Geste im Dokument bleibt gültig.
if (($_GET['json'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($ok ? ['ok' => true, 'id' => $id, 'mit' => $mit] : ['ok' => false, 'error' => 'nicht_freigegeben']);
    exit;
}
if (!$ok) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><body style="margin:0;height:100vh;display:flex;align-items:center;justify-content:center;'
        . 'background:#000;color:#fff;font:16px system-ui,sans-serif">Die Präsentation ist nicht mehr freigegeben.</body>';
    exit;
}
header('Location: present_audience.php?id=' . rawurlencode($id) . '&geraet=1' . ($mit ? '&mit=1' : ''));
exit;
