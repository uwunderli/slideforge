(function (global) {
  'use strict';

  const boot = global.SF_BOOTSTRAP?.ribbon || {};
  const I = boot.i18n || {};
  let backdrop = null;
  let panel = null;
  let editingLayout = null;
  let originalLayout = null;
  let selectedTabId = null;
  let selectedGroupId = null;
  let dragPayload = null;
  let previewTimer = null;
  let collapsedTabs = new Set();
  let collapsedGroups = new Set();

  const PANEL_SIZE_KEY = 'sf_ribbon_customize_panel_size';

  function appearanceIcon(name) {
    const icons = {
      sizeSmall:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<rect x="7" y="7" width="4" height="4" rx="0.75"/><rect x="13" y="7" width="4" height="4" rx="0.75"/>' +
        '<rect x="7" y="13" width="4" height="4" rx="0.75"/><rect x="13" y="13" width="4" height="4" rx="0.75"/></svg>',
      sizeMedium:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<rect x="5.5" y="5.5" width="6" height="6" rx="1"/><rect x="12.5" y="5.5" width="6" height="6" rx="1"/>' +
        '<rect x="5.5" y="12.5" width="6" height="6" rx="1"/><rect x="12.5" y="12.5" width="6" height="6" rx="1"/></svg>',
      sizeLarge:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/>' +
        '<rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg>',
      labelsOn:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<rect x="5" y="4" width="14" height="11" rx="1.5"/><path d="M8 18h8"/></svg>',
      labelsOff:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<rect x="5" y="6" width="14" height="12" rx="1.5"/></svg>',
      rows1:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<rect x="5" y="10" width="14" height="4" rx="1"/></svg>',
      rows2:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<rect x="5" y="5" width="14" height="4" rx="1"/><rect x="5" y="15" width="14" height="4" rx="1"/></svg>',
      tileSmall:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<rect x="8" y="8" width="8" height="8" rx="1"/></svg>',
      tileLarge:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<rect x="4" y="4" width="16" height="16" rx="1.5"/></svg>',
      separator:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">' +
        '<path d="M12 4v16"/></svg>',
      row_separator:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M19 5v6a3 3 0 0 1-3 3H7"/><path d="M10 10l-4 4 4 4"/></svg>',
    };
    return icons[name] || '';
  }

  function ensureEditingPrefs() {
    if (!editingLayout) return {};
    if (!editingLayout.prefs || typeof editingLayout.prefs !== 'object') {
      editingLayout.prefs = {};
    }
    if (global.SFRibbon?.normalizePrefs) {
      editingLayout.prefs = global.SFRibbon.normalizePrefs(editingLayout.prefs);
    } else {
      editingLayout.prefs.iconSize = editingLayout.prefs.iconSize || 'medium';
      editingLayout.prefs.showLabels = editingLayout.prefs.showLabels !== false;
    }
    return editingLayout.prefs;
  }

  function mergePreviewPrefs() {
    const prefs = ensureEditingPrefs();
    return {
      activeTab: selectedTabId || prefs.activeTab || editingLayout?.tabs?.[0]?.id || 'start',
      /* Eingeklappt-Zustand aus dem Dialog-Layout, nicht aus der Live-DOM-Vorschau. */
      collapsed: !!prefs.collapsed,
      iconSize: prefs.iconSize || 'medium',
      showLabels: prefs.showLabels !== false,
    };
  }

  function renderAppearanceControls() {
    if (!backdrop) return;
    const prefs = ensureEditingPrefs();
    backdrop.querySelectorAll('[data-appearance-icon-size]').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.appearanceIconSize === prefs.iconSize);
      btn.setAttribute('aria-pressed', btn.dataset.appearanceIconSize === prefs.iconSize ? 'true' : 'false');
    });
    const labelsBtn = backdrop.querySelector('#ribbonCustomizeToggleLabels');
    if (labelsBtn) {
      labelsBtn.classList.toggle('active', prefs.showLabels !== false);
      labelsBtn.setAttribute('aria-pressed', prefs.showLabels !== false ? 'true' : 'false');
      labelsBtn.innerHTML = appearanceIcon(prefs.showLabels !== false ? 'labelsOn' : 'labelsOff');
      labelsBtn.title = prefs.showLabels !== false
        ? (I.appearanceLabelsHide || 'Beschriftungen ausblenden')
        : (I.appearanceLabelsShow || 'Beschriftungen anzeigen');
    }
  }

  function initAppearanceControls() {
    const sizeWrap = backdrop.querySelector('#ribbonCustomizeIconSize');
    if (!sizeWrap || sizeWrap.dataset.wired) return;
    sizeWrap.dataset.wired = '1';
    [
      ['small', 'sizeSmall', I.appearanceIconSmall || 'Kleine Symbole'],
      ['medium', 'sizeMedium', I.appearanceIconMedium || 'Mittlere Symbole'],
      ['large', 'sizeLarge', I.appearanceIconLarge || 'Grosse Symbole'],
    ].forEach(([size, icon, title]) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ribbon-customize-tool-btn';
      btn.dataset.appearanceIconSize = size;
      btn.title = title;
      btn.setAttribute('aria-pressed', 'false');
      btn.innerHTML = appearanceIcon(icon);
      btn.addEventListener('click', () => {
        const prefs = ensureEditingPrefs();
        prefs.iconSize = size;
        renderAppearanceControls();
        if (global.SFRibbon?.applyAppearancePrefs) {
          global.SFRibbon.applyAppearancePrefs(mergePreviewPrefs());
        }
        layoutChanged();
      });
      sizeWrap.appendChild(btn);
    });

    const labelsBtn = backdrop.querySelector('#ribbonCustomizeToggleLabels');
    if (labelsBtn && !labelsBtn.dataset.wired) {
      labelsBtn.dataset.wired = '1';
      labelsBtn.addEventListener('click', () => {
        const prefs = ensureEditingPrefs();
        prefs.showLabels = prefs.showLabels === false;
        renderAppearanceControls();
        layoutChanged();
      });
    }
    renderAppearanceControls();
  }

  function setGroupRows(tabId, groupId, rows) {
    const group = findGroup(tabId, groupId);
    if (!group) return;
    const nextRows = parseInt(rows, 10) === 1 ? 1 : 2;
    if (nextRows === 2) {
      delete group.rows;
    } else {
      group.rows = 1;
    }
    layoutChanged();
  }

  function groupRowsHtml(tabId, group) {
    const rows = parseInt(group.rows, 10) === 1 ? 1 : 2;
    return '<div class="ribbon-customize-group-rows" role="group" aria-label="' + escapeHtml(I.appearanceGroupRows || 'Zeilen') + '">' +
      '<button type="button" class="ribbon-customize-tool-btn' + (rows === 1 ? ' active' : '') + '" data-group-rows="1" data-tab-id="' + escapeHtml(tabId) + '" data-group-id="' + escapeHtml(group.id) + '" title="' + escapeHtml(I.appearanceGroupRows1 || 'Eine Zeile') + '" aria-pressed="' + (rows === 1) + '">' + appearanceIcon('rows1') + '</button>' +
      '<button type="button" class="ribbon-customize-tool-btn' + (rows === 2 ? ' active' : '') + '" data-group-rows="2" data-tab-id="' + escapeHtml(tabId) + '" data-group-id="' + escapeHtml(group.id) + '" title="' + escapeHtml(I.appearanceGroupRows2 || 'Zwei Zeilen') + '" aria-pressed="' + (rows === 2) + '">' + appearanceIcon('rows2') + '</button>' +
      '</div>';
  }

  function itemCommandMeta(item) {
    return global.SFRibbonRenderer?.commandIndex?.()?.[item.id]
      || catalogEntry(item.id)
      || { id: item.id, kind: item.id === 'separator' ? 'separator' : (item.id === 'row_separator' ? 'row_separator' : 'command'), label: item.label || item.id };
  }

  function resolveItemSpan(item) {
    const cmd = itemCommandMeta(item);
    if (global.SFRibbonRenderer?.getGridSpan) {
      return global.SFRibbonRenderer.getGridSpan(cmd, item);
    }
    return item.gridSpan || cmd.gridSpan || { cols: 1, rows: 1 };
  }

  function itemTileSize(item) {
    const cmd = itemCommandMeta(item);
    const span = resolveItemSpan(item);
    if (global.SFRibbonRenderer?.tileSizeFromSpan) {
      return global.SFRibbonRenderer.tileSizeFromSpan(span, cmd.kind, cmd.id || item.id);
    }
    return (span.cols || 1) >= 2 && (span.rows || 1) >= 2 ? 'large' : 'small';
  }

  function canSetItemTile(item) {
    const cmd = itemCommandMeta(item);
    if (global.SFRibbonRenderer?.isTileable) {
      return global.SFRibbonRenderer.isTileable(cmd);
    }
    return cmd.kind !== 'widget';
  }

  function setItemTileSize(instanceId, size) {
    const found = findItem(instanceId);
    if (!found || !canSetItemTile(found.item)) return;
    const cmd = itemCommandMeta(found.item);
    const span = global.SFRibbonRenderer?.tileSpanFromSize
      ? global.SFRibbonRenderer.tileSpanFromSize(size, cmd.kind, cmd.id || found.item.id)
      : (size === 'large' ? { cols: 2, rows: 2 } : { cols: 1, rows: 1 });
    if (cmd.kind === 'separator') {
      found.item.gridSpan = { cols: 1, rows: span.rows };
    } else {
      found.item.gridSpan = span;
    }
    /* Grosses Symbol (2×2) bzw. zweizeiliger Trenner/Übergang braucht Gruppe mit 2 Zeilen —
       sonst clampSpan → optisch keine Änderung. */
    const needsTwoRows = span.rows >= 2;
    if (needsTwoRows && parseInt(found.group.rows, 10) === 1) {
      delete found.group.rows;
    }
    layoutChanged();
  }

  function itemShowLabel(item) {
    return item.showLabel !== false;
  }

  function setItemShowLabel(instanceId, show) {
    const found = findItem(instanceId);
    if (!found || !canSetItemTile(found.item)) return;
    if (show) {
      delete found.item.showLabel;
    } else {
      found.item.showLabel = false;
    }
    layoutChanged();
  }

  function itemTileHtml(item) {
    if (!canSetItemTile(item)) return '';
    const size = itemTileSize(item);
    const cmd = itemCommandMeta(item);
    const isSep = cmd.kind === 'separator';
    const isTransition = (cmd.id || item.id) === 'widget:slide_transition';
    const found = findItem(item.instanceId);
    const groupOneRow = found && parseInt(found.group.rows, 10) === 1;
    let titleSmall = isSep
      ? (I.itemTileSep1 || 'Trenner eine Zeile')
      : (I.itemTileSmall || 'Klein (1×1)');
    let titleLarge = isSep
      ? (I.itemTileSep2 || 'Trenner zwei Zeilen')
      : (I.itemTileLarge || 'Gross (2×2)');
    if (isTransition) {
      titleSmall = I.itemTileTransition1 || 'Übergang eine Zeile';
      titleLarge = I.itemTileTransition2 || 'Übergang zwei Zeilen';
    }
    if (groupOneRow && !isSep && size !== 'small') {
      titleLarge = I.itemTileLargeNeedsRows || (titleLarge + ' — Gruppe wird auf zwei Zeilen gestellt');
    }
    if (groupOneRow && isTransition) {
      titleLarge = I.itemTileTransition2NeedsRows || ((I.itemTileTransition2 || 'Übergang zwei Zeilen') + ' — Gruppe wird auf zwei Zeilen gestellt');
    }
    const labelOn = itemShowLabel(item);
    const labelToggle = (isSep || isTransition) ? '' : (
      '<button type="button" class="ribbon-customize-tool-btn' + (labelOn ? ' active' : '') + '" data-item-label="' + (labelOn ? '0' : '1') + '" data-instance-id="' + escapeHtml(item.instanceId) + '" title="' + escapeHtml(labelOn ? (I.itemLabelHide || 'Beschriftung aus') : (I.itemLabelShow || 'Beschriftung ein')) + '" aria-pressed="' + labelOn + '">' +
      '<span class="ribbon-customize-tool-btn-letter' + (labelOn ? '' : ' is-off') + '" aria-hidden="true">T</span>' +
      '</button>'
    );
    return '<div class="ribbon-customize-item-tiles" role="group">' +
      '<button type="button" class="ribbon-customize-tool-btn' + (size === 'small' ? ' active' : '') + '" data-item-tile="small" data-instance-id="' + escapeHtml(item.instanceId) + '" title="' + escapeHtml(titleSmall) + '" aria-pressed="' + (size === 'small') + '">' + appearanceIcon(isSep || isTransition ? 'rows1' : 'tileSmall') + '</button>' +
      '<button type="button" class="ribbon-customize-tool-btn' + (size === 'large' ? ' active' : '') + '" data-item-tile="large" data-instance-id="' + escapeHtml(item.instanceId) + '" title="' + escapeHtml(titleLarge) + '" aria-pressed="' + (size === 'large') + '">' + appearanceIcon(isSep || isTransition ? 'rows2' : 'tileLarge') + '</button>' +
      labelToggle +
      '</div>';
  }

  function ensureDom() {
    if (backdrop) return;
    backdrop = document.createElement('div');
    backdrop.className = 'ribbon-customize-backdrop';
    backdrop.id = 'ribbonCustomizeBackdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    backdrop.innerHTML =
      '<div class="ribbon-customize-panel" role="dialog" aria-modal="false" aria-labelledby="ribbonCustomizeTitle">' +
      '<header class="sf-dialog-header ribbon-customize-header" id="ribbonCustomizeDragHandle">' +
      '<h2 class="sf-dialog-title" id="ribbonCustomizeTitle"></h2>' +
      '<button type="button" class="sf-dialog-close ribbon-customize-close" id="ribbonCustomizeClose" aria-label="">×</button>' +
      '</header>' +
      '<p class="sf-dialog-hint ribbon-customize-hint" id="ribbonCustomizeHint"></p>' +
      '<div class="ribbon-customize-appearance" id="ribbonCustomizeAppearance">' +
      '<div class="ribbon-customize-appearance-row">' +
      '<span class="ribbon-customize-appearance-label">' + escapeHtml(I.appearanceIconSize || 'Symbolgrösse') + '</span>' +
      '<div class="ribbon-customize-icon-tools" id="ribbonCustomizeIconSize" role="group" aria-label="' + escapeHtml(I.appearanceIconSize || 'Symbolgrösse') + '"></div>' +
      '<button type="button" class="ribbon-customize-tool-btn" id="ribbonCustomizeToggleLabels" aria-pressed="true"></button>' +
      '</div>' +
      '</div>' +
      '<div class="ribbon-customize-body">' +
      '<div class="ribbon-customize-col ribbon-customize-catalog">' +
      '<label class="ribbon-customize-search-label" for="ribbonCustomizeSearch"></label>' +
      '<input type="search" class="ribbon-customize-search" id="ribbonCustomizeSearch" autocomplete="off">' +
      '<select class="ribbon-customize-category" id="ribbonCustomizeCategory"></select>' +
      '<ul class="ribbon-customize-command-list" id="ribbonCustomizeCommandList"></ul>' +
      '</div>' +
      '<div class="ribbon-customize-col ribbon-customize-layout">' +
      '<div class="ribbon-customize-layout-toolbar">' +
      '<button type="button" class="button button-ghost button-sm" id="ribbonCustomizeTabAdd"></button>' +
      '<button type="button" class="button button-ghost button-sm" id="ribbonCustomizeTabRename"></button>' +
      '<button type="button" class="button button-ghost button-sm" id="ribbonCustomizeTabDelete"></button>' +
      '</div>' +
      '<div class="ribbon-customize-tabs" id="ribbonCustomizeTabs"></div>' +
      '<div class="ribbon-customize-group-actions">' +
      '<button type="button" class="button button-ghost button-sm" id="ribbonCustomizeGroupAdd"></button>' +
      '<button type="button" class="button button-ghost button-sm" id="ribbonCustomizeGroupRename"></button>' +
      '<button type="button" class="button button-ghost button-sm" id="ribbonCustomizeGroupDelete"></button>' +
      '<button type="button" class="button button-ghost button-sm" id="ribbonCustomizeSepAdd"></button>' +
      '<button type="button" class="button button-ghost button-sm" id="ribbonCustomizeRowSepAdd"></button>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '<div class="sf-dialog-actions ribbon-customize-actions">' +
      '<button type="button" class="button button-ghost" id="ribbonCustomizeReset"></button>' +
      '<button type="button" class="button button-ghost" id="ribbonCustomizeCancel"></button>' +
      '<button type="button" class="button" id="ribbonCustomizeSave"></button>' +
      '</div>' +
      '<div class="ribbon-customize-resize-handle ribbon-customize-resize-handle--e" data-resize="e" aria-hidden="true"></div>' +
      '<div class="ribbon-customize-resize-handle ribbon-customize-resize-handle--s" data-resize="s" aria-hidden="true"></div>' +
      '<div class="ribbon-customize-resize-handle ribbon-customize-resize-handle--se" data-resize="se" id="ribbonCustomizeResizeHandle" aria-hidden="true" title="Grösse ändern"></div>' +
      '</div>';
    document.body.appendChild(backdrop);
    panel = backdrop.querySelector('.ribbon-customize-panel');

    backdrop.querySelector('#ribbonCustomizeTitle').textContent = I.title || 'Ribbon anpassen';
    backdrop.querySelector('#ribbonCustomizeHint').textContent = I.livePreviewHint || 'Änderungen werden sofort im Ribbon oben angezeigt.';
    backdrop.querySelector('.ribbon-customize-search-label').textContent = I.search || 'Suchen';
    backdrop.querySelector('#ribbonCustomizeClose').setAttribute('aria-label', I.cancel || 'Schliessen');
    backdrop.querySelector('#ribbonCustomizeTabAdd').textContent = I.tabAdd || 'Neuer Tab';
    backdrop.querySelector('#ribbonCustomizeTabRename').textContent = I.tabRename || 'Tab umbenennen';
    backdrop.querySelector('#ribbonCustomizeTabDelete').textContent = I.tabDelete || 'Tab löschen';
    backdrop.querySelector('#ribbonCustomizeGroupAdd').textContent = I.groupAdd || 'Neue Gruppe';
    backdrop.querySelector('#ribbonCustomizeGroupRename').textContent = I.groupRename || 'Gruppe umbenennen';
    backdrop.querySelector('#ribbonCustomizeGroupDelete').textContent = I.groupDelete || 'Gruppe löschen';
    backdrop.querySelector('#ribbonCustomizeSepAdd').textContent = I.separatorAdd || 'Trenner';
    backdrop.querySelector('#ribbonCustomizeRowSepAdd').textContent = I.rowSeparatorAdd || 'Zeilentrenner';
    backdrop.querySelector('#ribbonCustomizeReset').textContent = I.reset || 'Zurücksetzen';
    backdrop.querySelector('#ribbonCustomizeCancel').textContent = I.cancel || 'Abbrechen';
    backdrop.querySelector('#ribbonCustomizeSave').textContent = I.save || 'Speichern';

    backdrop.querySelector('#ribbonCustomizeClose').addEventListener('click', cancel);
    backdrop.querySelector('#ribbonCustomizeCancel').addEventListener('click', cancel);
    backdrop.querySelector('#ribbonCustomizeSave').addEventListener('click', save);
    backdrop.querySelector('#ribbonCustomizeReset').addEventListener('click', reset);
    backdrop.querySelector('#ribbonCustomizeTabAdd').addEventListener('click', addTab);
    backdrop.querySelector('#ribbonCustomizeTabRename').addEventListener('click', renameTab);
    backdrop.querySelector('#ribbonCustomizeTabDelete').addEventListener('click', deleteTab);
    backdrop.querySelector('#ribbonCustomizeGroupAdd').addEventListener('click', addGroup);
    backdrop.querySelector('#ribbonCustomizeGroupRename').addEventListener('click', renameGroup);
    backdrop.querySelector('#ribbonCustomizeGroupDelete').addEventListener('click', deleteGroup);
    backdrop.querySelector('#ribbonCustomizeSepAdd').addEventListener('click', () => addCommand('separator'));
    backdrop.querySelector('#ribbonCustomizeRowSepAdd').addEventListener('click', () => addCommand('row_separator'));
    backdrop.querySelector('#ribbonCustomizeSearch').addEventListener('input', renderCatalog);
    backdrop.querySelector('#ribbonCustomizeCategory').addEventListener('change', renderCatalog);

    initAppearanceControls();
    initDragPanel();
    initResizePanel();
  }

  function initCollapseState() {
    collapsedTabs = new Set((editingLayout?.tabs || []).map((t) => t.id));
    collapsedGroups = new Set();
    (editingLayout?.tabs || []).forEach((tab) => {
      (tab.groups || []).forEach((group) => {
        collapsedGroups.add(tab.id + ':' + group.id);
      });
    });
  }

  function groupKey(tabId, groupId) {
    return tabId + ':' + groupId;
  }

  function isTabCollapsed(tabId) {
    return collapsedTabs.has(tabId);
  }

  function isGroupCollapsed(tabId, groupId) {
    return collapsedGroups.has(groupKey(tabId, groupId));
  }

  function expandTab(tabId) {
    collapsedTabs.delete(tabId);
  }

  function expandGroup(tabId, groupId) {
    expandTab(tabId);
    collapsedGroups.delete(groupKey(tabId, groupId));
  }

  function toggleTabCollapsed(tabId) {
    if (collapsedTabs.has(tabId)) collapsedTabs.delete(tabId);
    else collapsedTabs.add(tabId);
  }

  function toggleGroupCollapsed(tabId, groupId) {
    const key = groupKey(tabId, groupId);
    if (collapsedGroups.has(key)) collapsedGroups.delete(key);
    else collapsedGroups.add(key);
  }

  function restorePanelSize() {
    if (!panel) return;
    try {
      const raw = localStorage.getItem(PANEL_SIZE_KEY);
      if (!raw) return;
      const size = JSON.parse(raw);
      const maxW = Math.max(520, window.innerWidth - 16);
      const maxH = Math.max(280, window.innerHeight - 16);
      if (size.width) {
        panel.style.width = Math.min(maxW, Math.max(520, Number(size.width) || 520)) + 'px';
      }
      if (size.height) {
        panel.style.height = Math.min(maxH, Math.max(280, Number(size.height) || 280)) + 'px';
      }
      if (typeof size.left === 'number' && typeof size.top === 'number') {
        const left = Math.max(8, Math.min(window.innerWidth - 80, size.left));
        const top = Math.max(8, Math.min(window.innerHeight - 80, size.top));
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
      }
    } catch (e) { /* ignore */ }
  }

  function storePanelSize() {
    if (!panel) return;
    try {
      const rect = panel.getBoundingClientRect();
      localStorage.setItem(PANEL_SIZE_KEY, JSON.stringify({
        width: Math.round(rect.width),
        height: Math.round(rect.height),
        left: Math.round(rect.left),
        top: Math.round(rect.top),
      }));
    } catch (e) { /* ignore */ }
  }

  function anchorPanelPosition() {
    if (!panel) return;
    const rect = panel.getBoundingClientRect();
    panel.style.left = rect.left + 'px';
    panel.style.top = rect.top + 'px';
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
  }

  function initResizePanel() {
    if (!backdrop || backdrop.dataset.resizeWired === '1') return;
    backdrop.dataset.resizeWired = '1';
    const handles = backdrop.querySelectorAll('[data-resize]');
    if (!handles.length) return;

    handles.forEach((handle) => {
      handle.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();
        const mode = handle.getAttribute('data-resize') || 'se';
        anchorPanelPosition();
        const rect = panel.getBoundingClientRect();
        const startX = e.clientX;
        const startY = e.clientY;
        const startW = rect.width;
        const startH = rect.height;
        const startLeft = rect.left;
        const startTop = rect.top;
        panel.classList.add('is-resizing');

        const onMove = (ev) => {
          const maxW = window.innerWidth - startLeft - 8;
          const maxH = window.innerHeight - startTop - 8;
          let nextW = startW;
          let nextH = startH;
          if (mode === 'e' || mode === 'se') {
            nextW = Math.max(520, Math.min(maxW, startW + ev.clientX - startX));
          }
          if (mode === 's' || mode === 'se') {
            nextH = Math.max(280, Math.min(maxH, startH + ev.clientY - startY));
          }
          panel.style.width = nextW + 'px';
          panel.style.height = nextH + 'px';
        };
        const onUp = () => {
          panel.classList.remove('is-resizing');
          storePanelSize();
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup', onUp);
        };
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
      });
    });
  }

  function initDragPanel() {
    const handle = backdrop.querySelector('#ribbonCustomizeDragHandle');
    if (!handle || handle.dataset.wired === '1') return;
    handle.dataset.wired = '1';
    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;

    handle.addEventListener('mousedown', (e) => {
      if (e.button !== 0 || e.target.closest('button')) return;
      e.preventDefault();
      const rect = panel.getBoundingClientRect();
      panel.style.left = rect.left + 'px';
      panel.style.top = rect.top + 'px';
      panel.style.right = 'auto';
      panel.style.bottom = 'auto';
      startX = e.clientX;
      startY = e.clientY;
      startLeft = rect.left;
      startTop = rect.top;
      const onMove = (ev) => {
        const nextLeft = Math.max(8, Math.min(window.innerWidth - 80, startLeft + ev.clientX - startX));
        const nextTop = Math.max(8, Math.min(window.innerHeight - 80, startTop + ev.clientY - startY));
        panel.style.left = nextLeft + 'px';
        panel.style.top = nextTop + 'px';
      };
      const onUp = () => {
        storePanelSize();
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
      };
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    });
  }

  function cloneLayout(layout) {
    return JSON.parse(JSON.stringify(layout));
  }

  function catalogEntries() {
    return boot.catalog || [];
  }

  function catalogEntry(cmdId) {
    return catalogEntries().find((c) => c.id === cmdId) || null;
  }

  function categoryLabel(cat) {
    const map = I.categories || {};
    return map[cat] || cat;
  }

  function isWidget(cmdId) {
    const entry = catalogEntry(cmdId);
    return entry?.kind === 'widget' || String(cmdId).startsWith('widget:');
  }

  function uid(prefix) {
    return prefix + '_' + Math.random().toString(36).slice(2, 9);
  }

  function ensureInstanceIds(layout) {
    (layout?.tabs || []).forEach((tab) => {
      (tab.groups || []).forEach((group) => {
        (group.items || []).forEach((item) => {
          if (!item.instanceId) item.instanceId = uid('item');
        });
      });
    });
  }

  function iconHtml(entry) {
    if (global.SFRibbonRenderer?.catalogIconHtml) {
      return global.SFRibbonRenderer.catalogIconHtml(entry);
    }
    return '';
  }

  function currentTab() {
    return editingLayout?.tabs?.find((t) => t.id === selectedTabId) || null;
  }

  function pruneEmptyGroups(layout) {
    (layout?.tabs || []).forEach((tab) => {
      tab.groups = (tab.groups || []).filter((g) => (g.items || []).length > 0);
    });
  }

  function removeCommandInstances(cmdId, exceptInstanceId) {
    (editingLayout?.tabs || []).forEach((tab) => {
      (tab.groups || []).forEach((group) => {
        group.items = (group.items || []).filter((item) => {
          if (item.id !== cmdId) return true;
          if (exceptInstanceId && item.instanceId === exceptInstanceId) return true;
          return false;
        });
      });
    });
    pruneEmptyGroups(editingLayout);
  }

  function findItem(instanceId) {
    for (const tab of editingLayout?.tabs || []) {
      for (const group of tab.groups || []) {
        const idx = (group.items || []).findIndex((i) => i.instanceId === instanceId);
        if (idx >= 0) {
          return { tab, group, index: idx, item: group.items[idx] };
        }
      }
    }
    return null;
  }

  function layoutChanged() {
    renderTree();
    schedulePreview();
  }

  function schedulePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(applyPreview, 60);
  }

  function clearPreviewTimer() {
    if (previewTimer) {
      clearTimeout(previewTimer);
      previewTimer = null;
    }
  }

  function applyLayoutAndNotify(layout) {
    if (!layout || !global.SFRibbon?.applyLayout) return;
    global.SFRibbon.applyLayout(cloneLayout(layout));
    document.dispatchEvent(new CustomEvent('sf:ribbon-rendered'));
  }

  function applyPreview() {
    previewTimer = null;
    if (!editingLayout || !global.SFRibbon?.applyLayout) return;
    const preview = cloneLayout(editingLayout);
    preview.prefs = mergePreviewPrefs();
    /* Während Anpassen immer ausgeklappt, sonst sind Symbolgrösse & Kacheln unsichtbar. */
    const collapsedPref = !!preview.prefs.collapsed;
    preview.prefs.collapsed = false;
    global.SFRibbon.applyLayout(preview, { persistCollapsed: false });
    if (editingLayout.prefs) editingLayout.prefs.collapsed = collapsedPref;
    if (selectedTabId && global.SFRibbon.setTab) {
      global.SFRibbon.setTab(selectedTabId);
    }
  }

  function restoreOriginalLayout() {
    clearPreviewTimer();
    if (!originalLayout) return;
    applyLayoutAndNotify(originalLayout);
  }

  function renderCategoryFilter() {
    const select = backdrop.querySelector('#ribbonCustomizeCategory');
    const cats = [...new Set(catalogEntries().map((c) => c.category))];
    select.innerHTML = '<option value="">' + escapeHtml(I.categoryAll || 'Alle Kategorien') + '</option>' +
      cats.map((c) => '<option value="' + escapeHtml(c) + '">' + escapeHtml(categoryLabel(c)) + '</option>').join('');
  }

  function renderCatalog() {
    const q = backdrop.querySelector('#ribbonCustomizeSearch').value.trim().toLowerCase();
    const cat = backdrop.querySelector('#ribbonCustomizeCategory').value;
    const list = backdrop.querySelector('#ribbonCustomizeCommandList');
    const items = catalogEntries().filter((cmd) => {
      if (cat && cmd.category !== cat) return false;
      if (q && !cmd.label.toLowerCase().includes(q)) return false;
      return true;
    });
    list.innerHTML = items.map((cmd) =>
      '<li draggable="true" class="ribbon-customize-catalog-item" data-cmd-id="' + escapeHtml(cmd.id) + '">' +
      '<button type="button" class="ribbon-customize-cmd" data-cmd-id="' + escapeHtml(cmd.id) + '">' +
      iconHtml(cmd) +
      '<span class="ribbon-customize-cmd-label">' + escapeHtml(cmd.label) + '</span>' +
      '</button></li>'
    ).join('');

    list.querySelectorAll('.ribbon-customize-catalog-item').forEach((row) => {
      row.addEventListener('dragstart', (e) => {
        dragPayload = { type: 'catalog', cmdId: row.dataset.cmdId };
        e.dataTransfer.effectAllowed = 'copy';
        e.dataTransfer.setData('text/plain', row.dataset.cmdId);
        row.classList.add('is-dragging');
      });
      row.addEventListener('dragend', () => {
        row.classList.remove('is-dragging');
        dragPayload = null;
        clearDropMarkers();
      });
    });

    list.querySelectorAll('.ribbon-customize-cmd').forEach((btn) => {
      btn.addEventListener('click', () => addCommand(btn.dataset.cmdId));
      btn.addEventListener('dblclick', () => addCommand(btn.dataset.cmdId));
    });
  }

  function renderTree() {
    const root = backdrop.querySelector('#ribbonCustomizeTabs');
    if (!editingLayout?.tabs?.length) {
      root.innerHTML = '<p class="ribbon-customize-empty">' + escapeHtml(I.emptyLayout || 'Noch keine Registerkarten.') + '</p>';
      return;
    }

    root.innerHTML = editingLayout.tabs.map((tab) => {
      const tabActive = tab.id === selectedTabId ? ' active' : '';
      const tabIsCollapsed = isTabCollapsed(tab.id);
      const groupCount = (tab.groups || []).length;
      const itemCount = (tab.groups || []).reduce((sum, g) => sum + (g.items || []).length, 0);
      const groupsHtml = (tab.groups || []).map((group) => {
        const groupActive = tab.id === selectedTabId && group.id === selectedGroupId ? ' active' : '';
        const groupIsCollapsed = isGroupCollapsed(tab.id, group.id);
        const itemsHtml = (group.items || []).map((item, index) => {
          const cmd = itemCommandMeta(item);
          const isSep = cmd.kind === 'separator';
          const isRowSep = cmd.kind === 'row_separator';
          return '<li class="ribbon-customize-item' + (isSep || isRowSep ? ' is-separator' : '') + (isRowSep ? ' is-row-separator' : '') + (dragPayload?.instanceId === item.instanceId ? ' is-dragging' : '') + '" ' +
            'draggable="true" data-instance-id="' + escapeHtml(item.instanceId) + '" data-cmd-id="' + escapeHtml(item.id) + '" ' +
            'data-tab-id="' + escapeHtml(tab.id) + '" data-group-id="' + escapeHtml(group.id) + '" data-index="' + index + '">' +
            '<span class="ribbon-customize-item-grip" aria-hidden="true">⠿</span>' +
            (isSep ? '<span class="ribbon-customize-icon" aria-hidden="true">' + appearanceIcon('separator') + '</span>'
              : (isRowSep ? '<span class="ribbon-customize-icon" aria-hidden="true">' + appearanceIcon('row_separator') + '</span>' : iconHtml(cmd))) +
            '<span class="ribbon-customize-item-label">' + escapeHtml(cmd?.label || item.label || item.id) + '</span>' +
            itemTileHtml(item) +
            '<button type="button" class="ribbon-customize-item-remove" data-instance-id="' + escapeHtml(item.instanceId) + '" title="' + escapeHtml(I.remove || 'Entfernen') + '">×</button>' +
            '</li>';
        }).join('');
        return '<li class="ribbon-customize-group' + groupActive + (groupIsCollapsed ? ' is-collapsed' : '') + '" draggable="true" data-tab-id="' + escapeHtml(tab.id) + '" data-group-id="' + escapeHtml(group.id) + '">' +
          '<div class="ribbon-customize-group-head">' +
          '<button type="button" class="ribbon-customize-toggle ribbon-customize-group-toggle" data-tab-id="' + escapeHtml(tab.id) + '" data-group-id="' + escapeHtml(group.id) + '" aria-expanded="' + (!groupIsCollapsed) + '" title="' + escapeHtml(I.toggleGroup || 'Gruppe ein-/ausklappen') + '"></button>' +
          '<button type="button" class="ribbon-customize-group-select" data-tab-id="' + escapeHtml(tab.id) + '" data-group-id="' + escapeHtml(group.id) + '">' +
          escapeHtml(group.label) + '</button>' +
          groupRowsHtml(tab.id, group) +
          '<span class="ribbon-customize-meta">' + (group.items || []).length + '</span>' +
          '</div>' +
          '<ul class="ribbon-customize-group-items"' + (groupIsCollapsed ? ' hidden' : '') + ' data-tab-id="' + escapeHtml(tab.id) + '" data-group-id="' + escapeHtml(group.id) + '">' +
          itemsHtml +
          '<li class="ribbon-customize-drop-slot" data-drop-kind="group-items" data-tab-id="' + escapeHtml(tab.id) + '" data-group-id="' + escapeHtml(group.id) + '"></li>' +
          '</ul></li>';
      }).join('');

      return '<section class="ribbon-customize-tab' + tabActive + (tabIsCollapsed ? ' is-collapsed' : '') + '" data-tab-id="' + escapeHtml(tab.id) + '">' +
        '<header class="ribbon-customize-tab-head">' +
        '<button type="button" class="ribbon-customize-toggle ribbon-customize-tab-toggle" data-tab-id="' + escapeHtml(tab.id) + '" aria-expanded="' + (!tabIsCollapsed) + '" title="' + escapeHtml(I.toggleTab || 'Registerkarte ein-/ausklappen') + '"></button>' +
        '<button type="button" class="ribbon-customize-tab-select" data-tab-id="' + escapeHtml(tab.id) + '">' + escapeHtml(tab.label) + '</button>' +
        '<span class="ribbon-customize-meta">' + groupCount + ' / ' + itemCount + '</span>' +
        '</header>' +
        '<div class="ribbon-customize-tab-body"' + (tabIsCollapsed ? ' hidden' : '') + '>' +
        '<ul class="ribbon-customize-groups" data-tab-id="' + escapeHtml(tab.id) + '">' +
        groupsHtml +
        '<li class="ribbon-customize-drop-slot ribbon-customize-drop-slot-group" data-drop-kind="tab-groups" data-tab-id="' + escapeHtml(tab.id) + '"></li>' +
        '</ul></div></section>';
    }).join('');

    root.querySelectorAll('.ribbon-customize-tab-toggle').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleTabCollapsed(btn.dataset.tabId);
        renderTree();
      });
    });

    root.querySelectorAll('.ribbon-customize-group-toggle').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleGroupCollapsed(btn.dataset.tabId, btn.dataset.groupId);
        renderTree();
      });
    });

    root.querySelectorAll('.ribbon-customize-tab-select').forEach((btn) => {
      btn.addEventListener('click', () => {
        selectedTabId = btn.dataset.tabId;
        selectedGroupId = null;
        expandTab(selectedTabId);
        renderTree();
        schedulePreview();
      });
    });

    root.querySelectorAll('.ribbon-customize-group-select').forEach((btn) => {
      btn.addEventListener('click', () => {
        selectedTabId = btn.dataset.tabId;
        selectedGroupId = btn.dataset.groupId;
        expandGroup(selectedTabId, selectedGroupId);
        renderTree();
      });
    });

    root.querySelectorAll('[data-item-tile]').forEach((btn) => {
      btn.addEventListener('mousedown', (e) => {
        e.stopPropagation();
      });
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        setItemTileSize(btn.dataset.instanceId, btn.dataset.itemTile);
      });
    });

    root.querySelectorAll('[data-item-label]').forEach((btn) => {
      btn.addEventListener('mousedown', (e) => {
        e.stopPropagation();
      });
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        setItemShowLabel(btn.dataset.instanceId, btn.dataset.itemLabel === '1');
      });
    });

    root.querySelectorAll('[data-group-rows]').forEach((btn) => {
      btn.addEventListener('mousedown', (e) => {
        e.stopPropagation();
      });
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        setGroupRows(btn.dataset.tabId, btn.dataset.groupId, btn.dataset.groupRows);
      });
    });

    root.querySelectorAll('.ribbon-customize-item-remove').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        removeItem(btn.dataset.instanceId);
      });
    });

    bindDragAndDrop(root);
  }

  function clearDropMarkers() {
    backdrop.querySelectorAll('.is-drop-target, .is-drop-before').forEach((el) => {
      el.classList.remove('is-drop-target', 'is-drop-before');
    });
  }

  function bindDragAndDrop(root) {
    root.querySelectorAll('.ribbon-customize-item[draggable="true"]').forEach((row) => {
      row.addEventListener('dragstart', (e) => {
        if (e.target.closest('button, .ribbon-customize-item-tiles')) {
          e.preventDefault();
          return;
        }
        e.stopPropagation();
        dragPayload = {
          type: 'item',
          instanceId: row.dataset.instanceId,
          tabId: row.dataset.tabId,
          groupId: row.dataset.groupId,
          index: parseInt(row.dataset.index, 10) || 0,
        };
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.instanceId);
        row.classList.add('is-dragging');
      });
      row.addEventListener('dragend', () => {
        row.classList.remove('is-dragging');
        dragPayload = null;
        clearDropMarkers();
      });
      row.addEventListener('dragover', (e) => {
        if (!dragPayload || dragPayload.type !== 'item') return;
        e.preventDefault();
        clearDropMarkers();
        const rect = row.getBoundingClientRect();
        const before = e.clientY < rect.top + rect.height / 2;
        row.classList.add(before ? 'is-drop-before' : 'is-drop-target');
      });
      row.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!dragPayload) return;
        const rect = row.getBoundingClientRect();
        const before = e.clientY < rect.top + rect.height / 2;
        const index = parseInt(row.dataset.index, 10) || 0;
        handleItemDrop(row.dataset.tabId, row.dataset.groupId, before ? index : index + 1);
      });
    });

    root.querySelectorAll('.ribbon-customize-group[draggable="true"]').forEach((row) => {
      row.addEventListener('dragstart', (e) => {
        if (e.target.closest('.ribbon-customize-item')) return;
        dragPayload = {
          type: 'group',
          tabId: row.dataset.tabId,
          groupId: row.dataset.groupId,
        };
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.groupId);
        row.classList.add('is-dragging');
      });
      row.addEventListener('dragend', () => {
        row.classList.remove('is-dragging');
        dragPayload = null;
        clearDropMarkers();
      });
      row.addEventListener('dragover', (e) => {
        if (!dragPayload || dragPayload.type !== 'group') return;
        if (dragPayload.groupId === row.dataset.groupId) return;
        e.preventDefault();
        clearDropMarkers();
        row.classList.add('is-drop-target');
      });
      row.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!dragPayload || dragPayload.type !== 'group') return;
        moveGroupRelative(dragPayload.tabId, dragPayload.groupId, row.dataset.tabId, row.dataset.groupId);
      });
    });

    root.querySelectorAll('[data-drop-kind]').forEach((slot) => {
      slot.addEventListener('dragover', (e) => {
        if (!dragPayload) return;
        e.preventDefault();
        clearDropMarkers();
        slot.classList.add('is-drop-target');
        if (slot.dataset.tabId) expandTab(slot.dataset.tabId);
        if (slot.dataset.groupId && slot.dataset.tabId) expandGroup(slot.dataset.tabId, slot.dataset.groupId);
      });
      slot.addEventListener('dragleave', () => {
        slot.classList.remove('is-drop-target');
      });
      slot.addEventListener('drop', (e) => {
        e.preventDefault();
        clearDropMarkers();
        if (!dragPayload) return;
        if (dragPayload.type === 'catalog') {
          addCommand(dragPayload.cmdId, slot.dataset.tabId, slot.dataset.groupId);
        } else if (dragPayload.type === 'item' && slot.dataset.dropKind === 'group-items') {
          const group = findGroup(slot.dataset.tabId, slot.dataset.groupId);
          handleItemDrop(slot.dataset.tabId, slot.dataset.groupId, group ? group.items.length : 0);
        } else if (dragPayload.type === 'group' && slot.dataset.dropKind === 'tab-groups') {
          moveGroupToTab(dragPayload.tabId, dragPayload.groupId, slot.dataset.tabId);
        }
      });
    });

    root.querySelectorAll('.ribbon-customize-tab-head').forEach((head) => {
      head.addEventListener('dragover', (e) => {
        if (!dragPayload) return;
        e.preventDefault();
        const tabId = head.closest('.ribbon-customize-tab')?.dataset.tabId;
        if (tabId) expandTab(tabId);
      });
    });

    root.querySelectorAll('.ribbon-customize-group-head').forEach((head) => {
      head.addEventListener('dragover', (e) => {
        if (!dragPayload) return;
        e.preventDefault();
        const group = head.closest('.ribbon-customize-group');
        if (group) expandGroup(group.dataset.tabId, group.dataset.groupId);
      });
    });
  }

  function findGroup(tabId, groupId) {
    const tab = editingLayout?.tabs?.find((t) => t.id === tabId);
    return tab?.groups?.find((g) => g.id === groupId) || null;
  }

  function addCommand(cmdId, tabId, groupId) {
    const tab = editingLayout.tabs.find((t) => t.id === (tabId || selectedTabId));
    if (!tab) return;
    let group = tab.groups.find((g) => g.id === (groupId || selectedGroupId));
    if (!group) {
      group = { id: uid('group'), label: I.newGroup || 'Neue Gruppe', items: [] };
      tab.groups.push(group);
      selectedTabId = tab.id;
      selectedGroupId = group.id;
    }
    if (isWidget(cmdId)) {
      removeCommandInstances(cmdId);
    }
    expandGroup(tab.id, group.id);
    const entry = { id: cmdId, instanceId: uid('item') };
    const cmd = itemCommandMeta(entry);
    if (cmd.kind === 'separator') {
      entry.gridSpan = { cols: 1, rows: 2 };
    } else if (cmd.kind === 'row_separator') {
      /* Layout-Marker ohne Span */
      delete entry.gridSpan;
    } else if (cmd.gridSpan) {
      entry.gridSpan = {
        cols: Math.max(1, parseInt(cmd.gridSpan.cols, 10) || 1),
        rows: Math.max(1, parseInt(cmd.gridSpan.rows, 10) || 1),
      };
    } else if (global.SFRibbonRenderer?.defaultGridSpan) {
      entry.gridSpan = global.SFRibbonRenderer.defaultGridSpan(cmd);
    }
    group.items.push(entry);
    layoutChanged();
  }

  function removeItem(instanceId) {
    const found = findItem(instanceId);
    if (!found) return;
    found.group.items.splice(found.index, 1);
    pruneEmptyGroups(editingLayout);
    layoutChanged();
  }

  function handleItemDrop(targetTabId, targetGroupId, targetIndex) {
    if (!dragPayload || dragPayload.type !== 'item') return;
    const found = findItem(dragPayload.instanceId);
    if (!found) return;

    const moving = found.group.items.splice(found.index, 1)[0];
    const targetGroup = findGroup(targetTabId, targetGroupId);
    if (!targetGroup) return;

    let insertAt = Math.max(0, Math.min(targetIndex, targetGroup.items.length));
    if (found.tab.id === targetTabId && found.group.id === targetGroupId && found.index < insertAt) {
      insertAt -= 1;
    }
    targetGroup.items.splice(insertAt, 0, moving);
    selectedTabId = targetTabId;
    selectedGroupId = targetGroupId;
    expandGroup(targetTabId, targetGroupId);
    pruneEmptyGroups(editingLayout);
    layoutChanged();
  }

  function moveGroupRelative(fromTabId, fromGroupId, toTabId, toGroupId) {
    const fromTab = editingLayout.tabs.find((t) => t.id === fromTabId);
    const toTab = editingLayout.tabs.find((t) => t.id === toTabId);
    if (!fromTab || !toTab) return;
    const fromIdx = fromTab.groups.findIndex((g) => g.id === fromGroupId);
    const toIdx = toTab.groups.findIndex((g) => g.id === toGroupId);
    if (fromIdx < 0 || toIdx < 0) return;
    const [group] = fromTab.groups.splice(fromIdx, 1);
    let insertAt = toIdx;
    if (fromTabId === toTabId && fromIdx < toIdx) insertAt -= 1;
    toTab.groups.splice(insertAt, 0, group);
    selectedTabId = toTabId;
    selectedGroupId = group.id;
    pruneEmptyGroups(editingLayout);
    layoutChanged();
  }

  function moveGroupToTab(fromTabId, groupId, toTabId) {
    const fromTab = editingLayout.tabs.find((t) => t.id === fromTabId);
    const toTab = editingLayout.tabs.find((t) => t.id === toTabId);
    if (!fromTab || !toTab) return;
    const fromIdx = fromTab.groups.findIndex((g) => g.id === groupId);
    if (fromIdx < 0) return;
    const [group] = fromTab.groups.splice(fromIdx, 1);
    toTab.groups.push(group);
    selectedTabId = toTabId;
    selectedGroupId = group.id;
    pruneEmptyGroups(editingLayout);
    layoutChanged();
  }

  async function promptText(label, defaultValue) {
    if (global.SFDialog?.prompt) {
      return global.SFDialog.prompt({ label, defaultValue: defaultValue || '' });
    }
    return window.prompt(label, defaultValue || '');
  }

  async function addTab() {
    const label = await promptText(I.tabNamePrompt || 'Name der Registerkarte', I.newTab || 'Neues Tab');
    if (label === null || !String(label).trim()) return;
    const tab = { id: uid('tab'), label: String(label).trim(), groups: [] };
    editingLayout.tabs.push(tab);
    selectedTabId = tab.id;
    selectedGroupId = null;
    expandTab(tab.id);
    layoutChanged();
  }

  async function renameTab() {
    const tab = currentTab();
    if (!tab) return;
    const label = await promptText(I.tabNamePrompt || 'Name der Registerkarte', tab.label);
    if (label === null || !String(label).trim()) return;
    tab.label = String(label).trim();
    layoutChanged();
  }

  async function deleteTab() {
    if (!editingLayout?.tabs || editingLayout.tabs.length <= 1) return;
    editingLayout.tabs = editingLayout.tabs.filter((t) => t.id !== selectedTabId);
    selectedTabId = editingLayout.tabs[0]?.id || null;
    selectedGroupId = null;
    layoutChanged();
  }

  async function addGroup() {
    const tab = currentTab();
    if (!tab) return;
    const label = await promptText(I.groupNamePrompt || 'Name der Gruppe', I.newGroup || 'Neue Gruppe');
    if (label === null || !String(label).trim()) return;
    const group = { id: uid('group'), label: String(label).trim(), items: [] };
    tab.groups.push(group);
    selectedGroupId = group.id;
    expandGroup(tab.id, group.id);
    layoutChanged();
  }

  async function renameGroup() {
    const tab = currentTab();
    if (!tab || !selectedGroupId) return;
    const group = tab.groups.find((g) => g.id === selectedGroupId);
    if (!group) return;
    const label = await promptText(I.groupNamePrompt || 'Name der Gruppe', group.label);
    if (label === null || !String(label).trim()) return;
    group.label = String(label).trim();
    layoutChanged();
  }

  function deleteGroup() {
    const tab = currentTab();
    if (!tab || !selectedGroupId) return;
    tab.groups = tab.groups.filter((g) => g.id !== selectedGroupId);
    selectedGroupId = null;
    layoutChanged();
  }

  function open() {
    ensureDom();
    if (!backdrop || !panel) {
      console.error('[ribbon] customize dialog DOM missing');
      return;
    }
    if (global.SFRibbonRenderer?.buildCommandIndex) {
      global.SFRibbonRenderer.buildCommandIndex(boot.commands || []);
    }
    const current = global.SFRibbon?.getLayout?.() || boot.layout;
    if (!current || !current.tabs) {
      console.error('[ribbon] no layout available for customize');
      return;
    }
    originalLayout = cloneLayout(current);
    editingLayout = cloneLayout(current);
    ensureInstanceIds(editingLayout);
    ensureEditingPrefs();
    initCollapseState();
    const activeRibbonTab = global.SFRibbon?.getActiveTab?.()
      || current.prefs?.activeTab
      || editingLayout.tabs[0]?.id
      || null;
    selectedTabId = editingLayout.tabs.some((t) => t.id === activeRibbonTab)
      ? activeRibbonTab
      : (editingLayout.tabs[0]?.id || null);
    selectedGroupId = null;
    if (selectedTabId) expandTab(selectedTabId);
    renderCategoryFilter();
    const catSelect = backdrop.querySelector('#ribbonCustomizeCategory');
    if (catSelect && selectedTabId && [...catSelect.options].some((o) => o.value === selectedTabId)) {
      catSelect.value = selectedTabId;
    }
    renderCatalog();
    renderAppearanceControls();
    renderTree();
    restorePanelSize();
    backdrop.style.display = 'block';
    backdrop.classList.add('open');
    backdrop.setAttribute('aria-hidden', 'false');
    applyPreview();
  }

  function close() {
    clearPreviewTimer();
    if (!backdrop) return;
    backdrop.classList.remove('open');
    backdrop.style.display = 'none';
    backdrop.setAttribute('aria-hidden', 'true');
    editingLayout = null;
    originalLayout = null;
    dragPayload = null;
  }

  function cancel() {
    restoreOriginalLayout();
    close();
  }

  async function save() {
    if (!editingLayout) return;
    clearPreviewTimer();
    const layoutToSave = cloneLayout(editingLayout);
    layoutToSave.prefs = mergePreviewPrefs();
    try {
      const res = await fetch(boot.apiUrl || 'ribbon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save',
          csrf_token: global.SF_BOOTSTRAP?.csrfToken,
          layout: layoutToSave,
          templateMode: !!global.SF_BOOTSTRAP?.templateMode,
          layoutSetMode: !!global.SF_BOOTSTRAP?.layoutSetMode,
          showMasterSlideNav: !!boot.meta?.masterSlideNav,
        }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'save failed');
      originalLayout = cloneLayout(data.layout);
      applyLayoutAndNotify(data.layout);
      close();
    } catch (err) {
      if (global.SFDialog?.alert) {
        await global.SFDialog.alert(err.message || I.errorSave || 'Speichern fehlgeschlagen');
      } else {
        window.alert(err.message || I.errorSave || 'Speichern fehlgeschlagen');
      }
    }
  }

  async function reset() {
    if (global.SFDialog?.confirm) {
      const ok = await global.SFDialog.confirm(I.resetConfirm || 'Standard-Layout wiederherstellen?');
      if (!ok) return;
    }
    try {
      const res = await fetch(boot.apiUrl || 'ribbon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'reset',
          csrf_token: global.SF_BOOTSTRAP?.csrfToken,
          templateMode: !!global.SF_BOOTSTRAP?.templateMode,
          layoutSetMode: !!global.SF_BOOTSTRAP?.layoutSetMode,
          showMasterSlideNav: !!boot.meta?.masterSlideNav,
        }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'reset failed');
      originalLayout = cloneLayout(data.layout);
      applyLayoutAndNotify(data.layout);
      close();
    } catch (err) {
      if (global.SFDialog?.alert) {
        global.SFDialog.alert(err.message || I.errorReset || 'Zurücksetzen fehlgeschlagen');
      }
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  global.SFRibbonCustomize = { open, close };
})(window);
