/**
 * SfFontSelect — durchsuchbare Schriftwahl (3.9.10, Urs 27.11.2026).
 *
 * Der Hub liefert sehr viele Schriftfamilien; ein nacktes <select> ist dann unbrauchbar. Der Baustein legt über ein
 * bestehendes <select> ein Suchfeld (ARIA-Combobox wie Hub `HubPlzOrt`): Tippen filtert (enthält, Gross/Klein egal),
 * Pfeile/Enter/Esc, Liste darunter. Das <select> bleibt versteckt die Quelle des Werts: Wahl setzt select.value und löst
 * `change` (bubbles) aus — bestehende Handler und Autosave bleiben unverändert.
 *
 * Reihenfolge: «Zuletzt benutzt» (höchstens 8, je Person in localStorage), «Hub-Schriften», «Weitere Schriften».
 * Höchstens MAX_ROWS Einträge im DOM; die Vorschau (font-family des Eintrags) wird erst gesetzt, wenn der Eintrag sichtbar wird.
 *
 *   SfFontSelect.attach(select, { families: [...], hub: [...], user: 'id' })
 *   SfFontSelect.setValue(select, 'Open Sans')   // Wert von aussen setzen, ohne change
 *   SfFontSelect.autoAttach()                     // alle select[data-sf-font-select] mit <script id="sf-font-data">
 */
