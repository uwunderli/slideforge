/**
 * Hub Split — Spaltenbreite per Drag (Lab Layout / Module-Vorlage).
 * Nutzung: HubSplit.bind(rootEl) auf .hub-split mit data-hub-split + Resize-Handles.
 * Einklappen (2.34.2, Vorbild PD-Personenansicht): Griff `.hub-split__grip` mit
 * data-hub-split-toggle="side|detail" am Rand der Spalte; Klasse is-side-off / is-detail-off
 * am Root, Stand je Person unter «split:<Storage-Key>:off» (true/false). Ohne Griff kein Einklappen.
 */
(function () {
  'use strict';

  function readPx(root, cssVar, minW, fallback) {
    const cur = getComputedStyle(root).getPropertyValue(cssVar).trim();
    if (cur.endsWith('px')) {
      const px = parseInt(cur, 10);
      if (px >= minW) return px;
    }
    return fallback;
  }

  function bindHandle(root, opts) {
    const handle = root.querySelector(opts.handle);
    if (!handle || handle.dataset.hubSplitBound) return;
    handle.dataset.hubSplitBound = '1';
    const storageKey = opts.storageKey || '';
    const cssVar = opts.cssVar;
    const minW = opts.minW || 160;
    const maxFrac = opts.maxFrac || 0.45;
    const invert = !!opts.invert;
    const resizingClass = opts.resizingClass || 'is-resizing';
    const defaultW = opts.defaultW || 224;
    const maxW = () => Math.max(minW + 40, Math.floor(window.innerWidth * maxFrac));

    function applyWidth(px) {
      const w = Math.max(minW, Math.min(maxW(), Math.round(px)));
      root.style.setProperty(cssVar, w + 'px');
      return w;
    }

    if (storageKey) {
      const prefs = window.HubUserPrefsUi;
      let applied = false;
      if (prefs) {
        try { prefs.migrateLocal(storageKey, 'split:' + storageKey); } catch (_) {}
        const v = prefs.get('split:' + storageKey, null);
        const saved = parseInt(String(v == null ? '' : v), 10);
        if (saved >= minW) {
          applyWidth(saved);
          applied = true;
        }
      }
      if (!applied) {
        try {
          const saved = parseInt(localStorage.getItem(storageKey) || '', 10);
          if (saved >= minW) applyWidth(saved);
        } catch (_) {}
      }
    }

    let startX = 0;
    let startW = 0;
    let active = false;

    function onMove(e) {
      if (!active) return;
      e.preventDefault();
      const delta = invert ? (startX - e.clientX) : (e.clientX - startX);
      applyWidth(startW + delta);
    }
    function onUp() {
      if (!active) return;
      active = false;
      root.classList.remove(resizingClass);
      document.body.classList.remove('hub-split-resizing');
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      document.removeEventListener('pointercancel', onUp);
      if (storageKey) {
        const w = readPx(root, cssVar, minW, defaultW);
        const prefs = window.HubUserPrefsUi;
        if (prefs) {
          try { prefs.set('split:' + storageKey, w); } catch (_) {}
          try { localStorage.removeItem(storageKey); } catch (_) {}
        } else {
          try {
            localStorage.setItem(storageKey, String(w));
          } catch (_) {}
        }
      }
    }

    handle.addEventListener('pointerdown', (e) => {
      if (e.button !== 0) return;
      e.preventDefault();
      active = true;
      startX = e.clientX;
      startW = readPx(root, cssVar, minW, defaultW);
      root.classList.add(resizingClass);
      document.body.classList.add('hub-split-resizing');
      document.addEventListener('pointermove', onMove, { passive: false });
      document.addEventListener('pointerup', onUp);
      document.addEventListener('pointercancel', onUp);
    });

    handle.addEventListener('keydown', (e) => {
      const cur = readPx(root, cssVar, minW, defaultW);
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        e.preventDefault();
        const next = applyWidth(
          e.key === 'ArrowLeft'
            ? (invert ? cur + 24 : cur - 24)
            : (invert ? cur - 24 : cur + 24)
        );
        if (storageKey) {
          const prefs = window.HubUserPrefsUi;
          if (prefs) {
            try { prefs.set('split:' + storageKey, next); } catch (_) {}
            try { localStorage.removeItem(storageKey); } catch (_) {}
          } else {
            try { localStorage.setItem(storageKey, String(next)); } catch (_) {}
          }
        }
      }
    });
  }

  function savePref(key, value) {
    if (!key) return;
    const prefs = window.HubUserPrefsUi;
    if (prefs) {
      try { prefs.set(key, value); } catch (_) {}
    } else {
      try { localStorage.setItem(key, JSON.stringify(value)); } catch (_) {}
    }
  }

  function readOff(key, fallback) {
    if (!key) return fallback;
    let v = null;
    const prefs = window.HubUserPrefsUi;
    if (prefs) {
      try { v = prefs.get(key, null); } catch (_) { v = null; }
    } else {
      try {
        const raw = localStorage.getItem(key);
        v = raw == null ? null : JSON.parse(raw);
      } catch (_) { v = null; }
    }
    if (v == null) return fallback;
    return v === true || v === 1 || v === '1' || v === 'true';
  }

  /* Griff zum Ein-/Ausklappen einer Seitenspalte (Knopf, also Enter/Leertaste; Beschriftung wechselt). */
  function bindToggle(root, opts) {
    const btn = root.querySelector(opts.toggle);
    if (!btn || btn.dataset.hubSplitBound) return;
    btn.dataset.hubSplitBound = '1';
    const cls = opts.offClass;
    const key = opts.storageKey ? 'split:' + opts.storageKey + ':off' : '';
    const labelHide = btn.getAttribute('data-label-hide') || btn.getAttribute('aria-label') || '';
    const labelShow = btn.getAttribute('data-label-show') || labelHide;

    function apply(off) {
      root.classList.toggle(cls, off);
      btn.setAttribute('aria-expanded', off ? 'false' : 'true');
      const t = off ? labelShow : labelHide;
      if (t) {
        btn.title = t;
        btn.setAttribute('aria-label', t);
      }
    }

    apply(readOff(key, root.classList.contains(cls)));
    btn.hidden = false;
    btn.addEventListener('click', () => {
      const off = !root.classList.contains(cls);
      apply(off);
      savePref(key, off);
      try {
        root.dispatchEvent(new CustomEvent('hub-split-toggle', { detail: { pane: opts.pane, off } }));
      } catch (_) {}
    });
  }

  function bind(root, options) {
    if (!root) return;
    const opts = options || {};
    bindHandle(root, {
      handle: opts.sideHandle || '[data-hub-split-resize="side"]',
      cssVar: opts.sideVar || '--hub-split-side-w',
      storageKey: opts.sideStorage || root.getAttribute('data-split-side-key') || '',
      minW: opts.sideMin || 160,
      defaultW: opts.sideDefault || 224,
      invert: false,
      resizingClass: 'is-resizing-side',
      maxFrac: 0.4,
    });
    bindHandle(root, {
      handle: opts.detailHandle || '[data-hub-split-resize="detail"]',
      cssVar: opts.detailVar || '--hub-split-detail-w',
      storageKey: opts.detailStorage || root.getAttribute('data-split-detail-key') || '',
      minW: opts.detailMin || 280,
      defaultW: opts.detailDefault || 416,
      invert: true,
      resizingClass: 'is-resizing-detail',
      maxFrac: 0.5,
    });
    const sideKey = opts.sideStorage || root.getAttribute('data-split-side-key') || '';
    const detailKey = opts.detailStorage || root.getAttribute('data-split-detail-key') || '';
    bindToggle(root, {
      toggle: opts.sideToggle || '[data-hub-split-toggle="side"]',
      offClass: 'is-side-off',
      storageKey: sideKey,
      pane: 'side',
    });
    bindToggle(root, {
      toggle: opts.detailToggle || '[data-hub-split-toggle="detail"]',
      offClass: 'is-detail-off',
      storageKey: detailKey,
      pane: 'detail',
    });
  }

  function auto() {
    document.querySelectorAll('[data-hub-split]').forEach((el) => bind(el));
  }

  window.HubSplit = { bind, auto };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', auto);
  } else {
    auto();
  }
})();
