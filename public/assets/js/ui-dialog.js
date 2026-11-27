/**
 * SFDialog — dünne Alias auf HubConfirm (V2.0a).
 * Call-Sites bleiben SFDialog.alert / confirm / prompt; Overlay ist Hub.
 * Quelle: hub/public/assets/js/hub-confirm.js — nicht forken.
 */
(function (global) {
  'use strict';

  const I18N = global.SF_DIALOG_I18N || { ok: 'OK', cancel: 'Abbrechen' };

  function hub() {
    return global.HubConfirm;
  }

  function alert(message) {
    const H = hub();
    if (!H || typeof H.choose !== 'function') return Promise.resolve();
    return H.choose({
      title: '',
      message: message || '',
      cancel: false,
      buttons: [{ id: 'ok', label: I18N.ok, primary: true }]
    }).then(() => undefined);
  }

  function confirm(message, options) {
    const opts = options && typeof options === 'object' ? options : {};
    const H = hub();
    if (!H || typeof H.ask !== 'function') return Promise.resolve(false);
    return H.ask({
      title: '',
      message: message || '',
      okLabel: I18N.ok,
      cancelLabel: I18N.cancel,
      danger: !!opts.danger
    }).then((v) => !!v);
  }

  function prompt(label, defaultValue, options) {
    const H = hub();
    if (!H || typeof H.prompt !== 'function') return Promise.resolve(null);
    if (label && typeof label === 'object') {
      const o = label;
      return H.prompt({
        title: o.title || o.label || '',
        message: o.message || '',
        value: o.value ?? o.defaultValue ?? '',
        placeholder: o.placeholder || o.label || '',
        okLabel: I18N.ok,
        cancelLabel: I18N.cancel
      });
    }
    let opts = options && typeof options === 'object' ? options : {};
    let value = defaultValue;
    if (typeof defaultValue === 'object' && defaultValue !== null) {
      opts = defaultValue;
      value = opts.defaultValue ?? opts.value ?? '';
    }
    return H.prompt({
      title: label || '',
      message: opts.message || '',
      value: value ?? '',
      placeholder: opts.placeholder || label || '',
      okLabel: I18N.ok,
      cancelLabel: I18N.cancel
    });
  }

  function bindConfirmForms() {
    document.addEventListener(
      'submit',
      (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const msg = form.dataset.sfConfirm;
        if (!msg) return;
        if (form.dataset.sfConfirmBypass === '1') {
          delete form.dataset.sfConfirmBypass;
          return;
        }
        e.preventDefault();
        const danger = form.dataset.sfConfirmDanger !== undefined;
        confirm(msg, { danger }).then((ok) => {
          if (!ok) return;
          form.dataset.sfConfirmBypass = '1';
          form.requestSubmit(e.submitter || undefined);
        });
      },
      true
    );

    document.addEventListener(
      'click',
      (e) => {
        const btn = e.target.closest('button[data-sf-confirm], input[type="submit"][data-sf-confirm]');
        if (!btn) return;
        const form = btn.form;
        if (form && form.dataset.sfConfirm) return;

        const msg = btn.dataset.sfConfirm;
        if (!msg) return;
        if (btn.dataset.sfConfirmBypass === '1') {
          delete btn.dataset.sfConfirmBypass;
          return;
        }

        e.preventDefault();
        const danger = btn.dataset.sfConfirmDanger !== undefined;
        confirm(msg, { danger }).then((ok) => {
          if (!ok) return;
          btn.dataset.sfConfirmBypass = '1';
          if (form) {
            form.requestSubmit(btn);
          } else {
            btn.click();
          }
        });
      },
      true
    );
  }

  bindConfirmForms();

  global.SFDialog = { alert, confirm, prompt };
})(window);
