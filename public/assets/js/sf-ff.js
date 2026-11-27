/**
 * FolienSchmiede als Editor für FileForge-Dateien (27.11.2026).
 *   SfFf.editor({ id, csrf, status })   Speichern im Editor: Menüband, Ctrl+S, alle 30 s, beim Schliessen; 409 → «Als Kopie speichern»
 *   SfFf.present({ id, csrf })          Presenter: Gruppe «Geräte» (Registrieren per QR/Code, Geräte inkl. «Web» wählen und senden)
 *   SfFf.home({ csrf })                 Startseite: Zuletzt geöffnet, Für mich freigegeben, Neu / Öffnen / Übernehmen, Vorlagen-Ordner
 *   [data-sf-ff-field]                  Dateifeld in Formularen (FfFile::fieldHtml), bindet sich selbst
 * Dateiwahl: HubFiles (FileForge-Dialog vom Hub). Das Recht prüft immer FileForge als die angemeldete Person.
 */
(function (global) {
  'use strict';

  var API = 'ff_api.php';
  var csrf = '';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  // Statusleiste unten (links Aktionen, Mitte Datei, rechts Version; includes/statusbar.php). Fehler zusätzlich als Hub-Meldung oben.
  function say(text, kind) {
    var el = document.getElementById('sfStatusMsg');
    if (!el) return false;
    var d = new Date(), hm = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    el.textContent = text ? (kind === 'busy' ? text : hm + ' · ' + text) : '';
    el.setAttribute('data-state', kind || '');
    return true;
  }
  function fileName(name) { var el = document.getElementById('sfStatusFile'); if (el) el.textContent = name || ''; }
  function toast(kind, text) {
    var inBar = say(text, kind === 'error' ? 'error' : '');
    if (kind === 'error' || !inBar) {
      if (global.CfStatus && global.CfStatus.show) { global.CfStatus.show(kind, text); }
    }
  }
  function post(data) {
    return fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(Object.assign({ csrf_token: csrf }, data)) })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .catch(function () { return { ok: false, message: 'Keine Verbindung.' }; });
  }
  // ------------------------------------------------- Anzeige-Geräte (27.11.2026; Gruppe «Geräte» im Presenter 3.4.0)
  // Wer ein Gerät koppeln oder bespielen darf, entscheidet der Hub (api/anzeige.php); hier nur zeigen, was er liefert.
  var DEV = 'geraet_api.php';
  function devSend(data) {
    return fetch(DEV, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(Object.assign({ csrf_token: csrf }, data)) })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .catch(function () { return { ok: false, message: 'Keine Verbindung.' }; });
  }
  // Meldung im Dialog als Hub-Hinweiskasten (hub-status, Lab › Status: lab/status.php:136–150); «busy» als leise Zeile.
  function devMsg(el, text, kind) {
    if (!el) return;
    if (!text) { el.hidden = true; el.className = 'sf-dev__msg'; el.innerHTML = ''; return; }
    el.hidden = false;
    if (kind === 'busy') {
      el.className = 'sf-dev__msg sf-dev__msg--busy';
      el.textContent = text;
      return;
    }
    var k = kind === 'error' ? 'error' : (kind === 'ok' ? 'ok' : 'info');
    el.className = 'sf-dev__msg hub-status hub-status--' + k;
    el.setAttribute('role', k === 'error' ? 'alert' : 'status');
    el.innerHTML = '<span class="hub-status__icon" aria-hidden="true">' + (k === 'error' ? '!' : (k === 'ok' ? '✓' : 'i')) + '</span>' +
      '<div class="hub-status__body"><span>' + esc(text) + '</span></div>';
  }
  /** Gerät-Icon aus dem Hub-Sprite (`hub-icons.svg`, Hub ≥ 2.33.10 liefert `symbol`); unbekannt → Bildschirm. */
  function devIcon(symbol) {
    var s = /^[a-z-]{1,24}$/.test(String(symbol || '')) ? String(symbol) : 'monitor';
    return '<svg class="sf-dev__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="' + esc(location.origin + '/assets/icons/hub-icons.svg#hub-i-' + s) + '"></use></svg>';
  }
  /** Zuschauer-Ansicht als Adresse («Web»): dieselbe Seite wie «Präsentieren» im eigenen Fenster; `mit`: folgt dem Editor (3.8.0). */
  function webUrl(id, mit) {
    return new URL('present_audience.php?id=' + encodeURIComponent(id) + (mit ? '&mit=1' : ''), location.href).href;
  }
  /**
   * Dialog «Geräte»: «Web» (Zuschauer-Adresse kopieren/öffnen) und die Geräte des Hubs; mehrere wählen → «Senden»,
   * je Gerät «Leeren». Aus dem Editor («Auf Gerät zeigen»), aus dem Presenter (Gruppe «Geräte») und mit `opts.mit` aus Ansicht ›
   * Vorschau «Auf Gerät» (3.8.0, Mitlaufen): das Gerät zeigt laufend die Folie, die im Editor bearbeitet wird; «Web» öffnet dieselbe
   * Mitlauf-Ansicht in einem eigenen Fenster dieses Browsers.
   * Aufbau wie Lab › Dialoge (lab/dialogs.php:401–421): Inhalt, Fusszeile `hub-dialog-actions` mit data-hub-dialog-footer.
   */
  function devices(id, opts) {
    opts = opts || {};
    if (!global.HubFloatDialog) { toast('error', 'Dialog nicht verfügbar.'); return; }
    var mit = !!opts.mit;
    var web = webUrl(id, mit);
    var html = '<div class="sf-dev" aria-busy="true">' +
      (mit ? '<p class="muted small">Das Gerät zeigt laufend die Folie, die du bearbeitest, und jede gespeicherte Änderung. ' +
        'Leeren oder eine Präsentation senden beendet das Mitlaufen.</p>' : '') +
      '<div class="sf-dev__list-wrap"><p class="muted small" role="status">Geräte werden geladen …</p></div>' +
      '<div class="sf-dev__msg" hidden></div></div>' +
      '<div class="hub-dialog-actions" data-hub-dialog-footer>' +
      (opts.onRegister ? '<button type="button" class="hub-btn hub-btn--ghost hub-dialog-actions__start sf-dev__register">Registrieren …</button>' : '') +
      '<button type="button" class="hub-btn hub-btn--ghost sf-dev__cancel">Schliessen</button>' +
      '<button type="button" class="hub-btn hub-btn--primary sf-dev__send" data-hub-primary disabled>Senden</button></div>';
    global.HubFloatDialog.open(opts.title || 'Geräte', html, { id: 'sfDevices', width: 480, bind: function (body, dlg) {
      var host = dlg || body;
      var root = body.querySelector('.sf-dev'), wrap = root.querySelector('.sf-dev__list-wrap'), msg = root.querySelector('.sf-dev__msg');
      var list = [];
      function sendBtn() { return host.querySelector('.sf-dev__send'); }
      function sync() { var s = sendBtn(); if (s) s.disabled = !wrap.querySelector('.sf-dev__pick[aria-pressed="true"]'); }
      function render(j) {
        root.removeAttribute('aria-busy');
        if (!j || !j.ok) {
          wrap.innerHTML = '<button type="button" class="hub-btn hub-btn--sm sf-dev__reload">Nochmals laden</button>';
          devMsg(msg, (j && j.message) || 'Die Geräte liessen sich nicht laden.', 'error');
          sync();
          return;
        }
        list = j.geraete || [];
        // Raster aus Kacheln (Urs 27.11.: «pro Gerät ein Icon … ein Grid mit den Icons»): Icon gross, Name, «zeigt …» klein.
        // Kachel antippen = wählen (mehrfach, aria-pressed). «Web» ist auch eine Kachel: Zuschauer-Adresse kopieren/öffnen.
        var tiles = '<li class="sf-dev__tile sf-dev__tile--web"><a class="sf-dev__pick" href="' + esc(web) + '" target="_blank" rel="noopener">' +
          devIcon('globe') + '<span class="sf-dev__name">Web</span><span class="sf-dev__state">' + (mit ? 'Mitlaufen im eigenen Fenster' : 'Zuschauer-Ansicht öffnen') + '</span></a>' +
          '<button type="button" class="hub-btn hub-btn--sm hub-btn--ghost sf-dev__copy">Adresse kopieren</button></li>';
        tiles += list.map(function (g, i) {
          var zeigt = g.zeigt ? 'zeigt ' + (g.zeigt.titel || g.zeigt.app) : 'zeigt nichts';
          return '<li class="sf-dev__tile"><button type="button" class="sf-dev__pick" aria-pressed="false" data-i="' + i + '">' +
            devIcon(g.symbol) + '<span class="sf-dev__name">' + esc(g.name) + '</span><span class="sf-dev__state">' + esc(zeigt) + '</span></button>' +
            '<div class="sf-dev__tile-act">' +
            (g.zeigt ? '<button type="button" class="hub-btn hub-btn--sm hub-btn--ghost sf-dev__clear" data-i="' + i + '" aria-label="' + esc(g.name) + ' leeren">Leeren</button>' : '') +
            '<button type="button" class="hub-btn hub-btn--sm hub-btn--ghost sf-dev__reloaddev" data-i="' + i + '" aria-label="' + esc(g.name) + ' neu laden">Neu laden</button></div></li>';
        }).join('');
        wrap.innerHTML = '<ul class="sf-dev__grid" aria-label="Geräte">' + tiles + '</ul>' +
          (list.length ? '' : '<p class="muted small">Noch kein Gerät registriert' + (opts.onRegister ? ' – mit «Registrieren …» eines hinzufügen.' : '.') + '</p>');
        sync();
      }
      function load() {
        root.setAttribute('aria-busy', 'true');
        fetch(DEV, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).catch(function () { return { ok: false }; }).then(render);
      }
      host.addEventListener('click', function (e) {
        if (e.target.closest('.sf-dev__cancel')) { global.HubFloatDialog.close('sfDevices'); return; }
        if (e.target.closest('.sf-dev__reload')) { devMsg(msg, ''); load(); return; }
        if (e.target.closest('.sf-dev__register')) { global.HubFloatDialog.close('sfDevices'); opts.onRegister(); return; }
        if (e.target.closest('.sf-dev__copy')) {
          var done = function (ok) { devMsg(msg, ok ? 'Adresse kopiert.' : 'Kopieren ging nicht – Link mit der rechten Maustaste kopieren.', ok ? 'ok' : 'error'); };
          if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(web).then(function () { done(true); }, function () { done(false); });
          else done(false);
          return;
        }
        if (mit && e.target.closest('a.sf-dev__pick')) {
          // «Web» im Modus Mitlaufen: eigenes Fenster dieses Browsers (ersetzt das frühere «Fenster»), Grösse nach Folienformat.
          e.preventDefault();
          var w = Math.min(1280, Math.round(screen.availWidth * 0.6)), h = Math.round(w * 9 / 16) + 40;
          var win = global.open(web, 'sf_mit_' + id, 'popup=1,width=' + w + ',height=' + h);
          if (win) { win.focus(); global.HubFloatDialog.close('sfDevices'); } else devMsg(msg, 'Das Fenster wurde blockiert – Pop-ups für diese Seite erlauben.', 'error');
          return;
        }
        var pk = e.target.closest('button.sf-dev__pick');
        if (pk) { pk.setAttribute('aria-pressed', pk.getAttribute('aria-pressed') === 'true' ? 'false' : 'true'); sync(); return; }
        var nl = e.target.closest('.sf-dev__reloaddev');
        if (nl) {
          var gn = list[+nl.getAttribute('data-i')];
          if (!gn) return;
          nl.disabled = true;
          devMsg(msg, 'Lädt ' + gn.name + ' neu …', 'busy');
          devSend({ action: 'neuladen', geraet: gn.id }).then(function (r) {
            nl.disabled = false;
            devMsg(msg, r.ok ? gn.name + ' lädt in wenigen Sekunden neu.' : (r.message || 'Das ging nicht.'), r.ok ? 'ok' : 'error');
          });
          return;
        }
        var c = e.target.closest('.sf-dev__clear');
        if (c) {
          var g = list[+c.getAttribute('data-i')];
          if (!g) return;
          c.disabled = true;
          devMsg(msg, 'Leert ' + g.name + ' …', 'busy');
          devSend({ action: 'leeren', geraet: g.id }).then(function (r) {
            c.disabled = false;
            if (r.ok) { devMsg(msg, g.name + ' geleert.', 'ok'); load(); } else devMsg(msg, r.message || 'Das ging nicht.', 'error');
          });
          return;
        }
        var s = e.target.closest('.sf-dev__send');
        if (!s || s.disabled) return;
        var picked = Array.prototype.map.call(wrap.querySelectorAll('.sf-dev__pick[aria-pressed="true"]'), function (cb) { return list[+cb.getAttribute('data-i')]; })
          .filter(Boolean);
        if (!picked.length) return;
        s.disabled = true;
        devMsg(msg, 'Schickt an ' + picked.map(function (g) { return g.name; }).join(', ') + ' …', 'busy');
        Promise.all(picked.map(function (g) {
          return devSend({ action: 'zeigen', geraet: g.id, id: id, mit: mit ? 1 : 0 }).then(function (r) { return { g: g, r: r }; });
        })).then(function (res) {
          var bad = res.filter(function (x) { return !x.r.ok; });
          if (!bad.length) {
            toast('success', (mit ? 'Läuft mit auf ' : 'Läuft auf ') + picked.map(function (g) { return g.name; }).join(', ') + '.');
            global.HubFloatDialog.close('sfDevices');
            return;
          }
          devMsg(msg, bad.map(function (x) { return x.g.name + ': ' + (x.r.message || 'Das ging nicht.'); }).join(' · '), 'error');
          load();
        });
      });
      load();
    } });
  }

  // QR-Leser: BarcodeDetector des Browsers, sonst jsQR (lokal, Apache-2.0; erst bei Bedarf geladen, kein CDN).
  var JSQR = 'assets/js/jsqr-1.4.0.js';
  function qrReader() {
    if (global.BarcodeDetector) {
      return Promise.resolve(global.BarcodeDetector.getSupportedFormats ? global.BarcodeDetector.getSupportedFormats() : ['qr_code']).then(function (f) {
        if (f.indexOf('qr_code') < 0) throw new Error('no_qr');
        var det = new global.BarcodeDetector({ formats: ['qr_code'] });
        return function (video) { return det.detect(video).then(function (r) { return r && r[0] ? String(r[0].rawValue || '') : ''; }); };
      }).catch(jsqrReader);
    }
    return jsqrReader();
  }
  function jsqrReader() {
    var ready = global.jsQR ? Promise.resolve() : new Promise(function (resolve, reject) {
      var sc = document.createElement('script');
      sc.src = JSQR;
      sc.onload = function () { global.jsQR ? resolve() : reject(new Error('no_qr')); };
      sc.onerror = function () { reject(new Error('no_qr')); };
      document.head.appendChild(sc);
    });
    var canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
    return ready.then(function () {
      return function (video) {
        var w = video.videoWidth, h = video.videoHeight;
        if (!w || !h) return Promise.resolve('');
        var scale = Math.min(1, 640 / Math.max(w, h));
        canvas.width = Math.round(w * scale); canvas.height = Math.round(h * scale);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var r = global.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
        return Promise.resolve(r ? String(r.data || '') : '');
      };
    });
  }
  /** Code aus dem QR von device.php (`…/admin/settings.php?tab=geraete&code=123456`) oder sechs Ziffern. */
  function codeFromQr(text) {
    var t = String(text || '').trim();
    try {
      var u = new URL(t);
      var c = (u.searchParams.get('code') || '').replace(/\D/g, '');
      if (c.length === 6) return c;
    } catch (_) { /* kein URL */ }
    t = t.replace(/\D/g, '');
    return t.length === 6 ? t : '';
  }
  /** Code-Feld: nur Ziffern, höchstens sechs, angezeigt als «123 456». */
  function fmtCode(v) {
    var d = String(v || '').replace(/\D/g, '').slice(0, 6);
    return d.length > 3 ? d.slice(0, 3) + ' ' + d.slice(3) : d;
  }
  /**
   * Dialog «Gerät registrieren»: Kamera scannt den QR von device.php (oder Code tippen), Name und Dauer wählen, koppeln.
   * Danach steht das Gerät im Dialog «Geräte» (opts.after). Aufbau wie Lab › Dialoge (lab/dialogs.php:401–421):
   * Felder untereinander (lab-fields-stack), Fusszeile Abbrechen + Registrieren.
   */
  function register(opts) {
    opts = opts || {};
    if (!global.HubFloatDialog) { toast('error', 'Dialog nicht verfügbar.'); return; }
    var stream = null, timer = 0, stopped = false;
    function stop() {
      stopped = true;
      if (timer) { clearTimeout(timer); timer = 0; }
      if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    }
    var dev = location.origin + '/device.php';
    var html = '<form class="sf-dev sf-dev--reg" id="sfDevRegForm" novalidate>' +
      '<div class="sf-dev__cam"><video class="sf-dev__video" muted playsinline aria-label="Kamerabild für den QR-Code"></video></div>' +
      '<div class="sf-dev__msg" hidden></div>' +
      '<p class="sf-dev__lead small">Auf dem Gerät <a class="sf-dev__link" href="' + esc(dev) + '" target="_blank" rel="noopener">' + esc(location.host) + '/device.php</a> öffnen, dann den QR-Code vor die Kamera halten oder den Code eintippen.</p>' +
      '<div class="sf-dev__stack">' +
      '<label class="hub-admin-field sf-dev__code"><span>Code vom Gerät</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123 456" aria-describedby="sfDevCodeHint" required>' +
      '<small class="hub-admin-field-hint" id="sfDevCodeHint">6 Ziffern, steht unter dem QR-Code.</small></label>' +
      '<label class="hub-admin-field"><span>Name</span><input type="text" name="name" maxlength="60" autocomplete="off" placeholder="z. B. Beamer Saal"></label>' +
      '<label class="hub-admin-field"><span>Freigegeben</span><select name="dauer"><option value="heute">für heute (bis Mitternacht)</option><option value="dauerhaft">dauerhaft</option></select></label>' +
      '</div></form>' +
      '<div class="hub-dialog-actions" data-hub-dialog-footer>' +
      '<button type="button" class="hub-btn hub-btn--ghost sf-dev__cancel">Abbrechen</button>' +
      '<button type="submit" form="sfDevRegForm" class="hub-btn hub-btn--primary" data-hub-primary>Registrieren</button></div>';
    global.HubFloatDialog.open('Gerät registrieren', html, { id: 'sfDeviceRegister', width: 420, onClose: stop, bind: function (body, dlg) {
      var host = dlg || body;
      var form = body.querySelector('form'), video = form.querySelector('video'), msg = form.querySelector('.sf-dev__msg');
      var cam = form.querySelector('.sf-dev__cam');
      var codeIn = form.elements.code, nameIn = form.elements.name, btn = host.querySelector('button[form="sfDevRegForm"]');
      var cancel = host.querySelector('.sf-dev__cancel');
      if (cancel) cancel.addEventListener('click', function () { stop(); global.HubFloatDialog.close('sfDeviceRegister'); });
      codeIn.addEventListener('input', function () {
        var f = fmtCode(codeIn.value);
        if (f !== codeIn.value) codeIn.value = f;
        codeIn.removeAttribute('aria-invalid');
        codeIn.closest('.hub-admin-field').classList.remove('is-error');
      });
      function noCam(text) { stop(); cam.hidden = true; devMsg(msg, text, 'info'); codeIn.focus(); }
      function found(code) {
        stop();
        codeIn.value = fmtCode(code);
        cam.hidden = true;
        devMsg(msg, 'Code erkannt. Name wählen und registrieren.', 'ok');
        nameIn.focus();
      }
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !global.isSecureContext) {
        noCam('Keine Kamera verfügbar – bitte den Code eintippen.');
      } else {
        devMsg(msg, 'Kamera wird gestartet …', 'busy');
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (s) {
          if (stopped) { s.getTracks().forEach(function (t) { t.stop(); }); return; }
          stream = s;
          video.srcObject = s;
          return video.play().then(qrReader).then(function (read) {
            devMsg(msg, 'QR-Code vor die Kamera halten …', 'busy');
            (function tick() {
              if (stopped) return;
              read(video).then(function (text) {
                var code = codeFromQr(text);
                if (code) found(code); else if (!stopped) timer = setTimeout(tick, 250);
              }, function () { if (!stopped) timer = setTimeout(tick, 500); });
            })();
          }, function () { noCam('QR-Leser nicht verfügbar – bitte den Code eintippen.'); });
        }, function (err) {
          noCam(err && err.name === 'NotAllowedError' ? 'Kamera nicht erlaubt – bitte den Code eintippen.' : 'Keine Kamera gefunden – bitte den Code eintippen.');
        });
      }
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var code = String(codeIn.value).replace(/\D/g, '');
        if (code.length !== 6) {
          codeIn.setAttribute('aria-invalid', 'true');
          codeIn.closest('.hub-admin-field').classList.add('is-error');
          devMsg(msg, 'Der Code hat sechs Ziffern.', 'error');
          codeIn.focus();
          return;
        }
        if (btn) btn.disabled = true;
        devMsg(msg, 'Registriert …', 'busy');
        devSend({ action: 'koppeln', code: code, name: String(nameIn.value).trim(), dauer: form.elements.dauer.value }).then(function (r) {
          if (btn) btn.disabled = false;
          if (!r.ok) {
            if (r.error === 'code') { codeIn.setAttribute('aria-invalid', 'true'); codeIn.closest('.hub-admin-field').classList.add('is-error'); }
            devMsg(msg, r.message || 'Das ging nicht.', 'error');
            return;
          }
          stop();
          global.HubFloatDialog.close('sfDeviceRegister');
          toast('success', 'Gerät ' + ((r.geraet && r.geraet.name) || '') + ' registriert.');
          if (opts.after) opts.after();
        });
      });
    } });
  }
  /** Presenter (present.php): Gruppe «Geräte» im Menüband – Registrieren und Geräte. */
  function present(cfg) {
    csrf = cfg.csrf;
    var openDevices = function () { devices(cfg.id, { onRegister: openRegister }); };
    var openRegister = function () { register({ after: openDevices }); };
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ribbon-command="device_register"], [data-ribbon-command="device_pick"]');
      if (!b || b.disabled) return;
      e.preventDefault();
      b.getAttribute('data-ribbon-command') === 'device_register' ? openRegister() : openDevices();
    }, true);
  }
  function loadHubFiles() {
    if (global.HubFiles) return Promise.resolve();
    return new Promise(function (resolve, reject) {
      var sc = document.createElement('script');
      sc.src = location.origin + '/assets/js/hub-files.js';
      sc.onload = function () { global.HubFiles ? resolve() : reject(new Error('not_available')); };
      sc.onerror = function () { reject(new Error('not_available')); };
      document.head.appendChild(sc);
    });
  }
  function pickError(e) {
    var code = String((e && (e.code || e.message)) || '');
    toast('error', code === 'busy' ? 'Der Dateidialog ist schon offen.' : 'Der Dateidialog von FileForge ist nicht erreichbar.');
  }
  /** FileForge-Dialog: Datei oder Ordner wählen → {ref, name, label} oder null */
  function choose(kinds, accept, title) {
    return loadHubFiles().then(function () {
      return global.HubFiles.choose({ app: 'slideforge', kinds: kinds, accept: accept || [], title: title });
    });
  }
  // Eingaben und Rückfragen über die Hub-Bausteine (HubConfirm, Lab › Dialoge), nie die nackten Browser-Dialoge (Liste UI 3).
  function ask(label, value) {
    if (global.HubConfirm && global.HubConfirm.prompt) return global.HubConfirm.prompt({ title: label, value: value || '' });
    if (global.SFDialog && global.SFDialog.prompt) return global.SFDialog.prompt(label, value || '');
    toast('error', 'Eingabedialog fehlt.');
    return Promise.resolve(null);
  }
  function confirmAsk(title, message, okLabel) {
    if (global.HubConfirm && global.HubConfirm.ask) return global.HubConfirm.ask({ title: title, message: message, okLabel: okLabel });
    if (global.SFDialog && global.SFDialog.confirm) return global.SFDialog.confirm(message);
    toast('error', 'Bestätigungsdialog fehlt.');
    return Promise.resolve(false);
  }
  function stem(name) { return String(name || '').replace(/\.(cfs|pptx?|odp)$/i, ''); }

  // ---------------------------------------------------------------- Editor
  function editor(cfg) {
    csrf = cfg.csrf;
    var st = cfg.status || {};
    var busy = false, again = false, blocked = '';
    function show(text, kind) {
      say(text, kind);
      fileName(st.name);
    }
    function idle() {
      if (st.source === 'local') { show('Noch nicht in FileForge · Speichern unter …', 'dirty'); return; }
      if (blocked) { show('Nicht gespeichert – Kopie nötig', 'error'); return; }
      if (!st.canWrite && st.source !== 'import') { show('Nur lesen', ''); return; }
      show(st.dirty ? 'Ungespeichert' : 'Gespeichert', st.dirty ? 'dirty' : 'ok');
    }
    idle();

    function save(manual) {
      if (busy) { again = again || manual; return Promise.resolve(); }
      if (st.source === 'local' || (!st.canWrite && st.source !== 'import')) { if (manual) saveCopy(); return Promise.resolve(); }
      if (blocked && !manual) return Promise.resolve();
      busy = true;
      show('Speichert …', 'busy');
      var flush = global.SFEditor && global.SFEditor.flush ? global.SFEditor.flush() : Promise.resolve();
      return Promise.resolve(flush).then(function () { return post({ action: 'save', id: cfg.id }); }).then(function (j) {
        busy = false;
        if (j.ok) {
          blocked = '';
          if (j.status) st = j.status;
          idle();
          if (manual && j.saved) toast('success', 'Gespeichert.');
        } else if (j.error === 'changed' || j.error === 'read_only' || j.error === 'need_copy') {
          blocked = j.error;
          idle();
          if (manual || j.error !== 'need_copy') {
            confirmAsk('Nicht gespeichert', (j.message || '') + ' Jetzt als Kopie speichern?', 'Als Kopie speichern').then(function (yes) { if (yes) saveCopy(); });
          }
        } else {
          idle();
          if (manual) toast('error', j.message || 'Nicht gespeichert.');
        }
        if (again) { again = false; save(true); }
      });
    }
    function saveCopy() {
      var dir;
      choose(['dir'], [], st.source === 'local' ? 'Speicherort in FileForge wählen' : 'Ordner für die Kopie wählen').then(function (r) {
        if (!r || !r.ref) return null;
        dir = r.ref;
        return ask(st.source === 'local' ? 'Name der Datei' : 'Name der Kopie', stem(st.name) + (st.source === 'local' ? '' : ' (Kopie)'));
      }).then(function (name) {
        if (!dir || name == null || String(name).trim() === '') return;
        show('Speichert …', 'busy');
        var flush = global.SFEditor && global.SFEditor.flush ? global.SFEditor.flush() : Promise.resolve();
        return Promise.resolve(flush).then(function () { return post({ action: 'save_copy', id: cfg.id, dir: dir, name: String(name).trim() }); }).then(function (j) {
          if (j.ok) { blocked = ''; if (j.status) st = j.status; idle(); toast('success', 'Gespeichert als ' + j.name + '.'); } else { idle(); toast('error', j.message || 'Nicht gespeichert.'); }
        });
      }).catch(pickError);
    }
    function saveTemplate() {
      ask('Name der Vorlage', stem(st.name)).then(function (name) {
        if (name == null || String(name).trim() === '') return;
        var flush = global.SFEditor && global.SFEditor.flush ? global.SFEditor.flush() : Promise.resolve();
        return Promise.resolve(flush).then(function () { return post({ action: 'save_template', id: cfg.id, name: String(name).trim() }); }).then(function (j) {
          j.ok ? toast('success', 'Vorlage ' + j.name + ' abgelegt.') : toast('error', j.message || 'Nicht abgelegt.');
        });
      });
    }

    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ribbon-command="ff_save"], [data-ribbon-command="ff_save_copy"], [data-ribbon-command="ff_save_template"]');
      if (!b || b.disabled) return;
      e.preventDefault();
      var c = b.getAttribute('data-ribbon-command');
      if (c === 'ff_save') save(true); else if (c === 'ff_save_copy') saveCopy(); else saveTemplate();
    }, true);
    // Auf Gerät zeigen (27.11.2026): Anzeige-Geräte des Hubs; das Gerät folgt dem Presenter/Tablet (Zuschauer-Ansicht).
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ribbon-command="show_on_device"]');
      if (!b || b.disabled) return;
      e.preventDefault();
      var openDevices = function () { devices(cfg.id, { title: 'Auf Gerät zeigen', onRegister: function () { register({ after: openDevices }); } }); };
      openDevices();
    }, true);
    // Ansicht › Vorschau › «Auf Gerät» (3.8.0, Mitlaufen): dasselbe Raster, das Gerät folgt dem Editor.
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ribbon-command="preview_device"]');
      if (!b || b.disabled || b.getAttribute('aria-disabled') === 'true') return;
      e.preventDefault();
      var openFollow = function () { devices(cfg.id, { title: 'Auf Gerät (Mitlaufen)', mit: true, onRegister: function () { register({ after: openFollow }); } }); };
      openFollow();
    }, true);
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && !e.altKey && String(e.key).toLowerCase() === 's') {
        e.preventDefault();
        e.stopPropagation();
        save(true);
      }
    }, true);
    // alle 30 s (der Server speichert nur, wenn sich etwas geändert hat)
    setInterval(function () { if (!document.hidden && st.source !== 'import') save(false); }, 30000);
    // beim Schliessen oder Wegwechseln
    function beacon() {
      if (blocked || (!st.canWrite) || st.source === 'import' || !navigator.sendBeacon) return;
      try { navigator.sendBeacon(API, new Blob([JSON.stringify({ action: 'save', id: cfg.id, csrf_token: csrf })], { type: 'application/json' })); } catch (e) { /* still */ }
    }
    document.addEventListener('visibilitychange', function () { if (document.hidden) { Promise.resolve(global.SFEditor && global.SFEditor.flush && global.SFEditor.flush()).then(beacon); } });
    global.addEventListener('pagehide', beacon);
  }

  // ---------------------------------------------------------------- Startseite
  function dateShort(ts) {
    var d = new Date((Number(ts) || 0) * 1000);
    if (!ts || isNaN(d)) return '';
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return p(d.getDate()) + '.' + p(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
  }
  function isoShort(iso) {
    var m = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/.exec(String(iso || ''));
    return m ? m[3] + '.' + m[2] + '.' + m[1] + (m[4] ? ' ' + m[4] + ':' + m[5] : '') : '';
  }
  function home(cfg) {
    csrf = cfg.csrf;
    var recentEl = document.getElementById('sfRecent');
    var formeEl = document.getElementById('sfForMe');
    var tplEl = document.getElementById('sfTemplateDir');
    var state = { templateDir: null };

    function openTarget(ref, share) {
      if (global.HubPageBusy && global.HubPageBusy.show) global.HubPageBusy.show({ title: 'Öffnet …' });
      post({ action: 'open', ref: ref || '', share: share || '' }).then(function (j) {
        if (j.ok) { location.href = 'editor.php?id=' + encodeURIComponent(j.id); return; }
        if (global.HubPageBusy && global.HubPageBusy.hide) global.HubPageBusy.hide();
        toast('error', j.message || 'Nicht geöffnet.');
      });
    }
    function row(it, kind) {
      var name = esc(it.name || '(ohne Name)');
      var sub = kind === 'recent' ? esc(dateShort(it.at)) : esc((it.from || '') + (it.changed ? ' · ' + isoShort(it.changed) : '') + (it.perm === 'r' || it.perm === 'read' ? ' · nur lesen' : ''));
      var data = ' data-ref="' + esc(it.ref || '') + '" data-share="' + esc(it.share || '') + '"';
      return '<tr><td class="hub-list__zelle"><a href="#" class="hub-list__sprung sf-home__open"' + data + '><span>' + name + '</span></a></td>' +
        '<td class="muted small">' + sub + '</td>' +
        '<td class="sf-home__act">' + (kind === 'recent' ? '<button type="button" class="hub-icon-btn sf-home__forget" title="Aus der Liste nehmen" aria-label="Aus der Liste nehmen"' + data + '>' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>' : '') + '</td></tr>';
    }
    function table(list, kind, head) {
      return '<table class="hub-list"><thead><tr><th>Name</th><th>' + head + '</th><th></th></tr></thead><tbody>' +
        list.map(function (it) { return row(it, kind); }).join('') + '</tbody></table>';
    }
    function draw(j) {
      var rec = j.recent || [], fm = j.forme || [];
      recentEl.innerHTML = rec.length ? table(rec, 'recent', 'Geöffnet')
        : '<p class="muted small">Noch nichts geöffnet. Öffne eine Präsentation aus FileForge oder lege eine neue an.</p>';
      formeEl.innerHTML = fm.length ? table(fm, 'forme', 'Von')
        : '<p class="muted small">Niemand hat dir eine Präsentation freigegeben.</p>';
      state.templateDir = j.templateDir || null;
      if (tplEl) tplEl.textContent = state.templateDir ? (state.templateDir.label || 'gewählt') : 'kein Ordner gewählt';
    }
    function reload() {
      fetch(API + '?action=home', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
        if (j.ok) { draw(j); return; }
        recentEl.innerHTML = formeEl.innerHTML = '<p class="muted small" role="alert">FileForge ist nicht erreichbar.</p>';
      }).catch(function () { recentEl.innerHTML = formeEl.innerHTML = '<p class="muted small" role="alert">Keine Verbindung zum Hub.</p>'; });
    }
    document.addEventListener('click', function (e) {
      var f = e.target.closest('.sf-home__forget');
      if (f) { post({ action: 'forget', ref: f.getAttribute('data-ref'), share: f.getAttribute('data-share') }).then(reload); return; }
      var o = e.target.closest('.sf-home__open');
      if (o) e.preventDefault();
      if (o) openTarget(o.getAttribute('data-ref'), o.getAttribute('data-share'));
    });

    var actions = {
      newBlank: function () { create(''); },
      newFromTemplate: function () {
        var other = function () {
          choose(['file'], ['cfs'], 'Vorlage wählen').then(function (r) { if (r && r.ref) create(r.ref, stem(r.name)); }).catch(pickError);
        };
        if (!state.templateDir || !global.HubFloatDialog) { other(); return; }
        fetch(API + '?action=templates', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
          var list = j && j.ok ? (j.items || []) : [];
          if (!j || !j.ok) toast('error', (j && j.message) || 'Vorlagen nicht lesbar.');
          var html = '<div class="sf-tpl">' + (list.length
            ? '<table class="hub-list sf-tpl__list"><thead><tr><th>Vorlage</th></tr></thead><tbody>' + list.map(function (it, i) {
                return '<tr><td class="hub-list__zelle"><a href="#" class="hub-list__sprung sf-tpl__pick" data-i="' + i + '">' + esc(stem(it.name)) + '</a></td></tr>';
              }).join('') + '</tbody></table>'
            : '<p class="muted small">Im Vorlagen-Ordner «' + esc(state.templateDir.label || '') + '» liegen keine Vorlagen (.cfs).</p>') +
            '<p><button type="button" class="hub-btn sf-tpl__other">Andere Vorlage aus FileForge …</button></p></div>';
          global.HubFloatDialog.open('Neu aus Vorlage', html, { id: 'sfTemplates', width: 420, bind: function (body) {
            body.addEventListener('click', function (e) {
              var b = e.target.closest('.sf-tpl__pick');
              if (b) { e.preventDefault(); var it = list[+b.getAttribute('data-i')]; global.HubFloatDialog.close('sfTemplates'); if (it) create(it.ref, stem(it.name)); return; }
              if (e.target.closest('.sf-tpl__other')) { global.HubFloatDialog.close('sfTemplates'); other(); }
            });
          } });
        }).catch(other);
      },
      open: function () {
        choose(['file'], ['cfs'], 'Präsentation öffnen').then(function (r) { if (r && r.ref) openTarget(r.ref, ''); }).catch(pickError);
      },
      importPpt: function () {
        choose(['file'], ['pptx', 'odp'], 'PowerPoint oder ODP übernehmen').then(function (r) { if (r && r.ref) openTarget(r.ref, ''); }).catch(pickError);
      },
      templateDir: function () {
        choose(['dir'], [], 'Ordner für Vorlagen wählen').then(function (r) {
          if (!r || !r.ref) return;
          return post({ action: 'template_dir', ref: r.ref, label: r.label || r.name || '' }).then(function (j) {
            j.ok ? (toast('success', 'Vorlagen-Ordner gespeichert.'), reload()) : toast('error', j.message || 'Nicht gespeichert.');
          });
        }).catch(pickError);
      }
    };
    function create(templateRef, suggested) {
      var name;
      ask('Name der neuen Präsentation', suggested || 'Neue Präsentation').then(function (n) {
        if (n == null || String(n).trim() === '') return null;
        name = String(n).trim();
        return choose(['dir'], [], 'Speicherort für «' + name + '» wählen');
      }).then(function (r) {
        if (!r || !r.ref) return;
        if (global.HubPageBusy && global.HubPageBusy.show) global.HubPageBusy.show({ title: 'Legt an …' });
        return post({ action: 'create', dir: r.ref, name: name, template: templateRef || '' }).then(function (j) {
          if (j.ok) { location.href = 'editor.php?id=' + encodeURIComponent(j.id); return; }
          if (global.HubPageBusy && global.HubPageBusy.hide) global.HubPageBusy.hide();
          toast('error', j.message || 'Nicht angelegt.');
        });
      }).catch(pickError);
    }
    reload();
    // Aus FileForge «Öffnen mit» (hooks/open.php → index.php?open=<ref>): hier im Browser laden, ohne Zeitfenster des App-Aufrufs
    var q = new URLSearchParams(location.search);
    if (q.get('open')) {
      var ref = q.get('open');
      if (global.history && global.history.replaceState) global.history.replaceState(null, '', location.pathname);
      openTarget(ref, '');
    }
    return actions;
  }

  /** Vorlagen-Ordner der Person in den Modul-Einstellungen (Urs 27.11.: gehört in die Hub-Einstellungen, nicht ins Menüband). */
  function templateSetting(cfg) {
    csrf = cfg.csrf;
    var label = document.getElementById(cfg.labelId), pick = document.getElementById(cfg.pickId), clear = document.getElementById(cfg.clearId);
    function show() {
      fetch(API + '?action=home', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
        var t = j && j.templateDir; label.textContent = t ? (t.label || 'gewählt') : 'kein Ordner gewählt';
        if (clear) clear.hidden = !t;
      }).catch(function () { label.textContent = 'nicht lesbar (keine Verbindung)'; });
    }
    function save(ref, lbl) {
      return post({ action: 'template_dir', ref: ref, label: lbl }).then(function (j) {
        j.ok ? (toast('success', ref ? 'Vorlagen-Ordner gespeichert.' : 'Vorlagen-Ordner entfernt.'), show()) : toast('error', j.message || 'Nicht gespeichert.');
      });
    }
    if (pick) pick.addEventListener('click', function () {
      choose(['dir'], [], 'Ordner für Vorlagen wählen').then(function (r) { if (r && r.ref) return save(r.ref, r.label || r.name || ''); }).catch(pickError);
    });
    if (clear) clear.addEventListener('click', function () { save('', ''); });
    show();
  }

  /** Medien im Editor (Bild, Audio, Video): Datei im FileForge-Dialog wählen, Hochladen vom Rechner bietet der Dialog selbst an.
   *  → {ref, name} oder null (Abbruch/Fehler, Fehler schon gemeldet). Urs 27.11.: «FF Piker verwenden, bei Bilder, Audio Video auch». */
  function pickMedia(accept, title) {
    return choose(['file'], accept, title).then(function (r) { return r && r.ref ? r : null; }).catch(function (e) { pickError(e); return null; });
  }

  /** Dateifeld in Formularen (Import, Traktanden, Folien-Set, Logo): Knopf öffnet den FileForge-Dialog, der `ref` landet im
   *  versteckten Feld, der Server holt die Datei als die Person (FfFile::fetchTemp). Markup: FfFile::fieldHtml(). */
  function bindField(box) {
    if (box.getAttribute('data-sf-ff-bound')) return;
    box.setAttribute('data-sf-ff-bound', '1');
    var input = box.querySelector('input[type="hidden"]');
    var pick = box.querySelector('[data-sf-ff-pick]');
    var clear = box.querySelector('[data-sf-ff-clear]');
    var label = box.querySelector('[data-sf-ff-name]');
    if (!input || !pick || !label) return;
    var empty = label.getAttribute('data-empty') || '';
    var accept = String(box.getAttribute('data-accept') || '').split(',').filter(Boolean);
    function show(name) {
      label.textContent = name || empty;
      label.classList.toggle('is-empty', !name);
      if (clear) clear.hidden = !name;
    }
    pick.addEventListener('click', function () {
      pickMedia(accept, box.getAttribute('data-title') || pick.textContent).then(function (r) {
        if (!r) return;
        input.value = r.ref;
        input.setAttribute('data-name', r.name || '');
        show(r.name || r.label || '');
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    if (clear) clear.addEventListener('click', function () {
      input.value = '';
      input.setAttribute('data-name', '');
      show('');
      input.dispatchEvent(new Event('change', { bubbles: true }));
      pick.focus();
    });
    show(input.value ? (input.getAttribute('data-name') || '') : '');
  }
  function bindFields() { Array.prototype.forEach.call(document.querySelectorAll('[data-sf-ff-field]'), bindField); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindFields); else bindFields();

  global.SfFf = { editor: editor, home: home, templateSetting: templateSetting, pickMedia: pickMedia, present: present, devices: devices, register: register, bindFields: bindFields };
})(window);
