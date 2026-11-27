/**
 * Hub Split — Spaltenbreite per Drag (Lab Layout / Module-Vorlage).
 * Nutzung: HubSplit.bind(rootEl) auf .hub-split mit data-hub-split + Resize-Handles.
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
