/**
 * Auto-Weiter für animAutoAdvance (eigener Timer, reveal.js autoSlide: 0).
 * Ghost-Fragmente (.sf-ghost-frag) werden von reveal.js ignoriert.
 */
(function (global) {
  'use strict';

  let autoTimer = null;

  function clearAutoTimer() {
    if (autoTimer) {
      clearTimeout(autoTimer);
      autoTimer = null;
    }
  }

  function isLiveFragment(el) {
    return el && !el.closest('.sf-slide-ghost');
  }

  function liveFragments(slide) {
    return Array.from(slide.querySelectorAll('.fragment')).filter(isLiveFragment);
  }

  function fragmentAutoMs(el) {
    if (!el || !el.hasAttribute('data-autoslide')) return 0;
    return parseInt(el.getAttribute('data-autoslide'), 10) || 0;
  }

  function canAutoAdvance(Reveal) {
    const slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
    if (!slide) return false;
    const frags = liveFragments(slide);
    if (!frags.length) return true;
    const avail = Reveal.availableFragments && Reveal.availableFragments();
    return !!(avail && avail.next);
  }

  function scheduleWithMs(Reveal, ms) {
    clearAutoTimer();
    if (ms <= 0) return;
    if (!canAutoAdvance(Reveal)) return;
    autoTimer = setTimeout(function () {
      autoTimer = null;
      if (!Reveal || typeof Reveal.next !== 'function') return;
      try { Reveal.next(); } catch (e) { /* ignore */ }
    }, ms);
  }

  function tryFirstStepAuto(Reveal) {
    const slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
    if (!slide) return;
    const frags = liveFragments(slide);
    if (!frags.length) return;
    if (frags.some(function (f) { return f.classList.contains('visible'); })) return;
    const ms = parseInt(slide.getAttribute('data-sf-first-autoadvance') || '0', 10) || 0;
    scheduleWithMs(Reveal, ms);
  }

  function tryWholeSlideAuto(Reveal) {
    const slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
    if (!slide || liveFragments(slide).length) return;
    const ms = parseInt(slide.getAttribute('data-autoslide') || '0', 10) || 0;
    scheduleWithMs(Reveal, ms);
  }

  function install(Reveal) {
    if (!Reveal || !Reveal.on) return;
    Reveal.configure({ autoSlide: 0, autoSlideStoppable: true });

    const afterPaint = function (fn) {
      requestAnimationFrame(function () { requestAnimationFrame(fn); });
    };

    Reveal.on('fragmentshown', function (event) {
      afterPaint(function () {
        const frag = event && event.fragment;
        const ms = isLiveFragment(frag) ? fragmentAutoMs(frag) : 0;
        scheduleWithMs(Reveal, ms);
      });
    });
    Reveal.on('fragmenthidden', clearAutoTimer);
    Reveal.on('slidechanged', function () {
      clearAutoTimer();
      afterPaint(function () {
        tryFirstStepAuto(Reveal);
        tryWholeSlideAuto(Reveal);
      });
    });
    Reveal.on('ready', function () {
      afterPaint(function () {
        tryFirstStepAuto(Reveal);
        tryWholeSlideAuto(Reveal);
      });
    });
  }

  global.SlideForgeRevealAutoSlide = { install: install };
})(window);