(function () {
  'use strict';

  var MAX_ROWS = 200;
  var MAX_RECENT = 8;
  var seq = 0;
  var states = typeof WeakMap === 'function' ? new WeakMap() : null;

  function lc(s) { return String(s || '').toLowerCase(); }

  function recentKey(user) { return 'sf.fontRecent.' + (user ? String(user) : 'anon'); }

  function readRecent(user) {
    try {
      var v = JSON.parse(window.localStorage.getItem(recentKey(user)) || '[]');
      return Array.isArray(v) ? v.filter(function (x) { return typeof x === 'string' && x !== ''; }).slice(0, MAX_RECENT) : [];
    } catch (e) { return []; }
  }

  function pushRecent(user, family) {
    try {
      var list = readRecent(user).filter(function (x) { return lc(x) !== lc(family); });
      list.unshift(family);
      window.localStorage.setItem(recentKey(user), JSON.stringify(list.slice(0, MAX_RECENT)));
    } catch (e) { /* ohne Speicher keine Liste «Zuletzt benutzt» */ }
  }

  function cssFamily(name) {
    return '"' + String(name).replace(/["\\]/g, '') + '", sans-serif';
  }

  function ensureOption(select, value) {
    var opts = select.options;
    for (var i = 0; i < opts.length; i++) {
      if (opts[i].value === value) return;
    }
    var o = document.createElement('option');
    o.value = value;
    o.textContent = value;
    select.appendChild(o);
  }

  function setValue(select, value) {
    var v = String(value || '');
    if (v !== '') ensureOption(select, v);
    select.value = v;
    var st = states ? states.get(select) : select._sfFontSelect;
    if (st) st.input.value = select.value;
  }

  function attach(select, opts) {
    if (!select || select.dataset.sfFselBound === '1') return null;
    select.dataset.sfFselBound = '1';
    opts = opts || {};
    var families = Array.isArray(opts.families) && opts.families.length ? opts.families.slice() : [];
    if (!families.length) {
      for (var i = 0; i < select.options.length; i++) families.push(select.options[i].value);
    }
    var hubSet = Object.create(null);
    (Array.isArray(opts.hub) ? opts.hub : []).forEach(function (h) { hubSet[lc(h)] = true; });
    var famSet = Object.create(null);
    families.forEach(function (f) { famSet[lc(f)] = f; });
    var user = opts.user || '';
    var I = opts.i18n || {};
    var T = {
      recent: I.recent || 'Zuletzt benutzt',
      hub: I.hub || 'Hub-Schriften',
      other: I.other || 'Weitere Schriften',
      none: I.none || 'Keine Schrift gefunden',
      more: I.more || 'weitere {n} – Suche verfeinern',
      hubTag: I.hubTag || 'Hub',
      label: I.label || select.getAttribute('title') || 'Schriftart',
    };

    var id = 'sfFsel' + (++seq);
    var wrap = document.createElement('span');
    wrap.className = 'sf-fsel';
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'sf-fsel-input';
    input.id = id + 'In';
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', id + 'List');
    input.setAttribute('aria-label', T.label);
    if (select.title) input.title = select.title;
    input.value = select.value;
    input.disabled = select.disabled;
    wrap.appendChild(input);

    var list = document.createElement('ul');
    list.className = 'sf-fsel-list';
    list.id = id + 'List';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', T.label);
    list.hidden = true;
    document.body.appendChild(list);

    select.classList.add('sf-fsel-native');
    select.setAttribute('aria-hidden', 'true');
    select.tabIndex = -1;
    select.parentNode.insertBefore(wrap, select.nextSibling);
    // Ein <label> um das <select> soll das Suchfeld fokussieren.
    if (select.id) {
      Array.prototype.forEach.call(document.querySelectorAll('label[for="' + select.id + '"]'), function (l) { l.htmlFor = input.id; });
    }

    var st = { select: select, input: input, list: list, rows: [], active: -1, open: false, io: null };
    if (states) states.set(select, st); else select._sfFontSelect = st;

    var io = null;
    if (typeof IntersectionObserver === 'function') {
      io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          var li = en.target;
          li.querySelector('.sf-fsel-name').style.fontFamily = cssFamily(li.dataset.value);
          io.unobserve(li);
        });
      }, { root: list, rootMargin: '80px 0px' });
    }

    function place() {
      var r = input.getBoundingClientRect();
      var below = window.innerHeight - r.bottom;
      var h = Math.min(360, Math.max(160, below - 12));
      list.style.minWidth = Math.max(r.width, 220) + 'px';
      list.style.left = Math.max(4, Math.min(r.left, window.innerWidth - Math.max(r.width, 220) - 4)) + 'px';
      if (below < 180 && r.top > below) {
        h = Math.min(360, r.top - 12);
        list.style.top = '';
        list.style.bottom = (window.innerHeight - r.top + 2) + 'px';
      } else {
        list.style.bottom = '';
        list.style.top = (r.bottom + 2) + 'px';
      }
      list.style.maxHeight = h + 'px';
    }

    function addGroup(title) {
      var li = document.createElement('li');
      li.className = 'sf-fsel-group';
      li.setAttribute('role', 'presentation');
      li.textContent = title;
      list.appendChild(li);
    }

    function addRow(family) {
      var li = document.createElement('li');
      li.className = 'sf-fsel-opt';
      li.id = id + 'o' + st.rows.length;
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', family === select.value ? 'true' : 'false');
      li.dataset.value = family;
      var name = document.createElement('span');
      name.className = 'sf-fsel-name';
      name.textContent = family;
      li.appendChild(name);
      if (hubSet[lc(family)]) {
        var tag = document.createElement('span');
        tag.className = 'sf-fsel-tag';
        tag.textContent = T.hubTag;
        li.appendChild(tag);
      }
      list.appendChild(li);
      st.rows.push(li);
      if (io) io.observe(li); else name.style.fontFamily = cssFamily(family);
    }

    function render(query) {
      if (io) io.disconnect();
      list.textContent = '';
      st.rows = [];
      st.active = -1;
      var q = lc(query).trim();
      var match = function (f) { return q === '' || lc(f).indexOf(q) !== -1; };
      var recent = readRecent(user).filter(function (f) { return famSet[lc(f)] && match(f); }).map(function (f) { return famSet[lc(f)]; });
      var hub = [];
      var other = [];
      families.forEach(function (f) {
        if (!match(f)) return;
        (hubSet[lc(f)] ? hub : other).push(f);
      });
      var total = recent.length + hub.length + other.length;
      if (!total) {
        var empty = document.createElement('li');
        empty.className = 'sf-fsel-empty';
        empty.setAttribute('role', 'presentation');
        empty.textContent = T.none;
        list.appendChild(empty);
        return;
      }
      var left = MAX_ROWS;
      [[T.recent, recent], [T.hub, hub], [T.other, other]].forEach(function (g) {
        if (!g[1].length || left <= 0) return;
        addGroup(g[0]);
        g[1].slice(0, left).forEach(addRow);
        left -= Math.min(left, g[1].length);
      });
      var shown = st.rows.length;
      if (total > shown) {
        var more = document.createElement('li');
        more.className = 'sf-fsel-more';
        more.setAttribute('role', 'presentation');
        more.textContent = T.more.replace('{n}', String(total - shown));
        list.appendChild(more);
      }
      var cur = -1;
      for (var k = 0; k < st.rows.length; k++) { if (st.rows[k].dataset.value === select.value) { cur = k; break; } }
      setActive(q === '' ? cur : 0, true);
    }

    function setActive(i, center) {
      if (st.active >= 0 && st.rows[st.active]) st.rows[st.active].classList.remove('is-active');
      st.active = i;
      var li = st.rows[i];
      if (!li) { input.removeAttribute('aria-activedescendant'); return; }
      li.classList.add('is-active');
      input.setAttribute('aria-activedescendant', li.id);
      if (center) {
        list.scrollTop = Math.max(0, li.offsetTop - list.clientHeight / 2 + li.offsetHeight / 2);
      } else if (li.offsetTop < list.scrollTop) {
        list.scrollTop = li.offsetTop;
      } else if (li.offsetTop + li.offsetHeight > list.scrollTop + list.clientHeight) {
        list.scrollTop = li.offsetTop + li.offsetHeight - list.clientHeight;
      }
    }

    function openList() {
      if (st.open || input.disabled) return;
      st.open = true;
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      wrap.classList.add('is-open');
      place();
      render('');
    }

    function closeList(restore) {
      if (!st.open) return;
      st.open = false;
      list.hidden = true;
      if (io) io.disconnect();
      list.textContent = '';
      st.rows = [];
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      wrap.classList.remove('is-open');
      if (restore) input.value = select.value;
    }

    function pick(family) {
      closeList(false);
      input.value = family;
      pushRecent(user, family);
      if (select.value === family) return;
      ensureOption(select, family);
      select.value = family;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Eigene input/change-Ereignisse des Suchfelds gehören nicht ins Formular (Autosave hört auf change).
    input.addEventListener('change', function (e) { e.stopPropagation(); });
    input.addEventListener('input', function (e) {
      e.stopPropagation();
      if (!st.open) openList();
      render(input.value);
    });
    input.addEventListener('focus', function () { input.select(); });
    input.addEventListener('click', function () { if (st.open) closeList(true); else openList(); });
    input.addEventListener('blur', function () { closeList(true); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!st.open) { openList(); return; }
        if (!st.rows.length) return;
        var n = st.active + (e.key === 'ArrowDown' ? 1 : -1);
        setActive(Math.max(0, Math.min(st.rows.length - 1, n)), false);
      } else if (e.key === 'Enter') {
        if (!st.open) return;
        e.preventDefault();
        var li = st.rows[st.active];
        if (li) pick(li.dataset.value); else closeList(true);
      } else if (e.key === 'Escape') {
        if (!st.open) return;
        e.preventDefault();
        e.stopPropagation();
        closeList(true);
      } else if (e.key === 'Tab') {
        closeList(true);
      }
    });
    list.addEventListener('mousedown', function (e) {
      e.preventDefault();   // Fokus bleibt im Suchfeld
      var li = e.target.closest ? e.target.closest('.sf-fsel-opt') : null;
      if (li) pick(li.dataset.value);
    });
    window.addEventListener('resize', function () { if (st.open) place(); });
    window.addEventListener('scroll', function (e) { if (st.open && e.target !== list && !list.contains(e.target)) place(); }, true);
    select.addEventListener('change', function () { input.value = select.value; });

    // Menüband schaltet das <select> per disabled ein und aus.
    if (typeof MutationObserver === 'function') {
      new MutationObserver(function () {
        input.disabled = select.disabled;
        if (select.disabled) closeList(true);
      }).observe(select, { attributes: true, attributeFilter: ['disabled'] });
    }
    return st;
  }

  function autoAttach(root) {
    var data = {};
    try {
      var el = document.getElementById('sf-font-data');
      if (el) data = JSON.parse(el.textContent || '{}') || {};
    } catch (e) { data = {}; }
    Array.prototype.forEach.call((root || document).querySelectorAll('select[data-sf-font-select]'), function (sel) {
      attach(sel, data);
    });
  }

  window.SfFontSelect = { attach: attach, setValue: setValue, autoAttach: autoAttach };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { autoAttach(); });
  } else {
    autoAttach();
  }
})();
