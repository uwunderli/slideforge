<?php
/**
 * Statusleiste unten (Urs 27.11.2026: «links die ausgeführten Aktionen wie gespeichert etc.», «In der Mitte kannst du ja die Datei
 * hinschreiben», «rechts wieder die Versionsnummer»). Wie die Leiste von FileForge (ff-statusbar). Inhalt links/Mitte setzt sf-ff.js.
 * @var string|null $sfStatusFile Name der geöffneten Datei (Editor), sonst leer
 */
$sfVersion = is_readable(BASE_PATH . '/VERSION') ? trim((string)file_get_contents(BASE_PATH . '/VERSION')) : '';
?>
<footer class="sf-statusbar" id="sfStatusbar" aria-label="Status">
  <div class="sf-statusbar__msg" id="sfStatusMsg" aria-live="polite"></div>
  <div class="sf-statusbar__file" id="sfStatusFile"><?= h((string)($sfStatusFile ?? '')) ?></div>
  <span class="sf-statusbar__ver" title="FolienSchmiede-Version"><?= $sfVersion !== '' ? 'v' . h($sfVersion) : '' ?></span>
</footer>
