/**
 * HubFloatDialog — Fenster-Chrome für schwebende Dialoge (DESIGN § Dialogfenster).
 * Quelle für Module: hub_asset() / vendorn, nicht forken.
 * Verhalten: Host, Persistenz, fitContent, Multi-Dialog (OF-Parity).
 *
 * **Persistenz ist Opt-in** (Urs, 2026-09-23). Ein Dialog übersteht den Tab-/Seitenwechsel nur,
 * wenn der Aufrufer `persist: true` setzt **und** für seine Kennung eine Verdrahtung vorliegt
 * (`bind:` beim Öffnen oder `register(id, fn)`). Vorher galt „sichern" als Standard — dabei kam
 * ein Dialog ohne Verdrahtung als Attrappe zurück: sieht normal aus, tut nichts (OF: 9 von ~45
 * Öffnungsstellen hatten eine Verdrahtung).
 * Dialoge, die **nicht** gesichert werden, bekommen beim Verlassen der Seite ihr `onClose`
 * **synchron** gerufen — Gelegenheit für ein `navigator.sendBeacon`. **Zugesichert ist nur der
 * synchrone Aufruf; ein zurückgegebenes Versprechen wird NICHT abgewartet**, das kann der Browser
 * beim Seitenwechsel nicht leisten. Wer Eingaben sichern muss, braucht ein Beacon oder Persistenz.
 *
 * HubFloatDialog.open(title, html, opts)
 * HubFloatDialog.close(id|el|'all')
 * HubFloatDialog.register(id, bindFn) / .rebindAll() / .fit() / .isOpen() / .body() / .list()
 * Legacy: .bind(el, opts) / .open(el) / .decorate / .upgradeBackdrop
 */
