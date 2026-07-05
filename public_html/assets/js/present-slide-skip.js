/**
 * Überspringt Folien mit presentDisabled beim Navigieren (next/prev/autoslide).
 */
(function (global) {
  'use strict';

  function installSkip(Reveal, disabled) {
    if (!Reveal || !Array.isArray(disabled) || !disabled.some(Boolean)) return;
    if (Reveal._sfSlideSkipInstalled) return;
    Reveal._sfSlideSkipInstalled = true;

    const count = disabled.length;

    function nextIdx(from, dir) {
      let i = from;
      let guard = 0;
      while (guard++ < count + 1) {
        i += dir;
        if (i < 0 || i >= count) return null;
        if (!disabled[i]) return i;
      }
      return null;
    }

    function redirectFromDisabled(preferredDir) {
      const h = (Reveal.getIndices() || { h: 0 }).h || 0;
      if (!disabled[h]) return;
      let n = nextIdx(h, preferredDir);
      if (n === null) {
        n = nextIdx(h, preferredDir === 1 ? -1 : 1);
      }
      if (n !== null && n !== h) {
        Reveal.slide(n, 0);
      }
    }

    const origNext = Reveal.next.bind(Reveal);
    const origPrev = Reveal.prev.bind(Reveal);

    Reveal.next = function () {
      const before = (Reveal.getIndices() || { h: 0 }).h || 0;
      origNext();
      const h = (Reveal.getIndices() || { h: 0 }).h || 0;
      if (h !== before && disabled[h]) {
        const n = nextIdx(before, 1);
        if (n !== null) Reveal.slide(n, 0);
      }
    };

    Reveal.prev = function () {
      const before = (Reveal.getIndices() || { h: 0 }).h || 0;
      origPrev();
      const h = (Reveal.getIndices() || { h: 0 }).h || 0;
      if (h !== before && disabled[h]) {
        const n = nextIdx(before, -1);
        if (n !== null) Reveal.slide(n, 0);
      }
    };

    Reveal.on('slidechanged', function (e) {
      const h = (Reveal.getIndices() || { h: 0 }).h || 0;
      if (!disabled[h]) {
        Reveal._sfLastSlideH = h;
        return;
      }
      const prevH = typeof e.previousIndexh === 'number' ? e.previousIndexh : Reveal._sfLastSlideH;
      const dir = typeof prevH === 'number' && h < prevH ? -1 : 1;
      redirectFromDisabled(dir);
      Reveal._sfLastSlideH = (Reveal.getIndices() || { h: 0 }).h || 0;
    });

    Reveal.on('ready', function () {
      redirectFromDisabled(1);
      Reveal._sfLastSlideH = (Reveal.getIndices() || { h: 0 }).h || 0;
    });
  }

  global.SlideForgePresentSlideSkip = {
    install: installSkip,

    /** Zielindex für Live-Sync (view.php): deaktivierte Folien überspringen. */
    normalizeLiveIndex(disabled, index, direction) {
      if (!Array.isArray(disabled) || !disabled.some(Boolean)) return index;
      const count = disabled.length;
      let i = Math.max(0, Math.min(count - 1, parseInt(index, 10) || 0));
      if (!disabled[i]) return i;
      const dir = direction === -1 ? -1 : 1;
      let guard = 0;
      while (guard++ < count + 1) {
        i += dir;
        if (i < 0 || i >= count) break;
        if (!disabled[i]) return i;
      }
      i = Math.max(0, Math.min(count - 1, parseInt(index, 10) || 0));
      guard = 0;
      while (guard++ < count + 1) {
        i -= dir;
        if (i < 0 || i >= count) break;
        if (!disabled[i]) return i;
      }
      return Math.max(0, Math.min(count - 1, parseInt(index, 10) || 0));
    },
  };
})(window);
