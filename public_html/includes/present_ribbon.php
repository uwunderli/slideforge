<?php
/** @var bool $canBroadcast */
/** @var string $perm */
/** @var array $meta */
/** @var array $acl */
/** @var string $publicUrl */
/** @var string $remoteUrl */
/** @var string $remoteQrSrc */
/** @var array $me */
/** @var list<array{id:string,visible?:bool}> $presentPanels */
/** @var array $presentLayout */
/** @var array $presentPanelIcons */
/** @var array $presentPanelLabels */
/** @var bool $showTimebar */
?>
<div
  id="presentRibbon"
  class="editor-ribbon present-ribbon"
  data-user-id="<?= h($me['id'] ?? '') ?>"
  data-sf-ribbon="present"
>
  <div class="editor-ribbon-tabs" role="tablist" aria-label="<?= h(t('present.section_present')) ?>"></div>
  <div class="editor-ribbon-panels"></div>
</div>

<div id="presentRibbonWidgetTemplates" class="ribbon-widget-templates" hidden aria-hidden="true">
  <?php if ($canBroadcast): ?>
  <div class="ribbon-widget-shell" data-widget-id="widget-present-audience">
    <?php if ($perm === 'owner'): ?>
    <div class="ribbon-present-display-inner">
      <div class="ribbon-present-display-col ribbon-present-display-col--link-grid">
        <label class="ribbon-toggle" title="<?= h(t('present.public_link')) ?>">
          <input type="checkbox" id="publicLinkToggle" role="switch" <?= !empty($acl['public']['enabled']) ? 'checked' : '' ?>>
          <span class="ribbon-toggle-track" aria-hidden="true"></span>
          <span class="ribbon-toggle-label"><?= h(t('ribbon.present_public_short')) ?></span>
        </label>
        <button type="button" class="button button-ghost button-sm ribbon-present-display-copy" id="sharePublicLinkBtn" hidden <?= (!empty($acl['public']['enabled']) && $publicUrl) ? '' : 'disabled' ?> title="<?= h(t('present.share_link_title')) ?>"><?= h(t('present.share_link')) ?></button>
        <button type="button" class="button button-ghost button-sm ribbon-present-display-copy" id="copyPublicLinkBtn" <?= (!empty($acl['public']['enabled']) && $publicUrl) ? '' : 'disabled' ?> title="<?= h(t('present.copy_link')) ?>"><?= h(t('present.copy')) ?></button>
        <button type="button" class="button button-ghost button-sm ribbon-present-display-copy" id="downloadPublicLinkBtn" <?= (!empty($acl['public']['enabled']) && $publicUrl) ? '' : 'disabled' ?> title="<?= h(t('present.download_link_title')) ?>"><?= h(t('present.download_link')) ?></button>
        <input type="hidden" id="presentPublicLinkInput" value="<?= h($publicUrl) ?>">
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="ribbon-widget-shell" data-widget-id="widget-present-local">
    <div class="present-ribbon-local-inner">
      <label class="ribbon-present-display-screen-label" for="presentScreenSelect"><?= h(t('ribbon.present_screen_short')) ?></label>
      <select id="presentScreenSelect" class="present-screen-select ribbon-present-screen-select" aria-describedby="presentScreenHint"></select>
      <p class="present-screen-hint ribbon-present-screen-hint" id="presentScreenHint" hidden></p>
      <button type="button" class="button button-primary present-local-btn" id="presentLocalBtn"><?= h(t('present.local_start')) ?></button>
    </div>
  </div>
  <?php if ($remoteUrl !== ''): ?>
  <div class="ribbon-widget-shell" data-widget-id="widget-present-remote">
    <div class="present-ribbon-remote-inner">
      <div class="present-ribbon-remote-status">
        <span class="present-remote-badge" id="presentRemoteBadge" hidden>
          <span class="present-remote-badge-dot"></span>
          <?= h(t('remote.mobile_connected')) ?>
        </span>
        <span class="present-control-status" id="presentControlStatus" hidden>
          <span class="present-control-status-dot" id="presentControlDot" aria-hidden="true"></span>
          <?= h(t('present.control_status')) ?>
        </span>
        <button type="button" class="button button-ghost button-sm ribbon-present-display-copy present-remote-link-copy" id="copyRemoteLinkBtn" title="<?= h(t('remote.copy_url')) ?>"><?= h(t('remote.copy_url')) ?></button>
      </div>
      <button
        type="button"
        class="ribbon-btn ribbon-grid-btn ribbon-btn-tall present-remote-qr-ribbon-btn"
        id="presentRemoteQrBtn"
        data-ribbon-command="remote_qr"
        title="<?= h(t('ribbon.remote_qr_short')) ?>"
      >
        <img class="present-remote-qr-ribbon-img" src="<?= h($remoteQrSrc) ?>" width="40" height="40" alt="" decoding="async">
        <span class="ribbon-btn-label"><?= h(t('ribbon.remote_qr_short')) ?></span>
      </button>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($canBroadcast && $remoteUrl !== ''): ?>
