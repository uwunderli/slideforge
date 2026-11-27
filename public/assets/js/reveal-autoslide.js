/**
 * Auto-Weiter für Fragment-/Folien-Zeiten (eigener Timer).
 * Nutzt data-sf-slide-autoslide / data-sf-fragment-autoslide — nicht reveal.js data-autoslide.
 * Nach Present-Boot: kein Auto, bis die Steuerung einmal manuell weitergeht.
 */
(function (global) {
  'use strict';

  let autoTimer = null;
  let autoSlidePaused = false;
  let allowAutoAdvance = false;
  let installedReveal = null;

  function clearAutoTimer() {
    if (autoTimer) {
      clearTimeout(autoTimer);
      autoTimer = null;
    }
  }

  function setPaused(paused) {
    autoSlidePaused = !!paused;
    if (autoSlidePaused) clearAutoTimer();
  }

  function enableAutoAdvance() {
    allowAutoAdvance = true;
    setPaused(false);
    if (installedReveal) {
      tryFirstStepAuto(installedReveal);
      tryWholeSlideAuto(installedReveal);
    }
  }

  function isLiveFragment(el) {
    return el && !el.closest('.sf-slide-ghost');
  }

  function liveFragments(slide) {
    return Array.from(slide.querySelectorAll('.fragment')).filter(isLiveFragment);
  }

  function fragmentAutoMs(el) {
    if (!el) return 0;
    const raw = el.getAttribute('data-sf-fragment-autoslide') || el.getAttribute('data-autoslide');
    if (raw == null || raw === '') return 0;
    return parseInt(raw, 10) || 0;
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
    if (autoSlidePaused || !allowAutoAdvance) return;
    if (ms <= 0) return;
    if (!canAutoAdvance(Reveal)) return;
    autoTimer = setTimeout(function () {
      autoTimer = null;
      if (autoSlidePaused || !allowAutoAdvance) return;
      if (!Reveal || typeof Reveal.next !== 'function') return;
      try { Reveal.next(); } catch (e) { /* ignore */ }
    }, ms);
  }

  function tryFirstStepAuto(Reveal) {
    if (!allowAutoAdvance || autoSlidePaused) return;
    const slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
    if (!slide) return;
    const frags = liveFragments(slide);
    if (!frags.length) return;
    if (frags.some(function (f) { return f.classList.contains('visible'); })) return;
    const ms = parseInt(slide.getAttribute('data-sf-first-autoadvance') || '0', 10) || 0;
    scheduleWithMs(Reveal, ms);
  }

  function tryWholeSlideAuto(Reveal) {
    if (!allowAutoAdvance || autoSlidePaused) return;
    const slide = Reveal.getCurrentSlide && Reveal.getCurrentSlide();
    if (!slide || liveFragments(slide).length) return;
    const ms = parseInt(
      slide.getAttribute('data-sf-slide-autoslide')
        || slide.getAttribute('data-autoslide')
        || '0',
      10
    ) || 0;
    scheduleWithMs(Reveal, ms);
  }

  function stripNativeAutoslideAttrs(root) {
    const scope = root || document;
    scope.querySelectorAll('.slides section[data-autoslide]').forEach(function (sec) {
      if (!sec.hasAttribute('data-sf-slide-autoslide')) {
        sec.setAttribute('data-sf-slide-autoslide', sec.getAttribute('data-autoslide'));
      }
      sec.removeAttribute('data-autoslide');
    });
    scope.querySelectorAll('.slides .fragment[data-autoslide]').forEach(function (frag) {
      if (!frag.hasAttribute('data-sf-fragment-autoslide')) {
        frag.setAttribute('data-sf-fragment-autoslide', frag.getAttribute('data-autoslide'));
      }
      frag.removeAttribute('data-autoslide');
    });
  }

  function install(Reveal, opts) {
    if (!Reveal || !Reveal.on) return;
    if (Reveal._sfAutoSlideInstalled) return;
    Reveal._sfAutoSlideInstalled = true;
    installedReveal = Reveal;
    Reveal.configure({ autoSlide: 0, autoSlideStoppable: true });
    stripNativeAutoslideAttrs(document);

    if (opts && opts.pausedUntilBoot) {
      autoSlidePaused = true;
      allowAutoAdvance = false;
    }

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
        // Kein allowAutoAdvance hier — Boot-/slide(0)-Events würden sonst Folie 1 wegschieben.
        tryFirstStepAuto(Reveal);
        tryWholeSlideAuto(Reveal);
      });
    });
    Reveal.on('ready', function () {
      stripNativeAutoslideAttrs(document);
      afterPaint(function () {
        tryFirstStepAuto(Reveal);
        tryWholeSlideAuto(Reveal);
      });
    });

    window.addEventListener('message', function (e) {
      if (!e.data) return;
      if (e.data.type === 'sf-present-boot-done') {
        // Nur entsperren — Auto-Weiter erst nach manueller Navigation.
        setPaused(false);
        clearAutoTimer();
        return;
      }
      if (e.data.type === 'sf-present-enable-auto') {
        enableAutoAdvance();
      }
    });
  }

  global.SlideForgeRevealAutoSlide = {
    install: install,
    setPaused: setPaused,
    clear: clearAutoTimer,
    enableAutoAdvance: enableAutoAdvance,
  };
})(window);
