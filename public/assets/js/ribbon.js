/**
 * Menüband (Editor und Präsentation) auf den Hub-Bausteinen (3.12.0, Urs 27.11.: «Ja umbauen auf den Hub»).
 *
 * HubRibbon zeichnet Reiter, Gruppen, Raster und Zeilenstapel, führt «Ribbon anpassen», Tastenkürzel, Tastentipps (Alt),
 * Einklappen (Doppelklick, Strg+F1) und Peek. SlideForge liefert nur:
 *   - Katalog und Layout (config/ribbon_*.php bzw. present_ribbon_*.php, Ablage weiter serverseitig über ribbon.php / present_ribbon.php
 *     — Speicher-Adapter),
 *   - den Inhalt der Einträge (SFRibbonRenderer.createItem: Knöpfe aller Arten, Widgets aus dem Vorrat),
 *   - die Bedeutung der Kürzel (SFShortcuts, z. B. Strg+C auf Folien oder Objekt),
 *   - die schwebenden Einstellungs-Panels und Auslöser-Knöpfe.
 * Klicks auf Befehle laufen wie bisher über die Delegation in editor.js / present.js (data-ribbon-command).
 *
 * Ladereihenfolge: Hub hub-shortcuts.js → hub-ribbon-customize.js → hub-ribbon.js, dann ribbon-renderer.js, dann diese Datei.
 */