(function (global) {
  'use strict';

  const DEFAULT_I18N = {
    minimize: 'Minimieren',
    maximize: 'Maximieren',
    restore: 'Wiederherstellen',
    close: 'Schliessen'
  };

  const EDGE = ['n', 'e', 's', 'w', 'ne', 'nw', 'se', 'sw'];
  const CONTENT_KEY = 'hub_float_dialogs_v1';
  const GEOM_PREFIX = 'hub_float_geom:';
  const binders = Object.create(null);
  const beforeEscapeHooks = [];
  const legacyInstances = new WeakMap();
  /**
   * Erst nach `restorePersisted()` darf `saveAll()` den gesicherten Stand **löschen**.
   *
   * Ein `open()`/`close()` vor dem Wiederherstellen (ein Modul, das beim Laden sofort einen Dialog
   * zeigt) ruft `saveAll()` mit einer noch leeren Liste — und warf damit den gesicherten Stand
   * weg, bevor ihn jemand zurückholen konnte. «Die Liste ist leer» ist eine andere Aussage als
   * «es gibt nichts zu sichern» — dieselbe Verwechslung wie beim `crons.json`-Verlust am
   * 2026-09-22 (Verdacht `Koordinator`, 2026-09-26). Neu aufgesetzt auf der Live-Fassung am
   * 14.10. (der erste Entwurf lag auf `wip/float-dialog-maximieren`, auf alter Grundlage);
   * bewacht von `scripts/test-float-dialog-guard.php`.
   */
  let storageRestored = false;
  let zTop = 12000;
  let dragState = null;
  let hostReady = false;
  let globalChromeWired = false;
  let storageMigrated = false;

  function i18nOf(opts) {
    return Object.assign({}, DEFAULT_I18N, global.HUB_FLOAT_DIALOG_I18N || {}, (opts && opts.i18n) || {});
  }

  function maxBox() {
    return {
      w: Math.floor(window.innerWidth * 0.96),
      h: Math.floor(window.innerHeight * 0.90)
    };
  }

  function escapeAttr(s) {
    return String(s || '').replace(/"/g, '&quot;');
  }

  function titlebarEl(dlg) {
    return dlg.querySelector('.hub-float-dialog__titlebar, .hub-float-dialog__title');
  }

  function bodyEl(dlg) {
    return dlg.querySelector('[data-hub-body], .hub-float-dialog__body');
  }

  function footerEl(dlg) {
    return dlg.querySelector('[data-hub-footer], .hub-float-dialog__footer');
  }

  function isChromeState(dlg) {
    return dlg.classList.contains('is-maximized') || dlg.classList.contains('is-minimized');
  }

  function getFitContent(dlg) {
    return dlg.dataset.hubFitContent !== '0';
  }

  function setFitContent(dlg, on) {
    dlg.dataset.hubFitContent = on ? '1' : '0';
  }

  function migrateStorageOnce() {
    if (storageMigrated) return;
    storageMigrated = true;
    try {
      // OfficeForge → Hub geom keys
      for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i);
        if (!k || !k.startsWith('of_float_geom:')) continue;
        const id = k.slice('of_float_geom:'.length);
        const hubKey = GEOM_PREFIX + id;
        if (!localStorage.getItem(hubKey)) {
          localStorage.setItem(hubKey, localStorage.getItem(k) || '');
        }
      }
    } catch (_) { /* ignore */ }
    try {
      if (!sessionStorage.getItem(CONTENT_KEY)) {
        const ofRaw = sessionStorage.getItem('of_float_dialogs_v2');
        if (ofRaw) sessionStorage.setItem(CONTENT_KEY, ofRaw);
      }
    } catch (_) { /* ignore */ }
  }

  function loadGeom(id) {
    migrateStorageOnce();
    const prefs = window.HubUserPrefsUi;
    if (prefs) {
      try { prefs.migrateLocal(GEOM_PREFIX + id, 'float:' + id); } catch (_) {}
      try { prefs.migrateLocal('of_float_geom:' + id, 'float:' + id); } catch (_) {}
      const fromServer = prefs.get('float:' + id, null);
      if (fromServer && typeof fromServer === 'object') return fromServer;
    }
    try {
      const hub = JSON.parse(localStorage.getItem(GEOM_PREFIX + id) || 'null');
      if (hub) return hub;
      return JSON.parse(localStorage.getItem('of_float_geom:' + id) || 'null');
    } catch (_) {
      return null;
    }
  }

  function saveGeom(dlg) {
    const id = dlg.getAttribute('data-hub-float-id') || '';
    if (!id || isChromeState(dlg)) return;
    const geom = {
      left: dlg.style.left,
      top: dlg.style.top,
      width: dlg.style.width,
      height: dlg.style.height,
      rev: Number(dlg.getAttribute('data-hub-geom-rev') || 0) || 0,
      fitContent: getFitContent(dlg)
    };
    const prefs = window.HubUserPrefsUi;
    if (prefs) {
      try { prefs.set('float:' + id, geom); } catch (_) {}
      try { localStorage.removeItem(GEOM_PREFIX + id); } catch (_) {}
      return;
    }
    try {
      localStorage.setItem(GEOM_PREFIX + id, JSON.stringify(geom));
    } catch (_) { /* ignore */ }
  }

  function focusDlg(dlg) {
    zTop += 1;
    dlg.style.zIndex = String(zTop);
  }

  function ensureHost() {
    let host = document.getElementById('hubFloatHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'hubFloatHost';
      host.className = 'hub-float-host';
      host.setAttribute('aria-live', 'polite');
      document.body.appendChild(host);
    }
    let tpl = document.getElementById('hubFloatDialogTpl');
    if (!tpl) {
      tpl = document.createElement('template');
      tpl.id = 'hubFloatDialogTpl';
      const I = i18nOf({});
      tpl.innerHTML = `
<div class="hub-float-dialog" data-hub-float hidden>
  <div class="hub-float-dialog__titlebar" data-hub-drag>
    <strong class="hub-float-dialog__title-text" data-hub-title>Dialog</strong>
    <div class="hub-float-dialog__win-btns">
      <button type="button" class="hub-btn hub-btn--ghost" data-hub-win="min" title="${escapeAttr(I.minimize)}" aria-label="${escapeAttr(I.minimize)}">▁</button>
      <button type="button" class="hub-btn hub-btn--ghost" data-hub-win="max" title="${escapeAttr(I.maximize)}" aria-label="${escapeAttr(I.maximize)}">▢</button>
      <button type="button" class="hub-btn hub-btn--ghost" data-hub-win="close" title="${escapeAttr(I.close)}" aria-label="${escapeAttr(I.close)}">×</button>
    </div>
  </div>
  <div class="hub-float-dialog__body hub-scroll" data-hub-body></div>
  <div class="hub-float-dialog__footer hub-dialog-actions" data-hub-footer hidden></div>
  ${EDGE.map((e) => `<div class="hub-float-dialog__handle hub-float-dialog__handle--${e}" data-hub-resize="${e}" aria-hidden="true"></div>`).join('')}
</div>`;
      document.body.appendChild(tpl);
    }
    hostReady = true;
    wireGlobalChrome();
    return { host, tpl };
  }

  function fieldKey(el, index) {
    return el.id || el.name || ('i' + index);
  }

  function persistableValue(v) {
    if (typeof v !== 'string') return v;
    if (v.length > 6000 && /^data:/i.test(v)) return '';
    return v;
  }

  /**
   * Geheimnisse bleiben aus dem Browser-Speicher (Review OF 16.10., B1: Mail-Passwörter landeten über
   * `persist: true` im sessionStorage). Ausgenommen: `type=password`, Passwort-`autocomplete`, alles in
   * oder mit `data-no-persist`, und Namen/Kennungen, die nach Geheimnis klingen. Lieber ein Feld zu viel
   * neu eintippen als ein Passwort im Speicher.
   */
  const SECRET_NAME_RE = /pass(wor[dt])?|pwd|secret|geheim|token|api[-_]?key|private[-_]?key|credential/i;
  function isSecretField(el) {
    if (!el) return false;
    const type = String(el.type || '').toLowerCase();
    if (type === 'password') return true;
    if (type === 'checkbox' || type === 'radio') return false;
    const ac = String((el.getAttribute && el.getAttribute('autocomplete')) || '').toLowerCase();
    if (/(current|new)-password|one-time-code/.test(ac)) return true;
    if (el.closest && el.closest('[data-no-persist]')) return true;
    return SECRET_NAME_RE.test(String(el.name || '')) || SECRET_NAME_RE.test(String(el.id || ''));
  }

  /** HTML eines Bereichs ohne Geheimnisse: vorbelegte `value`/Textinhalte der Geheimfelder fallen weg. */
  function htmlWithoutSecrets(root, outer) {
    if (!root) return '';
    if (!root.querySelector('input, textarea')) return outer ? root.outerHTML : root.innerHTML;
    const copy = root.cloneNode(true);
    copy.querySelectorAll('input, textarea').forEach((el) => {
      if (!isSecretField(el)) return;
      el.removeAttribute('value');
      if (el.tagName === 'TEXTAREA') el.textContent = '';
    });
    return outer ? copy.outerHTML : copy.innerHTML;
  }

  function captureValues(root) {
    const values = {};
    if (!root) return values;
    root.querySelectorAll('input, textarea, select').forEach((el, i) => {
      const k = fieldKey(el, i);
      if (isSecretField(el)) return;
      if (el.type === 'checkbox' || el.type === 'radio') values[k] = !!el.checked;
      else if (el.type === 'file') values[k] = '';
      else values[k] = persistableValue(el.value);
    });
    return values;
  }

  function htmlForPersist(html) {
    return String(html || '').replace(/\svalue="data:[^"]{200,}"/gi, ' value=""');
  }

  function applyValues(root, values) {
    if (!root || !values) return;
    root.querySelectorAll('input, textarea, select').forEach((el, i) => {
      const k = fieldKey(el, i);
      if (!(k in values) || isSecretField(el)) return;
      if (el.type === 'checkbox' || el.type === 'radio') el.checked = !!values[k];
      else el.value = values[k];
    });
  }

  function runBinder(id, body) {
    if (!id || typeof binders[id] !== 'function' || !body) return;
    const dlg = body.closest ? body.closest('[data-hub-float]') : null;
    try { binders[id](body, dlg || body); } catch (err) { console.warn('HubFloatDialog bind', id, err); }
  }

  function cssDim(v) {
    if (v == null || v === '') return '';
    if (typeof v === 'number' && Number.isFinite(v)) return Math.round(v) + 'px';
    const s = String(v).trim();
    if (!s) return '';
    if (/^\d+(\.\d+)?$/.test(s)) return Math.round(Number(s)) + 'px';
    return s;
  }

  function applyGeom(dlg, id, index, defaults) {
    defaults = defaults || {};
    const saved = loadGeom(id) || {};
    const rev = Number(defaults.geomRev || 0) || 0;
    const savedRev = Number(saved.rev || 0) || 0;
    const bump = rev > savedRev;
    const width = cssDim(bump
      ? (defaults.width || saved.width || '')
      : (saved.width || defaults.width || ''));
    const height = cssDim(bump
      ? (defaults.height || saved.height || '')
      : (saved.height || defaults.height || ''));
    dlg.setAttribute('data-hub-geom-rev', String(rev));
    if (defaults.fitContent === false) setFitContent(dlg, false);
    else if (saved.fitContent === false && !bump) setFitContent(dlg, false);
    else if (bump || saved.fitContent == null) setFitContent(dlg, true);
    else setFitContent(dlg, !!saved.fitContent);
    if (width) dlg.style.width = width;
    if (height) dlg.style.height = height;
    if (saved.left || saved.top) {
      applyClampedPosition(dlg, saved.left, saved.top);
      if (bump) saveGeom(dlg);
      return;
    }
    const offset = (index % 8) * 28;
    dlg.style.left = 'calc(20vw + ' + offset + 'px)';
    dlg.style.top = 'calc(18vh + ' + offset + 'px)';
    if (bump || !saved.width) saveGeom(dlg);
  }

  function fitToContent(dlg) {
    if (!dlg || !getFitContent(dlg) || isChromeState(dlg) || dlg._hubFitting) return;
    const title = titlebarEl(dlg);
    const body = bodyEl(dlg);
    const footer = footerEl(dlg);
    const box = maxBox();
    const th = title ? title.offsetHeight : 0;
    const fh = (footer && !footer.hidden) ? footer.offsetHeight : 0;
    dlg._hubFitting = true;
    const prevDlgH = dlg.style.height;
    const prevBody = body
      ? { overflow: body.style.overflow, flex: body.style.flex, height: body.style.height, minHeight: body.style.minHeight }
      : null;
    dlg.style.height = 'auto';
    if (body) {
      body.style.overflow = 'hidden';
      body.style.flex = '0 0 auto';
      body.style.height = 'auto';
      body.style.minHeight = '0';
    }
    const measured = Math.ceil(dlg.getBoundingClientRect().height);
    const bh = body ? Math.ceil(Math.max(body.scrollHeight, body.offsetHeight)) : 0;
    if (body && prevBody) {
      body.style.overflow = prevBody.overflow;
      body.style.flex = prevBody.flex;
      body.style.height = prevBody.height;
      body.style.minHeight = prevBody.minHeight;
    }
    const cs = getComputedStyle(dlg);
    const borderY = (parseFloat(cs.borderTopWidth) || 0) + (parseFloat(cs.borderBottomWidth) || 0);
    let h = Math.max(measured, th + fh + bh + borderY) + 2;
    let w = Math.max(320, dlg.offsetWidth || parseInt(dlg.style.width, 10) || 420);
    h = Math.min(box.h, Math.max(180, h));
    w = Math.min(box.w, w);
    const nextH = h + 'px';
    const nextW = w + 'px';
    const changed = prevDlgH !== nextH || dlg.style.width !== nextW;
    dlg.style.height = nextH;
    dlg.style.width = nextW;
    if (changed) saveGeom(dlg);
    queueMicrotask(() => { dlg._hubFitting = false; });
  }

  function watchFitContent(dlg) {
    if (!dlg || dlg._hubFitWatch) return;
    const body = bodyEl(dlg);
    if (!body || typeof MutationObserver === 'undefined') return;
    let raf = 0;
    const schedule = () => {
      if (!getFitContent(dlg) || isChromeState(dlg) || dlg._hubFitting) return;
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => fitToContent(dlg));
    };
    const mo = new MutationObserver(schedule);
    mo.observe(body, {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: ['hidden', 'class']
    });
    dlg._hubFitWatch = mo;
  }

  function hoistFooter(dlg) {
    const footer = footerEl(dlg);
    const body = bodyEl(dlg);
    if (!footer || !body) return;
    const nodes = Array.from(body.querySelectorAll(
      '[data-hub-dialog-footer]'
    ));
    if (!nodes.length) {
      footer.hidden = footer.childNodes.length === 0;
      return;
    }
    nodes.forEach((el) => footer.appendChild(el));
    footer.hidden = footer.childNodes.length === 0;
  }

  function syncWinButtons(dlg) {
    const I = i18nOf({});
    const minBtn = dlg.querySelector('[data-hub-win="min"]');
    const maxBtn = dlg.querySelector('[data-hub-win="max"]');
    const minimized = dlg.classList.contains('is-minimized');
    const maximized = dlg.classList.contains('is-maximized');
    if (minBtn) {
      const label = minimized ? I.restore : I.minimize;
      minBtn.title = label;
      minBtn.setAttribute('aria-label', label);
    }
    if (maxBtn) {
      const label = maximized ? I.restore : I.maximize;
      maxBtn.title = label;
      maxBtn.setAttribute('aria-label', label);
      maxBtn.textContent = maximized ? '❐' : '▢';
    }
  }

  function restoreStandardGeom(dlg) {
    dlg.classList.remove('is-minimized', 'is-maximized');
    const id = dlg.getAttribute('data-hub-float-id') || '';
    const saved = loadGeom(id) || {};
    applyClampedPosition(dlg, saved.left, saved.top);
    if (saved.left) dlg.style.right = 'auto';
    if (saved.width) dlg.style.width = saved.width;
    if (saved.height) dlg.style.height = saved.height;
    if (saved.fitContent === false) setFitContent(dlg, false);
    syncWinButtons(dlg);
    if (getFitContent(dlg)) fitToContent(dlg);
  }

  function hostEl() {
    return ensureHost().host;
  }

  function findById(id) {
    if (!id) return null;
    return hostEl().querySelector('[data-hub-float][data-hub-float-id="' + CSS.escape(id) + '"]');
  }

  function snapshotDlg(dlg) {
    const body = bodyEl(dlg);
    const footer = footerEl(dlg);
    const title = dlg.querySelector('[data-hub-title]');
    let html = body ? htmlForPersist(htmlWithoutSecrets(body, false)) : '';
    if (footer) {
      Array.from(footer.children).forEach((el) => { html += htmlWithoutSecrets(el, true); });
    }
    return {
      id: dlg.getAttribute('data-hub-float-id') || '',
      title: title ? title.textContent : '',
      html,
      values: Object.assign({}, captureValues(body), captureValues(footer)),
      scrollTop: body ? body.scrollTop : 0,
      left: dlg.style.left,
      top: dlg.style.top,
      width: dlg.style.width,
      height: dlg.style.height,
      minimized: dlg.classList.contains('is-minimized'),
      maximized: dlg.classList.contains('is-maximized'),
      // Opt-in: ausdrücklich gewünscht UND verdrahtet. Ohne Verdrahtung käme der Dialog
      // als Attrappe zurück, deshalb wird er dann gar nicht erst gesichert.
      persist: dlg.dataset.hubPersist === '1'
        && dlg.dataset.hubNoPersist !== '1'
        && typeof binders[dlg.getAttribute('data-hub-float-id') || ''] === 'function'
    };
  }

  /**
   * Seite wird verlassen: sichern, was gesichert werden darf — und für alles andere `onClose`
   * **synchron** rufen, damit ein Modul seinen Zwischenstand noch wegschreiben kann
   * (`navigator.sendBeacon`). Ein zurückgegebenes Versprechen wird **nicht** abgewartet; der
   * Browser hält die Seite dafür nicht an. Der Dialog wird nicht aus dem DOM entfernt — die
   * Seite verschwindet ohnehin — und `onClose` läuft je Dialog nur einmal.
   */
  function leavePage() {
    saveAll();
    hostEl().querySelectorAll('[data-hub-float]:not([hidden])').forEach((dlg) => {
      if (dlg._hubLeaveDone) return;
      if (snapshotDlg(dlg).persist) return;
      dlg._hubLeaveDone = true;
      const onClose = dlg._hubOnClose;
      if (typeof onClose === 'function') {
        try { onClose(); } catch (_) { /* ignore */ }
      }
    });
  }

  function saveAll() {
    const host = hostEl();
    const list = [];
    host.querySelectorAll('[data-hub-float]:not([hidden])').forEach((dlg) => {
      const snap = snapshotDlg(dlg);
      if (snap.persist) list.push(snap);
    });
    try {
      if (list.length) sessionStorage.setItem(CONTENT_KEY, JSON.stringify({ dialogs: list }));
      // Löschen erst, wenn wir wissen, was da war — siehe `storageRestored`.
      else if (storageRestored) sessionStorage.removeItem(CONTENT_KEY);
    } catch (_) {
      try {
        if (list.length) {
          const slim = list.map((d) => Object.assign({}, d, { html: htmlForPersist(d.html) }));
          sessionStorage.setItem(CONTENT_KEY, JSON.stringify({ dialogs: slim }));
        }
      } catch (__) { /* ignore */ }
    }
  }

  function removeDlg(dlg) {
    if (!dlg) return;
    /* Über bind()/decorate() angebundene Dialoge gehören der Seite des Aufrufers: Sie
       stehen fest in deren Aufbau und werden beim Schliessen nur versteckt, nicht aus
       dem Dokument genommen. Entfernt man sie, zeigen alle Verweise darauf — auch der
       Knopf, der sie öffnet — auf einen abgehängten Knoten, und der Dialog lässt sich
       bis zum Neuladen der Seite kein zweites Mal öffnen. Betrifft jeden Weg hinaus:
       X-Knopf, Escape und close().
       Aus der SlideForge-Kopie übernommen (14.10.): Dort war die Sperre gebaut, im Hub nie
       angekommen — wer die Hub-Fassung nachzog, holte den Fehler zurück. */
    const bound = legacyInstances.get(dlg);
    if (bound) {
      dlg.hidden = true;
      dlg.setAttribute('hidden', '');
      dlg.classList.remove('hub-float-dialog--open', 'is-minimized', 'is-maximized');
      saveAll();
      if (typeof bound.onClose === 'function') {
        try { bound.onClose(); } catch (_) { /* ignore */ }
      }
      return;
    }
    if (dlg._hubFitWatch) {
      try { dlg._hubFitWatch.disconnect(); } catch (_) {}
      dlg._hubFitWatch = null;
    }
    const onClose = dlg._hubOnClose;
    dlg.remove();
    saveAll();
    if (typeof onClose === 'function') {
      try { onClose(); } catch (_) { /* ignore */ }
    }
  }

  function bindChrome(dlg) {
    const handle = dlg.querySelector('[data-hub-drag]');
    const closeBtn = dlg.querySelector('[data-hub-win="close"]');
    const minBtn = dlg.querySelector('[data-hub-win="min"]');
    const maxBtn = dlg.querySelector('[data-hub-win="max"]');
    const body = bodyEl(dlg);
    const footer = footerEl(dlg);

    dlg.addEventListener('mousedown', () => focusDlg(dlg));

    handle?.addEventListener('mousedown', (e) => {
      if (e.button !== 0) return;
      if (e.target.closest('button')) return;
      if (dlg.classList.contains('is-maximized')) return;
      focusDlg(dlg);
      dragState = {
        mode: 'drag', dlg,
        sx: e.clientX, sy: e.clientY,
        sl: dlg.offsetLeft, st: dlg.offsetTop
      };
      beginDragUi(getComputedStyle(handle).cursor);
      e.preventDefault();
    });

    dlg.querySelectorAll('[data-hub-resize]').forEach((el) => {
      el.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        if (isChromeState(dlg)) return;
        focusDlg(dlg);
        // Mindestmass aus dem CSS (min-width/min-height), sonst wandert der Dialog beim Ziehen
        // über N/W, sobald das CSS stärker klemmt als die Rechnung.
        const cs = getComputedStyle(dlg);
        dragState = {
          mode: 'resize', dlg,
          edges: el.getAttribute('data-hub-resize') || 'se',
          sx: e.clientX, sy: e.clientY,
          sl: dlg.offsetLeft, st: dlg.offsetTop,
          sw: dlg.offsetWidth, sh: dlg.offsetHeight,
          minW: parseFloat(cs.minWidth) || 320,
          minH: parseFloat(cs.minHeight) || 180
        };
        beginDragUi(getComputedStyle(el).cursor);
        e.preventDefault();
        e.stopPropagation();
      });
    });

    closeBtn?.addEventListener('click', () => removeDlg(dlg));
    minBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      if (dlg.classList.contains('is-minimized')) restoreStandardGeom(dlg);
      else {
        if (!dlg.classList.contains('is-maximized')) saveGeom(dlg);
        dlg.classList.remove('is-maximized');
        dlg.classList.add('is-minimized');
        syncWinButtons(dlg);
      }
      saveAll();
    });
    maxBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      if (dlg.classList.contains('is-maximized')) restoreStandardGeom(dlg);
      else {
        if (!dlg.classList.contains('is-minimized')) saveGeom(dlg);
        dlg.classList.remove('is-minimized');
        dlg.classList.add('is-maximized');
        syncWinButtons(dlg);
      }
      saveAll();
    });
    body?.addEventListener('input', saveAll);
    body?.addEventListener('change', saveAll);
    footer?.addEventListener('input', saveAll);
    footer?.addEventListener('change', saveAll);
    syncWinButtons(dlg);
  }

  function createDlg(state, index) {
    const { host, tpl } = ensureHost();
    const node = tpl.content.firstElementChild.cloneNode(true);
    const id = String(state.id || ('anon_' + Date.now()));
    const titleNode = node.querySelector('[data-hub-title]');
    const body = bodyEl(node);
    const footer = footerEl(node);
    node.setAttribute('data-hub-float-id', id);
    if (titleNode) titleNode.textContent = state.title || 'Dialog';
    if (body) body.innerHTML = state.html || '';
    if (state.persist === false) node.dataset.hubNoPersist = '1';
    if (state.persist === true) node.dataset.hubPersist = '1';
    if (typeof state.onClose === 'function') node._hubOnClose = state.onClose;
    if (state.left || state.top || state.width || state.height) {
      if (state.width) node.style.width = state.width;
      if (state.height) node.style.height = state.height;
      applyClampedPosition(node, state.left, state.top);
      const saved = loadGeom(id) || {};
      if (saved.fitContent === false) setFitContent(node, false);
      else setFitContent(node, saved.fitContent !== false);
    } else {
      applyGeom(node, id, index, {
        width: state.defaultWidth || '',
        height: state.defaultHeight || '',
        geomRev: state.geomRev || 0,
        fitContent: state.fitContent
      });
    }
    node.hidden = false;
    bindChrome(node);
    host.appendChild(node);
    focusDlg(node);
    hoistFooter(node);
    runBinder(id, body);
    applyValues(body, state.values || {});
    applyValues(footer, state.values || {});
    if (body) body.scrollTop = state.scrollTop || 0;
    if (state.maximized) {
      node.classList.add('is-maximized');
      node.classList.remove('is-minimized');
    } else if (state.minimized) {
      node.classList.add('is-minimized');
      node.classList.remove('is-maximized');
    }
    syncWinButtons(node);
    watchFitContent(node);
    if (!isChromeState(node) && getFitContent(node)) {
      requestAnimationFrame(() => fitToContent(node));
    }
    const detail = { id, title: state.title, body, dialog: node };
    document.dispatchEvent(new CustomEvent('hub-dialog-restored', { detail }));
    return node;
  }

  function topFloatDlg() {
    const list = Array.from(hostEl().querySelectorAll('[data-hub-float]:not([hidden])'));
    if (!list.length) return null;
    list.sort((a, b) => (parseInt(b.style.zIndex, 10) || 0) - (parseInt(a.style.zIndex, 10) || 0));
    return list[0];
  }

  /**
   * Bug (OfficeForge-Fund 2026-09-08): Ziehen ohne Begrenzung liess den Dialog
   * komplett hinter fixe Chrome (z. B. die ChurchForge-Leiste) verschwinden —
   * Titlebar (= einziger Ziehgriff, trägt auch Min/Max/Schliessen) dann
   * unerreichbar, nur per Reload behebbar. Hält mindestens MIN_VISIBLE px der
   * Titlebar an jeder Kante im Viewport reachable; oben hart auf 0 geklemmt
   * (Titlebar darf nie über den Viewport-Rand hinaus nach oben wandern).
   */
  const DRAG_MIN_VISIBLE = 60;

  function clampDragPosition(dlg, left, top) {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const w = dlg.offsetWidth || parseFloat(dlg.style.width) || 320;
    const minLeft = DRAG_MIN_VISIBLE - w;
    const maxLeft = Math.max(minLeft, vw - DRAG_MIN_VISIBLE);
    const maxTop = Math.max(0, vh - DRAG_MIN_VISIBLE);
    return {
      left: Math.min(Math.max(left, minLeft), maxLeft),
      top: Math.min(Math.max(top, 0), maxTop)
    };
  }

  const PX_RE = /^-?\d+(\.\d+)?px$/;

  /**
   * Wendet eine gespeicherte left/top-Position an, geklemmt wie beim Ziehen —
   * heilt bereits verwaiste ausserhalb-Positionen (aus der Zeit vor diesem Fix,
   * oder falls je erneut welche entstehen) beim nächsten Öffnen/Wiederherstellen
   * automatisch, statt den Nutzer wieder festzufahren. Nur bei reinen px-Werten
   * (der Fall bei allem, was aus Ziehen stammt); andere Einheiten (vw/calc, z. B.
   * die Erst-Default-Position) unangetastet, um bestehendes Verhalten nicht zu
   * verändern.
   */
  function applyClampedPosition(dlg, left, top) {
    if (left && top && PX_RE.test(left) && PX_RE.test(top)) {
      const clamped = clampDragPosition(dlg, parseFloat(left), parseFloat(top));
      dlg.style.left = clamped.left + 'px';
      dlg.style.top = clamped.top + 'px';
      return;
    }
    if (left) dlg.style.left = left;
    if (top) dlg.style.top = top;
  }

  function beginDragUi(cursor) {
    const root = document.documentElement;
    root.classList.add('hub-float-dragging');
    root.style.cursor = cursor && cursor !== 'auto' ? cursor : '';
  }

  function endDrag() {
    if (!dragState) return;
    if (dragState.mode === 'resize') setFitContent(dragState.dlg, false);
    saveGeom(dragState.dlg);
    saveAll();
    dragState = null;
    const root = document.documentElement;
    root.classList.remove('hub-float-dragging');
    root.style.cursor = '';
  }

  function wireGlobalChrome() {
    if (globalChromeWired) return;
    globalChromeWired = true;

    window.addEventListener('mousemove', (e) => {
      if (!dragState) return;
      // Maustaste ausserhalb des Fensters losgelassen (mouseup nie angekommen) → Ziehen beenden,
      // statt dass der Dialog danach an der Maus klebt.
      if (e.buttons === 0) {
        endDrag();
        return;
      }
      const dlg = dragState.dlg;
      if (dragState.mode === 'drag') {
        const pos = clampDragPosition(
          dlg,
          dragState.sl + e.clientX - dragState.sx,
          dragState.st + e.clientY - dragState.sy
        );
        dlg.style.left = pos.left + 'px';
        dlg.style.top = pos.top + 'px';
        return;
      }
      const edges = dragState.edges || 'se';
      const minW = dragState.minW || 320;
      const minH = dragState.minH || 180;
      // Erste echte Bewegung: fitContent sofort aus, sonst setzt der MutationObserver
      // (watchFitContent) die Höhe mitten im Ziehen zurück.
      if (!dragState.moved) {
        dragState.moved = true;
        setFitContent(dlg, false);
      }
      let left = dragState.sl;
      let top = dragState.st;
      let w = dragState.sw;
      let h = dragState.sh;
      const dx = e.clientX - dragState.sx;
      const dy = e.clientY - dragState.sy;
      if (edges.includes('e')) w = Math.max(minW, dragState.sw + dx);
      if (edges.includes('s')) h = Math.max(minH, dragState.sh + dy);
      if (edges.includes('w')) {
        w = Math.max(minW, dragState.sw - dx);
        left = dragState.sl + (dragState.sw - w);
      }
      if (edges.includes('n')) {
        h = Math.max(minH, dragState.sh - dy);
        top = dragState.st + (dragState.sh - h);
      }
      // Gleiche Kante wie beim Ziehen (s.o.): Titlebar darf beim Resize über den
      // Nordrand nicht über den Viewport-Rand hinaus nach oben wandern.
      // Dabei bleibt die Unterkante stehen, statt nach unten mitzuwandern.
      if (top < 0) {
        if (edges.includes('n')) h = dragState.st + dragState.sh;
        top = 0;
      }
      dlg.style.left = left + 'px';
      dlg.style.top = top + 'px';
      dlg.style.width = w + 'px';
      dlg.style.height = h + 'px';
      dlg.style.right = 'auto';
    });

    window.addEventListener('mouseup', endDrag);

    window.addEventListener('pagehide', leavePage);
    window.addEventListener('beforeunload', leavePage);
    document.addEventListener('click', (e) => {
      const a = e.target.closest?.('a[href]');
      if (!a) return;
      try {
        const url = new URL(a.href, location.href);
        if (url.origin === location.origin && !a.target) saveAll();
      } catch (_) { /* ignore */ }
    }, true);

    document.addEventListener('keydown', (e) => {
      if (document.querySelector('.hub-user-menu__dropdown.is-open, .hub-user-menu .user-menu-dropdown.open, .user-menu-dropdown.open')) {
        return;
      }
      const dlg = topFloatDlg();
      if (!dlg) return;

      if (e.key === 'Escape') {
        for (let i = 0; i < beforeEscapeHooks.length; i++) {
          try {
            if (beforeEscapeHooks[i](dlg, e) === true) {
              e.preventDefault();
              e.stopPropagation();
              return;
            }
          } catch (_) { /* ignore */ }
        }
        e.preventDefault();
        e.stopPropagation();
        removeDlg(dlg);
        return;
      }

      const modEnter = e.key === 'Enter' && (e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey;
      const plainEnter = e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey && !e.altKey;
      if (!modEnter && !plainEnter) return;
      const t = e.target;
      const dlgId = dlg.getAttribute('data-hub-float-id') || '';
      if (!t || !dlg.contains(t)) {
        // OF contact-edit: Enter may save even if focus left the dialog
        if (dlgId !== 'contact-edit') return;
      } else if (!modEnter) {
        if (t.tagName === 'TEXTAREA' || t.isContentEditable) return;
        if (t.tagName === 'BUTTON' && !t.hasAttribute('data-hub-primary') && t.id !== 'cSave') return;
        if (t.closest && t.closest('#cCatBox, #cCatSuggest')) return;
      }
      const primary = dlg.querySelector('[data-hub-primary], #cSave');
      if (!primary || primary.disabled) return;
      e.preventDefault();
      e.stopPropagation();
      primary.click();
    }, true);
  }

  function restorePersisted() {
    ensureHost();
    migrateStorageOnce();
    let raw = null;
    try { raw = sessionStorage.getItem(CONTENT_KEY); } catch (_) {}
    if (!raw) {
      try { raw = sessionStorage.getItem('of_float_dialogs_v2'); } catch (_) {}
    }
    let dialogs = [];
    if (raw) {
      try {
        const parsed = JSON.parse(raw);
        dialogs = Array.isArray(parsed?.dialogs) ? parsed.dialogs : [];
      } catch (_) { /* ignore */ }
    }
    // Opt-in: Nur wiederherstellen, was ausdrücklich gesichert wurde. Ältere Momentaufnahmen
    // (vor 2026-09-23) haben das Merkmal nicht und kommen bewusst nicht zurück — sonst stünde
    // nach der Auslieferung noch einmal eine Attrappe auf dem Bildschirm.
    dialogs.filter((state) => state && state.persist === true).forEach((state, i) => createDlg(state, i));
    // **Vor** dem `saveAll()` setzen: Genau dieser Aufruf soll einen veralteten Stand (ohne
    // `persist`-Merkmal) noch aufräumen dürfen — danach ist der Behälter die Wahrheit.
    storageRestored = true;
    saveAll();
  }

  function ensureHandles(dlg) {
    EDGE.forEach((edge) => {
      if (dlg.querySelector('[data-hub-resize="' + edge + '"]')) return;
      const h = document.createElement('div');
      h.className = 'hub-float-dialog__handle hub-float-dialog__handle--' + edge;
      h.setAttribute('data-hub-resize', edge);
      h.setAttribute('aria-hidden', 'true');
      dlg.appendChild(h);
    });
  }

  /** Legacy: decorate existing DOM node (backdrop/modal migration). */
  function ensureChromeLegacy(dlg, opts) {
    const I = i18nOf(opts);
    dlg.classList.add('hub-float-dialog');
    if (!dlg.getAttribute('role')) dlg.setAttribute('role', 'dialog');

    let title = titlebarEl(dlg);
    if (!title) {
      const legacyHeader = dlg.querySelector('.sf-dialog-header, .ribbon-customize-header, header.modal-header');
      if (legacyHeader) {
        legacyHeader.classList.add('hub-float-dialog__titlebar');
        title = legacyHeader;
      } else {
        title = document.createElement('div');
        title.className = 'hub-float-dialog__titlebar';
        const heading = dlg.querySelector('h2, h3, .sf-dialog-title, .modal-dialog-title');
        if (heading) {
          heading.classList.add('hub-float-dialog__title-text');
          heading.setAttribute('data-hub-title', '');
          title.appendChild(heading);
        } else {
          const strong = document.createElement('strong');
          strong.className = 'hub-float-dialog__title-text';
          strong.setAttribute('data-hub-title', '');
          strong.textContent = opts.title || 'Dialog';
          title.appendChild(strong);
        }
        dlg.insertBefore(title, dlg.firstChild);
      }
    }
    title.setAttribute('data-hub-drag', '');
    title.classList.add('hub-float-dialog__titlebar');

    let winBtns = title.querySelector('.hub-float-dialog__win-btns');
    if (!winBtns) {
      winBtns = document.createElement('div');
      winBtns.className = 'hub-float-dialog__win-btns';
      title.appendChild(winBtns);
    }
    const legacyClose = title.querySelector('.sf-dialog-close, .ribbon-customize-close');
    if (legacyClose) legacyClose.hidden = true;

    function ensureBtn(text, action) {
      let btn = winBtns.querySelector('[data-hub-win="' + action + '"]');
      if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'hub-btn hub-btn--ghost';
        btn.setAttribute('data-hub-win', action);
        winBtns.appendChild(btn);
      }
      btn.textContent = text;
      return btn;
    }
    const minBtn = ensureBtn('▁', 'min');
    const maxBtn = ensureBtn('▢', 'max');
    const closeBtn = ensureBtn('×', 'close');
    minBtn.title = I.minimize;
    maxBtn.title = I.maximize;
    closeBtn.title = I.close;

    let body = bodyEl(dlg);
    if (!body) {
      body = document.createElement('div');
      body.className = 'hub-float-dialog__body hub-scroll';
      body.setAttribute('data-hub-body', '');
      const kids = [...dlg.children].filter((c) =>
        c !== title &&
        !c.classList.contains('hub-float-dialog__footer') &&
        !c.classList.contains('hub-float-dialog__handle')
      );
      kids.forEach((c) => body.appendChild(c));
      title.after(body);
    } else {
      body.classList.add('hub-scroll');
      body.setAttribute('data-hub-body', '');
    }

    let footer = footerEl(dlg);
    if (footer) {
      footer.classList.add('hub-dialog-actions');
      footer.setAttribute('data-hub-footer', '');
    }

    ensureHandles(dlg);
    return { title, body, footer, minBtn, maxBtn, closeBtn, I };
  }

  const HubFloatDialog = {
    /** Return true from hook to consume Escape (e.g. close nested panel only). */
    beforeEscape(fn) {
      if (typeof fn === 'function') beforeEscapeHooks.push(fn);
    },

    register(id, bindFn) {
      if (id && typeof bindFn === 'function') binders[id] = bindFn;
    },

    rebindAll() {
      hostEl().querySelectorAll('[data-hub-float]').forEach((dlg) => {
        hoistFooter(dlg);
        runBinder(dlg.getAttribute('data-hub-float-id') || '', bodyEl(dlg));
      });
    },

    fit(idOrEl) {
      let dlg = null;
      if (typeof idOrEl === 'string') dlg = findById(idOrEl);
      else if (idOrEl && idOrEl.nodeType === 1) dlg = idOrEl.closest('[data-hub-float]') || idOrEl;
      if (!dlg) return;
      // Explizites fit() = wieder an Inhalt anpassen (auch nach manuellem Resize)
      setFitContent(dlg, true);
      fitToContent(dlg);
    },

    open(titleOrEl, html, opts) {
      // Legacy: open(existingElement)
      if (titleOrEl && titleOrEl.nodeType === 1 && (html === undefined || typeof html === 'object') && arguments.length < 3) {
        const el = titleOrEl;
        const legacyOpts = (typeof html === 'object' && html) || {};
        const state = legacyInstances.get(el) || this.bind(el, legacyOpts);
        if (!state) return null;
        el.hidden = false;
        el.removeAttribute('hidden');
        el.classList.add('hub-float-dialog--open');
        el.classList.remove('is-minimized', 'is-maximized');
        if (getFitContent(el)) fitToContent(el);
        focusDlg(el);
        return el;
      }

      const title = titleOrEl;
      let id = '';
      let bind = null;
      let persist = false; // Opt-in seit 2026-09-23, siehe Kopf
      let defaultWidth = '';
      let defaultHeight = '';
      let geomRev = 0;
      let fitContent;
      let onClose = null;
      if (typeof opts === 'function') {
        bind = opts;
      } else if (opts && typeof opts === 'object') {
        id = String(opts.id || '');
        bind = typeof opts.bind === 'function' ? opts.bind : null;
        if (opts.persist === true) persist = true;
        if (opts.persist === false) persist = false;
        if (opts.width) defaultWidth = cssDim(opts.width);
        if (opts.height) defaultHeight = cssDim(opts.height);
        if (opts.geomRev != null) geomRev = Number(opts.geomRev) || 0;
        if (opts.fitContent === false) fitContent = false;
        else if (opts.fitContent === true) fitContent = true;
        if (typeof opts.onClose === 'function') onClose = opts.onClose;
      }
      if (id && bind) binders[id] = bind;
      else if (!id && bind) {
        id = 'anon_' + Date.now();
        binders[id] = bind;
      }
      if (!id) id = 'dlg_' + Date.now();

      const existing = findById(id);
      if (existing) removeDlg(existing);

      const count = hostEl().querySelectorAll('[data-hub-float]').length;
      const dlg = createDlg({
        id,
        title: title || 'Dialog',
        html: html || '',
        values: {},
        persist,
        scrollTop: 0,
        defaultWidth,
        defaultHeight,
        geomRev,
        fitContent,
        onClose
      }, count);
      saveAll();
      return dlg;
    },

    close(idOrEl) {
      if (idOrEl == null || idOrEl === '' || idOrEl === 'all') {
        hostEl().querySelectorAll('[data-hub-float]').forEach((d) => removeDlg(d));
        return;
      }
      if (typeof idOrEl === 'string') {
        const d = findById(idOrEl);
        if (d) removeDlg(d);
        return;
      }
      if (idOrEl && idOrEl.nodeType === 1) {
        const hosted = idOrEl.closest('[data-hub-float]');
        if (hosted) {
          removeDlg(hosted);
          return;
        }
        // Legacy hide-in-place
        const state = legacyInstances.get(idOrEl);
        if (state) {
          idOrEl.hidden = true;
          idOrEl.setAttribute('hidden', '');
          idOrEl.classList.remove('hub-float-dialog--open');
          if (state.onClose) state.onClose();
          return;
        }
        idOrEl.hidden = true;
        idOrEl.setAttribute('hidden', '');
      }
    },

    isOpen(id) {
      if (id) return !!findById(id);
      return hostEl().querySelectorAll('[data-hub-float]:not([hidden])').length > 0;
    },

    body(id) {
      const d = id ? findById(id) : hostEl().querySelector('[data-hub-float]:not([hidden])');
      return d ? bodyEl(d) : null;
    },

    list() {
      return Array.from(hostEl().querySelectorAll('[data-hub-float]')).map((d) => d.getAttribute('data-hub-float-id'));
    },

    decorate(el, opts) {
      return this.bind(el, opts);
    },

    bind(el, opts) {
      opts = opts || {};
      if (!el) return null;
      let state = legacyInstances.get(el);
      if (state) return state;
      ensureHost();
      const chrome = ensureChromeLegacy(el, opts);
      const id = String(opts.id || el.id || ('dlg_' + Date.now()));
      el.setAttribute('data-hub-float-id', id);
      el.setAttribute('data-hub-float', '');
      const defaultWidth = Number(opts.width) || 920;
      const defaultHeight = Number(opts.height) || 680;
      applyGeom(el, id, 0, {
        width: defaultWidth + 'px',
        height: defaultHeight + 'px',
        geomRev: Number(opts.geomRev) || 0,
        fitContent: opts.fitContent
      });
      if (opts.persist === false) el.dataset.hubNoPersist = '1';
      if (opts.persist === true) el.dataset.hubPersist = '1';
      bindChrome(el);
      watchFitContent(el);
      state = {
        el,
        id,
        onClose: typeof opts.onClose === 'function' ? opts.onClose : null,
        minBtn: chrome.minBtn,
        maxBtn: chrome.maxBtn,
        closeBtn: chrome.closeBtn
      };
      if (state.onClose) el._hubOnClose = state.onClose;
      legacyInstances.set(el, state);
      syncWinButtons(el);
      return state;
    },

    upgradeBackdrop(backdrop, opts) {
      if (!backdrop) return null;
      const modal = backdrop.querySelector('.modal, .ribbon-customize-panel, [role="dialog"]') || backdrop.firstElementChild;
      if (!modal) return null;
      opts = opts || {};
      const id = opts.id || modal.id || backdrop.id || ('float_' + Date.now());
      const state = this.bind(modal, Object.assign({ id }, opts));
      modal._hubFloatBackdrop = backdrop;
      const origOnClose = state.onClose;
      state.onClose = () => {
        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
        if (origOnClose) origOnClose();
        if (typeof opts.onClose === 'function') opts.onClose();
      };
      modal._hubOnClose = state.onClose;
      const openFloat = () => {
        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
        backdrop.style.background = 'transparent';
        backdrop.style.pointerEvents = 'none';
        modal.style.pointerEvents = 'auto';
        this.open(modal);
      };
      const closeFloat = () => this.close(modal);
      backdrop._hubFloatOpen = openFloat;
      backdrop._hubFloatClose = closeFloat;
      modal._hubFloatOpen = openFloat;
      modal._hubFloatClose = closeFloat;
      return { backdrop, modal, open: openFloat, close: closeFloat, state };
    }
  };

  global.HubFloatDialog = HubFloatDialog;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', restorePersisted, { once: true });
  } else {
    queueMicrotask(restorePersisted);
  }
  window.addEventListener('load', () => {
    if (!hostReady) return;
    hostEl().querySelectorAll('[data-hub-float]').forEach((dlg) => {
      const id = dlg.getAttribute('data-hub-float-id') || '';
      hoistFooter(dlg);
      runBinder(id, bodyEl(dlg));
      if (!isChromeState(dlg) && getFitContent(dlg)) fitToContent(dlg);
    });
    saveAll();
  });
})(typeof window !== 'undefined' ? window : globalThis);
