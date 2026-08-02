(function () {
  'use strict';
  const P = window.SF_PRESENT;
  const presentClientId = (typeof crypto !== 'undefined' && crypto.randomUUID)
    ? crypto.randomUUID()
    : ('sf-' + Date.now() + '-' + Math.random().toString(36).slice(2));
  let isPresentLeader = true;
  let applyingRemote = false;
  let multipleControllers = false;
  let globalLeaderKind = null;

  function slideDisabledFlags() {
    return Array.isArray(P.slideDisabled) ? P.slideDisabled : [];
  }

  function isSlideDisabled(idx) {
    const flags = slideDisabledFlags();
    return idx >= 0 && idx < flags.length && !!flags[idx];
  }

  function nextEnabledSlideIndex(from, direction) {
    const flags = slideDisabledFlags();
    let i = from;
    let guard = 0;
    while (guard++ < flags.length + 1) {
      i += direction;
      if (i < 0 || i >= flags.length) return null;
      if (!flags[i]) return i;
    }
    return null;
  }

  function normalizeSlideJumpIndex(idx, preferredDir) {
    if (!isSlideDisabled(idx)) return idx;
    const dir = preferredDir ?? 1;
    return nextEnabledSlideIndex(idx, dir) ?? nextEnabledSlideIndex(idx, -dir) ?? idx;
  }

  function nextPresentPreviewIndex(from) {
    const flags = slideDisabledFlags();
    for (let i = from + 1; i < flags.length; i++) {
      if (!flags[i]) return i;
    }
    return from;
  }

  function pad(n) { return n < 10 ? '0' + n : '' + n; }

  // ---------- Uhren (Studiouhr / Analog / Digital) ----------
  function getClockOrder() {
    return (P.clockOrder && P.clockOrder.length) ? P.clockOrder : ['analog', 'digital', 'studio'];
  }
  let clockModeIndex = 0;

  function showClockMode(index) {
    const clockOrder = getClockOrder();
    clockModeIndex = ((index % clockOrder.length) + clockOrder.length) % clockOrder.length;
    const mode = clockOrder[clockModeIndex];
    document.querySelectorAll('.present-clock-face').forEach((el) => {
      const active = el.dataset.clock === mode;
      el.classList.toggle('is-active', active);
      el.hidden = !active;
      el.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    requestAnimationFrame(() => {
      window.SlideForgePresentLayout?.fitPanel?.('clock');
    });
  }

  function cycleClockMode() {
    showClockMode(clockModeIndex + 1);
  }

  const SVG_NS = 'http://www.w3.org/2000/svg';

  function initStudioClock() {
    const ringG = document.getElementById('studioRingDots');
    const marksG = document.getElementById('studioHourMarks');
    if (!ringG || ringG.dataset.built) return;
    ringG.dataset.built = '1';

    for (let i = 0; i < 60; i++) {
      const angle = (i / 60) * 2 * Math.PI - Math.PI / 2;
      const cx = 100 + 79 * Math.cos(angle);
      const cy = 100 + 79 * Math.sin(angle);
      const dot = document.createElementNS(SVG_NS, 'circle');
      dot.setAttribute('cx', cx.toFixed(2));
      dot.setAttribute('cy', cy.toFixed(2));
      dot.setAttribute('r', '2.35');
      dot.setAttribute('class', 'studio-ring-dot');
      dot.dataset.sec = String(i);
      ringG.appendChild(dot);
    }

    [0, 15, 30, 45].forEach((tick) => {
      const angle = (tick / 60) * 2 * Math.PI - Math.PI / 2;
      const bx = 100 + 90 * Math.cos(angle);
      const by = 100 + 90 * Math.sin(angle);
      const tangent = angle + Math.PI / 2;
      [-3.2, 0, 3.2].forEach((spread) => {
        const mark = document.createElementNS(SVG_NS, 'circle');
        mark.setAttribute('cx', (bx + Math.cos(tangent) * spread).toFixed(2));
        mark.setAttribute('cy', (by + Math.sin(tangent) * spread).toFixed(2));
        mark.setAttribute('r', '2.55');
        mark.setAttribute('class', 'studio-mark-dot');
        marksG.appendChild(mark);
      });
    });
  }

  function initAnalogClock() {
    const ticksG = document.getElementById('analogHourTicks');
    const labelsG = document.getElementById('analogHourLabels');
    if (!ticksG || !labelsG || ticksG.dataset.built) return;
    ticksG.dataset.built = '1';

    for (let h = 0; h < 12; h++) {
      const angle = (h / 12) * 2 * Math.PI - Math.PI / 2;
      const cos = Math.cos(angle);
      const sin = Math.sin(angle);
      const tick = document.createElementNS(SVG_NS, 'line');
      tick.setAttribute('x1', (50 + 40 * cos).toFixed(2));
      tick.setAttribute('y1', (50 + 40 * sin).toFixed(2));
      tick.setAttribute('x2', (50 + 44 * cos).toFixed(2));
      tick.setAttribute('y2', (50 + 44 * sin).toFixed(2));
      tick.setAttribute('class', 'analog-hour-tick');
      ticksG.appendChild(tick);
    }

    for (let h = 1; h <= 12; h++) {
      const angle = (h / 12) * 2 * Math.PI - Math.PI / 2;
      const label = document.createElementNS(SVG_NS, 'text');
      label.setAttribute('x', (50 + 34 * Math.cos(angle)).toFixed(2));
      label.setAttribute('y', (50 + 34 * Math.sin(angle)).toFixed(2));
      label.setAttribute('text-anchor', 'middle');
      label.setAttribute('dominant-baseline', 'middle');
      label.setAttribute('class', 'analog-hour-label');
      label.textContent = String(h);
      labelsG.appendChild(label);
    }
  }

  function updateStudioClock(h, m, s) {
    const text = document.getElementById('studioTimeText');
    if (text) text.textContent = pad(h) + ':' + pad(m);
    // Gerade Minuten: Ring füllt sich im Uhrzeigersinn; ungerade: leert sich im Uhrzeigersinn.
    const fillMinute = (m % 2) === 0;
    document.querySelectorAll('.studio-ring-dot').forEach((dot) => {
      const idx = parseInt(dot.dataset.sec, 10);
      const lit = fillMinute ? idx < s : idx >= s;
      dot.classList.toggle('lit', lit);
    });
  }

  function updateClock() {
    const now = new Date();
    const h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
    const timeStr = pad(h) + ':' + pad(m) + ':' + pad(s);
    updateStudioClock(h, m, s);
    const digitalEl = document.getElementById('wallClockDigital');
    if (digitalEl) digitalEl.textContent = timeStr;
    const hourHand = document.getElementById('clockHourHand');
    const minuteHand = document.getElementById('clockMinuteHand');
    const secondHand = document.getElementById('clockSecondHand');
    if (hourHand) hourHand.setAttribute('transform', 'rotate(' + (((h % 12) + m / 60) * 30) + ' 50 50)');
    if (minuteHand) minuteHand.setAttribute('transform', 'rotate(' + ((m + s / 60) * 6) + ' 50 50)');
    if (secondHand) secondHand.setAttribute('transform', 'rotate(' + (s * 6) + ' 50 50)');
  }
  initStudioClock();
  initAnalogClock();
  showClockMode(0);
  updateClock();
  setInterval(updateClock, 1000);

  const clockArea = document.getElementById('presentClockArea');
  if (clockArea) {
    clockArea.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      cycleClockMode();
    });
    clockArea.addEventListener('keydown', (e) => {
      if (e.key === ' ') {
        e.preventDefault();
        cycleClockMode();
      }
    });
  }

  // ---------- Timer (Stoppuhr) + Fortschrittsring (wie Zeitleiste) ----------
  let timerSeconds = 0;
  let timerInterval = null;
  let timerRunning = false;
  const ICON_PAUSE = '<svg viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>';
  const ICON_PLAY = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 4l14 8-14 8z"/></svg>';

  function colorForPct(pct) {
    const stops = (P.timebarStops && P.timebarStops.length) ? P.timebarStops : [
      { pct: 0, color: '#4caf6b' }, { pct: 60, color: '#d9c23a' }, { pct: 90, color: '#dd8a2e' }, { pct: 100, color: '#d9483a' },
    ];
    let color = stops[0].color;
    stops.forEach((s) => { if (pct >= s.pct) color = s.color; });
    return color;
  }

  function getTimerProgress() {
    const durationInput = document.getElementById('timeBarDuration');
    const durationMin = durationInput ? (parseFloat(durationInput.value) || 30) : 30;
    const totalSeconds = Math.max(1, durationMin * 60);
    const ratio = timerSeconds / totalSeconds;
    const pct = Math.min(100, ratio * 100);
    return { pct, ratio, color: colorForPct(pct), litDots: Math.min(60, Math.round((pct / 100) * 60)) };
  }

  function buildTimerStopMarks() {
    const marksG = document.getElementById('timerStopMarks');
    if (!marksG) return;
    marksG.innerHTML = '';
    const stops = (P.timebarStops && P.timebarStops.length) ? P.timebarStops : [];
    stops.forEach((stop, i) => {
      if (i === 0 || i === stops.length - 1) return;
      const pct = Math.max(0, Math.min(100, Number(stop.pct) || 0));
      const angle = (pct / 100) * 2 * Math.PI - Math.PI / 2;
      const cos = Math.cos(angle);
      const sin = Math.sin(angle);
      const line = document.createElementNS(SVG_NS, 'line');
      line.setAttribute('x1', (100 + 68 * cos).toFixed(2));
      line.setAttribute('y1', (100 + 68 * sin).toFixed(2));
      line.setAttribute('x2', (100 + 86 * cos).toFixed(2));
      line.setAttribute('y2', (100 + 86 * sin).toFixed(2));
      line.setAttribute('class', 'timer-stop-tick');
      marksG.appendChild(line);

      const label = document.createElementNS(SVG_NS, 'text');
      label.setAttribute('x', (100 + 93 * cos).toFixed(2));
      label.setAttribute('y', (100 + 93 * sin).toFixed(2));
      label.setAttribute('text-anchor', 'middle');
      label.setAttribute('dominant-baseline', 'middle');
      label.setAttribute('class', 'timer-stop-label');
      label.textContent = String(Math.round(pct));
      marksG.appendChild(label);
    });
  }

  function rebuildTimebarTicks() {
    const container = document.querySelector('.present-timebar-ticks');
    if (!container) return;
    container.innerHTML = '';
    const stops = (P.timebarStops && P.timebarStops.length) ? P.timebarStops : [];
    stops.forEach((stop, i) => {
      if (i === 0 || i === stops.length - 1) return;
      const span = document.createElement('span');
      span.className = 'timebar-tick';
      span.style.bottom = stop.pct + '%';
      const b = document.createElement('b');
      b.textContent = String(Math.round(stop.pct));
      span.appendChild(b);
      container.appendChild(span);
    });
  }

  function initTimerRing() {
    const ringG = document.getElementById('timerRingDots');
    if (!ringG || ringG.dataset.built) return;
    ringG.dataset.built = '1';

    for (let i = 0; i < 60; i++) {
      const angle = (i / 60) * 2 * Math.PI - Math.PI / 2;
      const cx = 100 + 79 * Math.cos(angle);
      const cy = 100 + 79 * Math.sin(angle);
      const dot = document.createElementNS(SVG_NS, 'circle');
      dot.setAttribute('cx', cx.toFixed(2));
      dot.setAttribute('cy', cy.toFixed(2));
      dot.setAttribute('r', '2.35');
      dot.setAttribute('class', 'timer-ring-dot');
      dot.dataset.idx = String(i);
      ringG.appendChild(dot);
    }

    buildTimerStopMarks();
  }

  function updateTimerRing() {
    const { pct, ratio, color, litDots } = getTimerProgress();
    const wrap = document.getElementById('timerRingWrap');
    if (wrap) {
      wrap.style.setProperty('--timer-progress-color', color);
      wrap.classList.toggle('full', ratio >= 1);
    }
    document.querySelectorAll('.timer-ring-dot').forEach((dot) => {
      const idx = parseInt(dot.dataset.idx, 10);
      const lit = idx < litDots;
      dot.classList.toggle('lit', lit);
      if (lit) dot.style.fill = color;
      else dot.style.fill = '';
    });
  }

  function renderTimer() {
    const totalH = Math.floor(timerSeconds / 3600);
    const m = Math.floor((timerSeconds % 3600) / 60);
    const s = timerSeconds % 60;
    const el = document.getElementById('timerDisplay');
    if (el) el.textContent = (totalH > 0 ? pad(totalH) + ':' : '') + pad(m) + ':' + pad(s);
    updateTimeBar();
  }

  function updateClockProgressColors() {
    const { color } = getTimerProgress();
    const area = document.getElementById('presentClockArea');
    if (area) area.style.setProperty('--clock-progress-color', color);
  }

  function updateTimeBar() {
    const { pct, ratio, color } = getTimerProgress();
    const fill = document.getElementById('timeBarFill');
    if (fill) {
      fill.style.height = pct + '%';
      fill.style.background = color;
      fill.classList.toggle('full', ratio >= 1);
    }
    updateTimerRing();
    updateClockProgressColors();
  }

  initTimerRing();
  document.getElementById('timeBarDuration')?.addEventListener('input', updateTimeBar);
  updateTimeBar();
  function startTimer() {
    if (timerInterval) return;
    timerInterval = setInterval(() => { timerSeconds++; renderTimer(); }, 1000);
    timerRunning = true;
    const btn = document.getElementById('timerPauseBtn');
    if (btn) { btn.innerHTML = ICON_PAUSE; btn.title = 'Pausieren'; }
  }
  function pauseTimer() {
    clearInterval(timerInterval);
    timerInterval = null;
    timerRunning = false;
    const btn = document.getElementById('timerPauseBtn');
    if (btn) { btn.innerHTML = ICON_PLAY; btn.title = 'Weiter'; }
  }
  document.getElementById('timerPauseBtn')?.addEventListener('click', () => {
    timerRunning ? pauseTimer() : startTimer();
  });
  document.getElementById('timerResetBtn')?.addEventListener('click', () => {
    timerSeconds = 0;
    renderTimer();
  });
  renderTimer();
  startTimer(); // Timer läuft automatisch los, sobald der Präsentationsmodus startet.

  const presentConfigApi = window.SlideForgePresentConfig?.init({
    id: P.id,
    csrfToken: P.csrfToken,
    i18n: P.i18n,
  });

  const CONFIGURABLE_PANELS = ['next', 'clock', 'timer', 'media', 'slides'];

  (function initPresentRemoteQrDialog() {
    const modal = document.getElementById('presentRemoteQrModal');
    if (!modal) return;
    const qrSrc = document.getElementById('presentRemoteQr')?.getAttribute('src') || '';

    function isOpen() {
      return modal.classList.contains('open');
    }
    function open() {
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.getElementById('presentRemoteQrModalClose')?.focus();
    }
    function close() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }

    function paintRibbonQrThumb() {
      const btn = document.getElementById('presentRemoteQrBtn');
      if (!btn || !qrSrc) return;
      if (btn.querySelector('img.present-remote-qr-ribbon-img')) return;
      const thumb = document.createElement('img');
      thumb.className = 'present-remote-qr-ribbon-img';
      thumb.src = qrSrc;
      thumb.width = 40;
      thumb.height = 40;
      thumb.alt = '';
      thumb.decoding = 'async';
      const svg = btn.querySelector('svg');
      if (svg) svg.replaceWith(thumb);
      else btn.insertBefore(thumb, btn.firstChild);
    }
    paintRibbonQrThumb();
    /* Ribbon kann nach dem ersten Paint neu rendern */
    const ribbon = document.getElementById('presentRibbon');
    if (ribbon && typeof MutationObserver !== 'undefined') {
      const obs = new MutationObserver(() => paintRibbonQrThumb());
      obs.observe(ribbon, { childList: true, subtree: true });
    }

    document.addEventListener('click', (e) => {
      const btn = e.target.closest?.('#presentRemoteQrBtn, [data-ribbon-command="remote_qr"]');
      if (!btn || !document.getElementById('presentRibbon')?.contains(btn)) return;
      e.preventDefault();
      open();
    });
    document.getElementById('presentRemoteQrModalClose')?.addEventListener('click', close);
    document.getElementById('presentRemoteQrModalOk')?.addEventListener('click', close);
    if (window.SFModalBackdrop?.bindDismiss) {
      window.SFModalBackdrop.bindDismiss(modal, close);
    } else {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) close();
      });
    }
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape' || !isOpen()) return;
      e.preventDefault();
      e.stopPropagation();
      close();
    }, true);
  })();

  (function initPresentTimebarSettingsDialog() {
    const modal = document.getElementById('presentTimebarSettingsModal');
    if (!modal) return;

    function isOpen() {
      return modal.classList.contains('open');
    }
    function open() {
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.getElementById('presentTimebarSettingsModalClose')?.focus();
    }
    function close() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', (e) => {
      const btn = e.target.closest?.('#presentTimebarSettingsBtn, [data-ribbon-command="settings_timebar"]');
      if (!btn || !document.getElementById('presentRibbon')?.contains(btn)) return;
      e.preventDefault();
      e.stopPropagation();
      open();
    });
    document.getElementById('presentTimebarSettingsModalClose')?.addEventListener('click', close);
    document.getElementById('presentTimebarSettingsModalOk')?.addEventListener('click', close);
    if (window.SFModalBackdrop?.bindDismiss) {
      window.SFModalBackdrop.bindDismiss(modal, close);
    } else {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) close();
      });
    }
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape' || !isOpen()) return;
      e.preventDefault();
      e.stopPropagation();
      close();
    }, true);
  })();

  (function initPresentLaserSettingsDialog() {
    const modal = document.getElementById('presentLaserSettingsModal');
    if (!modal) return;

    function isOpen() {
      return modal.classList.contains('open');
    }
    function open() {
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.getElementById('presentLaserSettingsModalClose')?.focus();
    }
    function close() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', (e) => {
      const btn = e.target.closest?.('#presentLaserSettingsBtn, [data-ribbon-command="settings_laser"]');
      if (!btn || !document.getElementById('presentRibbon')?.contains(btn)) return;
      e.preventDefault();
      e.stopPropagation();
      open();
    });
    document.getElementById('presentLaserSettingsModalClose')?.addEventListener('click', close);
    document.getElementById('presentLaserSettingsModalOk')?.addEventListener('click', close);
    if (window.SFModalBackdrop?.bindDismiss) {
      window.SFModalBackdrop.bindDismiss(modal, close);
    } else {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) close();
      });
    }
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape' || !isOpen()) return;
      e.preventDefault();
      e.stopPropagation();
      close();
    }, true);
  })();

  (function initPresentClockSettingsDialog() {
    const modal = document.getElementById('presentClockSettingsModal');
    if (!modal) return;

    function isOpen() {
      return modal.classList.contains('open');
    }
    function open() {
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.getElementById('presentClockSettingsModalClose')?.focus();
    }
    function close() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', (e) => {
      const btn = e.target.closest?.('#presentClockSettingsBtn, [data-ribbon-command="settings_clock"]');
      if (!btn || !document.getElementById('presentRibbon')?.contains(btn)) return;
      e.preventDefault();
      e.stopPropagation();
      open();
    });
    document.getElementById('presentClockSettingsModalClose')?.addEventListener('click', close);
    document.getElementById('presentClockSettingsModalOk')?.addEventListener('click', close);
    if (window.SFModalBackdrop?.bindDismiss) {
      window.SFModalBackdrop.bindDismiss(modal, close);
    } else {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) close();
      });
    }
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape' || !isOpen()) return;
      e.preventDefault();
      e.stopPropagation();
      close();
    }, true);
  })();

  function syncTimebarToggleUi(show) {
    const on = show !== false;
    document.querySelectorAll('#presentRibbon [data-ribbon-command="panel_timebar"]').forEach((btn) => {
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function currentShowTimebar() {
    return !document.querySelector('.present-layout')?.classList.contains('present-timebar-hidden');
  }

  (function initPresentTimebarToggle() {
    const ribbon = document.getElementById('presentRibbon');
    if (!ribbon) return;

    ribbon.addEventListener('click', (e) => {
      const btn = e.target.closest?.('[data-ribbon-command="panel_timebar"]');
      if (!btn || !ribbon.contains(btn)) return;
      e.preventDefault();
      e.stopPropagation();
      const next = !currentShowTimebar();
      window.SlideForgePresentLayout?.setShowTimebar?.(next);
      syncTimebarToggleUi(next);
    });

    document.addEventListener('sf:show-timebar', (e) => {
      syncTimebarToggleUi(!!(e.detail && e.detail.show));
    });
    document.addEventListener('sf:ribbon-rendered', () => {
      syncTimebarToggleUi(currentShowTimebar());
    });
    syncTimebarToggleUi(currentShowTimebar());
  })();

  (function initPresentSettingsHost() {
    const wrap = document.querySelector('#presentSettingsHost[data-settings-menu]');
    if (!wrap) return;
    wrap.querySelectorAll('[data-settings-back]').forEach((backBtn) => {
      backBtn.addEventListener('click', () => {
        wrap.querySelectorAll('[data-settings-panel]').forEach((p) => { p.hidden = true; });
        if (window.SFRibbon?.resetFloatingSettingsPanels) {
          window.SFRibbon.resetFloatingSettingsPanels();
        }
        document.querySelectorAll('#presentRibbon [data-ribbon-settings].active').forEach((b) => {
          b.classList.remove('active');
        });
      });
    });
  })();

  function isPanelUserVisible(panelId) {
    const list = P.presentPanels || [];
    const found = list.find((p) => p.id === panelId);
    return found ? !!found.visible : true;
  }

  function applyPanelLayout(panels) {
    const container = document.querySelector('.present-side-accordions');
    if (!container || !Array.isArray(panels)) return;
    panels.forEach((p) => {
      if (!CONFIGURABLE_PANELS.includes(p.id)) return;
      const group = container.querySelector('.props-accordion-group[data-acc="' + p.id + '"]');
      if (!group) return;
      if (p.id === 'media') {
        group.dataset.userVisible = p.visible ? '1' : '0';
        if (!p.visible) group.hidden = true;
      } else {
        group.hidden = !p.visible;
      }
      container.appendChild(group);
    });
    if (panels.some((p) => p.id === 'next' && p.visible)) {
      requestAnimationFrame(layoutNextPreview);
    }
    updateMediaControls();
    window.SlideForgePresentLayout?.fitAllPanels?.();
    syncPanelToggleButtons(panels);
  }

  function readPanelsFromSidebar() {
    const container = document.querySelector('.present-side-accordions');
    if (!container) return P.presentPanels || [];
    const known = new Map((P.presentPanels || []).map((p) => [p.id, p]));
    return Array.from(container.querySelectorAll('.props-accordion-group[data-acc]'))
      .filter((group) => CONFIGURABLE_PANELS.includes(group.dataset.acc))
      .map((group) => {
        const id = group.dataset.acc;
        const saved = known.get(id);
        let visible;
        if (id === 'media') {
          visible = saved ? !!saved.visible : group.dataset.userVisible !== '0';
        } else {
          visible = saved ? !!saved.visible : !group.hidden;
        }
        return { id, visible };
      });
  }

  function syncPanelToggleButtons(panels) {
    const list = Array.isArray(panels) ? panels : (P.presentPanels || []);
    const byId = new Map(list.map((p) => [p.id, !!p.visible]));
    document.querySelectorAll('#presentRibbon [data-ribbon-command^="panel_"]').forEach((btn) => {
      const id = String(btn.dataset.ribbonCommand || '').replace(/^panel_/, '');
      if (!CONFIGURABLE_PANELS.includes(id)) return;
      const on = byId.has(id) ? byId.get(id) : true;
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function revealSidebarPanel(panelId) {
    const group = document.querySelector('.present-side-accordions .props-accordion-group[data-acc="' + panelId + '"]');
    if (!group || group.hidden) return;
    group.classList.add('open');
    try {
      group.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    } catch (e) { /* ignore */ }
  }

  function insertSidebarPanelAt(container, group, clientY) {
    const siblings = Array.from(container.querySelectorAll('.props-accordion-group[data-acc]'))
      .filter((g) => CONFIGURABLE_PANELS.includes(g.dataset.acc) && g !== group);
    let target = null;
    for (const g of siblings) {
      const rect = g.getBoundingClientRect();
      if (clientY < rect.top + rect.height / 2) {
        target = g;
        break;
      }
    }
    if (target) container.insertBefore(group, target);
    else container.appendChild(group);
  }

  let panelSaveTimer = null;
  function savePresentPanels(panels) {
    clearTimeout(panelSaveTimer);
    panelSaveTimer = setTimeout(() => {
      fetch('user_api.php?action=set_present_panels', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ panels: panels, csrf_token: P.csrfToken }),
      }).then((r) => r.json()).then((json) => {
        if (json.ok && json.panels) {
          P.presentPanels = json.panels;
          applyPanelLayout(json.panels);
        }
      }).catch(() => {});
    }, 180);
  }

  (function initPresentPanelToggles() {
    const ribbon = document.getElementById('presentRibbon');
    if (!ribbon) return;

    function currentPanels() {
      const known = new Map((P.presentPanels || []).map((p) => [p.id, p]));
      return CONFIGURABLE_PANELS.map((id) => {
        const saved = known.get(id);
        return { id, visible: saved ? !!saved.visible : true };
      });
    }

    ribbon.addEventListener('click', (e) => {
      const btn = e.target.closest?.('[data-ribbon-command^="panel_"]');
      if (!btn || !ribbon.contains(btn)) return;
      const id = String(btn.dataset.ribbonCommand || '').replace(/^panel_/, '');
      if (!CONFIGURABLE_PANELS.includes(id)) return;
      e.preventDefault();
      e.stopPropagation();
      const panels = currentPanels().map((p) => (
        p.id === id ? { id: p.id, visible: !p.visible } : p
      ));
      /* Reihenfolge aus der rechten Leiste beibehalten */
      const order = readPanelsFromSidebar().map((p) => p.id);
      const byId = new Map(panels.map((p) => [p.id, p]));
      const ordered = [];
      order.forEach((oid) => {
        if (byId.has(oid)) {
          ordered.push(byId.get(oid));
          byId.delete(oid);
        }
      });
      byId.forEach((p) => ordered.push(p));
      P.presentPanels = ordered;
      applyPanelLayout(ordered);
      savePresentPanels(ordered);
      const toggled = ordered.find((p) => p.id === id);
      if (toggled && toggled.visible) revealSidebarPanel(id);
    });

    document.addEventListener('sf:ribbon-rendered', () => {
      syncPanelToggleButtons(P.presentPanels || []);
    });
    syncPanelToggleButtons(P.presentPanels || []);
  })();

  (function initSidebarPanelReorder() {
    const container = document.querySelector('.present-side-accordions');
    if (!container) return;

    let dragGroup = null;
    let dragStarted = false;
    let suppressHeaderClick = false;
    let startY = 0;
    let activePointerId = null;

    function finishDrag() {
      document.body.classList.remove('present-panel-dragging');
      if (dragGroup) dragGroup.classList.remove('panel-dragging');
      if (dragStarted) {
        const panels = readPanelsFromSidebar();
        savePresentPanels(panels);
      }
      dragGroup = null;
      activePointerId = null;
      if (dragStarted) {
        setTimeout(() => { suppressHeaderClick = false; dragStarted = false; }, 120);
      } else {
        suppressHeaderClick = false;
        dragStarted = false;
      }
    }

    function onPointerMove(clientY) {
      if (!dragGroup) return;
      dragStarted = true;
      suppressHeaderClick = true;
      dragGroup.classList.add('panel-dragging');
      insertSidebarPanelAt(container, dragGroup, clientY);
    }

    function groupFromEventTarget(target) {
      const handle = target && target.closest ? target.closest('.present-panel-drag-handle') : null;
      if (!handle || !container.contains(handle)) return null;
      const group = handle.closest('.props-accordion-group');
      if (!group || group.hidden) return null;
      if (!CONFIGURABLE_PANELS.includes(group.dataset.acc)) return null;
      return group;
    }

    container.addEventListener('click', (e) => {
      if (e.target.closest('.present-panel-drag-handle')) e.stopPropagation();
    });

    container.addEventListener('pointerdown', (e) => {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      const group = groupFromEventTarget(e.target);
      if (!group) return;
      e.preventDefault();
      e.stopPropagation();
      dragGroup = group;
      dragStarted = false;
      startY = e.clientY;
      activePointerId = e.pointerId;
      document.body.classList.add('present-panel-dragging');
    });

    document.addEventListener('pointermove', (e) => {
      if (!dragGroup || e.pointerId !== activePointerId) return;
      const threshold = e.pointerType === 'touch' ? 6 : 4;
      if (Math.abs(e.clientY - startY) < threshold && !dragStarted) return;
      if (e.cancelable) e.preventDefault();
      onPointerMove(e.clientY);
    }, { passive: false });

    document.addEventListener('pointerup', (e) => {
      if (!dragGroup || e.pointerId !== activePointerId) return;
      finishDrag();
    });
    document.addEventListener('pointercancel', (e) => {
      if (!dragGroup || e.pointerId !== activePointerId) return;
      finishDrag();
    });

    container.addEventListener('click', (e) => {
      if (!suppressHeaderClick) return;
      if (e.target.closest('.props-accordion-header')) {
        e.stopImmediatePropagation();
      }
    }, true);
  })();

  const mainFrame = document.getElementById('mainFrame');
  const nextPreview = document.getElementById('nextSlidePreview');
  let mainReveal = null;
  let currentIndex = P.startSlide || 0;
  let controlsBound = false;

  function presentSlideIndex(preferredDir) {
    if (!mainReveal) return currentIndex;
    let h = (mainReveal.getIndices() || { h: 0 }).h || 0;
    if (isSlideDisabled(h)) {
      h = normalizeSlideJumpIndex(h, preferredDir);
    }
    return h;
  }

  document.querySelectorAll('.filmstrip-item').forEach((btn) => {
    btn.classList.toggle('active', parseInt(btn.dataset.index, 10) === currentIndex);
  });

  function waitForReveal(win, cb) {
    const check = () => {
      try {
        if (win.Reveal && win.Reveal.isReady && win.Reveal.isReady()) { cb(win.Reveal); return; }
      } catch (e) { /* Frame evtl. noch nicht bereit */ }
      setTimeout(check, 50);
    };
    check();
  }

  function layoutNextPreview() {
    if (!nextPreview) return;
    const scaleEl = nextPreview.querySelector('.present-next-thumb-scale');
    if (!scaleEl) return;
    const sw = P.slideWidth || 1920;
    const pw = nextPreview.clientWidth;
    if (pw < 1) return;
    const scale = pw / sw;
    scaleEl.style.transform = 'scale(' + scale + ')';
    nextPreview.style.height = '';

    const nextGroup = document.querySelector('.props-accordion-group[data-acc="next"]');
    if (nextGroup && nextGroup.classList.contains('open')) {
      const handle = nextGroup.querySelector('.present-panel-resize-handle');
      const handleH = handle && !handle.hidden ? handle.offsetHeight : 0;
      const bodyH = nextPreview.offsetHeight + handleH + 8;
      nextGroup.style.setProperty('--present-panel-body-h', bodyH + 'px');
    }
  }

  function syncNextPreview() {
    if (!nextPreview) return;
    const scaleEl = nextPreview.querySelector('.present-next-thumb-scale');
    const nextIdx = nextPresentPreviewIndex(currentIndex);
    const src = document.querySelector('.filmstrip-item[data-index="' + nextIdx + '"] .filmstrip-thumb-scale');
    if (scaleEl && src) scaleEl.innerHTML = src.innerHTML;
    layoutNextPreview();
  }

  if (nextPreview) {
    const nextRo = new ResizeObserver(() => layoutNextPreview());
    nextRo.observe(nextPreview);
    window.addEventListener('resize', layoutNextPreview);
    requestAnimationFrame(layoutNextPreview);
  }

  applyPanelLayout(P.presentPanels || []);

  function syncNotesForSlide(h) {
    const overlay = document.getElementById('presentNotesOverlay');
    const notesPanel = document.getElementById('notesPanel');
    const html = P.notesHtml[h];
    const hasNotes = !!(html && html.trim() !== '');
    if (notesPanel) notesPanel.innerHTML = hasNotes ? html : '';
    if (overlay) overlay.hidden = !hasNotes;
  }

  (function initPresentNotesCollapse() {
    const overlay = document.getElementById('presentNotesOverlay');
    const body = document.getElementById('presentNotesBody');
    const register = document.getElementById('presentNotesRegister');
    if (!overlay || !body || !register) return;

    const STORAGE_KEY = 'sf_present_notes_collapsed';
    const i18n = P.i18n || {};

    function isCollapsed() {
      return overlay.classList.contains('is-collapsed');
    }

    function setCollapsed(on) {
      overlay.classList.toggle('is-collapsed', !!on);
      register.setAttribute('aria-expanded', on ? 'false' : 'true');
      register.title = on
        ? (i18n.notesExpandHint || i18n.toggleNotes || register.title)
        : (i18n.notesCollapseHint || i18n.toggleNotes || register.title);
      body.title = on ? '' : (i18n.notesCollapseHint || '');
      try {
        localStorage.setItem(STORAGE_KEY, on ? '1' : '0');
      } catch (e) { /* ignore */ }
    }

    try {
      if (localStorage.getItem(STORAGE_KEY) === '1') setCollapsed(true);
      else setCollapsed(false);
    } catch (e) {
      setCollapsed(false);
    }

    let ptr = null;
    body.addEventListener('pointerdown', (e) => {
      if (e.button != null && e.button !== 0) return;
      if (isCollapsed()) return;
      ptr = { x: e.clientX, y: e.clientY, id: e.pointerId };
    });
    body.addEventListener('pointerup', (e) => {
      if (!ptr || ptr.id !== e.pointerId) return;
      const dx = Math.abs(e.clientX - ptr.x);
      const dy = Math.abs(e.clientY - ptr.y);
      ptr = null;
      if (dx > 10 || dy > 10) return;
      if (window.getSelection && String(window.getSelection()).length > 0) return;
      setCollapsed(true);
    });
    body.addEventListener('pointercancel', () => { ptr = null; });

    register.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      setCollapsed(!isCollapsed());
    });
  })();

  syncNotesForSlide(currentIndex);

  window.SlideForgePresentLayout?.init({
    P,
    getMainReveal: () => mainReveal,
    layoutNextPreview,
  });

  mainFrame.addEventListener('load', () => {
    waitForReveal(mainFrame.contentWindow, (reveal) => {
      mainReveal = reveal;
      mainReveal.on('slidechanged', (e) => {
        const prevH = typeof e.previousIndexh === 'number' ? e.previousIndexh : currentIndex;
        const dir = (typeof e.indexh === 'number' ? e.indexh : currentIndex) >= prevH ? 1 : -1;
        requestAnimationFrame(() => updateUI(presentSlideIndex(dir)));
      });
      // Fragmente (schrittweise Animationen innerhalb einer Folie) lösen KEIN
      // 'slidechanged' aus, müssen aber genauso live übertragen werden - sonst bleibt
      // die Zuschauer-Ansicht (view.php) beim vorherigen Fragment-Stand hängen und
      // überspringt ihn beim nächsten echten Folienwechsel.
      mainReveal.on('fragmentshown', () => broadcastPosition(currentIndex));
      mainReveal.on('fragmenthidden', () => broadcastPosition(currentIndex));
      updateUI((mainReveal.getIndices() || { h: 0 }).h || 0);
      window.SlideForgePresentLayout?.broadcastLaserConfig?.();
      window.SlideForgePresentLayout?.broadcastSlideGhost?.();
      try { mainFrame.contentWindow?.focus(); } catch (err) { /* ignore */ }
      if (!controlsBound) { bindControls(); controlsBound = true; }
    });
  });

  function updateUI(h) {
    currentIndex = h;
    const counter = document.getElementById('presCounter');
    if (counter) counter.textContent = (h + 1) + ' / ' + P.slideCount;

    const editorHref = 'editor.php?id=' + encodeURIComponent(P.id) + '&slide=' + h;
    document.querySelectorAll('#editorBackLink, #editorBackLinkRibbon, a[data-ribbon-command="back_to_editor"]').forEach((el) => {
      el.href = editorHref;
    });

    syncNotesForSlide(h);

    document.querySelectorAll('.filmstrip-item').forEach((btn) => {
      const active = parseInt(btn.dataset.index, 10) === h;
      btn.classList.toggle('active', active);
      if (active) btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    });

    syncNextPreview();
    broadcastPosition(h);
    updateMediaControls();
  }

  function updateMediaControls() {
    const accordion = document.getElementById('mediaControlAccordion');
    const list = document.getElementById('mediaControlList');
    if (!accordion || !list || !P.canBroadcast) return;
    const header = accordion.querySelector('.props-accordion-header');
    if (header && !header.querySelector('.present-panel-drag-handle')) {
      const handle = document.createElement('span');
      handle.className = 'present-panel-drag-handle';
      handle.setAttribute('aria-hidden', 'true');
      handle.title = (P.i18n && P.i18n.reorderPanel) || 'Ziehen zum Sortieren';
      handle.textContent = '⋮⋮';
      header.insertBefore(handle, header.firstChild);
    }
    const userWants = isPanelUserVisible('media');
    let mediaEls = [];
    let allSlides = [];
    try {
      mediaEls = Array.from(mainFrame.contentDocument.querySelectorAll('[data-media-id]'));
      allSlides = Array.from(mainFrame.contentDocument.querySelectorAll('.reveal .slides > section'));
    } catch (e) { /* Frame evtl. noch nicht bereit */ }
    if (!mediaEls.length) {
      accordion.hidden = true;
      list.innerHTML = '';
      return;
    }
    accordion.hidden = !userWants;
    if (!userWants) {
      list.innerHTML = '';
      return;
    }
    list.innerHTML = mediaEls.map((el) => {
      const mid = el.getAttribute('data-media-id');
      const label = el.tagName === 'AUDIO' ? 'Audio' : 'Video';
      const slideEl = el.closest('section');
      const slideIdx = allSlides.indexOf(slideEl);
      const slideLabel = slideIdx >= 0 ? (' &ndash; Folie ' + (slideIdx + 1)) : '';
      return '<div class="media-control-row">' +
        '<span>' + label + slideLabel + '</span>' +
        '<div class="media-control-btns">' +
          '<button type="button" data-media-cmd="play" data-media-id="' + mid + '" title="Abspielen">▶</button>' +
          '<button type="button" data-media-cmd="pause" data-media-id="' + mid + '" title="Pause">⏸</button>' +
          '<button type="button" data-media-cmd="stop" data-media-id="' + mid + '" title="Stopp">⏹</button>' +
        '</div>' +
      '</div>';
    }).join('');
    list.querySelectorAll('[data-media-cmd]').forEach((btn) => {
      btn.addEventListener('click', () => {
        withLeaderControl(() => sendMediaCommand(btn.dataset.mediaId, btn.dataset.mediaCmd));
      });
    });
  }

  function sendMediaCommand(mediaId, action) {
    if (!P.canBroadcast) return;
    fetch('live.php?id=' + encodeURIComponent(P.id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'media', media_id: mediaId, media_action: action, csrf_token: P.csrfToken }),
    }).catch(() => {});
  }

  function updateLeaderUI() {
    const status = document.getElementById('presentControlStatus');
    const dot = document.getElementById('presentControlDot');
    if (!P.canBroadcast) {
      if (status) status.hidden = true;
      return;
    }
    const show = multipleControllers;
    if (status) {
      status.hidden = !show;
      status.classList.toggle('is-master', show && isPresentLeader);
      status.classList.toggle('is-follower', show && !isPresentLeader);
    }
    if (!dot || !show) return;
    dot.classList.toggle('is-master', isPresentLeader);
    dot.classList.toggle('is-follower', !isPresentLeader);
  }

  function applyLeaderState(leader, controlClients) {
    if (!P.canBroadcast) return;
    if (controlClients && typeof controlClients.multiple === 'boolean') {
      multipleControllers = controlClients.multiple;
    }
    if (!leader) {
      updateLeaderUI();
      return;
    }
    const leaderId = leader.client_id;
    const active = !!leader.active;
    globalLeaderKind = leader.kind || null;
    if (active && leaderId && leaderId !== presentClientId) {
      isPresentLeader = false;
    } else if (!active || leaderId === presentClientId || !leaderId) {
      isPresentLeader = true;
    }
    updateLeaderUI();
  }

  function remoteDrivesPresentation() {
    return globalLeaderKind === 'remote' && !isPresentLeader;
  }

  function canBroadcastPosition() {
    if (!P.canBroadcast || applyingRemote) return false;
    if (isPresentLeader) return true;
    // Present-Modus leitet Remote-Schritte an Zuschauer (view.php) weiter.
    if (globalLeaderKind === 'remote') return true;
    return false;
  }

  function syncFollowerPosition(live) {
    if (isPresentLeader || !mainReveal || !live || typeof live.index !== 'number') return;
    const targetIndex = normalizeSlideJumpIndex(live.index);
    const frag = typeof live.frag === 'number' ? live.frag : null;
    const indices = mainReveal.getIndices() || {};
    const curH = indices.h || 0;
    const curF = typeof indices.f === 'number' ? indices.f : -1;
    if (targetIndex === curH && (frag === null || frag === curF)) return;
    applyingRemote = true;
    if (frag !== null) mainReveal.slide(targetIndex, 0, frag);
    else mainReveal.slide(targetIndex, 0);
    applyingRemote = false;
    currentIndex = targetIndex;
  }

  function claimPresentLeader() {
    if (!P.canBroadcast) return Promise.resolve(false);
    return fetch('live.php?id=' + encodeURIComponent(P.id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'claim_leader',
        client_id: presentClientId,
        client_kind: 'present',
        csrf_token: P.csrfToken,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data || !data.ok) return false;
        isPresentLeader = !!data.is_leader;
        globalLeaderKind = data.leader_kind || 'present';
        updateLeaderUI();
        if (isPresentLeader && mainReveal) broadcastPosition(currentIndex);
        return isPresentLeader;
      })
      .catch(() => false);
  }

  function withLeaderControl(fn) {
    if (!P.canBroadcast) return;
    if (isPresentLeader) { fn(); return; }
    claimPresentLeader().then((ok) => { if (ok) fn(); });
  }

  function broadcastPosition(h, fragOverride) {
    if (!canBroadcastPosition()) return;
    let frag = fragOverride;
    if (frag === undefined) {
      const indices = mainReveal ? mainReveal.getIndices() : null;
      frag = indices && typeof indices.f === 'number' ? indices.f : null;
    }
    fetch('live.php?id=' + encodeURIComponent(P.id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        index: h,
        frag: frag,
        channel: 'present',
        client_id: presentClientId,
        csrf_token: P.csrfToken,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data || !data.ok) return;
        if (data.is_leader === false) {
          isPresentLeader = false;
          updateLeaderUI();
        } else if (data.is_leader === true) {
          isPresentLeader = true;
          updateLeaderUI();
        }
      })
      .catch(() => {});
  }

  let laserBroadcastTimer = null;
  function broadcastLaser(data) {
    if (!P.canBroadcast || !data || P.presentLayout?.laserPointerEnabled === false) return;
    fetch('live.php?id=' + encodeURIComponent(P.id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'laser',
        active: !!data.active,
        x: data.x,
        y: data.y,
        slide_index: data.slideIndex,
        color: data.color,
        size: data.size,
        trail: !!data.trail,
        csrf_token: P.csrfToken,
      }),
    }).catch(() => {});
  }

  window.addEventListener('message', (e) => {
    if (!e.data || e.data.type !== 'sf-present-position') return;
    if (!mainFrame?.contentWindow || e.source !== mainFrame.contentWindow) return;
    if (typeof e.data.index !== 'number') return;
    currentIndex = e.data.index;
    const frag = typeof e.data.frag === 'number' ? e.data.frag : null;
    broadcastPosition(e.data.index, frag);
  });

  window.addEventListener('message', (e) => {
    if (!e.data || e.data.type !== 'sf-laser-live') return;
    if (!P.canBroadcast) return;
    if (!e.data.active) {
      clearTimeout(laserBroadcastTimer);
      broadcastLaser(e.data);
      return;
    }
    clearTimeout(laserBroadcastTimer);
    laserBroadcastTimer = setTimeout(() => broadcastLaser(e.data), 16);
  });

  function isPresentTypingTarget(el) {
    if (!el || !(el instanceof Element)) return false;
    const tag = el.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
    return !!el.isContentEditable;
  }

  function shouldSkipPresentNav(e) {
    const t = e.target;
    if (!t || !(t instanceof Element)) return false;
    if (isPresentTypingTarget(t)) return true;
    if (t.closest('button, a, [role="button"], #presentClockArea')) return true;
    const qrModal = document.getElementById('presentRemoteQrModal');
    if (qrModal && qrModal.classList.contains('open')) return true;
    return false;
  }

  let presentNavKeysBound = false;

  function bindPresentNavKeys() {
    if (presentNavKeysBound) return;
    presentNavKeysBound = true;

    function handleParentNavKey(e) {
      if (!mainReveal || shouldSkipPresentNav(e)) return;
      if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown' || e.key === 'Enter') {
        e.preventDefault();
        withLeaderControl(() => mainReveal.next());
      } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
        e.preventDefault();
        withLeaderControl(() => mainReveal.prev());
      }
    }

    document.addEventListener('keydown', handleParentNavKey);
  }

  function bindControls() {
    document.getElementById('presPrevBtn').addEventListener('click', () => {
      withLeaderControl(() => { if (mainReveal) mainReveal.prev(); });
    });
    document.getElementById('presNextBtn').addEventListener('click', () => {
      withLeaderControl(() => { if (mainReveal) mainReveal.next(); });
    });

    document.querySelectorAll('.filmstrip-item').forEach((btn) => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.index, 10);
        if (isSlideDisabled(idx)) return;
        withLeaderControl(() => {
          if (!mainReveal) return;
          if (idx === currentIndex) {
            // Gleiche Folie: nächster Animationsschritt, oder nächste Folie wenn fertig
            mainReveal.next();
          } else {
            mainReveal.slide(normalizeSlideJumpIndex(idx), 0);
          }
        });
      });
    });

    bindPresentNavKeys();

    document.querySelector('.present-current-panel')?.addEventListener('mousedown', () => {
      try { mainFrame.contentWindow?.focus(); } catch (err) { /* ignore */ }
    });
  }

  // Heartbeat: Live-Sitzung auch ohne Navigation "aktiv" halten (siehe getLivePosition()-Timeout serverseitig).
  let lastRemoteCommandTs = 0;
  let lastRemoteConfigTs = 0;
  let remotePollMs = 500;

  function forwardRemoteLaser(laser) {
    if ((P.presentLayout?.laserPointerEnabled === false) || !mainFrame?.contentWindow || !laser) return;
    mainFrame.contentWindow.postMessage({
      type: 'sf-laser-remote',
      active: !!laser.active,
      x: laser.x,
      y: laser.y,
      slideIndex: laser.slideIndex,
      color: laser.color,
      size: laser.size,
      trail: !!laser.trail,
    }, '*');
  }

  function pollLiveRemote() {
    fetch('live.php?id=' + encodeURIComponent(P.id) + '&full=1')
      .then((r) => r.json())
      .then((data) => {
        if (!data || !data.ok) return;

        const remoteActive = !!(data.sessions && data.sessions.remote && data.sessions.remote.active);
        const badge = document.getElementById('presentRemoteBadge');
        if (badge) badge.hidden = !remoteActive;
        remotePollMs = remoteActive ? 80 : 500;

        if (data.config && data.config.source === 'remote' && data.config.ts > lastRemoteConfigTs) {
          if (typeof data.config.showTimebar === 'boolean') {
            lastRemoteConfigTs = data.config.ts;
            window.SlideForgePresentLayout?.setShowTimebarLive?.(!!data.config.showTimebar);
            syncTimebarToggleUi(!!data.config.showTimebar);
          }
        }

        if (data.present_leader) {
          applyLeaderState(data.present_leader, data.control_clients);
        }

        const remoteControls = globalLeaderKind === 'remote';

        if (data.command && data.command.type === 'step' && data.command.cmd_ts > lastRemoteCommandTs && mainReveal && (isPresentLeader || remoteControls)) {
          lastRemoteCommandTs = data.command.cmd_ts;
          applyingRemote = true;
          const stepDir = data.command.direction === 'prev' ? -1 : 1;
          if (data.command.direction === 'next') mainReveal.next();
          else if (data.command.direction === 'prev') mainReveal.prev();
          applyingRemote = false;
          requestAnimationFrame(() => {
            const h = presentSlideIndex(stepDir);
            updateUI(h);
          });
        } else if (!isPresentLeader && data.live && !remoteControls) {
          syncFollowerPosition(data.live);
        }

        if (data.live && data.live.laser) {
          forwardRemoteLaser(data.live.laser);
        }
      })
      .catch(() => {});
  }

  let remotePollTimer = null;
  function scheduleRemotePoll() {
    clearTimeout(remotePollTimer);
    remotePollTimer = setTimeout(() => {
      pollLiveRemote();
      scheduleRemotePoll();
    }, remotePollMs);
  }
  scheduleRemotePoll();

  setInterval(() => {
    if (!P.canBroadcast) return;
    fetch('live.php?id=' + encodeURIComponent(P.id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'present_heartbeat',
        client_id: presentClientId,
        csrf_token: P.csrfToken,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data || !data.ok) return;
        if (data.is_leader === false) {
          isPresentLeader = false;
        } else if (data.is_leader === true) {
          isPresentLeader = true;
        }
        if (data.leader_kind) globalLeaderKind = data.leader_kind;
        updateLeaderUI();
      })
      .catch(() => {});
    if (isPresentLeader && mainReveal) {
      broadcastPosition(currentIndex);
    }
  }, 8000);

  window.addEventListener('beforeunload', () => {
    if (P.canBroadcast && isPresentLeader && navigator.sendBeacon) {
      navigator.sendBeacon(
        'live.php?id=' + encodeURIComponent(P.id),
        JSON.stringify({ action: 'stop', csrf_token: P.csrfToken })
      );
    }
  });

  // ---------- Seiten-Akkordeons (Nächste Folie, Uhr, Medien, Folien) ----------
  (function () {
    const container = document.querySelector('.present-side-accordions');
    if (!container) return;

    const collapseRules = [
      { acc: 'clock', below: 1280 },
      { acc: 'next', below: 1080 },
      { acc: 'media', below: 960 },
      { acc: 'timer', below: 900 },
    ];

    function applyResponsiveAccordions() {
      const w = window.innerWidth;
      let nextOpened = false;
      container.querySelectorAll('.props-accordion-group[data-acc]').forEach((group) => {
        if (group.hidden) return;
        if (group.dataset.userToggled === '1') return;
        const rule = collapseRules.find((r) => r.acc === group.dataset.acc);
        const shouldOpen = !rule || w >= rule.below;
        if (group.dataset.acc === 'next' && shouldOpen && !group.classList.contains('open')) nextOpened = true;
        group.classList.toggle('open', shouldOpen);
      });
      if (nextOpened) requestAnimationFrame(layoutNextPreview);
    }

    container.addEventListener('click', (e) => {
      const header = e.target.closest('.props-accordion-header');
      if (!header || !container.contains(header)) return;
      if (e.target.closest('.present-panel-drag-handle')) return;
      const group = header.closest('.props-accordion-group');
      if (!group) return;
      const willOpen = !group.classList.contains('open');
      group.classList.toggle('open', willOpen);
      group.dataset.userToggled = '1';
      window.SlideForgePresentLayout?.savePanelOpenState?.();
      if (willOpen) {
        requestAnimationFrame(function () {
          group.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
          if (group.dataset.acc === 'next') layoutNextPreview();
          else window.SlideForgePresentLayout?.fitPanel?.(group.dataset.acc);
        });
      }
    });

    applyResponsiveAccordions();
    window.addEventListener('resize', applyResponsiveAccordions);
  })();

  window.SlideForgePresentUi = {
    setClockOrder(order) {
      if (!Array.isArray(order) || !order.length) return;
      P.clockOrder = order;
      showClockMode(0);
    },
    setTimebarStops(stops) {
      if (!Array.isArray(stops) || stops.length < 2) return;
      P.timebarStops = stops;
      buildTimerStopMarks();
      rebuildTimebarTicks();
      updateTimeBar();
    },
  };
})();
