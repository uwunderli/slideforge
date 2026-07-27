(function (global) {
  'use strict';

  const ribbon = document.getElementById('editorRibbon');
  if (!ribbon) return;

  const boot = global.SF_BOOTSTRAP?.ribbon || {};
  const userId = ribbon.dataset.userId || 'default';
  const storageTab = 'sf_ribbon_tab_' + userId;
  const storageCollapsed = 'sf_ribbon_collapsed_' + userId;

  let tabs = [];
  let panels = [];

  function refreshTabPanels() {
    tabs = [...ribbon.querySelectorAll('[data-ribbon-tab]')];
    panels = [...ribbon.querySelectorAll('[data-ribbon-panel]')];
  }

  function normalizePrefs(prefs) {
    const p = prefs && typeof prefs === 'object' ? prefs : {};
    const iconSize = ['small', 'medium', 'large'].includes(p.iconSize) ? p.iconSize : 'medium';
    return {
      activeTab: p.activeTab || 'start',
      collapsed: !!p.collapsed,
      iconSize,
      showLabels: p.showLabels !== false,
    };
  }

  function applyAppearancePrefs(prefs) {
    const normalized = normalizePrefs(prefs);
    ribbon.dataset.iconSize = normalized.iconSize;
    ribbon.dataset.showLabels = normalized.showLabels ? 'true' : 'false';
    ribbon.classList.toggle('is-labels-hidden', !normalized.showLabels);
    return normalized;
  }

  function setTab(name) {
    refreshTabPanels();
    const valid = tabs.some((t) => t.dataset.ribbonTab === name);
    const tabName = valid ? name : tabs[0]?.dataset.ribbonTab || 'start';
    tabs.forEach((tab) => {
      const active = tab.dataset.ribbonTab === tabName;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach((panel) => {
      const active = panel.dataset.ribbonPanel === tabName;
      panel.hidden = !active;
    });
    try {
      localStorage.setItem(storageTab, tabName);
    } catch (e) { /* ignore */ }

    const layout = global.SFRibbonRenderer?.getLayoutData?.();
    if (layout?.prefs) layout.prefs.activeTab = tabName;
  }

  function getActiveTab() {
    const fromDom = ribbon?.querySelector?.('.editor-ribbon-tabs [data-ribbon-tab].active, .editor-ribbon-tabs [data-ribbon-tab][aria-selected="true"]');
    if (fromDom?.dataset?.ribbonTab) return fromDom.dataset.ribbonTab;
    const layout = global.SFRibbonRenderer?.getLayoutData?.();
    return layout?.prefs?.activeTab || 'start';
  }

  function setPeek(open) {
    const show = !!open && ribbon.classList.contains('is-collapsed');
    ribbon.classList.toggle('is-peek', show);
  }

  function setCollapsed(collapsed, opts) {
    const persist = !opts || opts.persist !== false;
    ribbon.classList.toggle('is-collapsed', !!collapsed);
    if (!collapsed) setPeek(false);
    if (!persist) return;
    try {
      localStorage.setItem(storageCollapsed, collapsed ? '1' : '0');
    } catch (e) { /* ignore */ }
    const layout = global.SFRibbonRenderer?.getLayoutData?.();
    if (layout?.prefs) layout.prefs.collapsed = !!collapsed;
  }

  function isCollapsed() {
    return ribbon.classList.contains('is-collapsed');
  }

  function isPeekOpen() {
    return ribbon.classList.contains('is-peek');
  }

  function closePeekUnlessTo(related) {
    if (!isCollapsed() || !isPeekOpen()) return;
    const el = eventElement(related);
    if (el && (ribbon.contains(el) || el.closest?.('[data-settings-menu]'))) return;
    setPeek(false);
  }

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

  function bindTabEvents() {
    refreshTabPanels();
    tabs.forEach((tab) => {
      tab.replaceWith(tab.cloneNode(true));
    });
    refreshTabPanels();
    tabs.forEach((tab) => {
      tab.addEventListener('click', (e) => {
        /* Zweiter Klick eines Doppelklicks ignorieren (detail >= 2). */
        if (e.detail > 1) return;
        const name = tab.dataset.ribbonTab;
        if (isCollapsed()) {
          if (!tab.classList.contains('active')) setTab(name);
          setPeek(true);
          return;
        }
        if (tab.classList.contains('active')) return;
        setTab(name);
      });
      tab.addEventListener('dblclick', (e) => {
        e.preventDefault();
        const nextCollapsed = !isCollapsed();
        setPeek(false);
        setCollapsed(nextCollapsed);
      });
    });
  }

  function bindRibbonPeekClose() {
    if (ribbon.dataset.peekBound === '1') return;
    ribbon.dataset.peekBound = '1';
    ribbon.addEventListener('mouseleave', (e) => {
      closePeekUnlessTo(e.relatedTarget);
    });
    document.addEventListener('mousedown', (e) => {
      if (!isPeekOpen()) return;
      const el = eventElement(e.target);
      if (el && (ribbon.contains(el) || el.closest?.('[data-settings-menu]'))) return;
      setPeek(false);
    }, true);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && isPeekOpen()) setPeek(false);
    });
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
        const alreadyOpen = !panel.hidden
          && panel.classList.contains('ribbon-settings-panel-floating');
        resetFloatingSettingsPanels();
        wrap.querySelectorAll('[data-settings-panel]').forEach((p) => { p.hidden = true; });
        ribbon.querySelectorAll('[data-ribbon-settings].active').forEach((b) => {
          b.classList.remove('active');
        });
        if (alreadyOpen) return;
        panel.hidden = false;
        btn.classList.add('active');
        positionFloatingSettingsPanel(panel, btn);
        document.dispatchEvent(new CustomEvent('sf:ribbon-settings-open', {
          detail: { panelId: panelId },
        }));
      });
    });
  }

  function rebindTabs() {
    bindTabEvents();
    bindRibbonActions();
    bindRibbonPeekClose();
    if (global.SFEditorMenus?.init) {
      global.SFEditorMenus.init();
    }
  }

  global.SFRibbon = {
    setTab,
    getActiveTab,
    setCollapsed,
    setPeek,
    resetFloatingSettingsPanels,
    normalizePrefs,
    applyAppearancePrefs,
    _rebindTabs: rebindTabs,
    getLayout: () => global.SFRibbonRenderer?.getLayoutData?.(),
    applyLayout: (layout, opts) => {
      global.SFRibbonRenderer?.setLayoutData?.(layout);
      const prefs = applyAppearancePrefs(layout?.prefs || {});
      setCollapsed(!!prefs.collapsed, {
        persist: !opts || opts.persistCollapsed !== false,
      });
      rebindTabs();
      const tab = prefs.activeTab || layout?.prefs?.activeTab;
      if (tab) setTab(tab);
    },
  };

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-ribbon-settings]') || e.target.closest('[data-settings-menu]')) return;
    const wrap = document.querySelector('[data-settings-menu]');
    if (!wrap) return;
    const openPanel = wrap.querySelector('[data-settings-panel]:not([hidden])');
    if (!openPanel) return;
    openPanel.hidden = true;
    resetFloatingSettingsPanels();
    ribbon.querySelectorAll('[data-ribbon-settings].active').forEach((b) => {
      b.classList.remove('active');
    });
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

  function eventElement(target) {
    if (!target) return null;
    if (target instanceof Element) return target;
    return target.parentElement || null;
  }

  function isInsideRibbon(el) {
    return !!(el && ribbon.contains(el));
  }

  function shouldAllowNativeRibbonContextMenu(el) {
    if (!el || !el.closest) return false;
    if (el.closest('input[type="text"], input[type="number"], input[type="search"], textarea, select')) return true;
    if (el.closest('a[href]:not([href="#"])')) return true;
    return false;
  }

  function openRibbonCustomize() {
    try {
      if (global.SFRibbonCustomize?.open) {
        global.SFRibbonCustomize.open();
        return;
      }
      console.warn('[ribbon] SFRibbonCustomize is not loaded');
    } catch (err) {
      console.error('[ribbon] customize open failed', err);
    }
  }

  function openRibbonCustomizeFromContext(e) {
    const el = eventElement(e.target);
    if (!isInsideRibbon(el)) return;
    if (shouldAllowNativeRibbonContextMenu(el)) return;
    e.preventDefault();
    e.stopPropagation();
    openRibbonCustomize();
  }

  // Capture + mousedown (button 2): deaktivierte Buttons feuern in manchen
  // Browsern kein contextmenu; pointer-events:none auf :disabled hilft zusätzlich.
  ribbon.addEventListener('contextmenu', openRibbonCustomizeFromContext, true);
  ribbon.addEventListener('mousedown', (e) => {
    if (e.button !== 2) return;
    const el = eventElement(e.target);
    if (!isInsideRibbon(el) || shouldAllowNativeRibbonContextMenu(el)) return;
    e.preventDefault();
    openRibbonCustomize();
  }, true);

  document.getElementById('ribbonCustomizeBtn')?.addEventListener('click', () => {
    openRibbonCustomize();
  });

  function bootRibbon() {
    if (global.SFRibbonRenderer && boot.layout) {
      global.SFRibbonRenderer.init({
        layout: boot.layout,
        commands: boot.commands || [],
        meta: boot.meta || {},
      });
    }

    let savedTab = boot.layout?.prefs?.activeTab || 'start';
    let savedCollapsed = !!boot.layout?.prefs?.collapsed;
    try {
      savedTab = localStorage.getItem(storageTab) || savedTab;
      savedCollapsed = localStorage.getItem(storageCollapsed) === '1' || savedCollapsed;
    } catch (e) { /* ignore */ }

    rebindTabs();
    applyAppearancePrefs(boot.layout?.prefs || {});
    setTab(savedTab);
    setCollapsed(savedCollapsed);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootRibbon);
  } else {
    bootRibbon();
  }
})(window);
