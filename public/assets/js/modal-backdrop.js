(function (global) {
  'use strict';

  /**
   * Schliesst nur, wenn Mousedown und Mouseup auf dem Backdrop passieren
   * (nicht bei Textmarkierung, die aus dem Dialog heraus endet).
   */
  function bindDismiss(backdrop, onDismiss) {
    if (!backdrop || typeof onDismiss !== 'function') return;
    backdrop.addEventListener('mousedown', (e) => {
      if (e.button !== 0 || e.target !== backdrop) return;
      const onUp = (ev) => {
        document.removeEventListener('mouseup', onUp);
        if (ev.target === backdrop) onDismiss(ev);
      };
      document.addEventListener('mouseup', onUp);
    });
  }

  global.SFModalBackdrop = { bindDismiss };
})(window);
