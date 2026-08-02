/**
 * ChurchForge appearance: dark | light | system
 * Cookie: cf_appearance (legacy cf_theme still read once, then cleared).
 * Hub shell → module iframe: postMessage { source:'churchforge', type:'cf-theme', pref }.
 */
(function () {
  var COOKIE = 'cf_appearance';
  var LEGACY = 'cf_theme';
  var MSG_SOURCE = 'churchforge';
  var MSG_TYPE = 'cf-theme';
  var applyingFromParent = false;

  function readCookie(name) {
    var parts = (document.cookie || '').split(';');
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i].trim();
      if (p.indexOf(name + '=') === 0) {
        return decodeURIComponent(p.slice(name.length + 1));
      }
    }
    return '';
  }

  function clearNamedCookie(name) {
    var host = '';
    try {
      host = location.hostname || '';
    } catch (e) {}
    var expires = 'Thu, 01 Jan 1970 00:00:00 GMT';
    var variants = [
      '',
      '; Secure',
      '; SameSite=Lax',
      '; SameSite=None',
      '; Secure; SameSite=Lax',
      '; Secure; SameSite=None'
    ];
    var domains = [''];
    if (host) {
      domains.push('; Domain=' + host);
      var parts = host.split('.');
      if (parts.length >= 2) {
        domains.push('; Domain=.' + parts.slice(-2).join('.'));
      }
    }
    ['/', ''].forEach(function (path) {
      var pathPart = path ? '; Path=' + path : '';
      domains.forEach(function (dom) {
        variants.forEach(function (extra) {
          try {
            document.cookie = name + '=; Max-Age=0; Expires=' + expires + pathPart + dom + extra;
          } catch (e) {}
        });
      });
    });
  }

  function writeCookie(value) {
    var maxAge = 365 * 24 * 3600;
    var secure = typeof location !== 'undefined' && location.protocol === 'https:';
    clearNamedCookie(LEGACY);
    var parts = [
      COOKIE + '=' + encodeURIComponent(value),
      'Path=/',
      'Max-Age=' + maxAge,
      'SameSite=Lax'
    ];
    if (secure) {
      parts.push('Secure');
    }
    try {
      document.cookie = parts.join('; ');
    } catch (e) {}
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

  function notifyFrames(pref) {
    if (applyingFromParent) {
      return;
    }
    pref = normalizePref(pref);
    var payload = { source: MSG_SOURCE, type: MSG_TYPE, pref: pref };
    try {
      document.querySelectorAll('iframe').forEach(function (frame) {
        try {
          if (frame.contentWindow) {
            frame.contentWindow.postMessage(payload, '*');
          }
        } catch (e) {}
      });
    } catch (e) {}
  }

  function bindFrameLoadSync() {
    try {
      document.querySelectorAll('iframe').forEach(function (frame) {
        if (frame.dataset && frame.dataset.cfThemeBound === '1') {
          return;
        }
        if (frame.dataset) {
          frame.dataset.cfThemeBound = '1';
        }
        frame.addEventListener('load', function () {
          notifyFrames(currentPref());
        });
      });
    } catch (e) {}
  }

  function apply(pref) {
    pref = normalizePref(pref);
    var resolved = resolve(pref);
    var root = document.documentElement;
    root.setAttribute('data-theme-pref', pref);
    root.setAttribute('data-theme', resolved);
    writeCookie(pref);
    try {
      root.style.colorScheme = resolved;
    } catch (e) {}
    notifyFrames(pref);
    return resolved;
  }

  function currentPref() {
    var fromAttr = document.documentElement.getAttribute('data-theme-pref');
    if (fromAttr) {
      return normalizePref(fromAttr);
    }
    var fromNew = readCookie(COOKIE);
    if (fromNew) {
      return normalizePref(fromNew);
    }
    return normalizePref(readCookie(LEGACY) || 'dark');
  }

  /** From user-menu <select>: apply immediately (incl. iframes), then POST prefs. */
  function commit(sel) {
    if (!sel || !sel.form) {
      return;
    }
    apply(sel.value);
    try {
      HTMLFormElement.prototype.submit.call(sel.form);
    } catch (e) {
      sel.form.submit();
    }
  }

  window.addEventListener('message', function (ev) {
    var d = ev && ev.data;
    if (!d || d.source !== MSG_SOURCE || d.type !== MSG_TYPE) {
      return;
    }
    var next = normalizePref(d.pref);
    if (next === currentPref()
      && document.documentElement.getAttribute('data-theme') === resolve(next)) {
      return;
    }
    applyingFromParent = true;
    try {
      apply(next);
    } finally {
      applyingFromParent = false;
    }
  });

  var pref = currentPref();
  apply(pref);
  bindFrameLoadSync();
  if (typeof MutationObserver === 'function') {
    try {
      var mo = new MutationObserver(function () {
        bindFrameLoadSync();
      });
      mo.observe(document.documentElement, { childList: true, subtree: true });
    } catch (e) {}
  }

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
    commit: commit,
    cookieName: COOKIE,
    notifyFrames: notifyFrames,
  };
})();
