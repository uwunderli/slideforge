<?php
/**
 * Startseite der FolienSchmiede im Hub (27.11.2026): Die Präsentationen liegen als .cfs in FileForge.
 * Zuletzt geöffnet (je Person, 20) · Für mich freigegeben (FileForge-Freigaben) · Neu / Öffnen / PowerPoint/ODP übernehmen ·
 * Vorlagen-Ordner: Hub › FolienSchmiede › Modul-Einstellungen (admin_settings.php).
 */
require __DIR__ . '/../config.php';
Auth::requireLogin();

$pageTitle = t('dashboard.title');
$hideTopbar = true;
$bodyClass = 'sf-home-page';
require __DIR__ . '/includes/header.php';
?>
<div class="hub-ribbon" id="sfRibbon" data-icon-size="small" role="region" aria-label="Menüband">
  <div class="hub-ribbon__tabs" role="tablist"><span class="hub-ribbon__tab is-active" role="tab" aria-selected="true">Start</span></div>
  <div class="hub-ribbon__panels"><div class="hub-ribbon__panel" id="sfRibbonBody" role="tabpanel"></div></div>
</div>
<main class="sf-home">
  <section class="sf-home__block" aria-labelledby="sfRecentTitle">
    <h2 id="sfRecentTitle">Zuletzt geöffnet</h2>
    <div id="sfRecent"><p class="muted small">Lädt …</p></div>
  </section>
  <section class="sf-home__block" aria-labelledby="sfForMeTitle">
    <h2 id="sfForMeTitle">Für mich freigegeben</h2>
    <div id="sfForMe"><p class="muted small">Lädt …</p></div>
  </section>
</main>
<script src="<?= h(sf_hub_asset('js/hub-ribbon-customize.js')) ?>"></script>
<script src="<?= h(sf_hub_asset('js/hub-ribbon.js')) ?>"></script>
<script src="assets/js/sf-ff.js?v=<?= ASSET_VERSION ?>"></script>
<script>
(function () {
  var act = SfFf.home(<?= json_encode(['csrf' => csrf_token()], JSON_HEX_TAG) ?>);
  if (!window.HubRibbon) return;
  var ic = function (p) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; };
  var commands = {
    newBlank: { id: 'newBlank', elId: 'sfNewBlank', label: 'Neu', title: 'Neue leere Präsentation in FileForge anlegen', icon: ic('<rect x="4" y="5" width="16" height="12" rx="1.5"/><path d="M12 8v6M9 11h6"/>') },
    newFromTemplate: { id: 'newFromTemplate', elId: 'sfNewTpl', label: 'Aus Vorlage …', title: 'Neue Präsentation aus einer Vorlage (.cfs)', icon: ic('<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18M8 9v9"/>') },
    open: { id: 'open', elId: 'sfOpen', label: 'Öffnen …', title: 'Präsentation (.cfs) aus FileForge öffnen', icon: ic('<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>') },
    importPpt: { id: 'importPpt', elId: 'sfImport', label: 'PowerPoint/ODP übernehmen …', title: 'PowerPoint (.pptx) oder ODP übernehmen; gespeichert wird als .cfs daneben', icon: ic('<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/><path d="M9 13h4a2 2 0 0 1 0 4H9v-4zM9 17v2"/>') }
  };
  HubRibbon.create({ root: document.getElementById('sfRibbon'), body: document.getElementById('sfRibbonBody'), commands: commands,
    defaultLayout: { iconSize: 'small', hideLabels: false, groups: [
      { id: 'neu', label: 'Neu', items: ['newBlank', 'newFromTemplate'] },
      { id: 'datei', label: 'Datei', items: ['open', 'importPpt'] }
    ] }, storageKey: 'sf_home_ribbon_v1', dialog: window.HubFloatDialog });
  var map = { sfNewBlank: 'newBlank', sfNewTpl: 'newFromTemplate', sfOpen: 'open', sfImport: 'importPpt' };
  document.getElementById('sfRibbonBody').addEventListener('click', function (e) {
    var b = e.target.closest('button[id]');
    if (!b || b.disabled || !map[b.id]) return;
    act[map[b.id]]();
  });
})();
</script>
<?php if (($_GET['hinweis'] ?? '') === 'kopie'): /* Arbeitskopie war weg (Neuladen im Hub nach dem Aufräumen) */ ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.CfStatus && window.CfStatus.show) {
    window.CfStatus.show('info', <?= json_encode(!empty($_GET['open'])
        ? 'Die Arbeitskopie gab es nicht mehr — die Präsentation wird neu aus FileForge geöffnet.'
        : 'Die Arbeitskopie gibt es nicht mehr. Bitte die Präsentation neu öffnen.', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>);
  }
});
</script>
<?php endif; ?>
<?php $sfStatusFile = ''; require __DIR__ . '/includes/statusbar.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
