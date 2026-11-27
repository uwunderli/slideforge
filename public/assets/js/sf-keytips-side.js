/**
 * SlideForge — Tastentipps für rechte Seitenleiste und Filmstreifen (Urs 27.11.2026).
 *
 * «Können wir das gleiche mit der rechten Seitenliste machen? Alt anschliessend Ctrl bringt die Tabs der rechten Werkzeugliste
 *  und dann weiter…» — «Und erneutes Ctrl geht auf den Filmstreifen und dann wieder zurück. Im Filmstreifen sind es dann Nummern,
 *  die man eingibt, nämlich die Foliennummer. Man kann da auch mehrstellige Nummern eingeben, also 15 z. B.»
 *
 * Nutzt HubShortcuts.keyTips.registerArea (Hub 2.37.4). Ohne diesen Hub tut die Datei nichts.
 * Reihenfolge der Anmeldung = Reihenfolge beim Wechsel mit Ctrl: Menüband → Seitenleiste → Filmstreifen bzw. Raster → Menüband.
 * Alles wird bei jedem Aufruf frisch aus dem DOM gelesen (Panels werden per innerHTML neu gezeichnet).
 */
(function () {
  'use strict';
  var HS = window.HubShortcuts;
  if (!HS || !HS.keyTips || typeof HS.keyTips.registerArea !== 'function') return;
  var I = (window.SF_BOOTSTRAP && window.SF_BOOTSTRAP.i18n) || {};

  function shown(el) { return !!(el && el.getClientRects().length && !el.closest('[hidden]')); }

  function text(el) { return String(el && el.textContent || '').replace(/\s+/g, ' ').trim(); }

  function labelOf(el) {
    var a = el.getAttribute('aria-label') || el.getAttribute('title');
    if (a) return a;
    if (el.id) {
      var l = document.querySelector('label[for="' + (window.CSS && CSS.escape ? CSS.escape(el.id) : el.id) + '"]');
      if (l && text(l)) return text(l);
    }
    var wrap = el.closest('label');
    if (wrap && text(wrap)) return text(wrap);
    var lb = el.getAttribute('aria-labelledby');
    if (lb) { var t = document.getElementById(lb.split(' ')[0]); if (t && text(t)) return text(t); }
    return el.getAttribute('placeholder') || text(el) || el.getAttribute('name') || '';
  }

  var CONTROLS = 'button, input:not([type="hidden"]), select, textarea, [role="button"], [role="switch"], [role="checkbox"], [contenteditable="true"]';

  /** Knöpfe und Felder einer Ansicht (ohne Reiter); ein Feld innerhalb eines anderen Steuerelements zählt nicht doppelt. */
  function controls(panel) {
    if (!panel) return [];
    var out = [];
    panel.querySelectorAll(CONTROLS).forEach(function (el) {
      if (el.getAttribute('role') === 'tab' || !shown(el)) return;
      var outer = el.parentElement && el.parentElement.closest(CONTROLS);
      if (outer && panel.contains(outer)) return;
      out.push({ el: el, label: labelOf(el), disabled: !!el.disabled || el.getAttribute('aria-disabled') === 'true' });
    });
    return out;
  }

  function activePanel() {
    var tab = document.querySelector('#propsSideTabs [data-side-tab][aria-selected="true"]');
    var id = tab ? tab.getAttribute('data-side-tab') : '';
    return id ? document.querySelector('[data-side-panel="' + id + '"]') : null;
  }

  /** Stufe 2: Unter-Reiter des aktiven Panels (Position, Format, Effekte …), sonst gleich die Knöpfe und Felder. */
  function panelLevel() {
    var panel = activePanel();
    if (!panel) return [];
    var subs = [];
    panel.querySelectorAll('[role="tab"]').forEach(function (el) {
      if (!shown(el)) return;
      subs.push({
        el: el,
        label: labelOf(el),
        children: function () { return controls(activePanel()); }
      });
    });
    return subs.length ? subs : controls(panel);
  }

  // Alt lange halten (Urs 27.11.: «mach beim langen Alt alle, auch die im linken und rechten Fenster»): Knöpfe ausserhalb des Menübands,
  // die einen Befehl aus dem Katalog auslösen → eingestelltes Kürzel (wie im Anpassen-Dialog), nur wo eines gesetzt ist.
  var SIDE_CMDS = {
    '[data-layer="up"]': 'stage_layer_up',
    '[data-layer="down"]': 'stage_layer_down',
    '[data-layer="front"]': 'stage_layer_front',
    '[data-layer="back"]': 'stage_layer_back'
  };
  var STRIP_CMDS = { '#addSlideBtn': 'add_slide' };

  function cmdShortcuts(root, map) {
    var hub = window.SFRibbon && typeof window.SFRibbon.hub === 'function' ? window.SFRibbon.hub() : null;
    if (!hub || typeof hub.formatShortcut !== 'function' || !root) return [];
    var out = [];
    Object.keys(map).forEach(function (sel) {
      var txt = hub.formatShortcut(map[sel]);
      if (!txt) return;
      root.querySelectorAll(sel).forEach(function (el) {
        if (shown(el)) out.push({ el: el, text: txt, disabled: !!el.disabled });
      });
    });
    return out;
  }

  var side = document.getElementById('propsPanelWrap');
  if (side && document.getElementById('propsSideTabs')) {
    HS.keyTips.registerArea({
      id: 'sf-side',
      label: I.keytipsSide || 'Seitenleiste',
      root: side,
      items: function () {
        var out = [];
        document.querySelectorAll('#propsSideTabs [data-side-tab]').forEach(function (el) {
          if (!shown(el) || el.id === 'ribbonCustomizeBtn') return;
          out.push({ el: el, label: labelOf(el), children: panelLevel });
        });
        return out;
      },
      shortcutItems: function () { return cmdShortcuts(side, SIDE_CMDS); }
    });
  }

  // ── Filmstreifen bzw. Raster: Plaketten mit der Foliennummer, Eingabe mehrstellig ──────────────────────────────────
  function numberInput(listItems, go) {
    return {
      chars: '0123456789',
      state: function (buf) {
        var n = listItems().length;
        var v = parseInt(buf, 10);
        if (!/^[1-9][0-9]*$/.test(buf) || v > n) return 'none';
        var more = v * 10 <= n;                        // eine weitere Ziffer kann noch eine gültige Nummer ergeben
        return more ? 'both' : 'exact';
      },
      go: function (buf) {
        var el = listItems()[parseInt(buf, 10) - 1];
        if (el) go(el);
      }
    };
  }

  /** Plaketten nur für Einträge im sichtbaren Teil der Liste (weggerollte nicht); die Nummer bleibt die Foliennummer. */
  function numbered(listItems, box) {
    var b = box ? box.getBoundingClientRect() : null;
    var out = [];
    listItems().forEach(function (el, i) {
      if (b) {
        var r = el.getBoundingClientRect(), y = r.top + r.height / 2;
        if (y < b.top || y > b.bottom) return;
      }
      out.push({ el: el, label: String(i + 1), code: String(i + 1) });
    });
    return out;
  }

  function gridOpen() { return shown(document.getElementById('canvasGridView')); }

  var strip = document.getElementById('slideFilmstrip');
  if (strip) {
    var stripItems = function () { return Array.prototype.slice.call(strip.querySelectorAll('.editor-filmstrip-item')); };
    HS.keyTips.registerArea({
      id: 'sf-filmstrip',
      label: I.keytipsFilmstrip || 'Filmstreifen',
      root: document.getElementById('sfFilmstripPanel') || strip,
      isActive: function () { return !gridOpen(); },
      items: function () { return numbered(stripItems, strip); },
      shortcutItems: function () {
        return numbered(stripItems, strip).map(function (en) { return { el: en.el, text: en.code }; })
          .concat(cmdShortcuts(document.getElementById('sfFilmstripPanel'), STRIP_CMDS));
      },
      // Klick wie mit der Maus: switchToSlide(), rollt den Eintrag in Sicht
      input: numberInput(stripItems, function (el) { el.click(); })
    });
  }

  var grid = document.getElementById('canvasGridView');
  if (grid) {
    var gridItems = function () { return Array.prototype.slice.call(grid.querySelectorAll('.editor-slide-grid-item')); };
    HS.keyTips.registerArea({
      id: 'sf-grid',
      label: I.keytipsFilmstrip || 'Filmstreifen',
      root: grid,
      items: function () { return numbered(gridItems); },
      shortcutItems: function () { return numbered(gridItems).map(function (en) { return { el: en.el, text: en.code }; }); },
      // Raster: Kachel wählen wie ein Klick und in Sicht rollen
      input: numberInput(gridItems, function (el) {
        el.click();
        try { el.scrollIntoView({ block: 'nearest' }); } catch (_) {}
      })
    });
  }
})();
