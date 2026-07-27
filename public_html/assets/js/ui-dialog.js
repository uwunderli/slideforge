(function (global) {
  'use strict';

  const I18N = global.SF_DIALOG_I18N || { ok: 'OK', cancel: 'Abbrechen' };

  let backdrop;
  let messageEl;
  let fieldWrap;
  let labelEl;
  let inputEl;
  let okBtn;
  let cancelBtn;
  let resolveFn = null;
  let keyHandler = null;
  let currentMode = 'alert';

  function ensureDom() {
    if (backdrop) return;

    backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop sf-ui-dialog-backdrop';
    backdrop.id = 'sfUiDialogBackdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.innerHTML =
      '<div class="modal sf-ui-dialog" role="dialog" aria-modal="true" aria-labelledby="sfUiDialogMessage">' +
      '<p class="sf-ui-dialog-message" id="sfUiDialogMessage"></p>' +
      '<div class="sf-ui-dialog-field present-config-section" id="sfUiDialogField" hidden style="padding-top:4px;">' +
      '<label class="sf-ui-dialog-label" id="sfUiDialogLabel" for="sfUiDialogInput"></label>' +
      '<input type="text" class="sf-ui-dialog-input" id="sfUiDialogInput" autocomplete="off">' +
      '</div>' +
      '<div class="sf-dialog-actions present-config-panel-footer modal-actions sf-ui-dialog-actions">' +
      '<button type="button" class="button button-ghost button-sm" id="sfUiDialogCancel"></button>' +
      '<button type="button" class="button button-sm" id="sfUiDialogOk"></button>' +
      '</div>' +
      '</div>';
    document.body.appendChild(backdrop);

    messageEl = backdrop.querySelector('#sfUiDialogMessage');
    fieldWrap = backdrop.querySelector('#sfUiDialogField');
    labelEl = backdrop.querySelector('#sfUiDialogLabel');
    inputEl = backdrop.querySelector('#sfUiDialogInput');
    okBtn = backdrop.querySelector('#sfUiDialogOk');
    cancelBtn = backdrop.querySelector('#sfUiDialogCancel');

    okBtn.textContent = I18N.ok;
    cancelBtn.textContent = I18N.cancel;

    okBtn.addEventListener('click', () => finish(currentMode === 'prompt' ? inputEl.value : true));
    cancelBtn.addEventListener('click', () => finish(null));

    if (global.SFModalBackdrop) {
      global.SFModalBackdrop.bindDismiss(backdrop, () => finish(null));
    }

    inputEl.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        finish(inputEl.value);
      }
    });
  }

  function finish(value) {
    if (!resolveFn) return;

    backdrop.classList.remove('open');
    backdrop.setAttribute('aria-hidden', 'true');
    document.removeEventListener('keydown', keyHandler);
    keyHandler = null;

    const r = resolveFn;
    resolveFn = null;
    const mode = currentMode;

    if (mode === 'confirm') {
      r(!!value);
    } else if (mode === 'prompt') {
      r(value === null ? null : String(value));
    } else {
      r();
    }
  }

  function open(opts) {
    ensureDom();
    currentMode = opts.mode;

    const isAlert = opts.mode === 'alert';
    const isPrompt = opts.mode === 'prompt';

    okBtn.classList.toggle('button-danger', !!opts.danger && !isAlert);

    if (isPrompt) {
      const body = opts.message || '';
      messageEl.hidden = !body;
      messageEl.textContent = body;
      fieldWrap.hidden = false;
      labelEl.textContent = opts.label || '';
      inputEl.value = opts.defaultValue ?? '';
    } else {
      messageEl.hidden = false;
      messageEl.textContent = opts.message || '';
      fieldWrap.hidden = true;
    }

    cancelBtn.hidden = isAlert;

    backdrop.classList.add('open');
    backdrop.setAttribute('aria-hidden', 'false');

    keyHandler = (e) => {
      if (e.key === 'Escape') finish(null);
    };
    document.addEventListener('keydown', keyHandler);

    return new Promise((resolve) => {
      resolveFn = resolve;
      requestAnimationFrame(() => {
        if (isPrompt) {
          inputEl.focus();
          inputEl.select();
        } else {
          okBtn.focus();
        }
      });
    });
  }

  function alert(message) {
    return open({ mode: 'alert', message: message || '' });
  }

  function confirm(message, options) {
    const opts = options && typeof options === 'object' ? options : {};
    return open({ mode: 'confirm', message: message || '', danger: !!opts.danger }).then((v) => !!v);
  }

  function prompt(label, defaultValue, options) {
    let opts = options && typeof options === 'object' ? options : {};
    let value = defaultValue;
    if (typeof defaultValue === 'object' && defaultValue !== null) {
      opts = defaultValue;
      value = '';
    }
    return open({
      mode: 'prompt',
      label: label || '',
      message: opts.message || '',
      defaultValue: value ?? '',
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
