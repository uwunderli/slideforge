/**
 * ChurchForge appearance: dark | light | system
 * Reads cookie cf_theme (or data-theme-pref on <html>), sets data-theme to dark|light.
 */
(function () {
  var COOKIE = 'cf_theme';

  function readCookie(name) {
    var parts = (';.cookie || '').split(';');
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i].trim();
      if (p.indexOf(name + '=') === 0) {
        return decodeURIComponent(p.slice(name.length + 1));
      }
    }
    return '';
  }

  function normalizePref(raw) {
    var v = String(raw || '').toLowerCase().trim();
    return v === 'light' || v === 'system' || v === 'dark' ? v : 'dark';
  }

  function systemTheme() {
    try {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    } catch (e) {
      return 'dark';
    }
  }

  function resolve(pref) {
    pref = normalizePref(pref);
    if (pref === 'system') {
      return systemTheme();
    }
    return pref;
  }

  function apply(pref) {
    pref = normalizePref(pref);
    var resolved = resolve(pref);
    var root = document.documentElement;
    root.setAttribute('data-theme-pref', pref);
    root.setAttribute('data-theme', resolved);
    try {
      root.style.colorScheme = resolved;
    } catch (e) {}
    return resolved;
  }

  function currentPref() {
    var fromAttr = document.documentElement.getAttribute('data-theme-pref');
    if (fromAttr) {
      return normalizePref(fromAttr);
    }
    return normalizePref(readCookie(COOKIE) || 'dark');
  }

  var pref = currentPref();
  apply(pref);

  try {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function () {
      if (normalizePref(document.documentElement.getAttribute('data-theme-pref')) === 'system') {
        apply('system');
      }
    };
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', onChange);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(onChange);
    }
  } catch (e) {}

  window.ChurchForgeTheme = {
    apply: apply,
    resolve: resolve,
    pref: currentPref,
    cookieName: COOKIE,
  };
})();
