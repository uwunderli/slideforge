(function () {
  'use strict';
  const P = window.SF_PRESENT;
  let dockHasNotes = false;
  let dockHasMedia = false;
  let dockActiveTab = 'notes';
  const presentClientId = (typeof crypto !== 'undefined' && crypto.randomUUID)
    ? crypto.randomUUID()
    : ('sf-' + Date.now() + '-' + Math.random().toString(36).slice(2));
  let isPresentLeader = true;
  let applyingRemote = false;
  let multipleControllers = false;
  let globalLeaderKind = null;

  function applyCsrfFromResponse(data) {
    if (data && typeof data.csrf_token === 'string' && data.csrf_token !== '') {
      P.csrfToken = data.csrf_token;
      if (window.SF_BOOTSTRAP) window.SF_BOOTSTRAP.csrfToken = data.csrf_token;
    }
  }

  function livePost(body, retried) {
    return fetch('live.php?id=' + encodeURIComponent(P.id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ csrf_token: P.csrfToken }, body || {})),
    })
      .then((r) => r.json().then((data) => ({ status: r.status, data })))
      .then(({ status, data }) => {
        applyCsrfFromResponse(data);
        if (!retried && data && data.error === 'csrf' && data.csrf_token) {
          return livePost(body, true);
        }
        if (status === 401) {
          return { ok: false, error: 'auth' };
        }
        return data;
      })
      .catch(() => null);
  }

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
    presentationTitle: P.title || '',
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

  (function initPresentNotesSettingsDialog() {
    const modal = document.getElementById('presentNotesSettingsModal');
    if (!modal) return;
    const MODE_KEY = 'sf_present_notes_mode';
    const radios = [...modal.querySelectorAll('input[name="presentNotesMode"]')];

    function readMode() {
      const fromLayout = P.presentLayout?.notesMode;
      if (fromLayout === 'always_open' || fromLayout === 'always_closed' || fromLayout === 'carry') {
        return fromLayout;
      }
      try {
        const v = localStorage.getItem(MODE_KEY);
        if (v === 'always_open' || v === 'always_closed' || v === 'carry') return v;
      } catch (e) { /* ignore */ }
      return 'carry';
    }

    function writeMode(mode) {
      const next = (mode === 'always_open' || mode === 'always_closed' || mode === 'carry') ? mode : 'carry';
      try { localStorage.setItem(MODE_KEY, next); } catch (e) { /* ignore */ }
      if (P.presentLayout) P.presentLayout.notesMode = next;
      window.SlideForgePresentLayout?.patchUserPrefs?.({ notesMode: next });
      window.SlideForgePresentNotes?.applyMode?.(next);
      return next;
    }

    function syncRadios() {
      const mode = readMode();
      radios.forEach((r) => { r.checked = r.value === mode; });
    }

    function isOpen() {
      return modal.classList.contains('open');
    }
    function open() {
      syncRadios();
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.getElementById('presentNotesSettingsModalClose')?.focus();
    }
    function close() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
    }

    radios.forEach((r) => {
      r.addEventListener('change', () => {
        if (r.checked) writeMode(r.value);
      });
    });

    document.addEventListener('click', (e) => {
      const btn = e.target.closest?.('#presentNotesSettingsBtn, [data-ribbon-command="settings_notes"]');
      if (!btn || !document.getElementById('presentRibbon')?.contains(btn)) return;
      e.preventDefault();
      e.stopPropagation();
      open();
    });
    document.getElementById('presentNotesSettingsModalClose')?.addEventListener('click', close);
    document.getElementById('presentNotesSettingsModalOk')?.addEventListener('click', close);
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
        group.hidden = true;
        const tabMedia = document.getElementById('presentDockTabMedia');
        if (tabMedia) tabMedia.dataset.userVisible = p.visible ? '1' : '0';
        syncDockVisibility();
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
  const bootStartIndex = Math.max(0, parseInt(P.startSlide, 10) || 0);
  let currentIndex = bootStartIndex;
  let controlsBound = false;
  // Verhindert, dass eine offene Remote-/Live-Position die Startfolie überschreibt
  // (Race: Poll setzt Follower → Folie 3, Claim kommt zu spät und broadcastet die falsche Folie).
  let presentBootLocked = true;
  let presentBootTimer = null;
  /** Nach Boot kurz keine Live-Follow/Leader-Demotion (Reload-Race mit alter Session). */
  let presentStartStickyUntil = 0;

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

  (function initFilmstripWheelScroll() {
    const strip = document.getElementById('filmstrip');
    if (!strip) return;
    strip.addEventListener('wheel', (e) => {
      // Vertikales Mausrad / Touchpad → horizontal scrollen.
      const dx = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
      if (!dx) return;
      if (strip.scrollWidth <= strip.clientWidth + 1) return;
      e.preventDefault();
      strip.scrollLeft += dx;
    }, { passive: false });
  })();

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

  function sampleSlideBgColor(raw) {
    const s = String(raw || '').trim();
    if (!s) return '#222222';
    if (s.charAt(0) === '#' && /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(s)) {
      return s.length === 9 ? s.slice(0, 7) : s;
    }
    const hex = s.match(/#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/);
    if (hex) return hex[0];
    const rgb = s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (rgb) {
      const toHex = (n) => Math.max(0, Math.min(255, parseInt(n, 10))).toString(16).padStart(2, '0');
      return '#' + toHex(rgb[1]) + toHex(rgb[2]) + toHex(rgb[3]);
    }
    return '#222222';
  }

  function expandHex(hex) {
    const h = String(hex || '').replace('#', '');
    if (h.length === 3) {
      return [0, 1, 2].map((i) => parseInt(h[i] + h[i], 16));
    }
    if (h.length >= 6) {
      return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
    }
    return [34, 34, 34];
  }

  function relativeLuminance(hex) {
    const channels = expandHex(hex).map((c) => {
      const v = c / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
  }

  function applyDockContrastForSlide(h) {
    const dock = document.getElementById('presentSlideDock');
    if (!dock) return;
    const swatches = Array.isArray(P.slideBgSwatches) ? P.slideBgSwatches : [];
    const sample = sampleSlideBgColor(swatches[h] != null ? swatches[h] : '#222222');
    const onLight = relativeLuminance(sample) >= 0.45;
    dock.dataset.notesContrast = onLight ? 'on-light' : 'on-dark';
  }

  function syncDockVisibility() {
    const dock = document.getElementById('presentSlideDock');
    const tabNotes = document.getElementById('presentDockTabNotes');
    const tabMedia = document.getElementById('presentDockTabMedia');
    const panelNotes = document.getElementById('presentDockPanelNotes');
    const panelMedia = document.getElementById('presentDockPanelMedia');
    if (!dock || !tabNotes || !tabMedia || !panelNotes || !panelMedia) return;

    const mediaAllowed = isPanelUserVisible('media') && tabMedia.dataset.userVisible !== '0';
    const showMedia = dockHasMedia && mediaAllowed;
    const showNotes = dockHasNotes;
    const showDock = showNotes || showMedia;

    dock.hidden = !showDock;
    tabNotes.hidden = !showNotes;
    tabMedia.hidden = !showMedia;

    let active = dockActiveTab;
    if (active === 'notes' && !showNotes) active = showMedia ? 'media' : 'notes';
    if (active === 'media' && !showMedia) active = showNotes ? 'notes' : 'media';
    dockActiveTab = active;

    const notesOn = showDock && active === 'notes' && showNotes;
    const mediaOn = showDock && active === 'media' && showMedia;
    tabNotes.setAttribute('aria-selected', notesOn ? 'true' : 'false');
    tabMedia.setAttribute('aria-selected', mediaOn ? 'true' : 'false');
    panelNotes.hidden = !notesOn || dock.classList.contains('is-collapsed');
    panelMedia.hidden = !mediaOn || dock.classList.contains('is-collapsed');
    // Collapsed: keep panels hidden but tabs visible
    if (dock.classList.contains('is-collapsed')) {
      panelNotes.hidden = true;
      panelMedia.hidden = true;
    } else {
      panelNotes.hidden = !notesOn;
      panelMedia.hidden = !mediaOn;
    }
  }

  function setDockTab(tab, persist) {
    if (tab !== 'notes' && tab !== 'media') return;
    dockActiveTab = tab;
    if (persist !== false) {
      if (P.presentLayout) P.presentLayout.dockTab = tab;
      window.SlideForgePresentLayout?.patchUserPrefs?.({ dockTab: tab });
    }
    syncDockVisibility();
  }

  /** Notizen haben Vorrang; Player nur wenn keine Notizen. */
  function applyDockDefaultTab() {
    if (dockHasNotes) setDockTab('notes', false);
    else if (dockHasMedia) setDockTab('media', false);
  }

  /** Timed-Autoplay → Player-Tab öffnen (ohne Preference zu überschreiben). */
  function focusDockPlayerForAutoplay() {
    if (!dockHasMedia) return;
    setDockTab('media', false);
    window.SlideForgePresentMedia?.expand?.(false);
  }

  function syncNotesForSlide(h) {
    const notesPanel = document.getElementById('notesPanel');
    const html = P.notesHtml[h];
    const hasNotes = !!(html && html.trim() !== '');
    if (notesPanel) notesPanel.innerHTML = hasNotes ? html : '';
    dockHasNotes = hasNotes;
    applyDockContrastForSlide(h);
    syncDockVisibility();
    if (hasNotes) {
      window.SlideForgePresentNotes?.onSlideChange?.();
    }
  }

  (function initPresentSlideDock() {
    const dock = document.getElementById('presentSlideDock');
    const tabNotes = document.getElementById('presentDockTabNotes');
    const tabMedia = document.getElementById('presentDockTabMedia');
    const toggle = document.getElementById('presentDockToggle');
    const notesPanelWrap = document.getElementById('presentDockPanelNotes');
    if (!dock || !toggle) return;

    const STORAGE_KEY = 'sf_present_notes_collapsed';
    const MODE_KEY = 'sf_present_notes_mode';
    const i18n = P.i18n || {};

    try {
      const localTab = localStorage.getItem('sf_present_dock_tab');
      if (localTab === 'notes' || localTab === 'media') {
        dockActiveTab = localTab;
        if (P.presentLayout?.dockTab !== localTab) {
          window.SlideForgePresentLayout?.patchUserPrefs?.({ dockTab: localTab });
        }
        localStorage.removeItem('sf_present_dock_tab');
      } else {
        const savedTab = P.presentLayout?.dockTab;
        if (savedTab === 'notes' || savedTab === 'media') dockActiveTab = savedTab;
      }
    } catch (e) { /* ignore */ }

    function readMode() {
      const fromLayout = P.presentLayout?.notesMode;
      if (fromLayout === 'always_open' || fromLayout === 'always_closed' || fromLayout === 'carry') {
        return fromLayout;
      }
      try {
        const v = localStorage.getItem(MODE_KEY);
        if (v === 'always_open' || v === 'always_closed' || v === 'carry') return v;
      } catch (e) { /* ignore */ }
      return 'carry';
    }

    function readCollapsedCarry() {
      if (typeof P.presentLayout?.notesCollapsed === 'boolean') {
        return !!P.presentLayout.notesCollapsed;
      }
      try {
        return localStorage.getItem(STORAGE_KEY) === '1';
      } catch (e) {
        return false;
      }
    }

    function isCollapsed() {
      return dock.classList.contains('is-collapsed');
    }

    function setCollapsed(on, persist) {
      dock.classList.toggle('is-collapsed', !!on);
      toggle.setAttribute('aria-expanded', on ? 'false' : 'true');
      toggle.title = on
        ? (i18n.notesExpandHint || i18n.mediaExpandHint || toggle.title)
        : (i18n.notesCollapseHint || i18n.mediaCollapseHint || toggle.title);
      syncDockVisibility();
      if (persist !== false) {
        try {
          localStorage.setItem(STORAGE_KEY, on ? '1' : '0');
        } catch (e) { /* ignore */ }
        if (P.presentLayout) P.presentLayout.notesCollapsed = !!on;
        window.SlideForgePresentLayout?.patchUserPrefs?.({ notesCollapsed: !!on });
      }
    }

    function applyMode(mode) {
      const m = mode || readMode();
      if (m === 'always_open') {
        setCollapsed(false, false);
      } else if (m === 'always_closed') {
        setCollapsed(true, false);
      } else {
        setCollapsed(readCollapsedCarry(), false);
      }
    }

    applyMode();

    function onTabClick(tab) {
      if (isCollapsed()) {
        setDockTab(tab, true);
        setCollapsed(false, readMode() === 'carry');
        return;
      }
      if (dockActiveTab === tab) {
        setCollapsed(true, readMode() === 'carry');
        return;
      }
      setDockTab(tab, true);
    }

    tabNotes?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      onTabClick('notes');
    });
    tabMedia?.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      onTabClick('media');
    });
    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      setCollapsed(!isCollapsed(), readMode() === 'carry');
    });

    // Notizen-Inhalt tippen → einklappen (wie bisher), nicht bei Textauswahl
    let ptr = null;
    notesPanelWrap?.addEventListener('pointerdown', (e) => {
      if (e.button != null && e.button !== 0) return;
      if (isCollapsed()) return;
      ptr = { x: e.clientX, y: e.clientY, id: e.pointerId };
    });
    notesPanelWrap?.addEventListener('pointerup', (e) => {
      if (!ptr || ptr.id !== e.pointerId) return;
      const dx = Math.abs(e.clientX - ptr.x);
      const dy = Math.abs(e.clientY - ptr.y);
      ptr = null;
      if (dx > 10 || dy > 10) return;
      if (window.getSelection && String(window.getSelection()).length > 0) return;
      setCollapsed(true, readMode() === 'carry');
    });
    notesPanelWrap?.addEventListener('pointercancel', () => { ptr = null; });

    window.SlideForgePresentNotes = {
      applyMode,
      onSlideChange: () => applyMode(),
      getMode: readMode,
    };
    window.SlideForgePresentMedia = {
      setCollapsed,
      isCollapsed,
      expand: (persist) => setCollapsed(false, persist !== false),
      showTab: () => setDockTab('media', true),
    };
  })();

  syncNotesForSlide(currentIndex);

  window.SlideForgePresentLayout?.init({
    P,
    getMainReveal: () => mainReveal,
    layoutNextPreview,
  });

  function finishPresentBoot() {
    if (!presentBootLocked) return;
    presentBootLocked = false;
    if (presentBootTimer) {
      clearTimeout(presentBootTimer);
      presentBootTimer = null;
    }
    // Alles, was während des Starts in live.json lag, nicht nachträglich anwenden.
    lastRemoteCommandTs = Date.now() / 1000;
    presentStartStickyUntil = Date.now() + 5000;
    applyBootStartSlide();
    try {
      mainFrame.contentWindow?.postMessage({ type: 'sf-present-boot-done' }, '*');
    } catch (e) { /* ignore */ }
    scheduleRemotePoll();
  }

  function inPresentStartSticky() {
    return presentBootLocked || Date.now() < presentStartStickyUntil;
  }

  function applyBootStartSlide() {
    if (!mainReveal) return;
    const target = normalizeSlideJumpIndex(bootStartIndex);
    applyingRemote = true;
    try {
      mainReveal.slide(target, 0);
    } catch (e) { /* ignore */ }
    applyingRemote = false;
    currentIndex = target;
    updateUI(target);
    if (isPresentLeader) broadcastPosition(target);
  }

  mainFrame.addEventListener('load', () => {
    waitForReveal(mainFrame.contentWindow, (reveal) => {
      mainReveal = reveal;
      mainReveal.on('slidechanged', (e) => {
        if (presentBootLocked) return;
        const prevH = typeof e.previousIndexh === 'number' ? e.previousIndexh : currentIndex;
        const dir = (typeof e.indexh === 'number' ? e.indexh : currentIndex) >= prevH ? 1 : -1;
        requestAnimationFrame(() => updateUI(presentSlideIndex(dir)));
      });
      // Fragmente (schrittweise Animationen innerhalb einer Folie) lösen KEIN
      // 'slidechanged' aus, müssen aber genauso live übertragen werden - sonst bleibt
      // die Zuschauer-Ansicht (view.php) beim vorherigen Fragment-Stand hängen und
      // überspringt ihn beim nächsten echten Folienwechsel.
      mainReveal.on('fragmentshown', () => {
        if (!presentBootLocked) broadcastPosition(currentIndex);
      });
      mainReveal.on('fragmenthidden', () => {
        if (!presentBootLocked) broadcastPosition(currentIndex);
      });
      applyBootStartSlide();
      claimPresentLeader().then(() => {
        applyBootStartSlide();
        finishPresentBoot();
      });
      presentBootTimer = setTimeout(finishPresentBoot, 2500);
      window.SlideForgePresentLayout?.broadcastLaserConfig?.();
      window.SlideForgePresentLayout?.broadcastSlideGhost?.();
      try { mainFrame.contentWindow?.focus(); } catch (err) { /* ignore */ }
      if (!controlsBound) { bindControls(); controlsBound = true; }
      wrapRevealNavigation(mainReveal);
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
    clearConsoleMediaClocks();
    updateMediaControls();
    if (!presentBootLocked) autoPlayTimedMediaForAudience();
  }

  /** Timed-Medien auf der Beamer-/view-Ansicht anstoßen (Autoplay-Policy umgehen). */
  let timedAudienceTimers = [];
  function clearTimedAudienceTimers() {
    timedAudienceTimers.forEach((t) => clearTimeout(t));
    timedAudienceTimers = [];
  }

  function autoPlayTimedMediaForAudience() {
    clearTimedAudienceTimers();
    if (!P.canBroadcast || !mainFrame?.contentDocument) return;
    let slide = null;
    try {
      slide = mainFrame.contentDocument.querySelector('.reveal .slides > section.present');
    } catch (e) { return; }
    if (!slide) return;
    const slideIndexAtArm = currentIndex;
    slide.querySelectorAll('audio[data-play-trigger="timed"], video[data-play-trigger="timed"]').forEach((el) => {
      const mid = el.getAttribute('data-media-id');
      if (!mid) return;
      // Pro Folienbesuch nur einmal — sonst restart nach ended (= falsche «Dauerschleife»).
      if (el.dataset.sfTimedAudienceSent === '1') return;
      if (!el.loop && el.ended) return;
      if (!el.paused && !el.ended) {
        el.dataset.sfTimedAudienceSent = '1';
        focusDockPlayerForAutoplay();
        return;
      }
      el.dataset.sfTimedAudienceSent = '1';
      const delay = Math.max(0, parseInt(el.getAttribute('data-play-delay'), 10) || 0);
      const start = () => {
        if (currentIndex !== slideIndexAtArm) return;
        if (el.dataset.sfTimedAudienceSent !== '1') return;
        focusDockPlayerForAutoplay();
        // Konsole stumm mitsyncen (Timecode/▶-Status); Ton nur Zuschauer via live.
        applyMediaLocally(mid, 'play');
        sendMediaCommand(mid, 'play');
      };
      if (delay <= 0) start();
      else timedAudienceTimers.push(setTimeout(start, delay));
    });
  }

  function formatMediaTimecode(sec) {
    if (!Number.isFinite(sec) || sec < 0) sec = 0;
    const total = Math.floor(sec);
    const s = total % 60;
    const m = Math.floor(total / 60) % 60;
    const h = Math.floor(total / 3600);
    const pad = (n) => (n < 10 ? '0' : '') + n;
    if (h > 0) return h + ':' + pad(m) + ':' + pad(s);
    return m + ':' + pad(s);
  }

  function stableMediaDuration(el) {
    if (!el) return NaN;
    const raw = el.duration;
    if (!Number.isFinite(raw) || raw <= 0) return NaN;
    // Einmal gelernte Dauer nicht mehr durch Mini-Schwankungen ersetzen.
    const prev = parseFloat(el.dataset.sfStableDur || '');
    if (Number.isFinite(prev) && prev > 0) {
      if (Math.abs(raw - prev) < 0.75) return prev;
    }
    el.dataset.sfStableDur = String(raw);
    return raw;
  }

  /** Virtuelle Konsole-Uhr: Audio wird lokal nicht abgespielt (kein Raum-Ton). */
  const consoleMediaClock = Object.create(null);

  function clearConsoleMediaClocks() {
    Object.keys(consoleMediaClock).forEach((k) => { delete consoleMediaClock[k]; });
  }

  function consoleMediaPlaying(mediaId) {
    return !!(consoleMediaClock[mediaId] && consoleMediaClock[mediaId].playing);
  }

  function consoleMediaNow(mediaId, el) {
    const c = consoleMediaClock[mediaId];
    if (c && c.playing) {
      let t = c.originTime + (performance.now() - c.originWall) / 1000;
      const dur = stableMediaDuration(el);
      if (Number.isFinite(dur) && dur > 0 && t >= dur) {
        c.playing = false;
        t = dur;
        try { if (el) el.currentTime = dur; } catch (e) { /* ignore */ }
      }
      return t;
    }
    if (el && el.tagName === 'VIDEO' && !el.paused) {
      return Number.isFinite(el.currentTime) ? el.currentTime : 0;
    }
    return el && Number.isFinite(el.currentTime) ? el.currentTime : (c ? c.originTime : 0);
  }

  function setConsoleMediaClock(mediaId, el, action, time) {
    const c = consoleMediaClock[mediaId] || (consoleMediaClock[mediaId] = {
      playing: false,
      originWall: 0,
      originTime: 0,
    });
    if (action === 'play') {
      c.originTime = el && Number.isFinite(el.currentTime) ? el.currentTime : (c.originTime || 0);
      c.originWall = performance.now();
      c.playing = true;
    } else if (action === 'pause') {
      if (c.playing) {
        const t = consoleMediaNow(mediaId, el);
        c.originTime = t;
        try { if (el) el.currentTime = t; } catch (e) { /* ignore */ }
      }
      c.playing = false;
    } else if (action === 'stop') {
      c.playing = false;
      c.originTime = 0;
      try { if (el) el.currentTime = 0; } catch (e) { /* ignore */ }
    } else if (action === 'seek' && Number.isFinite(time)) {
      c.originTime = Math.max(0, time);
      c.originWall = performance.now();
      try { if (el) el.currentTime = c.originTime; } catch (e) { /* ignore */ }
    }
  }

  function mediaTimecodeText(el, mediaId) {
    if (!el) return '0:00 / —';
    const mid = mediaId || el.getAttribute('data-media-id');
    const cur = formatMediaTimecode(consoleMediaNow(mid, el));
    const dur = stableMediaDuration(el);
    return cur + ' / ' + (Number.isFinite(dur) ? formatMediaTimecode(dur) : '—');
  }

  /** Echtes Folien-Medium — nicht #slideGhostStore (dort liegen Klone mit gleicher data-media-id). */
  function findLocalMediaEl(mediaId) {
    try {
      const doc = mainFrame.contentDocument;
      if (!doc || !mediaId) return null;
      const safe = String(mediaId).replace(/"/g, '');
      const sel = '[data-media-id="' + safe + '"]';
      return doc.querySelector('.reveal .slides > section ' + sel)
        || doc.querySelector('.reveal .slides ' + sel)
        || null;
    } catch (e) {
      return null;
    }
  }

  function refreshMediaTimecodes() {
    const list = document.getElementById('mediaControlList');
    if (!list) return;
    list.querySelectorAll('.media-control-row[data-media-id]').forEach((row) => {
      const mid = row.getAttribute('data-media-id');
      const timeEl = row.querySelector('.media-control-time');
      const range = row.querySelector('[data-media-seek]');
      if (!timeEl || !mid) return;
      const media = findLocalMediaEl(mid);
      const next = mediaTimecodeText(media, mid);
      if (timeEl.textContent !== next) timeEl.textContent = next;
      const playing = consoleMediaPlaying(mid)
        || !!(media && media.tagName === 'VIDEO' && !media.paused && !media.ended);
      row.classList.toggle('is-playing', playing);
      if (range && media && range.dataset.scrubbing !== '1') {
        const dur = stableMediaDuration(media);
        if (Number.isFinite(dur) && dur > 0) {
          const maxStr = dur.toFixed(1);
          if (range.max !== maxStr) range.max = maxStr;
        }
        const cur = consoleMediaNow(mid, media);
        const prev = parseFloat(range.value);
        if (!Number.isFinite(prev) || Math.abs(prev - cur) >= 0.2) {
          range.value = cur.toFixed(1);
        }
      }
    });
  }

  let mediaTimecodeTimer = null;
  function ensureMediaTimecodeTimer() {
    if (mediaTimecodeTimer) return;
    mediaTimecodeTimer = setInterval(refreshMediaTimecodes, 250);
  }

  function getCurrentSlideSection() {
    try {
      const slides = Array.from(mainFrame.contentDocument.querySelectorAll('.reveal .slides > section'));
      return slides[currentIndex] || null;
    } catch (e) {
      return null;
    }
  }

  function currentSlideHasPlayingMedia() {
    const slide = getCurrentSlideSection();
    if (!slide) return false;
    return Array.from(slide.querySelectorAll('video, audio')).some((el) => {
      try {
        const mid = el.getAttribute('data-media-id');
        if (mid && consoleMediaPlaying(mid)) return true;
        return el.tagName === 'VIDEO' && !el.paused && !el.ended && el.readyState > 0;
      } catch (e) {
        return false;
      }
    });
  }

  function navigationWouldLeaveSlide(method, args) {
    if (!mainReveal) return false;
    if (method === 'slide') {
      const targetH = args[0];
      const cur = (mainReveal.getIndices() || {}).h;
      return typeof targetH === 'number' && targetH !== cur;
    }
    try {
      const fr = typeof mainReveal.availableFragments === 'function'
        ? (mainReveal.availableFragments() || {})
        : {};
      if (method === 'next' && fr.next) return false;
      if (method === 'prev' && fr.prev) return false;
    } catch (e) { /* ignore */ }
    return method === 'next' || method === 'prev';
  }

  let mediaNavGuardPending = false;
  let mediaNavAllowedOnce = false;
  let revealNavWrapped = false;

  async function confirmLeavePlayingMedia() {
    if (!currentSlideHasPlayingMedia()) return true;
    const msg = (P.i18n && P.i18n.mediaLeaveConfirm)
      || 'Auf dieser Folie läuft noch eine Mediendatei. Wirklich wechseln und die Wiedergabe stoppen?';
    if (window.SFDialog && typeof window.SFDialog.confirm === 'function') {
      return window.SFDialog.confirm(msg);
    }
    return true;
  }

  function wrapRevealNavigation(reveal) {
    if (!reveal || revealNavWrapped) return;
    revealNavWrapped = true;
    ['next', 'prev', 'slide'].forEach((method) => {
      if (typeof reveal[method] !== 'function') return;
      const orig = reveal[method].bind(reveal);
      reveal[method] = function (...args) {
        if (mediaNavAllowedOnce) {
          mediaNavAllowedOnce = false;
          return orig(...args);
        }
        if (mediaNavGuardPending) return undefined;
        if (!navigationWouldLeaveSlide(method, args) || !currentSlideHasPlayingMedia()) {
          return orig(...args);
        }
        mediaNavGuardPending = true;
        confirmLeavePlayingMedia().then((ok) => {
          mediaNavGuardPending = false;
          if (!ok) return;
          mediaNavAllowedOnce = true;
          orig(...args);
        });
        return undefined;
      };
    });
  }

  function formatMediaTime(sec) {
    if (!Number.isFinite(sec) || sec < 0) sec = 0;
    const s = Math.floor(sec % 60);
    const m = Math.floor(sec / 60) % 60;
    const h = Math.floor(sec / 3600);
    const pad = (n) => String(n).padStart(2, '0');
    return h > 0 ? (h + ':' + pad(m) + ':' + pad(s)) : (m + ':' + pad(s));
  }

  function findFrameMedia(mediaId) {
    return findLocalMediaEl(mediaId);
  }

  function applyMediaLocally(mediaId, action, time) {
    const el = findFrameMedia(mediaId);
    if (!el) return;
    // Presenter-Iframe: kein Ton. Audio gar nicht abspielen; Video nur stumm (Bild).
    el.muted = true;
    el.volume = 0;
    // Verhindert play/pause → postMessage → erneutes sendMediaCommand (Doppelstart).
    el.dataset.sfSyncing = '1';
    try {
      setConsoleMediaClock(mediaId, el, action, time);
      if (el.tagName === 'VIDEO') {
        if (action === 'play') {
          el.play && el.play().catch(() => {});
        } else if (action === 'pause') {
          el.pause && el.pause();
        } else if (action === 'stop') {
          el.pause && el.pause();
          try { el.currentTime = 0; } catch (e) { /* ignore */ }
        } else if (action === 'seek' && Number.isFinite(time)) {
          try { el.currentTime = Math.max(0, time); } catch (e) { /* ignore */ }
        }
      } else {
        // AUDIO: nur Uhr / Seek — play() würde Raum-Ton erzeugen.
        el.pause && el.pause();
        if (action === 'stop') {
          try { el.currentTime = 0; } catch (e) { /* ignore */ }
        } else if (action === 'seek' && Number.isFinite(time)) {
          try { el.currentTime = Math.max(0, time); } catch (e) { /* ignore */ }
        } else if (action === 'pause') {
          try { el.currentTime = consoleMediaNow(mediaId, el); } catch (e) { /* ignore */ }
        }
      }
    } finally {
      const clearSync = () => { try { delete el.dataset.sfSyncing; } catch (err) { /* ignore */ } };
      if (typeof requestAnimationFrame === 'function') requestAnimationFrame(clearSync);
      else setTimeout(clearSync, 0);
    }
    refreshMediaTimecodes();
  }

  function updateMediaControls() {
    const accordion = document.getElementById('mediaControlAccordion');
    const tabMedia = document.getElementById('presentDockTabMedia');
    const list = document.getElementById('mediaControlList');
    if (!list || !P.canBroadcast) {
      dockHasMedia = false;
      syncDockVisibility();
      return;
    }

    const userWants = isPanelUserVisible('media')
      && (tabMedia ? tabMedia.dataset.userVisible !== '0' : true)
      && (accordion ? accordion.dataset.userVisible !== '0' : true);

    let mediaEls = [];
    try {
      const slide = getCurrentSlideSection();
      mediaEls = slide
        ? Array.from(slide.querySelectorAll('audio[data-media-id], video[data-media-id]'))
        : [];
    } catch (e) { /* Frame evtl. noch nicht bereit */ }

    const show = userWants && mediaEls.length > 0;
    dockHasMedia = show;
    if (accordion) accordion.hidden = true;
    syncDockVisibility();
    applyDockContrastForSlide(currentIndex);

    if (!show) {
      list.innerHTML = '';
      applyDockDefaultTab();
      return;
    }

    applyDockDefaultTab();

    const i18n = P.i18n || {};
    list.innerHTML = mediaEls.map((el) => {
      const mid = el.getAttribute('data-media-id');
      const label = el.tagName === 'AUDIO' ? 'Audio' : 'Video';
      const dur = stableMediaDuration(el);
      const cur = Number.isFinite(el.currentTime) ? el.currentTime : 0;
      const max = Number.isFinite(dur) && dur > 0 ? dur : Math.max(cur, 1);
      return '<div class="media-control-row" data-media-row="' + mid + '" data-media-id="' + mid + '">' +
        '<div class="media-control-row-top">' +
          '<span>' + label + '</span>' +
          '<div class="media-control-btns">' +
            '<button type="button" data-media-cmd="play" data-media-id="' + mid + '" title="' + (i18n.mediaPlay || 'Abspielen') + '">▶</button>' +
            '<button type="button" data-media-cmd="pause" data-media-id="' + mid + '" title="' + (i18n.mediaPause || 'Pause') + '">⏸</button>' +
            '<button type="button" data-media-cmd="stop" data-media-id="' + mid + '" title="' + (i18n.mediaStop || 'Stopp') + '">⏹</button>' +
          '</div>' +
        '</div>' +
        '<div class="media-control-seek">' +
          '<input type="range" min="0" max="' + max + '" step="0.1" value="' + cur + '" data-media-seek="' + mid + '" aria-label="' + (i18n.mediaSeek || 'Position') + '">' +
          '<span class="media-control-time" data-media-time="' + mid + '">' + mediaTimecodeText(el, mid) + '</span>' +
        '</div>' +
      '</div>';
    }).join('');

    list.querySelectorAll('[data-media-cmd]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        withLeaderControl(() => {
          applyMediaLocally(btn.dataset.mediaId, btn.dataset.mediaCmd);
          sendMediaCommand(btn.dataset.mediaId, btn.dataset.mediaCmd);
        });
      });
    });

    list.querySelectorAll('[data-media-seek]').forEach((range) => {
      const mid = range.dataset.mediaSeek;
      const commitSeek = () => {
        const t = parseFloat(range.value);
        if (!Number.isFinite(t)) return;
        withLeaderControl(() => {
          applyMediaLocally(mid, 'seek', t);
          sendMediaCommand(mid, 'seek', t);
        });
      };
      range.addEventListener('pointerdown', (e) => {
        e.stopPropagation();
        range.dataset.scrubbing = '1';
      });
      range.addEventListener('pointerup', () => {
        range.dataset.scrubbing = '0';
        commitSeek();
      });
      range.addEventListener('change', () => {
        range.dataset.scrubbing = '0';
        commitSeek();
      });
      range.addEventListener('click', (e) => e.stopPropagation());
      range.addEventListener('input', () => {
        const t = parseFloat(range.value);
        const el = findFrameMedia(mid);
        const dur = stableMediaDuration(el);
        const timeEl = list.querySelector('[data-media-time="' + mid + '"]');
        if (timeEl) {
          timeEl.textContent = formatMediaTimecode(t) + ' / ' +
            (Number.isFinite(dur) ? formatMediaTimecode(dur) : '—');
        }
        applyMediaLocally(mid, 'seek', t);
      });
    });

    ensureMediaTimecodeTimer();
    refreshMediaTimecodes();
  }

  function sendMediaCommand(mediaId, action, time) {
    if (!P.canBroadcast) return;
    const body = { action: 'media', media_id: mediaId, media_action: action, csrf_token: P.csrfToken };
    if (action === 'seek' && Number.isFinite(time)) {
      body.media_time = time;
    }
    fetch('live.php?id=' + encodeURIComponent(P.id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
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
    if (presentBootLocked || isPresentLeader || !mainReveal || !live || typeof live.index !== 'number') return;
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
    return livePost({
      action: 'claim_leader',
      client_id: presentClientId,
      client_kind: 'present',
    }).then((data) => {
      if (!data || !data.ok) return false;
      isPresentLeader = !!data.is_leader;
      globalLeaderKind = data.leader_kind || 'present';
      updateLeaderUI();
      if (isPresentLeader && mainReveal) broadcastPosition(currentIndex);
      return isPresentLeader;
    });
  }

  function withLeaderControl(fn) {
    if (!P.canBroadcast) return;
    if (isPresentLeader) { fn(); return; }
    claimPresentLeader().then((ok) => { if (ok) fn(); });
  }

  let presentAutoEnabled = false;
  function enablePresentAutoAdvance() {
    if (presentAutoEnabled) return;
    presentAutoEnabled = true;
    try {
      mainFrame.contentWindow?.postMessage({ type: 'sf-present-enable-auto' }, '*');
    } catch (e) { /* ignore */ }
  }

  function presentUserNavigate(fn) {
    withLeaderControl(() => {
      enablePresentAutoAdvance();
      fn();
    });
  }

  function broadcastPosition(h, fragOverride) {
    if (!canBroadcastPosition()) return;
    let frag = fragOverride;
    if (frag === undefined) {
      const indices = mainReveal ? mainReveal.getIndices() : null;
      frag = indices && typeof indices.f === 'number' ? indices.f : null;
    }
    livePost({
      index: h,
      frag: frag,
      channel: 'present',
      client_id: presentClientId,
    }).then((data) => {
      if (!data || !data.ok) return;
      if (data.is_leader === false) {
        isPresentLeader = false;
        updateLeaderUI();
      } else if (data.is_leader === true) {
        isPresentLeader = true;
        updateLeaderUI();
      }
    });
  }

  let laserBroadcastTimer = null;
  function broadcastLaser(data) {
    if (!P.canBroadcast || !data || P.presentLayout?.laserPointerEnabled === false) return;
    livePost({
      action: 'laser',
      active: !!data.active,
      x: data.x,
      y: data.y,
      slide_index: data.slideIndex,
      color: data.color,
      size: data.size,
      trail: !!data.trail,
      client_id: presentClientId,
    });
  }

  window.addEventListener('message', (e) => {
    if (!e.data || e.data.type !== 'sf-present-position') return;
    if (!mainFrame?.contentWindow || e.source !== mainFrame.contentWindow) return;
    if (typeof e.data.index !== 'number') return;
    if (presentBootLocked) return;
    currentIndex = e.data.index;
    const frag = typeof e.data.frag === 'number' ? e.data.frag : null;
    broadcastPosition(e.data.index, frag);
  });

  window.addEventListener('message', (e) => {
    if (!e.data || e.data.type !== 'sf-media-cmd') return;
    if (!mainFrame?.contentWindow || e.source !== mainFrame.contentWindow) return;
    if (!P.canBroadcast || presentBootLocked) return;
    const mid = e.data.mediaId;
    const action = e.data.action;
    if (!mid || !['play', 'pause', 'stop'].includes(action)) return;
    withLeaderControl(() => sendMediaCommand(mid, action));
  });

  window.addEventListener('message', (e) => {
    if (!e.data || e.data.type !== 'sf-present-user-nav') return;
    if (!mainFrame?.contentWindow || e.source !== mainFrame.contentWindow) return;
    enablePresentAutoAdvance();
  });

  /* Beamer-Fenster (present_audience.php) hat den Tastaturfokus: Tasten kommen von dort. */
  window.addEventListener('message', (e) => {
    if (!e.data || e.data.type !== 'sf_present_key') return;
    if (e.origin !== window.location.origin || !mainReveal) return;
    if (e.data.dir === 'next') presentUserNavigate(() => mainReveal.next());
    else if (e.data.dir === 'prev') presentUserNavigate(() => mainReveal.prev());
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
    /* Die Folien im Filmstreifen sind Knöpfe. Stand die Eingabemarke nach einem
       Klick noch darauf, sprang Enter zurück auf dieselbe Folie, statt
       weiterzugehen. Für die Navigationstasten zählt der Filmstreifen darum
       nicht als Bedienelement — angeklickt wird er weiterhin ganz normal. */
    if (t.closest('.filmstrip-item')) return false;
    /* Presenter (Funkfernbedienung) sendet Bild auf/ab bzw. Pfeile: Die gehen auch,
       wenn nach einem Klick noch ein Knopf den Fokus hat. Leertaste/Enter bleiben
       beim Knopf. */
    if (/^(PageUp|PageDown|ArrowLeft|ArrowRight|ArrowUp|ArrowDown)$/.test(e.key)) return false;
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
      if (e.key === 'Escape') {
        const t = e.target;
        if (t instanceof Element && isPresentTypingTarget(t)) return;
        /* Ein offenes Fenster hat Vorrang: Dessen eigener Esc-Zuhörer schliesst es.
           Sonst verliesse man mit einem Tastendruck gleich die ganze Präsentation. */
        if (document.querySelector('.modal-backdrop.open')) return;
        /* Adresse selbst bauen statt den Knopf im Menüband zu suchen: Den kann
           man sich wegkonfigurieren, die Taste soll trotzdem gehen. Gleiche
           Bildung wie in updateUI(). */
        if (!P || !P.id) return;
        e.preventDefault();
        window.location.href = 'editor.php?id=' + encodeURIComponent(P.id) + '&slide=' + currentIndex;
        return;
      }
      if (!mainReveal || shouldSkipPresentNav(e)) return;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === ' ' || e.key === 'PageDown' || e.key === 'Enter') {
        e.preventDefault();
        presentUserNavigate(() => mainReveal.next());
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp' || e.key === 'PageUp') {
        e.preventDefault();
        presentUserNavigate(() => mainReveal.prev());
      }
    }

    /* Capture: kein anderer Zuhoerer soll die Tasten des Presenters schlucken. */
    document.addEventListener('keydown', handleParentNavKey, true);

    /* Fokus auf die Seite holen (Start, Rueckkehr ins Fenster, Vollbild), damit der
       Presenter ohne vorherigen Klick schaltet. */
    function grabPresentFocus() {
      const a = document.activeElement;
      if (a && a !== document.body && a !== document.documentElement) return;
      try { window.focus(); } catch (e) { /* ignore */ }
    }
    window.addEventListener('load', grabPresentFocus);
    window.addEventListener('focus', grabPresentFocus);
    document.addEventListener('fullscreenchange', grabPresentFocus);
  }

  /* Tasten sofort binden, nicht erst wenn Reveal im Frame bereit ist und bindControls
     lief: Ein Fehler oder eine Verzoegerung davor liess sonst den Presenter tot. */
  bindPresentNavKeys();

  function bindControls() {
    document.getElementById('presPrevBtn').addEventListener('click', () => {
      presentUserNavigate(() => { if (mainReveal) mainReveal.prev(); });
    });
    document.getElementById('presNextBtn').addEventListener('click', () => {
      presentUserNavigate(() => { if (mainReveal) mainReveal.next(); });
    });

    document.querySelectorAll('.filmstrip-item').forEach((btn) => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.index, 10);
        if (isSlideDisabled(idx)) return;
        presentUserNavigate(() => {
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
  // Befehle von vor diesem Seitenladen ignorieren (sonst: alter «next» → sofort Folie 2).
  let lastRemoteCommandTs = Date.now() / 1000;
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

        if (data.present_leader && !inPresentStartSticky()) {
          applyLeaderState(data.present_leader, data.control_clients);
        }

        const remoteControls = globalLeaderKind === 'remote';

        if (inPresentStartSticky()) {
          // Startphase: keine Remote-Steps / kein Follow auf alte Live-Position.
        } else if (data.command && data.command.type === 'step' && data.command.cmd_ts > lastRemoteCommandTs && mainReveal && (isPresentLeader || remoteControls)) {
          lastRemoteCommandTs = data.command.cmd_ts;
          enablePresentAutoAdvance();
          applyingRemote = true;
          const stepDir = data.command.direction === 'prev' ? -1 : 1;
          if (data.command.direction === 'next') mainReveal.next();
          else if (data.command.direction === 'prev') mainReveal.prev();
          applyingRemote = false;
          requestAnimationFrame(() => {
            const h = presentSlideIndex(stepDir);
            updateUI(h);
          });
        } else if (!isPresentLeader && data.live) {
          if (P.canBroadcast) {
            claimPresentLeader();
          } else {
            syncFollowerPosition(data.live);
          }
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
  // Poll erst nach Boot — sonst Follow/Commands vor Claim.

  function sendPresentHeartbeat() {
    if (!P.canBroadcast) return Promise.resolve(null);
    return livePost({
      action: 'present_heartbeat',
      client_id: presentClientId,
    }).then((data) => {
      if (!data || !data.ok) return data;
      if (inPresentStartSticky()) {
        // Während Sticky Leadership nicht entziehen lassen.
        if (data.is_leader === true) isPresentLeader = true;
      } else if (data.is_leader === false) {
        isPresentLeader = false;
      } else if (data.is_leader === true) {
        isPresentLeader = true;
      }
      if (data.leader_kind && !inPresentStartSticky()) globalLeaderKind = data.leader_kind;
      updateLeaderUI();
      return data;
    });
  }

  setInterval(() => {
    sendPresentHeartbeat();
    if (isPresentLeader && mainReveal) {
      broadcastPosition(currentIndex);
    }
  }, 8000);

  // Nach Tab-Schlaf / Bildschirm aus: sofort Heartbeat + Poll (CSRF/Session erneuern).
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') return;
    sendPresentHeartbeat();
    pollLiveRemote();
    remotePollMs = 80;
    scheduleRemotePoll();
  });
  window.addEventListener('focus', () => {
    sendPresentHeartbeat();
    pollLiveRemote();
  });

  window.addEventListener('beforeunload', () => {
    if (P.canBroadcast && isPresentLeader && navigator.sendBeacon) {
      const payload = JSON.stringify({
        action: 'stop',
        client_id: presentClientId,
        csrf_token: P.csrfToken,
      });
      navigator.sendBeacon('live.php?id=' + encodeURIComponent(P.id), payload);
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
