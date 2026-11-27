/**
 * Mobile / Tablet UI detection:
 * - sf-mobile on <html> for smartphones (max-width 767px)
 * - sf-tablet on <html> for touch tablets (Galaxy Tab, iPad, …)
 * Override via ?mobile=1 / ?desktop=1 (sets cookie sf_ui_mode).
 */
(function () {
  'use strict';

  function readModeCookie() {
    const m = document.cookie.match(/(?:^|;\s*)sf_ui_mode=(mobile|desktop)/);
    return m ? m[1] : null;
  }

  function isTabletLayout() {
    if (document.body && document.body.dataset.sfTablet === '1') {
      return true;
    }
    const sw = Math.min(window.screen.width, window.screen.height);
    const sh = Math.max(window.screen.width, window.screen.height);
    if (sw >= 600 && sh >= 900) {
      return true;
    }
    if (window.matchMedia('(pointer: coarse)').matches && window.innerWidth >= 600) {
      return true;
    }
    return false;
  }

  function applyTabletClass() {
    document.documentElement.classList.toggle('sf-tablet', isTabletLayout());
  }

  function applyMobileClass() {
    const forced = readModeCookie();
    const narrow = window.matchMedia('(max-width: 767px)').matches;
    const mobile = forced === 'mobile' || (forced !== 'desktop' && narrow && !isTabletLayout());
    document.documentElement.classList.toggle('sf-mobile', mobile);
  }

  function applyUiClasses() {
    applyTabletClass();
    applyMobileClass();
  }

  applyUiClasses();
  window.matchMedia('(max-width: 767px)').addEventListener('change', applyUiClasses);
  window.addEventListener('resize', applyUiClasses);
  window.addEventListener('orientationchange', applyUiClasses);
})();
