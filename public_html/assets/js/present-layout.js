/**
 * Grösse und Spaltenaufteilung im Präsentationsmodus (Drag & Drop, User-Prefs).
 */
(function (global) {
  'use strict';

  const MIN_COL = { main: 200, side: 180, time: 100 };
  const MIN_PANEL_H = 72;
  const MAX_PANEL_H = 900;
  const SPLITTER_W = 6;

  let P = null;
  let layoutState = null;
  let getMainReveal = () => null;
  let layoutNextPreview = () => {};
  let saveTimer = null;

  function defaultLayout() {
    return {
      colWeights: { main: 3, side: 1 },
      timebarPx: 110,
      showTimebar: true,
      panelHeights: {},
      clockOrder: ['analog', 'digital', 'studio'],
      timebarStops: null,
      laserPointerColor: '#ff0000',
      laserPointerSize: 24,
      laserPointerTrail: false,
      laserPointerEnabled: true,
      showSlideGhost: true,
      slideGhostOpacity: 25,
      notesMode: 'carry',
      notesCollapsed: false,
    };
  }

  function isTimebarVisible(layout) {
    const src = layout || layoutState || defaultLayout();
    return src.showTimebar !== false;
  }

  function applyTimebarVisibility(layout) {
    const show = isTimebarVisible(layout);
    const root = document.querySelector('.present-layout');
    if (root) root.classList.toggle('present-timebar-hidden', !show);
    document.querySelectorAll('.present-timebar-panel').forEach((el) => {
      el.hidden = !show;
    });
    document.querySelectorAll('.present-layout-splitter[data-split="side-time"]').forEach((el) => {
      el.hidden = !show;
    });
    if (!show) {
      const { row, topbar } = gridEls();
      if (row) row.style.gridTemplateColumns = '';
      if (topbar) topbar.style.gridTemplateColumns = '';
    }
  }

  function cloneLayout(src) {
    const base = defaultLayout();
    if (!src || typeof src !== 'object') return base;
    const weights = { ...base.colWeights };
    if (src.colWeights && typeof src.colWeights === 'object') {
      if (src.colWeights.main != null) weights.main = src.colWeights.main;
      if (src.colWeights.side != null) weights.side = src.colWeights.side;
      const legacyNotes = src.colWeights.notes;
      if (legacyNotes > 0) {
        weights.main += legacyNotes * 0.65;
        weights.side += legacyNotes * 0.35;
      }
    }
    return {
      colWeights: weights,
      timebarPx: src.timebarPx || base.timebarPx,
      showTimebar: src.showTimebar !== false,
      panelHeights: src.panelHeights && typeof src.panelHeights === 'object' ? { ...src.panelHeights } : {},
      panelOpen: src.panelOpen && typeof src.panelOpen === 'object' ? { ...src.panelOpen } : {},
      clockOrder: Array.isArray(src.clockOrder) ? [...src.clockOrder] : base.clockOrder,
      timebarStops: Array.isArray(src.timebarStops) ? src.timebarStops.map((s) => ({ ...s })) : base.timebarStops,
      laserPointerColor: src.laserPointerColor || base.laserPointerColor,
      laserPointerSize: src.laserPointerSize != null ? src.laserPointerSize : base.laserPointerSize,
      laserPointerTrail: src.laserPointerTrail != null ? !!src.laserPointerTrail : base.laserPointerTrail,
      laserPointerEnabled: src.laserPointerEnabled != null ? !!src.laserPointerEnabled : base.laserPointerEnabled,
      showSlideGhost: src.showSlideGhost != null ? !!src.showSlideGhost : base.showSlideGhost,
      slideGhostOpacity: src.slideGhostOpacity != null ? src.slideGhostOpacity : base.slideGhostOpacity,
      notesMode: (src.notesMode === 'always_open' || src.notesMode === 'always_closed' || src.notesMode === 'carry')
        ? src.notesMode
        : base.notesMode,
      notesCollapsed: src.notesCollapsed != null ? !!src.notesCollapsed : base.notesCollapsed,
    };
  }

  function gridEls() {
    return {
      row: document.getElementById('presentMainRow'),
      topbar: document.getElementById('presentTopbar'),
    };
  }

  function colElements() {
    return {
      main: document.querySelector('.present-current-panel'),
      side: document.querySelector('.present-side-panel'),
      time: document.querySelector('.present-timebar-panel'),
    };
  }

  function applyGridVars(layout) {
    const w = layout.colWeights || defaultLayout().colWeights;
    const root = document.documentElement;
    root.style.setProperty('--present-col-main', w.main + 'fr');
    root.style.setProperty('--present-col-side', w.side + 'fr');
    root.style.setProperty('--present-col-time', (layout.timebarPx || 110) + 'px');
    const { row, topbar } = gridEls();
    if (row) row.style.gridTemplateColumns = '';
    if (topbar) topbar.style.gridTemplateColumns = '';
  }

  function applyPanelOpen(openMap) {
    if (!openMap || typeof openMap !== 'object') return;
    Object.entries(openMap).forEach(([id, open]) => {
      const group = document.querySelector('.present-side-accordions .props-accordion-group[data-acc="' + id + '"]');
      if (!group || group.hidden) return;
      group.classList.toggle('open', !!open);
      group.dataset.userToggled = '1';
    });
    fitAllPanels();
  }

  function readPanelOpenState() {
    const openMap = {};
    document.querySelectorAll('.present-side-accordions .props-accordion-group[data-acc]').forEach((group) => {
      if (group.hidden) return;
      openMap[group.dataset.acc] = group.classList.contains('open');
    });
    return openMap;
  }

  function savePanelOpenState() {
    layoutState.panelOpen = readPanelOpenState();
    saveLayout();
  }

  function applyPanelHeights(heights) {
    document.querySelectorAll('.present-side-accordions .props-accordion-group[data-acc]').forEach((group) => {
      const id = group.dataset.acc;
      const h = heights && heights[id];
      if (id === 'next') {
        group.style.removeProperty('--present-panel-body-h');
        return;
      }
      if (h && h >= MIN_PANEL_H) {
        group.style.setProperty('--present-panel-body-h', h + 'px');
      } else {
        group.style.removeProperty('--present-panel-body-h');
      }
    });
    fitAllPanels();
  }

  function saveLayout() {
    if (!P) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      fetch('user_api.php?action=set_present_layout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ layout: layoutState, csrf_token: P.csrfToken }),
      }).then((r) => r.json()).then((json) => {
        if (json.ok && json.layout) {
          layoutState = cloneLayout(json.layout);
          P.presentLayout = layoutState;
        }
      }).catch(() => {});
    }, 220);
  }

  function relayoutMainReveal() {
    try {
      const reveal = getMainReveal();
      if (reveal && typeof reveal.layout === 'function') reveal.layout();
    } catch (e) { /* Frame evtl. noch nicht bereit */ }
  }

  function fitClockPanel(inner, body, handleH, pad) {
    inner.style.transform = '';
    inner.style.width = '';
    inner.style.margin = '';
    const stage = inner.querySelector('.present-clock-stage');
    const clockArea = inner.querySelector('.present-clock-area');
    if (!stage || !clockArea) {
      fitScaledPanel(inner, body, handleH, pad);
      return;
    }
    stage.style.transform = '';
    stage.style.transformOrigin = 'center center';
    stage.style.margin = '';

    const hint = clockArea.querySelector('.present-clock-hint');
    const hintH = hint ? hint.offsetHeight : 0;
    const availH = Math.max(0, body.clientHeight - handleH - pad - hintH - 6);
    const availW = Math.max(0, body.clientWidth - pad * 2);
    const naturalH = stage.scrollHeight;
    const naturalW = stage.scrollWidth;
    if (availH < 1 || naturalH < 1 || naturalW < 1) return;

    const scale = Math.min(1, Math.max(0.35, Math.min(availH / naturalH, availW / naturalW)));
    if (scale < 1) {
      stage.style.transform = 'scale(' + scale + ')';
    }
  }

  function fitScaledPanel(inner, body, handleH, pad) {
    inner.style.transform = '';
    inner.style.width = '';
    inner.style.marginBottom = '';
    inner.style.marginLeft = '';
    inner.style.marginRight = '';
    const avail = body.clientHeight - handleH - pad;
    const natural = inner.scrollHeight;
    if (avail > 0 && natural > avail) {
      const scale = Math.max(0.35, avail / natural);
      inner.style.transform = 'scale(' + scale + ')';
      inner.style.transformOrigin = 'center top';
      inner.style.marginBottom = (-(natural - natural * scale)) + 'px';
    }
  }

  function fitPanel(accId) {
    const group = document.querySelector('.props-accordion-group[data-acc="' + accId + '"]');
    if (!group || group.hidden || !group.classList.contains('open')) return;
    const body = group.querySelector('.props-accordion-body');
    const inner = group.querySelector('.present-panel-body-inner');
    if (!body || !inner) return;

    inner.style.transform = '';
    inner.style.width = '';
    inner.style.marginBottom = '';
    inner.style.marginLeft = '';
    inner.style.marginRight = '';

    if (accId === 'next') {
      layoutNextPreview();
      return;
    }

    const handle = group.querySelector('.present-panel-resize-handle');
    const handleH = handle && !handle.hidden ? handle.offsetHeight : 0;
    const pad = 8;

    if (accId === 'clock') {
      fitClockPanel(inner, body, handleH, pad);
      return;
    }

    if (accId === 'timer') {
      fitScaledPanel(inner, body, handleH, pad);
    }
  }

  function fitAllPanels() {
    ['next', 'clock', 'timer', 'slides', 'media'].forEach(fitPanel);
    relayoutMainReveal();
  }

  function readColWidths() {
    const cols = colElements();
    return {
      main: cols.main?.offsetWidth || MIN_COL.main,
      side: cols.side?.offsetWidth || MIN_COL.side,
      time: cols.time?.offsetWidth || layoutState.timebarPx || 110,
    };
  }

  function setColumnPx(mainPx, sidePx, timePx) {
    const { row, topbar } = gridEls();
    let tpl;
    if (isTimebarVisible()) {
      tpl = mainPx + 'px ' + SPLITTER_W + 'px ' + sidePx + 'px ' + SPLITTER_W + 'px ' + timePx + 'px';
    } else {
      tpl = mainPx + 'px ' + SPLITTER_W + 'px ' + sidePx + 'px';
    }
    if (row) row.style.gridTemplateColumns = tpl;
    if (topbar) topbar.style.gridTemplateColumns = tpl;
    relayoutMainReveal();
    layoutNextPreview();
  }

  function pxToWeights(mainPx, sidePx) {
    const sum = mainPx + sidePx;
    if (sum <= 0) return defaultLayout().colWeights;
    const scale = 4 / sum;
    return { main: mainPx * scale, side: sidePx * scale };
  }

  function startColumnDrag(split, startEvent) {
    const widths = readColWidths();
    const startX = startEvent.clientX;
    const start = { ...widths };
    const splitter = startEvent.currentTarget;
    document.body.classList.add('present-col-resizing');
    if (splitter && splitter.classList) splitter.classList.add('is-resizing');
    let raf = 0;
    let lastX = startX;

    function apply(clientX) {
      const dx = clientX - startX;
      let main = start.main;
      let side = start.side;
      let time = start.time;

      if (split === 'main-side') {
        main = Math.max(MIN_COL.main, Math.min(start.main + start.side - MIN_COL.side, start.main + dx));
        side = start.main + start.side - main;
      } else if (split === 'side-time' && isTimebarVisible()) {
        side = Math.max(MIN_COL.side, Math.min(start.side + start.time - MIN_COL.time, start.side + dx));
        time = Math.max(MIN_COL.time, Math.min(220, start.side + start.time - side));
      }

      setColumnPx(main, side, time);
    }

    function onMove(e) {
      lastX = e.clientX;
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = 0;
        apply(lastX);
      });
    }

    function onUp(e) {
      document.body.classList.remove('present-col-resizing');
      if (splitter && splitter.classList) splitter.classList.remove('is-resizing');
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      document.removeEventListener('pointercancel', onUp);
      if (raf) {
        cancelAnimationFrame(raf);
        raf = 0;
      }
      apply(e.clientX != null ? e.clientX : lastX);
      const w = readColWidths();
      if (isTimebarVisible()) layoutState.timebarPx = w.time;
      layoutState.colWeights = pxToWeights(w.main, w.side);
      applyGridVars(layoutState);
      saveLayout();
      fitAllPanels();
    }

    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
    document.addEventListener('pointercancel', onUp);
    if (startEvent.pointerId != null && splitter?.setPointerCapture) {
      try { splitter.setPointerCapture(startEvent.pointerId); } catch (err) { /* ignore */ }
    }
    startEvent.preventDefault();
  }

  function startPanelDrag(accId, startEvent) {
    const group = document.querySelector('.props-accordion-group[data-acc="' + accId + '"]');
    const body = group?.querySelector('.props-accordion-body');
    const handle = startEvent.currentTarget;
    if (!body) return;
    const startY = startEvent.clientY;
    const startH = body.offsetHeight;
    document.body.classList.add('present-panel-resizing');
    if (handle && handle.classList) handle.classList.add('is-resizing');
    let raf = 0;
    let lastY = startY;

    function apply(clientY) {
      const h = Math.max(MIN_PANEL_H, Math.min(MAX_PANEL_H, startH + (clientY - startY)));
      group.style.setProperty('--present-panel-body-h', h + 'px');
      if (!group.classList.contains('open')) group.classList.add('open');
      fitPanel(accId);
    }

    function onMove(e) {
      lastY = e.clientY;
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = 0;
        apply(lastY);
      });
    }

    function onUp(e) {
      document.body.classList.remove('present-panel-resizing');
      if (handle && handle.classList) handle.classList.remove('is-resizing');
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      document.removeEventListener('pointercancel', onUp);
      if (raf) {
        cancelAnimationFrame(raf);
        raf = 0;
      }
      apply(e.clientY != null ? e.clientY : lastY);
      const h = parseInt(getComputedStyle(body).height, 10) || body.offsetHeight;
      if (!layoutState.panelHeights) layoutState.panelHeights = {};
      layoutState.panelHeights[accId] = h;
      saveLayout();
      fitPanel(accId);
    }

    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
    document.addEventListener('pointercancel', onUp);
    if (startEvent.pointerId != null && handle?.setPointerCapture) {
      try { handle.setPointerCapture(startEvent.pointerId); } catch (err) { /* ignore */ }
    }
    startEvent.preventDefault();
  }

  function initColumnSplitters() {
    document.querySelectorAll('.present-layout-splitter[data-split]').forEach((el) => {
      if (el.classList.contains('present-topbar-splitter')) return;
      el.addEventListener('pointerdown', (e) => {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        startColumnDrag(el.dataset.split, e);
      });
    });
  }

  function initPanelSplitters() {
    document.querySelectorAll('.present-panel-resize-handle[data-panel-resize]').forEach((el) => {
      el.addEventListener('pointerdown', (e) => {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        startPanelDrag(el.dataset.panelResize, e);
      });
    });
    document.querySelectorAll('.present-side-accordions .props-accordion-header').forEach((header) => {
      header.addEventListener('click', () => {
        const id = header.closest('.props-accordion-group')?.dataset.acc;
        requestAnimationFrame(() => { if (id) fitPanel(id); });
      });
    });
  }

  function initObservers() {
    const mainPanel = document.querySelector('.present-current-panel');
    if (mainPanel) {
      new ResizeObserver(() => relayoutMainReveal()).observe(mainPanel);
    }
    document.querySelectorAll('.present-side-accordions .props-accordion-body').forEach((body) => {
      new ResizeObserver(() => {
        const id = body.closest('.props-accordion-group')?.dataset.acc;
        if (id) fitPanel(id);
      }).observe(body);
    });
    window.addEventListener('resize', () => {
      applyGridVars(layoutState);
      fitAllPanels();
    });
  }

  function setShowTimebar(show, opts) {
    layoutState.showTimebar = !!show;
    applyTimebarVisibility(layoutState);
    const { row, topbar } = gridEls();
    if (row) row.style.gridTemplateColumns = '';
    if (topbar) topbar.style.gridTemplateColumns = '';
    applyGridVars(layoutState);
    if (!opts || !opts.skipSave) saveLayout();
    fitAllPanels();
    try {
      document.dispatchEvent(new CustomEvent('sf:show-timebar', { detail: { show: !!show } }));
    } catch (e) { /* ignore */ }
  }

  function setShowTimebarLive(show) {
    setShowTimebar(show, { skipSave: true });
  }

  function broadcastLaserConfig() {
    const frame = document.getElementById('mainFrame');
    if (!frame?.contentWindow) return;
    const ls = layoutState || defaultLayout();
    frame.contentWindow.postMessage({
      type: 'sf-laser-config',
      color: ls.laserPointerColor || '#ff0000',
      size: ls.laserPointerSize || 24,
      trail: !!ls.laserPointerTrail,
      enabled: ls.laserPointerEnabled !== false,
    }, '*');
  }

  function patchUserPrefs(partial) {
    if (!partial || typeof partial !== 'object') return;
    if (partial.clockOrder) layoutState.clockOrder = [...partial.clockOrder];
    if (partial.timebarStops) layoutState.timebarStops = partial.timebarStops.map((s) => ({ ...s }));
    if (partial.laserPointerColor) layoutState.laserPointerColor = partial.laserPointerColor;
    if (partial.laserPointerSize != null) layoutState.laserPointerSize = partial.laserPointerSize;
    if (partial.laserPointerTrail != null) layoutState.laserPointerTrail = !!partial.laserPointerTrail;
    if (partial.laserPointerEnabled != null) layoutState.laserPointerEnabled = !!partial.laserPointerEnabled;
    if (partial.showSlideGhost != null) layoutState.showSlideGhost = !!partial.showSlideGhost;
    if (partial.slideGhostOpacity != null) layoutState.slideGhostOpacity = partial.slideGhostOpacity;
    if (partial.notesMode != null) {
      layoutState.notesMode = (partial.notesMode === 'always_open' || partial.notesMode === 'always_closed' || partial.notesMode === 'carry')
        ? partial.notesMode
        : 'carry';
    }
    if (partial.notesCollapsed != null) layoutState.notesCollapsed = !!partial.notesCollapsed;
    if (P) P.presentLayout = cloneLayout(layoutState);
    saveLayout();
    if (partial.laserPointerColor || partial.laserPointerSize != null || partial.laserPointerTrail != null || partial.laserPointerEnabled != null) broadcastLaserConfig();
    if (partial.showSlideGhost != null || partial.slideGhostOpacity != null) broadcastSlideGhost();
  }

  function broadcastSlideGhost() {
    const frame = document.getElementById('mainFrame');
    if (!frame?.contentWindow) return;
    const ls = layoutState || defaultLayout();
    frame.contentWindow.postMessage({
      type: 'sf-slide-ghost',
      enabled: !!ls.showSlideGhost,
      opacity: ls.slideGhostOpacity != null ? ls.slideGhostOpacity : 25,
    }, '*');
  }

  function init(opts) {
    P = opts.P || global.SF_PRESENT;
    getMainReveal = opts.getMainReveal || getMainReveal;
    layoutNextPreview = opts.layoutNextPreview || layoutNextPreview;
    layoutState = cloneLayout(P.presentLayout || defaultLayout());
    applyTimebarVisibility(layoutState);
    applyGridVars(layoutState);
    applyPanelHeights(layoutState.panelHeights || {});
    applyPanelOpen(layoutState.panelOpen || {});
    initColumnSplitters();
    initPanelSplitters();
    initObservers();
  }

  global.SlideForgePresentLayout = {
    init,
    fitAllPanels,
    fitPanel,
    relayoutMainReveal,
    setShowTimebar,
    setShowTimebarLive,
    patchUserPrefs,
    broadcastLaserConfig,
    broadcastSlideGhost,
    savePanelOpenState,
    applyGridVars: () => applyGridVars(layoutState),
  };
})(window);
