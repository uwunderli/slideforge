/**
 * Mobile UI detection: adds sf-mobile on <html> for smartphones (max-width 767px).
 * Override via ?mobile=1 / ?desktop=1 (sets cookie sf_ui_mode).
 */
(function () {
  'use strict';

  function readModeCookie() {
    const m = document.cookie.match(/(?:^|;\s*)sf_ui_mode=(mobile|desktop)/);
    return m ? m[1] : null;
  }

  function applyMobileClass() {
    const forced = readModeCookie();
    const narrow = window.matchMedia('(max-width: 767px)').matches;
    const mobile = forced === 'mobile' || (forced !== 'desktop' && narrow);
    document.documentElement.classList.toggle('sf-mobile', mobile);
  }

  applyMobileClass();
  window.matchMedia('(max-width: 767px)').addEventListener('change', applyMobileClass);
})();
