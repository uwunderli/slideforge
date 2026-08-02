/**
 * Bindet bestehende SlideForge .modal-backdrop-Dialoge an HubFloatDialog
 * (globale hub-float-dialog Klassen). Confirm-Overlay (.sf-ui-dialog) bleibt unberührt.
 * Upgrade erst beim ersten Öffnen (lazy), kein DOM-Umhängen in einen Host.
 */
(function (global) {
  'use strict';

  const SKIP = new Set(['sfUiDialogBackdrop']);

  const SIZE_HINTS = {
    shareModal: { width: 560, height: 520 },
    exportModal: { width: 560, height: 560 },
    pixabayModal: { width: 920, height: 680 },
    iconifyModal: { width: 920, height: 680 },
    openclipartModal: { width: 920, height: 680 },
    webdavModal: { width: 920, height: 680 },
    templateModal: { width: 560, height: 480 },
    elementLinksModal: { width: 560, height: 480 },
    slideBgGradientModal: { width: 440, height: 420 },
    slideBgMediaModal: { width: 560, height: 520 },
    createModal: { width: 480, height: 420 },
    presentRemoteQrModal: { width: 420, height: 480 },
    presentTimebarSettingsModal: { width: 520, height: 480 },
    presentLaserSettingsModal: { width: 480, height: 520 },
    presentClockSettingsModal: { width: 480, height: 420 },
    presentNotesSettingsModal: { width: 440, height: 380 }
  };

  function upgradeOne(backdrop) {
    if (!backdrop || !global.HubFloatDialog) return null;
    if (SKIP.has(backdrop.id)) return null;
    if (backdrop.classList.contains('sf-ui-dialog-backdrop')) return null;
    if (backdrop.dataset.hubFloatUpgraded === '1') {
      return { open: backdrop._hubFloatOpen, close: backdrop._hubFloatClose };
    }

    const modal = backdrop.querySelector('.modal, [role="dialog"]');
    if (!modal) return null;

    const id = backdrop.id || modal.id || ('sf_modal_' + Math.random().toString(36).slice(2, 8));
    const hint = SIZE_HINTS[id] || SIZE_HINTS[modal.id] || { width: 560, height: 480 };

    const pack = global.HubFloatDialog.upgradeBackdrop(backdrop, {
      id: 'sf:' + id,
      width: hint.width,
      height: hint.height,
      minWidth: 320,
      minHeight: 200,
      fitContent: true,
      persist: false,
      geomRev: 1
    });
    if (!pack) return null;

    backdrop.dataset.hubFloatUpgraded = '1';
    backdrop._hubFloatModal = pack.modal;

    pack.modal.querySelectorAll(
      '.sf-dialog-close, [id$="ModalClose"], [id$="ModalDone"], [id$="ModalOk"]'
    ).forEach((btn) => {
      if (btn.dataset.hubFloatCloseWired === '1') return;
      btn.dataset.hubFloatCloseWired = '1';
      btn.addEventListener('click', () => {
        backdrop.classList.remove('open');
        pack.close();
      });
    });

    return pack;
  }

  function watchBackdrop(backdrop) {
    if (SKIP.has(backdrop.id) || backdrop.classList.contains('sf-ui-dialog-backdrop')) return;

    let wasOpen = backdrop.classList.contains('open');
    if (wasOpen) {
      const pack = upgradeOne(backdrop);
      if (pack && typeof pack.open === 'function') pack.open();
    }

    const obs = new MutationObserver(() => {
      const isOpen = backdrop.classList.contains('open');
      if (isOpen && !wasOpen) {
        const pack = upgradeOne(backdrop);
        if (pack && typeof pack.open === 'function') pack.open();
        else if (typeof backdrop._hubFloatOpen === 'function') backdrop._hubFloatOpen();
      } else if (!isOpen && wasOpen) {
        const modal = backdrop._hubFloatModal;
        if (modal && global.HubFloatDialog) global.HubFloatDialog.close(modal);
      }
      wasOpen = isOpen;
    });
    obs.observe(backdrop, { attributes: true, attributeFilter: ['class'] });
  }

  function init() {
    if (!global.HubFloatDialog) return;
    document.querySelectorAll('.modal-backdrop').forEach(watchBackdrop);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  global.SFFloatBridge = { init, upgradeOne };
})(window);
