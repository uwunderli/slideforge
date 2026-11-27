/**
 * FolienSchmiede als Editor für FileForge-Dateien (27.11.2026).
 *   SfFf.editor({ id, csrf, status })   Speichern im Editor: Menüband, Ctrl+S, alle 30 s, beim Schliessen; 409 → «Als Kopie speichern»
 *   SfFf.home({ csrf })                 Startseite: Zuletzt geöffnet, Für mich freigegeben, Neu / Öffnen / Übernehmen, Vorlagen-Ordner
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
      if (global.CfStatus && global.CfStatus.show) { global.CfStatus.show(kind, text); } else if (!inBar) { global.alert(text); }
    }
  }
  function post(data) {
    return fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(Object.assign({ csrf_token: csrf }, data)) })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .catch(function () { return { ok: false, message: 'Keine Verbindung.' }; });
  }
  /** Dialog «Auf Gerät zeigen»: Geräte des Hubs, zeigen/leeren, neues Gerät anmelden (Hub › Einstellungen › Geräte). */
  function showOnDevice(id) {
    var DEV = 'geraet_api.php';
    var send = function (data) {
      return fetch(DEV, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(Object.assign({ csrf_token: csrf }, data)) })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .catch(function () { return { ok: false, message: 'Keine Verbindung.' }; });
    };
    fetch(DEV, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).catch(function () { return { ok: false }; }).then(function (j) {
      if (!j || !j.ok) { toast('error', (j && j.message) || 'Geräte nicht lesbar.'); return; }
      var list = j.geraete || [];
      var anmelden = location.origin + (j.anmelden || '/admin/settings.php?tab=geraete');
      var html = '<div class="sf-dev">' + (list.length
        ? '<ul class="hub-list sf-dev__list">' + list.map(function (g, i) {
            var zeigt = g.zeigt ? (g.zeigt.titel || g.zeigt.app) : '';
            return '<li class="hub-list__row"><span class="hub-list__label">' + esc(g.name) +
              (zeigt ? ' <small class="muted">zeigt: ' + esc(zeigt) + '</small>' : '') + '</span>' +
              '<span class="hub-list__actions"><button type="button" class="hub-btn hub-btn--sm hub-btn--primary sf-dev__show" data-i="' + i + '">Zeigen</button> ' +
              (g.zeigt ? '<button type="button" class="hub-btn hub-btn--sm sf-dev__clear" data-i="' + i + '">Leeren</button>' : '') + '</span></li>';
          }).join('') + '</ul>'
        : '<p class="muted small">Noch kein Gerät angemeldet.</p>') +
        '<p class="muted small">Neues Gerät: dort <code>' + esc(location.origin) + '/device.php</code> öffnen, QR abknipsen oder den Code im Hub eingeben.</p>' +
        '<p><a class="hub-btn" href="' + esc(anmelden) + '" target="_blank" rel="noopener">Gerät anmelden …</a></p></div>';
      if (!global.HubFloatDialog) { toast('error', 'Dialog nicht verfügbar.'); return; }
      global.HubFloatDialog.open('Auf Gerät zeigen', html, { id: 'sfDevices', width: 440, bind: function (body) {
        body.addEventListener('click', function (e) {
          var s = e.target.closest('.sf-dev__show'), c = e.target.closest('.sf-dev__clear');
          var g = (s || c) ? list[+(s || c).getAttribute('data-i')] : null;
          if (!g) return;
          send(c ? { action: 'leeren', geraet: g.id } : { action: 'zeigen', geraet: g.id, id: id }).then(function (r) {
            if (r.ok) { toast('success', c ? 'Gerät ' + g.name + ' geleert.' : 'Läuft auf ' + g.name + '.'); global.HubFloatDialog.close('sfDevices'); }
            else toast('error', r.message || 'Das ging nicht.');
          });
        });
      } });
    });
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
  function ask(label, value) {
    if (global.SFDialog && global.SFDialog.prompt) return global.SFDialog.prompt(label, value || '');
    return Promise.resolve(global.prompt(label, value || ''));
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
            var ok = global.SFDialog && global.SFDialog.confirm ? global.SFDialog.confirm(j.message + ' Jetzt als Kopie speichern?') : Promise.resolve(global.confirm(j.message));
            Promise.resolve(ok).then(function (yes) { if (yes) saveCopy(); });
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
      showOnDevice(cfg.id);
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
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>' : '') + '</td></tr>';
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
        if (j.ok) draw(j); else recentEl.innerHTML = '<p class="muted small">FileForge ist nicht erreichbar.</p>';
      }).catch(function () { recentEl.innerHTML = '<p class="muted small">Keine Verbindung.</p>'; });
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
            ? '<ul class="hub-list sf-tpl__list">' + list.map(function (it, i) {
                return '<li><button type="button" class="hub-list__sprung sf-tpl__pick" data-i="' + i + '">' + esc(stem(it.name)) + '</button></li>';
              }).join('') + '</ul>'
            : '<p class="muted small">Im Vorlagen-Ordner «' + esc(state.templateDir.label || '') + '» liegen keine Vorlagen (.cfs).</p>') +
            '<p><button type="button" class="hub-btn sf-tpl__other">Andere Vorlage aus FileForge …</button></p></div>';
          global.HubFloatDialog.open('Neu aus Vorlage', html, { id: 'sfTemplates', width: 420, bind: function (body) {
            body.addEventListener('click', function (e) {
              var b = e.target.closest('.sf-tpl__pick');
              if (b) { var it = list[+b.getAttribute('data-i')]; global.HubFloatDialog.close('sfTemplates'); if (it) create(it.ref, stem(it.name)); return; }
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
      }).catch(function () { label.textContent = '—'; });
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

  global.SfFf = { editor: editor, home: home, templateSetting: templateSetting };
})(window);