<div class="modal-backdrop present-remote-qr-modal-backdrop" id="presentRemoteQrModal" aria-hidden="true">
  <div class="modal present-remote-qr-modal" role="dialog" aria-modal="true" aria-labelledby="presentRemoteQrModalTitle">
    <div class="sf-dialog-header">
      <h2 id="presentRemoteQrModalTitle" class="sf-dialog-title"><?= h(t('remote.qr_section')) ?></h2>
      <button type="button" class="sf-dialog-close" id="presentRemoteQrModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint"><?= h(t('remote.qr_hint')) ?></p>
    <div class="modal-dialog-body present-remote-qr-modal-body">
      <div class="present-remote-qr-box present-remote-qr-box--dialog">
        <img id="presentRemoteQr" src="<?= h($remoteQrSrc) ?>" width="280" height="280" alt="<?= h(t('remote.qr_section')) ?>" decoding="async">
      </div>
      <button type="button" class="button button-ghost button-sm" id="copyRemoteLinkPanelBtn"><?= h(t('remote.copy_url')) ?></button>
    </div>
    <div class="sf-dialog-actions modal-actions">
      <button type="button" class="button" id="presentRemoteQrModalOk"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="modal-backdrop present-timebar-settings-modal-backdrop" id="presentTimebarSettingsModal" aria-hidden="true">
  <div class="modal present-timebar-settings-modal" role="dialog" aria-modal="true" aria-labelledby="presentTimebarSettingsModalTitle">
    <div class="sf-dialog-header">
      <h2 id="presentTimebarSettingsModalTitle" class="sf-dialog-title"><?= h(t('present.settings_submenu_timebar')) ?></h2>
      <button type="button" class="sf-dialog-close" id="presentTimebarSettingsModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint"><?= h(t('present.timebar_stops_hint')) ?></p>
    <div class="modal-dialog-body present-timebar-settings-modal-body">
      <div class="present-config-section">
        <div class="present-config-section-title"><?= h(t('present.timebar_stops_label')) ?></div>
        <div id="presentTimebarStopsList"></div>
        <button type="button" class="button button-ghost button-sm" id="presentAddTimebarStopBtn"><?= h(t('present.add_timebar_stop')) ?></button>
      </div>
    </div>
    <div class="sf-dialog-actions modal-actions">
      <button type="button" class="button" id="presentTimebarSettingsModalOk"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>

<div class="modal-backdrop present-laser-settings-modal-backdrop" id="presentLaserSettingsModal" aria-hidden="true">
  <div class="modal present-laser-settings-modal" role="dialog" aria-modal="true" aria-labelledby="presentLaserSettingsModalTitle">
    <div class="sf-dialog-header">
      <h2 id="presentLaserSettingsModalTitle" class="sf-dialog-title"><?= h(t('present.settings_submenu_laser')) ?></h2>
      <button type="button" class="sf-dialog-close" id="presentLaserSettingsModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint"><?= h(t('present.laser_hint')) ?></p>
    <div class="modal-dialog-body present-laser-settings-modal-body">
      <div class="present-config-section" id="presentLaserOptions">
        <div class="present-laser-preview" id="presentLaserPreview" aria-hidden="true">
          <span class="present-laser-preview-dot" id="presentLaserPreviewDot"></span>
        </div>
        <label for="presentLaserColor"><?= h(t('present.laser_color')) ?></label>
        <input type="color" id="presentLaserColor" value="<?= h($presentLayout['laserPointerColor'] ?? '#ff0000') ?>">
        <div class="brand-palette mini" id="presentLaserPalette"></div>
        <label for="presentLaserSize" style="margin-top:12px;"><?= h(t('present.laser_size')) ?> (<span id="presentLaserSizeVal"><?= (int)($presentLayout['laserPointerSize'] ?? 24) ?></span> px)</label>
        <input type="range" id="presentLaserSize" min="8" max="64" step="1" value="<?= (int)($presentLayout['laserPointerSize'] ?? 24) ?>">
        <label style="display:flex;align-items:center;gap:8px;margin-top:14px;cursor:pointer;">
          <input type="checkbox" id="presentLaserTrail" style="width:auto;" <?= !empty($presentLayout['laserPointerTrail']) ? 'checked' : '' ?>>
          <?= h(t('present.laser_trail')) ?>
        </label>
        <p class="props-video-note" style="margin-top:6px;"><?= h(t('present.laser_trail_hint')) ?></p>
      </div>
    </div>
    <div class="sf-dialog-actions modal-actions">
      <button type="button" class="button" id="presentLaserSettingsModalOk"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>

