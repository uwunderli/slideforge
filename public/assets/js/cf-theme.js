/**
 * ChurchForge appearance: dark | light | system
 * Cookie: cf_appearance (legacy cf_theme still read once, then cleared).
 * Hub shell ↔ module iframe: postMessage { source:'churchforge', type:'cf-theme', pref, persist?, dialogChrome? }.
 * dialogChrome ({ chrome:{light,dark}, accent:{light,dark} }) travels one-way, parent → iframe only.
 *
 * This file is vendored byte-for-byte into modules (of/planforge/…), which may be
 * mounted below the domain root. A module that needs its own prefs.php resolved
 * sets data-base-path on the <script> tag that loads this file, e.g.
 *   <script src=".../cf-theme.js" data-base-path="/officeforge/public"></script>
 */
(function () {
  var COOKIE = 'cf_appearance';
  var LEGACY = 'cf_theme';
  var MSG_SOURCE = 'churchforge';
  var MSG_TYPE = 'cf-theme';
  var SCRIPT_TAG = document.currentScript;
  var SECOND_LEVEL = ['co', 'com', 'org', 'net', 'ac', 'gov', 'edu'];

  /** Gemeinsame Domain einer Adresse: die letzten zwei Namensteile (drei bei «co.uk» u. ä.); IP-Adressen und «localhost» bleiben, wie sie sind. */
  function baseDomain(host) {
    host = String(host || '').toLowerCase();
    var l = host.split('.');
    if (l.length <= 2 || /^\d+(\.\d+){3}$/.test(host)) {
      return host;
    }
    var n = (l[l.length - 1].length === 2 && SECOND_LEVEL.indexOf(l[l.length - 2]) !== -1) ? 3 : 2;
    return l.slice(-n).join('.');
  }
  var DIALOG_CHROMES = ['klassisch', 'flach'];
  var DIALOG_ACCENTS = ['', 'blau', 'gruen', 'indigo', 'terracotta'];
  var applyingFromParent = false;

  /**
   * Herkunft der Rahmen-Nachrichten (Review OF 16.10.): angenommen wird nur vom direkten Parent oder von einem
   * eigenen iframe, und nur, wenn dessen Origin stimmt; gesendet wird an den konkreten Origin, nie an '*'.
   * Der Hub-Origin im Modul kommt aus dem Referrer, `hub_origin` der Rahmenadresse (app/embed.php) oder dem
   * gemerkten Wert und gilt nur für die Hubs in HUB_ORIGINS bzw. den eigenen Origin. Bekannte Grenze: Ruft man
   * den Hub über eine LAN-Adresse (http) auf, fehlt im Rahmen der Darstellungs-Abgleich; der Zugriff bleibt.
   */
  var HUB_ORIGIN_KEY = 'cf_hub_origin';
  /**
   * Zusätzliche Hub-Adressen (https), die der Server der Seite nennt: `window.CF_HUB_ORIGINS` (Liste) oder `data-hub-origins`
   * (Komma-Liste) am Script-Tag. Ohne Angabe gilt nur der eigene Origin und `hub.<gemeinsame Domain>`.
   */
  function extraHubOrigins() {
    var out = [];
    try {
      if (Array.isArray(window.CF_HUB_ORIGINS)) {
        out = out.concat(window.CF_HUB_ORIGINS);
      }
      var attr = SCRIPT_TAG && SCRIPT_TAG.getAttribute('data-hub-origins');
      if (attr) {
        out = out.concat(attr.split(','));
      }
    } catch (e) {}
    return out.map(function (o) { return String(o || '').trim().replace(/\/$/, ''); }).filter(Boolean);
  }

  function originOf(url) {
    if (!url) {
      return '';
    }
    try {
      var u = new URL(String(url || ''), location.href);
      return (u.protocol === 'https:' || u.protocol === 'http:') ? u.origin : '';
    } catch (e) {
      return '';
    }
  }

  function trustedHubOrigin(origin) {
    if (!origin) {
      return false;
    }
    if (origin === location.origin) {
      return true;
    }
    // Nur die Hubs selbst, nicht jede Subdomain (Review 16.10.): `hub.<gemeinsame Domain>` oder eine vom Server genannte Adresse.
    try {
      var u = new URL(origin);
      if (u.protocol === 'https:' && u.hostname === 'hub.' + baseDomain(location.hostname) && !u.port) {
        return true;
      }
    } catch (e) {
      return false;
    }
    return extraHubOrigins().indexOf(origin) !== -1;
  }

  /** Origin des Hubs, der dieses Fenster einrahmt — oder '' wenn unbekannt/nicht vertrauenswürdig. */
  var parentOrigin = (function () {
    if (!window.parent || window.parent === window) {
      return '';
    }
    // Referrer zuerst: Er nennt den Rahmen, der wirklich geladen hat (auch wenn der Hub unter einem anderen
    // seiner Namen aufgerufen wurde); `hub_origin` und der gemerkte Wert decken Navigation im Rahmen ab.
    var kandidaten = [originOf(document.referrer)];
    try {
      kandidaten.push(new URLSearchParams(location.search).get('hub_origin') || '');
    } catch (e) {}
    try {
      kandidaten.push(sessionStorage.getItem(HUB_ORIGIN_KEY) || '');
    } catch (e) {}
    for (var i = 0; i < kandidaten.length; i++) {
      var o = originOf(kandidaten[i]);
      if (trustedHubOrigin(o)) {
        try { sessionStorage.setItem(HUB_ORIGIN_KEY, o); } catch (e) {}
        return o;
      }
    }
    return '';
  })();

  /** Das eigene iframe, aus dem die Nachricht kommt, mit passendem Origin — sonst null. */
  function childFrameFor(ev) {
    try {
      var frames = document.querySelectorAll('iframe');
      for (var i = 0; i < frames.length; i++) {
        if (frames[i].contentWindow === ev.source) {
          return originOf(frames[i].src) === ev.origin ? frames[i] : null;
        }
      }
    } catch (e) {}
    return null;
  }
  var BASE_PATH = (function () {
    try {
      var el = document.currentScript;
      var p = el && el.dataset ? String(el.dataset.basePath || '') : '';
      return p.replace(/\/+$/, '');
    } catch (e) {
      return '';
    }
  })();

  function prefsUrl() {
    return BASE_PATH + '/prefs.php';
  }

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

  function parentCookieDomain() {
    try {
      var h = location.hostname || '';
      if (h !== 'localhost' && h.indexOf('.') !== -1 && !/^\d+(\.\d+){3}$/.test(h)) {
        return '.' + baseDomain(h);
      }
    } catch (e) {}
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
    var parent = parentCookieDomain();
    if (parent) {
      domains.push('; Domain=' + parent);
    }
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
      'SameSite=' + (secure ? 'None' : 'Lax')
    ];
    if (secure) {
      parts.push('Secure');
    }
    var dom = parentCookieDomain();
    if (dom) {
      parts.push('Domain=' + dom);
    }
    try {
      document.cookie = parts.join('; ');
    } catch (e) {}
  }

  function normalizePref(raw) {
    var v = String(raw || '').toLowerCase().trim();
    return v === 'light' || v === 'system' || v === 'dark' ? v : 'dark';
  }

  /** Dialog-Chrome-Paar auf erlaubte Werte filtern; null, wenn kein Objekt. */
  /** Akzent: leer, ein Name aus der Liste oder eine freie Farbe `#rrggbb` (Urs 09.11.2026). */
  function validAccent(a) {
    return a === '' || DIALOG_ACCENTS.indexOf(a) !== -1 || /^#[0-9a-f]{6}$/.test(a);
  }

  /** Farbton (OKLCH-Hue, 0–360) einer Farbe `#rrggbb`; dieselbe Rechnung wie `UserPrefs::accentHue()` im Hub. */
  function accentTone(hex) {
    var n = parseInt(hex.slice(1), 16);
    var c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(function (v) {
      v /= 255;
      return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    var l = Math.cbrt(0.4122214708 * c[0] + 0.5363325363 * c[1] + 0.0514459929 * c[2]);
    var m = Math.cbrt(0.2119034982 * c[0] + 0.6806995451 * c[1] + 0.1073969566 * c[2]);
    var s = Math.cbrt(0.0883024619 * c[0] + 0.2817188376 * c[1] + 0.6299787005 * c[2]);
    var a = 1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s;
    var b = 0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s;
    var h = Math.atan2(b, a) * 180 / Math.PI;
    var chroma = Math.sqrt(a * a + b * b);
    return [Math.round((h < 0 ? h + 360 : h) * 10) / 10, Math.round(Math.min(1, chroma / 0.08) * 1000) / 1000];
  }
  function accentHue(hex) { return accentTone(hex)[0]; }

  function sanitizeDialogChrome(raw) {
    if (!raw || typeof raw !== 'object') {
      return null;
    }
    var chromeIn = raw.chrome && typeof raw.chrome === 'object' ? raw.chrome : {};
    var accentIn = raw.accent && typeof raw.accent === 'object' ? raw.accent : {};
    var out = {
      chrome: { light: 'klassisch', dark: 'klassisch' },
      accent: { light: '', dark: '' }
    };
    ['light', 'dark'].forEach(function (mode) {
      var chrome = String(chromeIn[mode] || '');
      var accent = String(accentIn[mode] || '').toLowerCase();
      if (DIALOG_CHROMES.indexOf(chrome) !== -1) {
        out.chrome[mode] = chrome;
      }
      if (validAccent(accent)) {
        out.accent[mode] = accent;
      }
    });
    return out;
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
    var dialogChrome = sanitizeDialogChrome(window.__cfDialogChromePrefs);
    if (dialogChrome) {
      payload.dialogChrome = dialogChrome;
    }
    try {
      document.querySelectorAll('iframe').forEach(function (frame) {
        try {
          var ziel = originOf(frame.src);
          if (frame.contentWindow && ziel) {
            frame.contentWindow.postMessage(payload, ziel);
          }
        } catch (e) {}
      });
    } catch (e) {}
  }

  function notifyParent(pref, persist) {
    if (applyingFromParent) {
      return;
    }
    if (!window.parent || window.parent === window || !parentOrigin) {
      return;
    }
    try {
      window.parent.postMessage({
        source: MSG_SOURCE,
        type: MSG_TYPE,
        pref: normalizePref(pref),
        persist: !!persist
      }, parentOrigin);
    } catch (e) {}
  }

  function persistForm(form, onFail) {
    var fallback = onFail || function () {
      try {
        HTMLFormElement.prototype.submit.call(form);
      } catch (e) {
        form.submit();
      }
    };
    try {
      if (typeof fetch !== 'function') {
        fallback();
        return;
      }
      fetch(form.getAttribute('action') || form.action || prefsUrl(), {
        method: (form.method || 'POST').toUpperCase(),
        body: new FormData(form),
        credentials: 'same-origin',
        redirect: 'manual',
        headers: { 'X-Requested-With': 'ChurchForgeTheme' }
      }).then(function (res) {
        if (res.type === 'opaqueredirect' || res.status === 0 || (res.status >= 200 && res.status < 400)) {
          return;
        }
        fallback();
      }).catch(fallback);
    } catch (e) {
      fallback();
    }
  }

  /** Hub shell: write UserPrefs so the next iframe load keeps hub_theme. */
  function persistHubPrefs(pref) {
    var csrf = document.querySelector('form.hub-user-menu__pref input[name="csrf_token"], input[name="csrf_token"]');
    if (!csrf || !csrf.value) {
      return;
    }
    if (typeof fetch !== 'function') {
      return;
    }
    var fd = new FormData();
    fd.append('csrf_token', csrf.value);
    fd.append('action', 'set_theme');
    fd.append('theme', normalizePref(pref));
    fd.append('return', '/');
    fetch(prefsUrl(), {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      redirect: 'manual'
    }).catch(function () {});
    document.querySelectorAll('select[name="theme"]').forEach(function (sel) {
      sel.value = normalizePref(pref);
    });
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

  /**
   * Dialog-Chrome/Akzent (DESIGN.md § Dialogfenster → Chrome-Variante & Akzentfarbe):
   * pro Nutzer wählbar, getrennt Hell/Dunkel. Das Hell/Dunkel-Paar wird serverseitig
   * einmal pro Seitenaufruf injiziert (window.__cfDialogChromePrefs, siehe header.php /
   * app/embed.php) — eingebettet ersetzt es die Nachricht des Parents (nur im Speicher).
   * Hier wird beim Theme-Wechsel nur zwischen den beiden Werten umgeschaltet, kein
   * Extra-Request. Fehlt das Paar (Modul ohne Auswahl, nicht eingebettet), bleiben die
   * Attribute unangetastet — heutiges Verhalten.
   */
  function applyDialogChrome(resolved) {
    var prefs = window.__cfDialogChromePrefs;
    if (!prefs || typeof prefs !== 'object') {
      return;
    }
    var root = document.documentElement;
    var chromeMap = prefs.chrome || {};
    var accentMap = prefs.accent || {};
    var chrome = String(chromeMap[resolved] || 'klassisch');
    var accent = String(accentMap[resolved] || '');
    if (chrome === 'flach') {
      root.setAttribute('data-dialog-chrome', 'flach');
    } else {
      root.removeAttribute('data-dialog-chrome');
    }
    if (/^#[0-9a-f]{6}$/.test(accent)) {
      // Freie Farbe: Farbton und Farbe als Variablen an <html>, die Regeln hängen an --hub-base-hue.
      root.setAttribute('data-dialog-accent', 'custom');
      var tone = accentTone(accent);
      root.style.setProperty('--hub-base-hue', String(tone[0]));
      root.style.setProperty('--hub-chroma', String(tone[1]));
      root.style.setProperty('--hub-dialog-accent', accent);
    } else {
      root.style.removeProperty('--hub-base-hue');
      root.style.removeProperty('--hub-chroma');
      root.style.removeProperty('--hub-dialog-accent');
      if (accent) {
        root.setAttribute('data-dialog-accent', accent);
      } else {
        root.removeAttribute('data-dialog-accent');
      }
    }
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
    applyDialogChrome(resolved);
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

  /** From user-menu <select>: apply immediately, persist prefs, sync Hub/iframe. */
  function commit(sel) {
    if (!sel || !sel.form) {
      return;
    }
    apply(sel.value);
    persistForm(sel.form);
    notifyParent(sel.value, true);
  }

  window.addEventListener('message', function (ev) {
    var d = ev && ev.data;
    if (!d || d.source !== MSG_SOURCE || d.type !== MSG_TYPE) {
      return;
    }
    var vomParent = window.parent !== window && ev.source === window.parent
      && !!parentOrigin && ev.origin === parentOrigin;
    var vomKind = !vomParent && childFrameFor(ev) !== null;
    if (!vomParent && !vomKind) {
      return;
    }
    // Dialog-Chrome nur vom direkten Parent annehmen (Einbahn Hub → Modul), nur im Speicher.
    var chromeFromParent = null;
    if (d.dialogChrome && vomParent) {
      chromeFromParent = sanitizeDialogChrome(d.dialogChrome);
      if (chromeFromParent) {
        window.__cfDialogChromePrefs = chromeFromParent;
      }
    }
    var next = normalizePref(d.pref);
    var already = next === currentPref()
      && document.documentElement.getAttribute('data-theme') === resolve(next);
    if (!already) {
      applyingFromParent = true;
      try {
        apply(next);
      } finally {
        applyingFromParent = false;
      }
    } else if (chromeFromParent) {
      applyDialogChrome(resolve(next));
    }
    // Speichern nur in Richtung Modul → Hub, also nur, wenn es aus dem eigenen iframe kommt.
    if (d.persist && vomKind) {
      persistHubPrefs(next);
    }
  });

  /**
   * Fenster-Stil und Akzent des Hubs schon im ersten Bild (OF-Befund Sand-Flackern 16.10.): `app/embed.php`
   * hängt `hub_chrome`/`hub_accent` (aufgelöst für das aktuelle Hell/Dunkel) an die Rahmenadresse. Gilt nur im
   * Rahmen eines vertrauenswürdigen Hubs und nur mit Werten aus der Liste (sanitizeDialogChrome); die
   * postMessage nach `load` bleibt für spätere Wechsel.
   */
  (function bootChromeFromUrl() {
    if (!parentOrigin) {
      return;
    }
    try {
      var q = new URLSearchParams(location.search);
      var c = q.get('hub_chrome');
      var a = q.get('hub_accent');
      if (c === null && a === null) {
        return;
      }
      var clean = sanitizeDialogChrome({
        chrome: { light: c || '', dark: c || '' },
        accent: { light: a || '', dark: a || '' }
      });
      if (clean) {
        window.__cfDialogChromePrefs = clean;
      }
    } catch (e) {}
  })();

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
    accentHue: accentHue,
    accentTone: accentTone,
  };
})();
