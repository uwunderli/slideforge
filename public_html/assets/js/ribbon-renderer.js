(function (global) {
  'use strict';

  const ICONS = {
    undo: '<path d="M9 14l-4-4 4-4"/><path d="M5 10h11a4 4 0 0 1 0 8h-1"/>',
    redo: '<path d="M15 14l4-4-4-4"/><path d="M19 10H8a4 4 0 0 0 0 8h1"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
    cut: '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.5 15.5M8.5 8.5L20 20"/>',
    paste: '<rect x="8" y="4" width="8" height="4" rx="1"/><path d="M8 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>',
    duplicate: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16V6a2 2 0 0 1 2-2h10"/><line x1="12" y1="14" x2="18" y2="14"/><line x1="15" y1="11" x2="15" y2="17"/>',
    group: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    ungroup: '<rect x="2" y="2" width="8" height="8" rx="1"/><rect x="14" y="2" width="8" height="8" rx="1"/><rect x="2" y="14" width="8" height="8" rx="1"/><rect x="14" y="14" width="8" height="8" rx="1"/><path d="M10 6h4M6 10v4M18 10v4M10 18h4"/>',
    spellcheck: '<path d="M4 20h16"/><path d="M6 16l6-10 6 10"/><path d="M8.5 13h7"/><path d="M16 18l2 2 4-4"/>',
    slides: {
      strokeWidth: 1.5,
      paths:
        '<rect x="3.5" y="4.5" width="11" height="8.5" rx="1"/>' +
        '<rect x="6.5" y="8.5" width="11" height="8.5" rx="1"/>' +
        '<path d="M8.5 11.5h7M8.5 13.75h4.5"/>',
    },
    presentation: {
      strokeWidth: 1.5,
      paths:
        '<circle cx="12" cy="13" r="7"/>' +
        '<path d="M12 9.5v3.8l2.4 1.4"/>' +
        '<path d="M9 3.5h6"/>' +
        '<path d="M12 3.5v2"/>',
    },
    navigation: {
      strokeWidth: 1.5,
      paths:
        '<path d="M5 7h11"/>' +
        '<path d="M5 12h8"/>' +
        '<path d="M5 17h11"/>' +
        '<circle cx="18.5" cy="7" r="1.6"/>' +
        '<circle cx="15.5" cy="12" r="1.6"/>' +
        '<circle cx="18.5" cy="17" r="1.6"/>',
    },
    add_slide: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8"/><path d="M8 12h8"/>',
    text_field: '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8"/><path d="M8 12h8"/><path d="M8 15h5"/>',
    line: '<path d="M5 19L19 5"/>',
    rect: '<rect x="5" y="6" width="14" height="12" rx="1.5"/>',
    triangle: '<path d="M12 5L20 19H4L12 5z"/>',
    ellipse: '<ellipse cx="12" cy="12" rx="8" ry="6"/>',
    bracket: '<path d="M15 4c0 0-5 1.5-5 5.5 0 2.5 2 3.5 2 5s-2 2.5-2 5C10 23.5 15 25 15 25"/>',
    arrow: '<path d="M5 12h12"/><path d="M13 6l6 6-6 6"/>',
    star: '<path d="M12 3.5l2.2 4.5 5 .7-3.6 3.5.9 5L12 15.4 7.5 17.2l.9-5L4.8 8.7l5-.7L12 3.5z"/>',
    speech_bubble: '<path d="M5 6.5A3.5 3.5 0 0 1 8.5 3h7A3.5 3.5 0 0 1 19 6.5v5a3.5 3.5 0 0 1-3.5 3.5H11l-4 3.5V15H8.5A3.5 3.5 0 0 1 5 11.5v-5z"/>',
    media_image: '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="M21 16l-5.5-5.5L5 21"/>',
    media_audio: '<path d="M11 5L6 9H3v6h3l5 4V5z"/><path d="M16 9a4 4 0 0 1 0 6"/><path d="M18.5 6.5a7.5 7.5 0 0 1 0 11"/>',
    media_video: '<rect x="3" y="6" width="14" height="12" rx="2"/><path d="M17 10l4-2v8l-4-2"/>',
    pixabay: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 14l2.5-3 2 2.5L14 11l3 4"/><circle cx="16" cy="9" r="1.2" fill="currentColor" stroke="none"/>',
    iconify: '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
    clipart: '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><path d="M20 4L8.5 15.5"/><path d="M14.5 6.5L20 12"/><path d="M8.5 15.5L14 21"/>',
    present: {
      strokeWidth: 1.35,
      paths:
        /* Monitor + Stand, Play als Fläche — wirkt in Tall-Zellen nicht so klobig */
        '<rect x="3.5" y="4.5" width="17" height="10.5" rx="1.5"/>' +
        '<path d="M9.5 18.75h5"/>' +
        '<path d="M12 15v3.75"/>' +
        '<path d="M10.4 7.6v5.3l4.6-2.65-4.6-2.65z" fill="currentColor" stroke="none"/>',
    },
    preview: {
      strokeWidth: 1.5,
      paths:
        '<path d="M2.5 12s3.8-6.5 9.5-6.5S21.5 12 21.5 12s-3.8 6.5-9.5 6.5S2.5 12 2.5 12z"/>' +
        '<circle cx="12" cy="12" r="2.6"/>',
    },
    preview_window: {
      strokeWidth: 1.5,
      paths:
        '<rect x="3" y="5.5" width="12" height="9.5" rx="1.2"/>' +
        '<path d="M14 3.5h6.5v6.5"/>' +
        '<path d="M20.5 3.5l-7.5 7.5"/>',
    },
    present_local: {
      strokeWidth: 1.5,
      paths:
        '<rect x="2.5" y="4.5" width="14" height="10.5" rx="1.4"/>' +
        '<path d="M7.5 19h9"/>' +
        '<path d="M12 15v4"/>' +
        '<path d="M18.5 9.5l3 2-3 2v-4z"/>',
    },
    present_display: {
      strokeWidth: 1.5,
      paths:
        '<rect x="2.5" y="4" width="19" height="12.5" rx="1.8"/>' +
        '<path d="M8 20h8"/>' +
        '<path d="M12 16.5V20"/>' +
        '<path d="M7.5 8.5h9M7.5 11.5h6"/>',
    },
    share: {
      strokeWidth: 1.5,
      paths:
        '<circle cx="18" cy="5.5" r="2.2"/>' +
        '<circle cx="6" cy="12" r="2.2"/>' +
        '<circle cx="18" cy="18.5" r="2.2"/>' +
        '<path d="M8.1 10.9l7.4-4.2M8.1 13.1l7.4 4.2"/>',
    },
    export: {
      strokeWidth: 1.5,
      paths:
        '<path d="M12 3.5v11"/>' +
        '<path d="M7.5 10l4.5 4.5L16.5 10"/>' +
        '<path d="M4.5 18.5h15"/>',
    },
    grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    master_slide: '<rect x="4" y="5" width="14" height="10" rx="1.5"/><path d="M8 19h12"/><rect x="10" y="9" width="14" height="10" rx="1.5"/>',
    widget: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 12h8"/><path d="M12 8v8"/>',
    separator: '<path d="M12 4v16"/>',
    row_separator: '<path d="M19 5v6a3 3 0 0 1-3 3H7"/><path d="M10 10l-4 4 4 4"/>',
    bg_none: '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M7 17L17 7"/>',
    bg_gradient: '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 20L20 4"/><path d="M8 20h12V8"/>',
    bg_image: '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5" fill="currentColor" stroke="none"/><path d="M21 16l-5.5-5.5a1.5 1.5 0 0 0-2.12 0L7 17"/>',
    bg_video: '<rect x="3" y="6" width="14" height="12" rx="2"/><path d="M17 10l4-2v8l-4-2"/>',
    apply_all: {
      strokeWidth: 1.4,
      paths:
        /* Folie → Stapel: dünnere Rahmen, klarere Silhouette */
        '<rect x="3.5" y="4.5" width="9" height="11" rx="1"/>' +
        '<path d="M5.5 8h5M5.5 10.5h3.5"/>' +
        '<path d="M13.5 10h3"/>' +
        '<path d="M15 7.5L18.5 10.5 15 13.5"/>' +
        '<rect x="16.5" y="5" width="4.5" height="3" rx="0.6"/>' +
        '<rect x="16.5" y="10" width="4.5" height="3" rx="0.6"/>' +
        '<rect x="16.5" y="15" width="4.5" height="3" rx="0.6"/>',
    },
    apply_selected: {
      strokeWidth: 1.4,
      paths:
        '<rect x="4" y="4.5" width="10" height="12" rx="1"/>' +
        '<path d="M6.5 8h5M6.5 10.5h3.5M6.5 13h4"/>' +
        '<path d="M15.5 11.5h4"/>' +
        '<path d="M17.5 9.5L20 12l-2.5 2.5"/>',
    },
  };

  const commandIndex = {};
  let layoutData = null;
  let ribbonMeta = {};
  let renderState = null;

  function svgIcon(name) {
    const entry = ICONS[name];
    if (!entry) return '';
    const paths = typeof entry === 'string' ? entry : entry.paths;
    const strokeWidth = typeof entry === 'object' && entry.strokeWidth != null ? entry.strokeWidth : 2;
    if (!paths) return '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' + strokeWidth + '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';
  }

  function catalogIconHtml(entry) {
    if (!entry) return '';
    const cmd = commandIndex[entry.id] || entry;
    if (cmd.kind === 'separator' || entry.id === 'separator') {
      return '<span class="ribbon-customize-icon" aria-hidden="true">' + svgIcon('separator') + '</span>';
    }
    if (cmd.kind === 'row_separator' || entry.id === 'row_separator') {
      return '<span class="ribbon-customize-icon" aria-hidden="true">' + svgIcon('row_separator') + '</span>';
    }
    const iconName = cmd.icon || entry.icon;
    if (iconName && ICONS[iconName]) {
      return '<span class="ribbon-customize-icon" aria-hidden="true">' + svgIcon(iconName) + '</span>';
    }
    return '<span class="ribbon-customize-icon ribbon-customize-icon-empty" aria-hidden="true"></span>';
  }

  function beginRenderState() {
    renderState = {
      usedDomIds: new Set(),
      usedWidgetTemplates: new Set(),
    };
  }

  function normalizeSpan(span) {
    const cols = Math.max(1, Math.min(16, parseInt(span?.cols ?? span?.[0] ?? 1, 10) || 1));
    const rows = Math.max(1, Math.min(2, parseInt(span?.rows ?? span?.[1] ?? 1, 10) || 1));
    return { cols, rows };
  }

  function defaultGridSpan(cmd) {
    if (cmd.gridSpan) return normalizeSpan(cmd.gridSpan);
    if (cmd.kind === 'separator') return { cols: 1, rows: 2 };
    if (cmd.kind === 'tool' || cmd.kind === 'media' || cmd.kind === 'trigger' || cmd.kind === 'bgtype') {
      return { cols: 1, rows: 2 };
    }
    if (cmd.kind === 'settings') return { cols: 1, rows: 2 };
    if (cmd.kind === 'link') return { cols: 2, rows: 2 };
    if (cmd.id === 'paste') return { cols: 2, rows: 2 };
    if (cmd.id === 'spellcheck') return { cols: 1, rows: 2 };
    return { cols: 1, rows: 1 };
  }

  /** small=1-zeilig (eine Reihe), large=2-zeilig (3+3). */
  function tileSpanFromSize(size, kind, cmdId) {
    if (kind === 'separator') {
      return { cols: 1, rows: size === 'small' ? 1 : 2 };
    }
    if (cmdId === 'widget:slide_transition') {
      /* 1-zeilig: eine Reihe Tall-Buttons; 2-zeilig: 3+3-Raster */
      return size === 'small' ? { cols: 10, rows: 2 } : { cols: 5, rows: 2 };
    }
    return size === 'large' ? { cols: 2, rows: 2 } : { cols: 1, rows: 1 };
  }

  function tileSizeFromSpan(span, kind, cmdId) {
    if (kind === 'separator') {
      return (span?.rows || 2) >= 2 ? 'large' : 'small';
    }
    if (cmdId === 'widget:slide_transition') {
      /* Schmal + 2 Zeilen = Wrap (2-zeilig), sonst eine Reihe. */
      return (span?.rows || 1) >= 2 && (span?.cols || 10) <= 6 ? 'large' : 'small';
    }
    if ((span?.cols || 1) >= 2 && (span?.rows || 1) >= 2) return 'large';
    return 'small';
  }

  function isTileable(cmd) {
    if (!cmd) return false;
    if (cmd.kind === 'row_separator') return false;
    if (cmd.kind === 'widget') return !!cmd.tileable || cmd.id === 'widget:slide_transition';
    return cmd.kind !== 'widget';
  }

  function lineWidth(entries) {
    return entries.reduce((sum, entry) => sum + Math.max(1, entry.cols || 1), 0);
  }

  function isTransitionWrapSpan(span) {
    return (span?.rows || 1) >= 2 && (span?.cols || 10) <= 6;
  }

  function normalizeGroupRows(rows) {
    const n = parseInt(rows, 10);
    return n === 1 ? 1 : 2;
  }

  function clampSpan(span, maxRows, cmd) {
    const rows = Math.min(span.rows, maxRows);
    if (cmd && cmd.id === 'widget:slide_transition') {
      /* Breite nie auf 1 Spalte kollabieren — sonst nur «Keiner» sichtbar. */
      if (rows < 2) {
        return { cols: Math.max(span.cols, 10), rows: 1 };
      }
      if (isTransitionWrapSpan(span)) {
        return { cols: Math.max(span.cols, 5), rows: 2 };
      }
      return { cols: Math.max(span.cols, 10), rows: 2 };
    }
    /* Grosses Symbol (2×2) in 1-zeiliger Gruppe nicht zu 2×1 verkümmern. */
    if (span.rows >= 2 && rows < 2 && span.cols >= 2) {
      return { cols: 1, rows };
    }
    return { cols: span.cols, rows };
  }

  function getGridSpan(cmd, item) {
    /* Feste Inhaltsbreiten für kompakte Start-Widgets. */
    if (cmd && (cmd.id === 'widget:absatz' || cmd.templateId === 'widget-absatz')) {
      return { cols: 5, rows: 2 };
    }
    if (cmd && (cmd.id === 'widget:text_colors' || cmd.templateId === 'widget-text-colors')) {
      return { cols: 4, rows: 2 };
    }
    if (item && item.gridSpan) return normalizeSpan(item.gridSpan);
    return defaultGridSpan(cmd);
  }

  function createGridCell(content, cols, rows) {
    const cell = document.createElement('div');
    cell.className = 'ribbon-grid-cell';
    cell.dataset.gridCols = String(cols);
    cell.dataset.gridRows = String(rows);
    cell.style.gridColumn = 'span ' + cols;
    cell.style.gridRow = 'span ' + rows;
    if (content) cell.appendChild(content);
    return cell;
  }

  function createGroupShell(groupId, label, groupRows) {
    const rows = normalizeGroupRows(groupRows);
    const wrap = document.createElement('div');
    wrap.className = 'ribbon-group';
    wrap.dataset.ribbonGroup = groupId;
    wrap.dataset.groupRows = String(rows);
    const grid = document.createElement('div');
    grid.className = 'ribbon-group-grid';
    grid.setAttribute('role', 'group');
    grid.style.setProperty('--group-rows', String(rows));
    wrap.appendChild(grid);
    const labelEl = document.createElement('div');
    labelEl.className = 'ribbon-group-label';
    labelEl.textContent = label;
    wrap.appendChild(labelEl);
    return wrap;
  }

  function extractWidgetBody(widget) {
    if (!widget) return null;
    if (
      widget.classList.contains('ribbon-widget-grid') ||
      widget.classList.contains('ribbon-widget-body') ||
      widget.classList.contains('ribbon-widget-wrap') ||
      widget.classList.contains('ribbon-slide-bg-color-inner') ||
      widget.classList.contains('ribbon-slide-bg-preview-inner') ||
      widget.classList.contains('ribbon-slide-transition-inner') ||
      widget.classList.contains('ribbon-slide-autoadvance-inner') ||
      widget.classList.contains('ribbon-present-display-inner') ||
      widget.classList.contains('ribbon-settings-title-inner') ||
      widget.classList.contains('ribbon-settings-size-inner') ||
      widget.classList.contains('ribbon-settings-margin-inner') ||
      widget.classList.contains('ribbon-settings-duration-inner') ||
      widget.classList.contains('ribbon-settings-spellcheck-inner') ||
      widget.classList.contains('ribbon-settings-layout-set-inner') ||
      widget.classList.contains('ribbon-settings-slides-inner') ||
      widget.classList.contains('ribbon-settings-presentation-inner') ||
      widget.classList.contains('ribbon-zoom-inner')
    ) {
      return widget;
    }
    const widgetId = widget.dataset.widgetId || '';
    if (!widget.classList.contains('ribbon-group') && !widget.classList.contains('ribbon-widget-shell')) {
      return widget;
    }

    /* Layout-Shell liefert das Gruppenlabel — Widget-Label entfernen. */
    widget.querySelectorAll(':scope > .ribbon-group-label').forEach((el) => el.remove());

    const content = widget.querySelector(
      ':scope > .ribbon-widget-grid, ' +
      ':scope > .ribbon-slide-bg-grid, ' +
      ':scope > .ribbon-slide-bg-color-inner, ' +
      ':scope > .ribbon-slide-bg-preview-inner, ' +
      ':scope > .ribbon-slide-transition-inner, ' +
      ':scope > .ribbon-slide-timing-inner, ' +
      ':scope > .ribbon-slide-autoadvance-inner, ' +
      ':scope > .ribbon-present-display-inner, ' +
      ':scope > .ribbon-settings-title-inner, ' +
      ':scope > .ribbon-settings-size-inner, ' +
      ':scope > .ribbon-settings-margin-inner, ' +
      ':scope > .ribbon-settings-duration-inner, ' +
      ':scope > .ribbon-settings-spellcheck-inner, ' +
      ':scope > .ribbon-settings-layout-set-inner, ' +
      ':scope > .ribbon-settings-slides-inner, ' +
      ':scope > .ribbon-settings-presentation-inner, ' +
      ':scope > .ribbon-zoom-inner, ' +
      ':scope > .ribbon-group-content, ' +
      ':scope > .ribbon-group-items, ' +
      ':scope > .present-config-wrap'
    );

    if (content) {
      if (widgetId && !content.dataset.widgetId) {
        content.dataset.widgetId = widgetId;
      }
      /* Leere Gruppenhülle entfernen, sonst greift takeWidget sie als Nächstes. */
      if (widget.parentNode) widget.remove();
      return content;
    }

    const body = document.createElement('div');
    body.className = 'ribbon-widget-body';
    if (widgetId) body.dataset.widgetId = widgetId;
    while (widget.firstChild) body.appendChild(widget.firstChild);
    if (widget.parentNode) widget.remove();
    return body;
  }

  function createWidgetDuplicatePlaceholder(cmd) {
    const el = document.createElement('div');
    el.className = 'ribbon-widget-dup-hint';
    el.title = cmd.label;
    el.innerHTML = svgIcon('widget') + '<span>' + escapeHtml(cmd.label) + '</span>';
    return el;
  }

  function buildCommandIndex(commands) {
    Object.keys(commandIndex).forEach((k) => delete commandIndex[k]);
    (commands || []).forEach((cmd) => {
      commandIndex[cmd.id] = cmd;
    });
  }

  function widgetHasContent(el) {
    if (!el) return false;
    if (el.children.length > 0) return true;
    return !!(el.textContent && el.textContent.trim());
  }

  function returnWidgetsToStore() {
    const store = document.getElementById('ribbonWidgetTemplates');
    if (!store) return;
    document.querySelectorAll('[data-widget-id]').forEach((el) => {
      if (el.closest('#ribbonWidgetTemplates')) return;
      store.appendChild(el);
    });
    /* Leere Duplikat-Hüllen entfernen, Inhalt behalten. */
    const byId = new Map();
    [...store.querySelectorAll(':scope > [data-widget-id]')].forEach((el) => {
      const id = el.dataset.widgetId;
      if (!id) return;
      const prev = byId.get(id);
      if (!prev) {
        byId.set(id, el);
        return;
      }
      if (widgetHasContent(el) && !widgetHasContent(prev)) {
        prev.remove();
        byId.set(id, el);
      } else {
        el.remove();
      }
    });
  }

  function takeWidget(templateId) {
    const store = document.getElementById('ribbonWidgetTemplates');
    if (!store) return null;
    const nodes = [...store.querySelectorAll('[data-widget-id="' + templateId + '"]')];
    if (!nodes.length) return null;
    const withContent = nodes.find(widgetHasContent);
    const node = withContent || nodes[0];
    nodes.forEach((n) => {
      if (n !== node && !widgetHasContent(n)) n.remove();
    });
    return node;
  }

  function createSeparator(rows) {
    const el = document.createElement('div');
    el.className = 'ribbon-separator';
    el.setAttribute('role', 'separator');
    el.setAttribute('aria-hidden', 'true');
    el.dataset.gridRows = String(rows);
    return el;
  }

  function createCommandButton(cmd, span) {
    const kind = cmd.kind;
    const state = renderState || { usedDomIds: new Set(), usedWidgetTemplates: new Set() };
    const useTall = !span || span.rows >= 2;
    const tallClass = useTall ? ' ribbon-btn-tall' : '';

    if (kind === 'separator') {
      return createSeparator(span?.rows || 2);
    }

    if (kind === 'widget') {
      if (state.usedWidgetTemplates.has(cmd.templateId)) {
        return createWidgetDuplicatePlaceholder(cmd);
      }
      state.usedWidgetTemplates.add(cmd.templateId);
      return takeWidget(cmd.templateId);
    }

    if (kind === 'bgtype') {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ribbon-btn ribbon-grid-btn bg-type-btn' + tallClass;
      btn.dataset.bgtype = cmd.bgType || 'none';
      btn.dataset.ribbonCommand = cmd.id;
      btn.title = cmd.label;
      btn.innerHTML = svgIcon(cmd.icon || 'bg_none') + '<span class="ribbon-btn-label">' + escapeHtml(cmd.label) + '</span>';
      return btn;
    }

    if (kind === 'tool') {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ribbon-btn ribbon-grid-btn' + tallClass;
      btn.dataset.tool = cmd.tool;
      btn.dataset.ribbonCommand = cmd.id;
      btn.title = cmd.label;
      btn.innerHTML = svgIcon(cmd.icon || 'text_field') + '<span class="ribbon-btn-label">' + escapeHtml(cmd.label) + '</span>';
      return btn;
    }

    if (kind === 'media') {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ribbon-btn ribbon-grid-btn' + tallClass;
      btn.dataset.mediaAction = cmd.mediaAction;
      btn.dataset.ribbonCommand = cmd.id;
      btn.title = cmd.label;
      btn.innerHTML = svgIcon(cmd.icon || 'media_image') + '<span class="ribbon-btn-label">' + escapeHtml(cmd.label) + '</span>';
      return btn;
    }

    if (kind === 'settings') {
      return null; /* Legacy — Katalog hat keine settings-Befehle mehr */
    }

    if (kind === 'trigger') {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ribbon-btn ribbon-grid-btn' + tallClass;
      if (cmd.domId && !state.usedDomIds.has(cmd.domId)) {
        btn.id = cmd.domId;
        state.usedDomIds.add(cmd.domId);
      }
      btn.dataset.ribbonCommand = cmd.id;
      if (cmd.triggerId) btn.dataset.ribbonTrigger = cmd.triggerId;
      btn.title = cmd.label;
      btn.innerHTML = svgIcon(cmd.icon || 'add_slide') + '<span class="ribbon-btn-label">' + escapeHtml(cmd.label) + '</span>';
      return btn;
    }

    if (kind === 'link') {
      const a = document.createElement('a');
      a.className = 'ribbon-btn editor-topbar-link ribbon-grid-btn' + tallClass;
      if (cmd.domId && !state.usedDomIds.has(cmd.domId)) {
        a.id = cmd.domId;
        state.usedDomIds.add(cmd.domId);
      }
      a.dataset.ribbonCommand = cmd.id;
      const hrefKey = cmd.hrefKey || 'present';
      a.href = (ribbonMeta.urls && ribbonMeta.urls[hrefKey]) || ribbonMeta.urls?.present || '#';
      if (cmd.target) a.target = cmd.target;
      if (cmd.target === '_blank') a.rel = 'noopener noreferrer';
      a.title = cmd.label;
      a.innerHTML = svgIcon(cmd.icon || 'present') + '<span class="ribbon-btn-label">' + escapeHtml(cmd.label) + '</span>';
      if (ribbonMeta.masterSlideEditing && (cmd.id === 'present_mode' || cmd.id === 'preview_tab')) {
        const tip = ribbonMeta.masterSlideCommandsDisabled || cmd.label;
        a.classList.add('is-disabled', 'is-master-disabled');
        a.setAttribute('aria-disabled', 'true');
        a.tabIndex = -1;
        a.title = tip;
        a.removeAttribute('href');
        a.setAttribute('role', 'link');
      }
      return a;
    }

    if (kind === 'command' && cmd.id === 'master_slide_nav') {
      const nav = ribbonMeta.masterSlideNav;
      if (!nav) return null;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ribbon-btn ribbon-grid-btn' + tallClass + (nav.active ? ' active' : '');
      btn.dataset.ribbonCommand = cmd.id;
      if (cmd.domId && !state.usedDomIds.has(cmd.domId)) {
        btn.id = cmd.domId;
        state.usedDomIds.add(cmd.domId);
      }
      btn.title = nav.title || cmd.label;
      btn.dataset.setId = nav.setId || '';
      btn.dataset.presentationId = nav.presentationId || '';
      btn.dataset.returnId = nav.returnId || '';
      btn.dataset.returnSlide = String(nav.returnSlide || 0);
      btn.setAttribute('aria-pressed', nav.active ? 'true' : 'false');
      btn.innerHTML = svgIcon(cmd.icon || 'master_slide') + '<span class="ribbon-btn-label">' + escapeHtml(cmd.label) + '</span>';
      return btn;
    }

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ribbon-btn ribbon-grid-btn' + tallClass;
    btn.dataset.ribbonCommand = cmd.id;
    if (cmd.domId && !state.usedDomIds.has(cmd.domId)) {
      btn.id = cmd.domId;
      state.usedDomIds.add(cmd.domId);
    }
    if (cmd.disabled) btn.disabled = true;
    const title = cmd.shortcut ? cmd.label + ' (' + cmd.shortcut + ')' : cmd.label;
    btn.title = title;
    btn.innerHTML = (cmd.icon ? svgIcon(cmd.icon) : '') + '<span class="ribbon-btn-label">' + escapeHtml(cmd.label) + '</span>';
    if (ribbonMeta.masterSlideEditing && (cmd.id === 'share' || cmd.id === 'export' || cmd.id === 'preview_window' || cmd.id === 'present_local')) {
      const tip = ribbonMeta.masterSlideCommandsDisabled || title;
      btn.setAttribute('aria-disabled', 'true');
      btn.title = tip;
      btn.classList.add('is-master-disabled');
      btn.addEventListener('click', (ev) => { ev.preventDefault(); ev.stopPropagation(); }, true);
    }
    return btn;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderGroup(group) {
    const state = renderState || { usedDomIds: new Set(), usedWidgetTemplates: new Set() };
    const groupRows = normalizeGroupRows(group.rows);
    const wrap = createGroupShell(group.id, group.label, groupRows);
    const grid = wrap.querySelector('.ribbon-group-grid');
    let hasContent = false;
    /* Zeilen-Stapel: 1-zeilige Items; Zeilentrenner = <br> bis voller Trenner / Gruppenende. */
    let lineStack = null;

    function appendCell(cell) {
      grid.appendChild(cell);
      hasContent = true;
    }

    function buildItemCell(cmd, itemRef, span) {
      if (cmd.kind === 'widget') {
        if (state.usedWidgetTemplates.has(cmd.templateId)) {
          return null;
        }
        state.usedWidgetTemplates.add(cmd.templateId);
        const widget = takeWidget(cmd.templateId);
        if (!widget) return null;
        const body = extractWidgetBody(widget);
        const cell = createGridCell(body, span.cols, span.rows);
        cell.classList.add('ribbon-grid-cell--widget');
        if (cmd.id === 'widget:slide_transition') {
          const wrapLayout = isTransitionWrapSpan(span);
          cell.classList.add(wrapLayout ? 'ribbon-transition--wrap' : 'ribbon-transition--row');
          if (body) {
            body.dataset.transitionLayout = wrapLayout ? 'wrap' : 'row';
          }
        }
        if (cmd.id === 'widget:settings_size' && body) {
          /* Gross (2 Zeilen) = B über H; klein (1 Zeile) = B × H nebeneinander. */
          body.dataset.sizeLayout = span.rows >= 2 ? 'stack' : 'row';
        }
        return cell;
      }

      if (cmd.kind === 'separator') {
        const sep = createSeparator(span.rows);
        const cell = createGridCell(sep, 1, span.rows);
        cell.classList.add('ribbon-grid-cell--separator');
        return cell;
      }

      const el = createCommandButton(cmd, span);
      if (!el) return null;
      const cell = createGridCell(el, span.cols, span.rows);
      if (itemRef.showLabel === false) {
        cell.classList.add('ribbon-grid-cell--no-label');
        el.classList.add('ribbon-no-label');
      }
      return cell;
    }

    function prepareLineCell(cell, cols) {
      cell.dataset.gridCols = String(cols);
      cell.dataset.gridRows = '1';
      cell.style.gridColumn = '';
      cell.style.gridRow = '';
      cell.style.flex = '0 0 auto';
      cell.style.width = cols <= 1
        ? 'var(--ribbon-cell)'
        : 'calc(' + cols + ' * var(--ribbon-cell) + ' + (cols - 1) + ' * var(--ribbon-cell-gap))';
      cell.style.height = 'var(--ribbon-cell)';
      return cell;
    }

    function flushLineStack() {
      if (!lineStack) return;
      lineStack = lineStack.filter((line) => line.length > 0);
      if (!lineStack.length) {
        lineStack = null;
        return;
      }

      if (groupRows < 2) {
        lineStack.forEach((line) => {
          line.forEach((entry) => appendCell(entry.cell));
        });
        lineStack = null;
        return;
      }

      const packCols = Math.max(1, ...lineStack.map((line) => lineWidth(line)));
      const lineCount = lineStack.length;
      const stackCell = document.createElement('div');
      stackCell.className = 'ribbon-grid-cell ribbon-grid-cell--line-stack';
      stackCell.dataset.gridCols = String(packCols);
      stackCell.dataset.gridRows = String(groupRows);
      stackCell.style.gridColumn = 'span ' + packCols;
      stackCell.style.gridRow = 'span ' + groupRows;

      const stack = document.createElement('div');
      stack.className = 'ribbon-line-stack';
      stack.style.setProperty('--line-count', String(lineCount));
      stack.style.gridTemplateRows = 'repeat(' + lineCount + ', var(--ribbon-cell))';

      lineStack.forEach((line) => {
        const row = document.createElement('div');
        row.className = 'ribbon-line-stack-row';
        line.forEach((entry) => row.appendChild(prepareLineCell(entry.cell, entry.cols)));
        stack.appendChild(row);
      });

      stackCell.appendChild(stack);
      appendCell(stackCell);
      lineStack = null;
    }

    function ensureLineStack() {
      if (!lineStack) lineStack = [[]];
      if (!lineStack.length) lineStack.push([]);
    }

    function pushToCurrentLine(entry) {
      ensureLineStack();
      lineStack[lineStack.length - 1].push(entry);
    }

    group.items.forEach((item) => {
      const itemRef = typeof item === 'object' ? item : { id: item };
      const cmd = commandIndex[itemRef.id];
      if (!cmd) return;

      if (cmd.kind === 'row_separator') {
        /* <br> in der Zeile; allein am Anfang startet nur den Zeilenmodus. */
        if (!lineStack) {
          lineStack = [[]];
        } else {
          lineStack.push([]);
        }
        return;
      }

      const span = clampSpan(getGridSpan(cmd, itemRef), groupRows, cmd);

      if (cmd.kind === 'separator') {
        flushLineStack();
        const cell = buildItemCell(cmd, itemRef, span);
        if (cell) appendCell(cell);
        return;
      }

      /* Offener Zeilenstapel (nach Zeilentrenner/<br>): auch breite/hohe Widgets in die aktuelle Zeile. */
      const joinLineStack = groupRows >= 2 && (lineStack || span.rows < 2);
      const lineSpan = joinLineStack ? { cols: Math.max(1, span.cols), rows: 1 } : span;
      const cell = buildItemCell(cmd, itemRef, lineSpan);
      if (!cell) return;

      if (joinLineStack) {
        pushToCurrentLine({ cell: cell, cols: lineSpan.cols, rows: 1 });
        return;
      }

      flushLineStack();
      appendCell(cell);
    });

    flushLineStack();

    if (!hasContent) return [];
    return [wrap];
  }

  function renderLayout(layout) {
    const ribbon = document.getElementById('editorRibbon');
    if (!ribbon || !layout) return;

    beginRenderState();
    returnWidgetsToStore();

    const tabsEl = ribbon.querySelector('.editor-ribbon-tabs');
    const panelsEl = ribbon.querySelector('.editor-ribbon-panels');
    if (!tabsEl || !panelsEl) return;

    tabsEl.innerHTML = '';
    panelsEl.innerHTML = '';

    layout.tabs.forEach((tab, tabIndex) => {
      const tabBtn = document.createElement('button');
      tabBtn.type = 'button';
      tabBtn.className = 'editor-ribbon-tab' + (tabIndex === 0 ? ' active' : '');
      tabBtn.role = 'tab';
      tabBtn.dataset.ribbonTab = tab.id;
      tabBtn.setAttribute('aria-selected', tabIndex === 0 ? 'true' : 'false');
      tabBtn.textContent = tab.label;
      tabsEl.appendChild(tabBtn);

      const panel = document.createElement('div');
      panel.className = 'editor-ribbon-panel';
      panel.dataset.ribbonPanel = tab.id;
      panel.role = 'tabpanel';
      if (tabIndex !== 0) panel.hidden = true;

      tab.groups.forEach((group) => {
        renderGroup(group).forEach((node) => {
          if (node) panel.appendChild(node);
        });
      });

      panelsEl.appendChild(panel);
    });

    layoutData = layout;
    if (global.SFRibbon && global.SFRibbon.applyAppearancePrefs) {
      global.SFRibbon.applyAppearancePrefs(layout.prefs || {});
    }
    if (global.SFRibbon && global.SFRibbon._rebindTabs) {
      global.SFRibbon._rebindTabs();
    }
    document.dispatchEvent(new CustomEvent('sf:ribbon-rendered'));
  }

  function init(config) {
    ribbonMeta = config.meta || {};
    buildCommandIndex(config.commands || []);
    renderLayout(config.layout);
  }

  function getLayoutData() {
    return layoutData;
  }

  function setLayoutData(layout) {
    renderLayout(layout);
  }

  global.SFRibbonRenderer = {
    init,
    renderLayout,
    getLayoutData,
    setLayoutData,
    buildCommandIndex,
    returnWidgetsToStore,
    catalogIconHtml,
    svgIcon,
    commandIndex: () => commandIndex,
    getGridSpan,
    normalizeSpan,
    tileSpanFromSize,
    tileSizeFromSpan,
    defaultGridSpan,
    isTileable,
  };
})(window);