<div class="modal-backdrop present-clock-settings-modal-backdrop" id="presentClockSettingsModal" aria-hidden="true">
  <div class="modal present-clock-settings-modal" role="dialog" aria-modal="true" aria-labelledby="presentClockSettingsModalTitle">
    <div class="sf-dialog-header">
      <h2 id="presentClockSettingsModalTitle" class="sf-dialog-title"><?= h(t('present.clock_section')) ?></h2>
      <button type="button" class="sf-dialog-close" id="presentClockSettingsModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint"><?= h(t('present.clock_order_hint')) ?></p>
    <div class="modal-dialog-body present-clock-settings-modal-body">
      <div class="present-config-section">
        <div class="present-config-section-title"><?= h(t('present.clock_order_label')) ?></div>
        <div class="clock-order-list" id="presentClockOrderList"></div>
      </div>
    </div>
    <div class="sf-dialog-actions modal-actions">
      <button type="button" class="button" id="presentClockSettingsModalOk"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>

<div class="modal-backdrop present-notes-settings-modal-backdrop" id="presentNotesSettingsModal" aria-hidden="true">
  <div class="modal present-notes-settings-modal" role="dialog" aria-modal="true" aria-labelledby="presentNotesSettingsModalTitle">
    <div class="sf-dialog-header">
      <h2 id="presentNotesSettingsModalTitle" class="sf-dialog-title"><?= h(t('present.settings_submenu_notes')) ?></h2>
      <button type="button" class="sf-dialog-close" id="presentNotesSettingsModalClose" aria-label="<?= h(t('common.close')) ?>">×</button>
    </div>
    <p class="sf-dialog-hint"><?= h(t('present.notes_mode_hint')) ?></p>
    <div class="modal-dialog-body present-notes-settings-modal-body">
      <div class="present-config-section" role="radiogroup" aria-labelledby="presentNotesSettingsModalTitle">
        <label class="present-config-check present-config-check-block">
          <input type="radio" name="presentNotesMode" value="always_open" id="presentNotesModeAlwaysOpen">
          <span class="present-config-check-grow">
            <strong><?= h(t('present.notes_mode_always_open')) ?></strong>
            <span class="present-notes-mode-desc"><?= h(t('present.notes_mode_always_open_desc')) ?></span>
          </span>
        </label>
        <label class="present-config-check present-config-check-block">
          <input type="radio" name="presentNotesMode" value="always_closed" id="presentNotesModeAlwaysClosed">
          <span class="present-config-check-grow">
            <strong><?= h(t('present.notes_mode_always_closed')) ?></strong>
            <span class="present-notes-mode-desc"><?= h(t('present.notes_mode_always_closed_desc')) ?></span>
          </span>
        </label>
        <label class="present-config-check present-config-check-block">
          <input type="radio" name="presentNotesMode" value="carry" id="presentNotesModeCarry" checked>
          <span class="present-config-check-grow">
            <strong><?= h(t('present.notes_mode_carry')) ?></strong>
            <span class="present-notes-mode-desc"><?= h(t('present.notes_mode_carry_desc')) ?></span>
          </span>
        </label>
      </div>
    </div>
    <div class="sf-dialog-actions modal-actions">
      <button type="button" class="button" id="presentNotesSettingsModalOk"><?= h(t('common.close')) ?></button>
    </div>
  </div>
</div>
