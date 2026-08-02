/**
 * HubFloatDialog — Fenster-Chrome für schwebende Dialoge (DESIGN § Dialogfenster).
 * Quelle für Module: diese Datei vendorn, nicht forken.
 *
 * HubFloatDialog.bind(el, {
 *   id, width, height, minWidth, minHeight, fitContent, persist, geomRev, onClose, i18n
 * })
 * HubFloatDialog.open(el) / .close(el) / .decorate(el, opts)
 */
(function (global) {
  'use strict';

  const DEFAULT_I18N = {
    minimize: 'Minimieren',
    maximize: 'Maximieren',
    restore: 'Wiederherstellen',
    close: 'Schliessen'
  };

  const EDGE = ['n', 'e', 's', 'w', 'ne', 'nw', 'se', 'sw'];
  const instances = new WeakMap();

  function i18nOf(opts) {
    return Object.assign({}, DEFAULT_I18N, (global.HUB_FLOAT_DIALOG_I18N || {}), (opts && opts.i18n) || {});
  }

  function maxBox() {
    return {
      w: Math.floor(window.innerWidth * 0.96),
      h: Math.floor(window.innerHeight * 0.90)
    };
  }

  function geomKey(id) {
    return 'hub_float_geom:' + id;
  }

  function readGeom(id) {
    try {
      return JSON.parse(localStorage.getItem(geomKey(id)) || 'null') || {};
    } catch (_) {
      return {};
    }
  }

  function writeGeom(state) {
    const dlg = state.el;
    if (!state.id || dlg.classList.contains('is-maximized') || dlg.classList.contains('is-minimized')) {
      return;
    }
    try {
      localStorage.setItem(geomKey(state.id), JSON.stringify({
        left: dlg.style.left,
        top: dlg.style.top,
        width: dlg.style.width,
        height: dlg.style.height,
        fitContent: state.fitContent,
        rev: state.geomRev || 0
      }));
    } catch (_) { /* ignore */ }
  }

  function ensureHandles(dlg) {
    EDGE.forEach((edge) => {
      if (dlg.querySelector('[data-hub-resize="' + edge + '"]')) return;
      const h = document.createElement('div');
      h.className = 'hub-float-dialog__handle hub-float-dialog__handle--' + edge;
      h.setAttribute('data-hub-resize', edge);
      h.setAttribute('aria-hidden', 'true');
      dlg.appendChild(h);
    });
  }

  function ensureChrome(dlg, opts) {
    const I = i18nOf(opts);
    dlg.classList.add('hub-float-dialog');
    if (!dlg.getAttribute('role')) dlg.setAttribute('role', 'dialog');

    let title = dlg.querySelector('.hub-float-dialog__title');
    if (!title) {
      const legacyHeader = dlg.querySelector('.sf-dialog-header, .ribbon-customize-header, header.modal-header');
      if (legacyHeader) {
        legacyHeader.classList.add('hub-float-dialog__title');
        title = legacyHeader;
      } else {
        title = document.createElement('div');
        title.className = 'hub-float-dialog__title';
        const heading = dlg.querySelector('h2, h3, .sf-dialog-title, .modal-dialog-title');
        if (heading) {
          heading.classList.add('hub-float-dialog__title-text');
          title.appendChild(heading);
        } else {
          const strong = document.createElement('strong');
          strong.className = 'hub-float-dialog__title-text';
          strong.textContent = opts.title || 'Dialog';
          title.appendChild(strong);
        }
        dlg.insertBefore(title, dlg.firstChild);
      }
    }
    title.setAttribute('data-hub-drag', '');

    let titleText = title.querySelector('.hub-float-dialog__title-text, .sf-dialog-title, h2, h3');
    if (titleText) titleText.classList.add('hub-float-dialog__title-text');

    let winBtns = title.querySelector('.hub-float-dialog__win-btns');
    if (!winBtns) {
      winBtns = document.createElement('div');
      winBtns.className = 'hub-float-dialog__win-btns';
      title.appendChild(winBtns);
    }

    const legacyClose = title.querySelector('.sf-dialog-close, .ribbon-customize-close');
    if (legacyClose) legacyClose.hidden = true;

    function ensureBtn(cls, text, action) {
      let btn = winBtns.querySelector('[data-hub-win="' + action + '"]');
      if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'hub-btn hub-btn--ghost';
        btn.setAttribute('data-hub-win', action);
        winBtns.appendChild(btn);
      }
      btn.textContent = text;
      return btn;
    }

    const minBtn = ensureBtn('min', '▁', 'min');
    const maxBtn = ensureBtn('max', '▢', 'max');
    const closeBtn = ensureBtn('close', '×', 'close');
    minBtn.title = I.minimize;
    minBtn.setAttribute('aria-label', I.minimize);
    maxBtn.title = I.maximize;
    maxBtn.setAttribute('aria-label', I.maximize);
    closeBtn.title = I.close;
    closeBtn.setAttribute('aria-label', I.close);

    let body = dlg.querySelector('.hub-float-dialog__body');
    if (!body) {
      body = document.createElement('div');
      body.className = 'hub-float-dialog__body hub-scroll';
      const kids = [...dlg.children].filter((c) =>
        c !== title &&
        !c.classList.contains('hub-float-dialog__footer') &&
        !c.classList.contains('hub-float-dialog__handle') &&
        !c.classList.contains('sf-dialog-actions') &&
        !c.classList.contains('present-config-panel-footer') &&
        !c.classList.contains('modal-actions') &&
        !c.classList.contains('ribbon-customize-actions')
      );
      kids.forEach((c) => body.appendChild(c));
      title.after(body);
    } else {
      body.classList.add('hub-scroll');
    }

    let footer = dlg.querySelector('.hub-float-dialog__footer');
    if (!footer) {
      const legacyFooter = dlg.querySelector(
        '.sf-dialog-actions, .present-config-panel-footer, .modal-actions, .ribbon-customize-actions'
      );
      if (legacyFooter) {
        legacyFooter.classList.add('hub-float-dialog__footer', 'hub-dialog-actions');
        footer = legacyFooter;
      }
    } else {
      footer.classList.add('hub-dialog-actions');
    }

    ensureHandles(dlg);
    return { title, body, footer, minBtn, maxBtn, closeBtn, I };
  }

  function syncWinButtons(state) {
    const { el, minBtn, maxBtn, I } = state;
    const minimized = el.classList.contains('is-minimized');
    const maximized = el.classList.contains('is-maximized');
    if (minBtn) {
      minBtn.title = minimized ? I.restore : I.minimize;
      minBtn.setAttribute('aria-label', minBtn.title);
    }
    if (maxBtn) {
      maxBtn.title = maximized ? I.restore : I.maximize;
      maxBtn.setAttribute('aria-label', maxBtn.title);
      maxBtn.textContent = maximized ? '❐' : '▢';
    }
  }

  function applyNormalGeom(state, geom) {
    const dlg = state.el;
    if (!geom) return;
    if (geom.left) { dlg.style.left = geom.left; dlg.style.right = 'auto'; }
    if (geom.top) dlg.style.top = geom.top;
    if (geom.width) dlg.style.width = geom.width;
    if (geom.height) dlg.style.height = geom.height;
  }

  function fitToContent(state) {
    const dlg = state.el;
    if (!state.fitContent || dlg.hidden || dlg.hasAttribute('hidden')) return;
    if (dlg.classList.contains('is-maximized') || dlg.classList.contains('is-minimized')) return;
    const title = dlg.querySelector('.hub-float-dialog__title');
    const body = dlg.querySelector('.hub-float-dialog__body');
    const footer = dlg.querySelector('.hub-float-dialog__footer');
    const box = maxBox();
    const th = title ? title.offsetHeight : 0;
    const fh = footer ? footer.offsetHeight : 0;
    const bh = body ? body.scrollHeight : 0;
    let h = th + fh + bh + 2;
    let w = Math.max(state.minWidth, dlg.offsetWidth || state.defaultWidth);
    h = Math.min(box.h, Math.max(state.minHeight, h));
    w = Math.min(box.w, Math.max(state.minWidth, w));
    dlg.style.height = h + 'px';
    dlg.style.width = w + 'px';
  }

  function clearChrome(state) {
    state.el.classList.remove('is-minimized', 'is-maximized');
    syncWinButtons(state);
  }

  function restoreStandard(state) {
    clearChrome(state);
    applyNormalGeom(state, readGeom(state.id));
    if (state.fitContent) fitToContent(state);
  }

  function wire(state) {
    if (state.wired) return;
    state.wired = true;
    const dlg = state.el;

    state.minBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      if (dlg.classList.contains('is-minimized')) {
        restoreStandard(state);
      } else {
        if (!dlg.classList.contains('is-maximized')) writeGeom(state);
        dlg.classList.remove('is-maximized');
        dlg.classList.add('is-minimized');
        syncWinButtons(state);
      }
    });

    state.maxBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      if (dlg.classList.contains('is-maximized')) {
        restoreStandard(state);
      } else {
        if (!dlg.classList.contains('is-minimized')) writeGeom(state);
        dlg.classList.remove('is-minimized');
        dlg.classList.add('is-maximized');
        syncWinButtons(state);
      }
    });

    state.closeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      HubFloatDialog.close(dlg);
    });

    let drag = null;
    const titlebar = dlg.querySelector('[data-hub-drag]');
    titlebar?.addEventListener('mousedown', (e) => {
      if (e.button !== 0) return;
      if (e.target.closest('button')) return;
      if (dlg.classList.contains('is-maximized')) return;
      drag = {
        mode: 'drag',
        sx: e.clientX, sy: e.clientY,
        sl: dlg.offsetLeft, st: dlg.offsetTop
      };
      e.preventDefault();
    });

    dlg.querySelectorAll('[data-hub-resize]').forEach((el) => {
      el.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        if (dlg.classList.contains('is-maximized') || dlg.classList.contains('is-minimized')) return;
        drag = {
          mode: 'resize',
          edges: el.getAttribute('data-hub-resize') || 'se',
          sx: e.clientX, sy: e.clientY,
          sl: dlg.offsetLeft, st: dlg.offsetTop,
          sw: dlg.offsetWidth, sh: dlg.offsetHeight
        };
        e.preventDefault();
        e.stopPropagation();
      });
    });

    const onMove = (e) => {
      if (!drag) return;
      if (drag.mode === 'drag') {
        dlg.style.left = Math.max(0, drag.sl + e.clientX - drag.sx) + 'px';
        dlg.style.top = Math.max(0, drag.st + e.clientY - drag.sy) + 'px';
        dlg.style.right = 'auto';
        return;
      }
      const edges = drag.edges || 'se';
      let left = drag.sl, top = drag.st, w = drag.sw, h = drag.sh;
      const dx = e.clientX - drag.sx;
      const dy = e.clientY - drag.sy;
      if (edges.includes('e')) w = Math.max(state.minWidth, drag.sw + dx);
      if (edges.includes('s')) h = Math.max(state.minHeight, drag.sh + dy);
      if (edges.includes('w')) {
        w = Math.max(state.minWidth, drag.sw - dx);
        left = drag.sl + (drag.sw - w);
      }
      if (edges.includes('n')) {
        h = Math.max(state.minHeight, drag.sh - dy);
        top = drag.st + (drag.sh - h);
      }
      dlg.style.left = left + 'px';
      dlg.style.top = top + 'px';
      dlg.style.width = w + 'px';
      dlg.style.height = h + 'px';
      dlg.style.right = 'auto';
    };

    const onUp = () => {
      if (!drag) return;
      if (drag.mode === 'resize') state.fitContent = false;
      drag = null;
      writeGeom(state);
    };

    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);

    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      if (dlg.hidden || dlg.hasAttribute('hidden')) return;
      if (!dlg.classList.contains('hub-float-dialog--open') && dlg.style.display === 'none') return;
      const open = !dlg.hidden && !dlg.hasAttribute('hidden') && getComputedStyle(dlg).display !== 'none';
      if (!open) return;
      HubFloatDialog.close(dlg);
    });
  }

  function applyDefaults(state) {
    const dlg = state.el;
    const box = maxBox();
    const geom = readGeom(state.id);
    const revOk = !state.geomRev || Number(geom.rev || 0) >= Number(state.geomRev);

    if (revOk && geom.width) {
      if (geom.fitContent === false) state.fitContent = false;
      applyNormalGeom(state, geom);
    } else {
      dlg.style.width = Math.min(box.w, state.defaultWidth) + 'px';
      dlg.style.height = Math.min(box.h, state.defaultHeight) + 'px';
      if (!dlg.style.left) {
        dlg.style.left = Math.max(8, Math.floor((window.innerWidth - Math.min(box.w, state.defaultWidth)) / 2)) + 'px';
        dlg.style.top = Math.max(8, Math.floor(window.innerHeight * 0.08)) + 'px';
        dlg.style.right = 'auto';
      }
    }
  }

  const HubFloatDialog = {
    decorate(el, opts) {
      return this.bind(el, opts);
    },

    bind(el, opts) {
      opts = opts || {};
      if (!el) return null;
      let state = instances.get(el);
      if (state) return state;

      const chrome = ensureChrome(el, opts);
      state = {
        el,
        id: String(opts.id || el.id || ('dlg_' + Date.now())),
        defaultWidth: Number(opts.width) || 920,
        defaultHeight: Number(opts.height) || 680,
        minWidth: Number(opts.minWidth) || 320,
        minHeight: Number(opts.minHeight) || 180,
        fitContent: opts.fitContent !== false,
        persist: opts.persist !== false,
        geomRev: Number(opts.geomRev) || 0,
        onClose: typeof opts.onClose === 'function' ? opts.onClose : null,
        minBtn: chrome.minBtn,
        maxBtn: chrome.maxBtn,
        closeBtn: chrome.closeBtn,
        I: chrome.I,
        wired: false
      };
      el.setAttribute('data-hub-float-id', state.id);
      applyDefaults(state);
      wire(state);
      syncWinButtons(state);
      instances.set(el, state);
      return state;
    },

    open(el) {
      const state = instances.get(el) || this.bind(el, {});
      if (!state) return;
      el.hidden = false;
      el.removeAttribute('hidden');
      el.classList.add('hub-float-dialog--open');
      clearChrome(state);
      if (state.fitContent) fitToContent(state);
      return state;
    },

    close(el) {
      const state = instances.get(el);
      if (!state) {
        if (el) {
          el.hidden = true;
          el.setAttribute('hidden', '');
        }
        return;
      }
      if (state._closing) return;
      const already = el.hidden || !el.classList.contains('hub-float-dialog--open');
      state._closing = true;
      if (!already) writeGeom(state);
      clearChrome(state);
      el.hidden = true;
      el.setAttribute('hidden', '');
      el.classList.remove('hub-float-dialog--open');
      try {
        if (state.onClose) state.onClose();
      } finally {
        state._closing = false;
      }
    },

    /**
     * Backdrop-Modal → schwebendes hub-float-dialog.
     * Modal bleibt im Backdrop (kein DOM-Umhängen); Backdrop wird bei Open transparent.
     */
    upgradeBackdrop(backdrop, opts) {
      if (!backdrop) return null;
      const modal = backdrop.querySelector('.modal, .ribbon-customize-panel, [role="dialog"]') || backdrop.firstElementChild;
      if (!modal) return null;

      opts = opts || {};
      const id = opts.id || modal.id || backdrop.id || ('float_' + Date.now());

      const state = this.bind(modal, Object.assign({ id }, opts));
      modal._hubFloatBackdrop = backdrop;

      const origOnClose = state.onClose;
      state.onClose = () => {
        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
        if (origOnClose) origOnClose();
        if (typeof opts.onClose === 'function') opts.onClose();
      };

      const openFloat = () => {
        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
        backdrop.style.background = 'transparent';
        backdrop.style.pointerEvents = 'none';
        // Modal selbst empfängt Pointer-Events
        modal.style.pointerEvents = 'auto';
        this.open(modal);
      };

      const closeFloat = () => this.close(modal);

      backdrop._hubFloatOpen = openFloat;
      backdrop._hubFloatClose = closeFloat;
      modal._hubFloatOpen = openFloat;
      modal._hubFloatClose = closeFloat;

      return { backdrop, modal, open: openFloat, close: closeFloat, state };
    }
  };

  global.HubFloatDialog = HubFloatDialog;
})(typeof window !== 'undefined' ? window : globalThis);