(function (global) {
  'use strict';

  const boot = global.SF_BOOTSTRAP?.ribbon || {};
  const rootId = boot.rootId || 'editorRibbon';
  const ribbon = document.getElementById(rootId);
  if (!ribbon) return;

  const apiUrl = boot.apiUrl || (rootId === 'presentRibbon' ? 'present_ribbon.php' : 'ribbon.php');
  const R = global.SFRibbonRenderer;
  const I = boot.i18n || {};
  const tabsEl = ribbon.querySelector('.editor-ribbon-tabs');
  const panelsEl = ribbon.querySelector('.editor-ribbon-panels');
  let hub = null;

  /* ── Befehle für den Hub: Katalog + Definitionen; icon = SVG für Katalog/Dialog, iconName = Name für SF-Knöpfe ───────────── */
  const catalogById = {};
  (boot.catalog || []).forEach((c) => { catalogById[c.id] = c; });
  const commands = {};
  (boot.commands || []).forEach((def) => {
    if (!def || !def.id) return;
    if (def.kind === 'separator' || def.kind === 'row_separator' || def.id === 'separator' || def.id === 'row_separator') return;
    if (def.id === 'ribbon_customize') return;   // Anpassen liegt in der Reiter-Leiste
    const cat = catalogById[def.id] || {};
    /* Weitere Befehle (3.12.1): kuerzelOnly ohne Icon → im Hub-Dialog nur Kürzelfeld, nicht ins Menüband ziehbar. */
    const iconName = def.kuerzelOnly ? '' : (def.icon || cat.icon || (def.kind === 'widget' ? 'widget' : ''));
    commands[def.id] = Object.assign({}, def, {
      label: def.label || cat.label || def.id,
      category: cat.category || def.category || '',
      iconName: iconName,
      icon: R && iconName ? R.svgIcon(iconName) : '',
      tileable: !!(def.tileable || cat.tileable),
    });
  });
  const categoryLabels = I.categories || {};
  const categories = [...new Set(Object.values(commands).map((c) => c.category).filter(Boolean))]
    .map((id) => ({ id: id, label: categoryLabels[id] || id }));

  /* ── Layout: SlideForge-Format ↔ Hub-Format ─────────────────────────────────────────────────────────────────────────── */
  function toHub(sf) {
    if (!sf || !Array.isArray(sf.tabs)) return null;
    const prefs = sf.prefs || {};
    const out = {
      iconSize: ['small', 'medium', 'large'].includes(prefs.iconSize) ? prefs.iconSize : 'medium',
      hideLabels: prefs.showLabels === false,
      tabs: sf.tabs.map((t) => ({
        id: String(t.id),
        label: String(t.label || t.id),
        groups: (t.groups || []).map((g) => ({
          id: String(g.id),
          label: String(g.label || g.id),
          rows: g.rows === 1 ? 1 : 2,
          items: (g.items || []).map((it) => {
            const id = typeof it === 'object' ? String(it.id || '') : String(it || '');
            const o = { id: id };
            if (it && it.gridSpan) o.gridSpan = { cols: it.gridSpan.cols, rows: it.gridSpan.rows };
            if (it && it.showLabel === false) o.showLabel = false;
            const cmd = commands[id];
            if (cmd && o.gridSpan && R) o.size = R.tileSizeFromSpan(o.gridSpan, cmd.kind, id);
            return o;
          }),
        })),
      })),
      shortcuts: sf.shortcuts && typeof sf.shortcuts === 'object' && !Array.isArray(sf.shortcuts) ? sf.shortcuts : {},
    };
    if (Array.isArray(sf.known) && sf.known.length) out.known = sf.known;
    if (prefs.collapsed) out.collapsed = true;
    if (prefs.activeTab) out.activeTab = String(prefs.activeTab);
    return out;
  }

  function fromHub(l) {
    return {
      version: 1,
      tabs: (l.tabs || []).map((t) => ({
        id: t.id,
        label: t.label,
        groups: (t.groups || []).map((g) => {
          const go = {
            id: g.id,
            label: g.label,
            items: (g.items || []).map((it) => {
              const o = { id: it.id };
              if (it.gridSpan) o.gridSpan = { cols: it.gridSpan.cols, rows: it.gridSpan.rows };
              if (it.showLabel === false) o.showLabel = false;
              return o;
            }),
          };
          if (g.rows === 1) go.rows = 1;
          return go;
        }),
      })),
      known: l.known || [],
      shortcuts: l.shortcuts || {},
      prefs: {
        activeTab: l.activeTab || (hub ? hub.activeTab() : '') || 'start',
        collapsed: !!l.collapsed,
        iconSize: l.iconSize || 'medium',
        showLabels: !l.hideLabels,
      },
    };
  }

  function apiExtra() {
    if (rootId !== 'presentRibbon') {
      return {
        templateMode: !!global.SF_BOOTSTRAP?.templateMode,
        layoutSetMode: !!global.SF_BOOTSTRAP?.layoutSetMode,
        showMasterSlideNav: !!boot.meta?.masterSlideNav,
      };
    }
    return {
      canBroadcast: !!boot.meta?.canBroadcast,
      isOwner: !!boot.meta?.isOwner,
      hasRemote: !!boot.meta?.hasRemote,
    };
  }

  function post(payload) {
    return fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ csrf_token: global.SF_BOOTSTRAP?.csrfToken }, payload, apiExtra())),
    }).then((r) => r.json()).then((data) => {
      if (!data || !data.ok) throw new Error((data && data.error) || 'save failed');
      return data;
    });
  }

  /* Speicher-Adapter: Aufbau geändert → «save» (Server prüft, behält im Kontext ausgeblendete Befehle), nur Reiter/Einklappen → «save_prefs». */
  let lastStructure = '';
  function structureOf(sf) {
    return JSON.stringify([sf.tabs, sf.shortcuts, sf.prefs.iconSize, sf.prefs.showLabels]);
  }
  const storage = {
    load: function () {
      const l = toHub(boot.layout);
      if (l) lastStructure = structureOf(fromHub(l));
      return l;
    },
    save: function (l) {
      const sf = fromHub(l);
      const st = structureOf(sf);
      if (st === lastStructure) {
        return post({ action: 'save_prefs', prefs: { activeTab: sf.prefs.activeTab, collapsed: sf.prefs.collapsed } }).catch(() => {});
      }
      lastStructure = st;
      return post({
        action: 'save',
        layout: sf,
        visible: Object.keys(commands).concat(['separator', 'row_separator', 'ribbon_customize']),
      }).then((data) => {
        if (data.layout) boot.layout = data.layout;
      }).catch((err) => {
        lastStructure = '';
        if (global.SFDialog?.alert) global.SFDialog.alert((err && err.message) || I.errorSave || 'Speichern fehlgeschlagen');
      });
    },
  };

  /* ── Schwebende Einstellungs-Panels und Auslöser (SlideForge-eigen) ─────────────────────────────────────────────────── */
  function resetFloatingSettingsPanels() {
    const wrap = document.querySelector('[data-settings-menu]');
    if (!wrap) return;
    wrap.querySelectorAll('[data-settings-panel]').forEach((panel) => {
      panel.classList.remove('ribbon-settings-panel-floating');
      panel.style.position = '';
      panel.style.left = '';
      panel.style.top = '';
      panel.style.right = '';
      panel.style.zIndex = '';
    });
  }

  function positionFloatingSettingsPanel(panel, anchor) {
    if (!panel || !anchor) return;
    const rect = anchor.getBoundingClientRect();
    panel.classList.add('ribbon-settings-panel-floating');
    panel.style.position = 'fixed';
    panel.style.left = Math.max(8, rect.left) + 'px';
    panel.style.top = (rect.bottom + 6) + 'px';
    panel.style.right = 'auto';
    panel.style.zIndex = '500';
    const panelRect = panel.getBoundingClientRect();
    if (panelRect.right > window.innerWidth - 8) {
      panel.style.left = Math.max(8, window.innerWidth - panelRect.width - 8) + 'px';
    }
    if (panelRect.bottom > window.innerHeight - 8) {
      panel.style.top = Math.max(8, rect.top - panelRect.height - 6) + 'px';
    }
  }

  function bindRibbonActions() {
    ribbon.querySelectorAll('[data-ribbon-trigger]').forEach((btn) => {
      if (btn.dataset.wiredRibbonTrigger) return;
      btn.dataset.wiredRibbonTrigger = '1';
      btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.ribbonTrigger);
        if (target) target.click();
      });
    });

    ribbon.querySelectorAll('[data-ribbon-settings]').forEach((btn) => {
      if (btn.dataset.wiredRibbonSettings) return;
      btn.dataset.wiredRibbonSettings = '1';
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const panelId = btn.dataset.ribbonSettings;
        const wrap = document.querySelector('[data-settings-menu]');
        if (!wrap || !panelId) return;
        const panel = wrap.querySelector('[data-settings-panel="' + panelId + '"]');
        if (!panel) return;
        const alreadyOpen = !panel.hidden && panel.classList.contains('ribbon-settings-panel-floating');
        resetFloatingSettingsPanels();
        wrap.querySelectorAll('[data-settings-panel]').forEach((p) => { p.hidden = true; });
        ribbon.querySelectorAll('[data-ribbon-settings].active').forEach((b) => b.classList.remove('active'));
        if (alreadyOpen) return;
        panel.hidden = false;
        btn.classList.add('active');
        positionFloatingSettingsPanel(panel, btn);
        document.dispatchEvent(new CustomEvent('sf:ribbon-settings-open', { detail: { panelId: panelId } }));
      });
    });
  }

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-ribbon-settings]') || e.target.closest('[data-settings-menu]')) return;
    const wrap = document.querySelector('[data-settings-menu]');
    if (!wrap) return;
    const openPanel = wrap.querySelector('[data-settings-panel]:not([hidden])');
    if (!openPanel) return;
    openPanel.hidden = true;
    resetFloatingSettingsPanels();
    ribbon.querySelectorAll('[data-ribbon-settings].active').forEach((b) => b.classList.remove('active'));
  });

  window.addEventListener('resize', () => {
    const wrap = document.querySelector('[data-settings-menu]');
    if (!wrap) return;
    const openPanel = wrap.querySelector('[data-settings-panel].ribbon-settings-panel-floating:not([hidden])');
    if (!openPanel) return;
    const panelId = openPanel.getAttribute('data-settings-panel');
    const anchor = ribbon.querySelector('[data-ribbon-settings="' + panelId + '"]');
    if (anchor) positionFloatingSettingsPanel(openPanel, anchor);
  });

  /* ── Anpassen: Knopf in der Reiter-Leiste, Rechtsklick auf freie Menüband-Fläche ───────────────────────────────────── */
  function customizeButton() {
    let btn = tabsEl && tabsEl.querySelector('#sfRibbonTabCustomize');
    if (btn) return btn;
    if (!tabsEl) return null;
    btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'sfRibbonTabCustomize';
    btn.className = 'editor-ribbon-tab-customize';
    btn.title = I.title || 'Ribbon anpassen';
    btn.setAttribute('aria-label', btn.title);
    btn.innerHTML = '<span class="editor-ribbon-tab-customize__icon" aria-hidden="true">' + (R ? R.svgIcon('ribbon_customize') : '') + '</span>';
    tabsEl.appendChild(btn);   // rechts in der Reiter-Leiste; HubRibbon setzt die Reiter davor
    return btn;
  }

  function openRibbonCustomize() {
    if (hub) hub.openCustomize();
  }

  function shouldAllowNativeRibbonContextMenu(el) {
    if (!el || !el.closest) return false;
    if (el.closest('input[type="text"], input[type="number"], input[type="search"], textarea, select')) return true;
    if (el.closest('a[href]:not([href="#"])')) return true;
    return false;
  }

  ribbon.addEventListener('contextmenu', (e) => {
    const el = e.target instanceof Element ? e.target : e.target?.parentElement;
    if (!el || !ribbon.contains(el) || shouldAllowNativeRibbonContextMenu(el)) return;
    e.preventDefault();
    e.stopPropagation();
    openRibbonCustomize();
  }, true);

  document.getElementById('ribbonCustomizeBtn')?.addEventListener('click', openRibbonCustomize);
  document.getElementById('presentRibbonCustomizeBtn')?.addEventListener('click', openRibbonCustomize);
  ribbon.addEventListener('click', (e) => {
    const btn = e.target.closest?.('[data-ribbon-command="ribbon_customize"]');
    if (!btn || !ribbon.contains(btn)) return;
    e.preventDefault();
    e.stopPropagation();
    openRibbonCustomize();
  });

  /* ── Start ─────────────────────────────────────────────────────────────────────────────────────────────────────────── */
  function hubI18n() {
    return {
      title: I.title,
      hint: I.livePreviewHint,
      search: I.search,
      categoryAll: I.categoryAll,
      tabAdd: I.tabAdd,
      tabNew: I.newTab,
      groupAdd: I.groupAdd,
      reset: I.reset,
      save: I.save,
      cancel: I.cancel,
      emptyLayout: I.emptyLayout,
      toggleGroup: I.toggleGroup,
      iconSize: I.appearanceIconSize,
      rowsTitle: I.appearanceGroupRows,
      rows1: I.appearanceGroupRows1,
      rows2: I.appearanceGroupRows2,
    };
  }

  function bootRibbon() {
    if (!global.HubRibbon || !R || !tabsEl || !panelsEl) {
      console.warn('[ribbon] HubRibbon (Hub ≥ 2.36.1) oder SFRibbonRenderer fehlt');
      return;
    }
    const i18n = {};
    const raw = hubI18n();
    Object.keys(raw).forEach((k) => { if (raw[k]) i18n[k] = raw[k]; });
    R.beginRender({ root: ribbon, widgetStoreId: boot.widgetStoreId || 'ribbonWidgetTemplates', meta: boot.meta || {}, commands: Object.values(commands) });
    const def = toHub(boot.defaultLayout || boot.layout) || { iconSize: 'medium', hideLabels: false, tabs: [] };
    delete def.known;
    delete def.shortcuts;
    delete def.collapsed;
    delete def.activeTab;
    hub = global.HubRibbon.create({
      root: ribbon,
      body: panelsEl,
      tabsEl: tabsEl,
      panels: panelsEl,
      grid: true,
      commands: commands,
      defaultLayout: def,
      storage: storage,
      categories: categories,
      i18n: i18n,
      dialogId: rootId === 'presentRibbon' ? 'present-ribbon-customize' : 'ribbon-customize',
      customizeButton: customizeButton(),
      dialog: {
        open: (t, h, o) => global.HubFloatDialog.open(t, h, o),
        close: (id) => global.HubFloatDialog.close(id),
        register: (id, fn) => global.HubFloatDialog.register(id, fn),
      },
      classes: {
        tab: 'editor-ribbon-tab',
        tabActive: 'active',
        panel: 'editor-ribbon-panel',
        group: 'ribbon-group',
        groupLabel: 'ribbon-group-label',
        grid: 'ribbon-group-grid',
        cell: 'ribbon-grid-cell',
        cellWidget: 'ribbon-grid-cell--widget',
        cellSep: 'ribbon-grid-cell--separator',
        cellNoLabel: 'ribbon-grid-cell--no-label',
        cellStack: 'ribbon-grid-cell--line-stack',
        lineStack: 'ribbon-line-stack',
        lineRow: 'ribbon-line-stack-row',
        sep: 'ribbon-separator',
        cmdAttr: 'data-ribbon-command',
        varCell: '--ribbon-cell',
        varGap: '--ribbon-cell-gap',
        varGroupRows: '--group-rows',
        varLineCount: '--line-count',
      },
      peekKeepOpen: '[data-settings-menu]',
      beforeRender: () => R.beginRender(),
      renderItem: (cmd, item, ctx) => R.createItem(cmd, item, ctx),
      spanOf: (cmd, item, rows) => R.spanOf(cmd, item, rows),
      isTileable: (cmd) => R.isTileable(cmd),
      spanForSize: (cmd, size) => R.tileSpanFromSize(size, cmd && cmd.kind, cmd && cmd.id),
      sizeOfSpan: (cmd, span) => R.tileSizeFromSpan(span, cmd && cmd.kind, cmd && cmd.id),
      onShortcut: (id, e) => (global.SFShortcuts && typeof global.SFShortcuts.run === 'function' ? global.SFShortcuts.run(id, e) : undefined),
      onRendered: () => {
        /* Bisherige Attribute für SlideForge-CSS (data-ribbon-panel/-group/-tab) */
        ribbon.querySelectorAll('[data-hub-ribbon-panel]').forEach((p) => p.setAttribute('data-ribbon-panel', p.getAttribute('data-hub-ribbon-panel')));
        ribbon.querySelectorAll('.ribbon-group[data-group]').forEach((g) => g.setAttribute('data-ribbon-group', g.getAttribute('data-group')));
        ribbon.querySelectorAll('[data-hub-ribbon-tab]').forEach((t) => t.setAttribute('data-ribbon-tab', t.getAttribute('data-hub-ribbon-tab')));
        R.afterRender(ribbon);
        bindRibbonActions();
        if (global.SFEditorMenus?.init) global.SFEditorMenus.init();
        document.dispatchEvent(new CustomEvent('sf:ribbon-rendered'));
      },
    });
  }

  /* Bisherige Schnittstelle für editor.js/present.js */
  global.SFRibbon = {
    setTab: (name) => hub && hub.setTab(name),
    getActiveTab: () => (hub ? hub.activeTab() : 'start'),
    setCollapsed: (on) => hub && hub.setCollapsed(!!on),
    resetFloatingSettingsPanels: resetFloatingSettingsPanels,
    openCustomize: openRibbonCustomize,
    hub: () => hub,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootRibbon);
  } else {
    bootRibbon();
  }
})(window);
