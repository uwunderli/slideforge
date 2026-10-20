/**
 * SlideForge ↔ Hub primitives (V2.0a).
 * data-hub-page-busy on forms; data-hub-secure-delete for irreversible deletes.
 */
(function (global) {
  'use strict';

  function busyShow(title, detail) {
    if (!global.HubPageBusy || typeof HubPageBusy.show !== 'function') return;
    HubPageBusy.show({ title: title || 'Bitte warten…', detail: detail || '', soft: true });
  }

  function busyHide() {
    if (global.HubPageBusy && typeof HubPageBusy.hide === 'function') HubPageBusy.hide();
  }

  global.SFBusy = { show: busyShow, hide: busyHide };

  document.addEventListener(
    'submit',
    function (e) {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (!form.hasAttribute('data-hub-page-busy')) return;
      const title = form.getAttribute('data-hub-page-busy') || 'Bitte warten…';
      busyShow(title);
    },
    true
  );

  document.addEventListener(
    'submit',
    function (e) {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (!form.hasAttribute('data-hub-secure-delete')) return;
      if (form.dataset.hubSecureDeleteBypass === '1') {
        delete form.dataset.hubSecureDeleteBypass;
        return;
      }
      e.preventDefault();
      const ask = global.HubSecureDelete && typeof HubSecureDelete.ask === 'function'
        ? HubSecureDelete.ask.bind(HubSecureDelete)
        : null;
      if (!ask) {
        if (global.CfStatus) CfStatus.show('error', 'Sichere Löschmethode nicht geladen');
        return;
      }
      ask({
        title: form.getAttribute('data-hub-secure-delete-title') || 'Löschen?',
        message: form.getAttribute('data-hub-secure-delete-message') || '',
        okLabel: form.getAttribute('data-hub-secure-delete-ok') || 'Endgültig löschen',
        cancelLabel: form.getAttribute('data-hub-secure-delete-cancel') || 'Abbrechen',
        inputLabel: form.getAttribute('data-hub-secure-delete-input') || 'Bestätigungswort',
      }).then(function (ok) {
        if (!ok) return;
        form.dataset.hubSecureDeleteBypass = '1';
        busyShow(form.getAttribute('data-hub-secure-delete-busy') || 'Wird gelöscht…');
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      });
    },
    true
  );
})(window);
