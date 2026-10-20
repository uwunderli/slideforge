/**
 * HubConfirm — Bestätigungs-Overlay (kein window.confirm).
 * Quelle: hub/public/assets/js/hub-confirm.js — Module vendorn / hub_asset(), nicht forken.
 *
 * API:
 *   HubConfirm.ask({ title?, message?, okLabel?, cancelLabel?, danger?,
 *                    rememberKey?, rememberLabel?, isRemembered?, setRemembered? }) → Promise<boolean>
 *   HubConfirm.choose({ title?, message?, buttons, cancelLabel?,
 *                       rememberKey?, rememberLabel?, isRemembered?, setRemembered? }) → Promise<string|null>
 *   HubConfirm.prompt({ title?, message?, value?, placeholder?, okLabel?, cancelLabel? }) → Promise<string|null>
 *   Declarative: form[data-hub-confirm] / data-hub-confirm-message
 *
 * Klassen: .hub-confirm-overlay / __dialog / __body / __actions / __extra / __remember
 * Demo: Lab → Dialogfenster.
 */
(function (global) {
  'use strict';

  var active = null;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function ensureHost() {
    var el = document.getElementById('hubConfirmOverlay');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'hubConfirmOverlay';
    el.className = 'hub-confirm-overlay';
    el.hidden = true;
    el.setAttribute('aria-hidden', 'true');
    el.innerHTML =
      '<div class="hub-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="hubConfirmTitle">' +
      '  <h2 id="hubConfirmTitle" data-hub-confirm-title>Bestätigen?</h2>' +
      '  <p class="hub-confirm-body" data-hub-confirm-body></p>' +
      '  <div class="hub-confirm-extra" data-hub-confirm-extra hidden></div>' +
      '  <div class="hub-confirm-actions" data-hub-confirm-actions></div>' +
      '</div>';
    document.body.appendChild(el);
    return el;
  }

  function clearExtra(extra) {
    if (!extra) return;
    extra.hidden = true;
    extra.innerHTML = '';
  }

  function fillRemember(extra, opts) {
    if (!extra) return;
    if (!opts.rememberKey) {
      clearExtra(extra);
      return;
    }
    var label = opts.rememberLabel || 'Diese Frage nicht mehr stellen';
    extra.hidden = false;
    extra.innerHTML =
      '<label class="hub-admin-check hub-confirm-remember">' +
      '<input type="checkbox" data-hub-confirm-remember>' +
      '<span>' + esc(label) + '</span></label>';
  }

  /**
   * @param {{
   *   title?: string,
   *   message?: string,
   *   buttons?: Array<{id:string,label:string,primary?:boolean,danger?:boolean}>,
   *   cancelLabel?: string,
   *   rememberKey?: string,
   *   rememberLabel?: string,
   *   isRemembered?: function(string):boolean,
   *   setRemembered?: function(string,boolean):void
   * }} opts
   * @returns {Promise<string|null>}
   */
  function choose(opts) {
    opts = opts || {};
    var prefs = global.HUB_CONFIRM_PREFS || {};
    var isRemembered = opts.isRemembered || prefs.isRemembered;
    var setRemembered = opts.setRemembered || prefs.setRemembered;
    if (opts.rememberKey && typeof isRemembered === 'function' && isRemembered(opts.rememberKey)) {
      return Promise.resolve(opts.rememberDefault != null ? opts.rememberDefault : 'ok');
    }
    if (active) {
      try { active(null); } catch (_) {}
      active = null;
    }

    var overlay = ensureHost();
    var titleEl = overlay.querySelector('[data-hub-confirm-title]');
    var bodyEl = overlay.querySelector('[data-hub-confirm-body]');
    var actions = overlay.querySelector('[data-hub-confirm-actions]');
    var extra = overlay.querySelector('[data-hub-confirm-extra]');
    var dialog = overlay.querySelector('.hub-confirm-dialog');
    var showCancel = opts.cancel !== false;
    var cancelLabel = opts.cancelLabel || 'Abbrechen';
    var buttons = Array.isArray(opts.buttons) ? opts.buttons : [];

    if (titleEl) titleEl.textContent = opts.title || 'Bestätigen?';
    if (bodyEl) {
      bodyEl.textContent = opts.message || '';
      bodyEl.hidden = !opts.message;
    }
    fillRemember(extra, opts);
    if (dialog) {
      dialog.classList.toggle(
        'hub-confirm-dialog--danger',
        buttons.some(function (b) { return !!b.danger; })
      );
    }

    actions.innerHTML = '';
    var cancelBtn = null;
    if (showCancel) {
      cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'hub-btn hub-btn--ghost';
      cancelBtn.setAttribute('data-hub-confirm-cancel', '');
      cancelBtn.textContent = cancelLabel;
      actions.appendChild(cancelBtn);
    }

    buttons.forEach(function (b) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'hub-btn'
        + (b.danger ? ' hub-btn--danger' : (b.primary ? ' hub-btn--primary' : ' hub-btn--ghost'));
      btn.setAttribute('data-hub-confirm-choice', String(b.id || ''));
      btn.textContent = b.label || b.id;
      actions.appendChild(btn);
    });

    overlay.hidden = false;
    overlay.setAttribute('aria-hidden', 'false');

    return new Promise(function (resolve) {
      function finish(v) {
        if (v != null && opts.rememberKey && typeof setRemembered === 'function') {
          var chk = overlay.querySelector('[data-hub-confirm-remember]');
          if (chk && chk.checked) setRemembered(opts.rememberKey, true);
        }
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        clearExtra(extra);
        active = null;
        actions.removeEventListener('click', onClick);
        overlay.removeEventListener('click', onBackdrop);
        document.removeEventListener('keydown', onKey);
        resolve(v);
      }
      function onClick(e) {
        var choice = e.target.closest('[data-hub-confirm-choice]');
        if (choice && actions.contains(choice)) {
          finish(choice.getAttribute('data-hub-confirm-choice') || null);
          return;
        }
        if (e.target.closest('[data-hub-confirm-cancel]')) finish(null);
      }
      function onBackdrop(e) {
        if (e.target === overlay) finish(null);
      }
      function onKey(e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          finish(null);
        }
      }
      active = finish;
      actions.addEventListener('click', onClick);
      overlay.addEventListener('click', onBackdrop);
      document.addEventListener('keydown', onKey);
      setTimeout(function () {
        var focusBtn = actions.querySelector('[data-hub-confirm-choice]') || cancelBtn;
        try { focusBtn.focus(); } catch (_) {}
      }, 30);
    });
  }

  /**
   * @param {{title?:string,message?:string,okLabel?:string,cancelLabel?:string,danger?:boolean,
   *          rememberKey?:string,rememberLabel?:string,isRemembered?:Function,setRemembered?:Function}} opts
   * @returns {Promise<boolean>}
   */
  function ask(opts) {
    opts = opts || {};
    return choose(Object.assign({}, opts, {
      rememberDefault: 'ok',
      buttons: [{
        id: 'ok',
        label: opts.okLabel || 'OK',
        primary: !opts.danger,
        danger: !!opts.danger
      }]
    })).then(function (id) {
      return id === 'ok';
    });
  }

  function bindDeclarative() {
    document.addEventListener('click', function (ev) {
      var t = ev.target;
      if (!(t instanceof Element)) return;
      var btn = t.closest('[data-hub-confirm], [data-hub-confirm-message]');
      if (!(btn instanceof HTMLElement)) return;
      if (btn.tagName === 'FORM') return;
      if (btn.getAttribute('data-hub-confirm-skip') === '1') return;
      // Let form submit handler deal with submit buttons inside forms (needs FormData/submitter).
      if ((btn instanceof HTMLButtonElement || btn instanceof HTMLInputElement) && btn.type === 'submit' && btn.form) {
        return;
      }
      var message = btn.getAttribute('data-hub-confirm-message') || btn.getAttribute('data-hub-confirm') || '';
      if (!message) return;
      ev.preventDefault();
      ev.stopPropagation();
      ask({
        title: btn.getAttribute('data-hub-confirm-title') || 'Bitte bestätigen',
        message: message,
        okLabel: btn.getAttribute('data-hub-confirm-ok') || 'OK',
        cancelLabel: btn.getAttribute('data-hub-confirm-cancel') || 'Abbrechen',
        danger: btn.hasAttribute('data-hub-confirm-danger')
      }).then(function (ok) {
        if (!ok) return;
        btn.setAttribute('data-hub-confirm-skip', '1');
        try { btn.click(); } finally {
          setTimeout(function () { btn.removeAttribute('data-hub-confirm-skip'); }, 0);
        }
      });
    }, true);

    document.addEventListener('submit', function (ev) {
      var form = ev.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (form.getAttribute('data-hub-confirm-skip') === '1') {
        form.removeAttribute('data-hub-confirm-skip');
        return;
      }
      var submitter = ev.submitter instanceof HTMLElement ? ev.submitter : null;
      var source = null;
      if (submitter && (submitter.hasAttribute('data-hub-confirm') || submitter.hasAttribute('data-hub-confirm-message'))) {
        source = submitter;
      } else if (form.hasAttribute('data-hub-confirm') || form.hasAttribute('data-hub-confirm-message')) {
        source = form;
      }
      if (!source) return;
      var message = source.getAttribute('data-hub-confirm-message') || source.getAttribute('data-hub-confirm') || '';
      if (!message) return;
      ev.preventDefault();
      ask({
        title: source.getAttribute('data-hub-confirm-title') || 'Bitte bestätigen',
        message: message,
        okLabel: source.getAttribute('data-hub-confirm-ok') || 'OK',
        cancelLabel: source.getAttribute('data-hub-confirm-cancel') || 'Abbrechen',
        danger: source.hasAttribute('data-hub-confirm-danger')
      }).then(function (ok) {
        if (!ok) return;
        form.setAttribute('data-hub-confirm-skip', '1');
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit(submitter instanceof HTMLElement ? submitter : undefined);
        } else {
          form.submit();
        }
      });
    }, true);
  }


  /**
   * Text-Eingabe statt window.prompt.
   * @param {{title?:string,message?:string,value?:string,placeholder?:string,okLabel?:string,cancelLabel?:string}} opts
   * @returns {Promise<string|null>}
   */
  function prompt(opts) {
    opts = opts || {};
    if (active) {
      try { active(null); } catch (_) {}
      active = null;
    }
    var overlay = ensureHost();
    var titleEl = overlay.querySelector('[data-hub-confirm-title]');
    var bodyEl = overlay.querySelector('[data-hub-confirm-body]');
    var actions = overlay.querySelector('[data-hub-confirm-actions]');
    var extra = overlay.querySelector('[data-hub-confirm-extra]');
    var dialog = overlay.querySelector('.hub-confirm-dialog');
    var cancelLabel = opts.cancelLabel || 'Abbrechen';
    var okLabel = opts.okLabel || 'OK';

    if (titleEl) titleEl.textContent = opts.title || 'Eingabe';
    if (bodyEl) {
      bodyEl.textContent = opts.message || '';
      bodyEl.hidden = !opts.message;
    }
    if (dialog) dialog.classList.remove('hub-confirm-dialog--danger');
    if (extra) {
      extra.hidden = false;
      extra.innerHTML =
        '<label class="hub-admin-field hub-confirm-prompt">' +
        '<input type="text" data-hub-confirm-input autocomplete="off" ' +
        'value="' + esc(opts.value || '') + '" ' +
        'placeholder="' + esc(opts.placeholder || '') + '">' +
        '</label>';
    }

    actions.innerHTML = '';
    var cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'hub-btn hub-btn--ghost';
    cancelBtn.setAttribute('data-hub-confirm-cancel', '');
    cancelBtn.textContent = cancelLabel;
    actions.appendChild(cancelBtn);
    var okBtn = document.createElement('button');
    okBtn.type = 'button';
    okBtn.className = 'hub-btn hub-btn--primary';
    okBtn.setAttribute('data-hub-confirm-choice', 'ok');
    okBtn.textContent = okLabel;
    actions.appendChild(okBtn);

    overlay.hidden = false;
    overlay.setAttribute('aria-hidden', 'false');

    return new Promise(function (resolve) {
      function inputEl() {
        return overlay.querySelector('[data-hub-confirm-input]');
      }
      function finish(v) {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        clearExtra(extra);
        active = null;
        actions.removeEventListener('click', onClick);
        overlay.removeEventListener('click', onBackdrop);
        document.removeEventListener('keydown', onKey);
        resolve(v);
      }
      function accept() {
        var inp = inputEl();
        finish(inp ? String(inp.value || '') : '');
      }
      function onClick(e) {
        if (e.target.closest('[data-hub-confirm-choice]')) {
          accept();
          return;
        }
        if (e.target.closest('[data-hub-confirm-cancel]')) finish(null);
      }
      function onBackdrop(e) {
        if (e.target === overlay) finish(null);
      }
      function onKey(e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          finish(null);
        } else if (e.key === 'Enter' && e.target && e.target.matches && e.target.matches('[data-hub-confirm-input]')) {
          e.preventDefault();
          accept();
        }
      }
      active = finish;
      actions.addEventListener('click', onClick);
      overlay.addEventListener('click', onBackdrop);
      document.addEventListener('keydown', onKey);
      setTimeout(function () {
        var inp = inputEl();
        try {
          if (inp) {
            inp.focus();
            inp.select();
          } else {
            okBtn.focus();
          }
        } catch (_) {}
      }, 30);
    });
  }

  global.HubConfirm = { ask: ask, choose: choose, prompt: prompt };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindDeclarative);
  } else {
    bindDeclarative();
  }
})(typeof window !== 'undefined' ? window : globalThis);
