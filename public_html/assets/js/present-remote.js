/**
 * Mobile Present-Remote (v2.0.0)
 */
(function () {
  'use strict';

  const R = window.SF_REMOTE;
  if (!R || !R.id) return;

  function isRemoteTabletLayout() {
    if (R.isTablet) return true;
    if (window.innerWidth >= 600) return true;
    const sw = Math.min(window.screen.width, window.screen.height);
    const sh = Math.max(window.screen.width, window.screen.height);
    if (sw >= 600 && sh >= 900) return true;
    if (window.matchMedia('(pointer: coarse)').matches && sw >= 480) return true;
    return false;
  }

  function applyRemoteLayoutClass() {
    const tablet = document.body.classList.contains('present-remote-tablet') || isRemoteTabletLayout();
    document.body.classList.toggle('present-remote-tablet', tablet);
  }

  applyRemoteLayoutClass();
  window.addEventListener('resize', applyRemoteLayoutClass);
  window.addEventListener('orientationchange', applyRemoteLayoutClass);

  const remoteClientId = (typeof crypto !== 'undefined' && crypto.randomUUID)
    ? crypto.randomUUID()
    : ('sf-r-' + Date.now() + '-' + Math.random().toString(36).slice(2));

  const SVG_NS = 'http://www.w3.org/2000/svg';
  const ICON_PAUSE = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>';
  const ICON_PLAY = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4l14 8-14 8z"/></svg>';

  let currentIndex = R.startIndex || 0;
  let activeMode = 'nav';
  let lastCommandTs = 0;
  let timerSeconds = 0;
  let timerRunning = true;
  let timerInterval = null;
  let clockInterval = null;
  let previewLoadedFor = -1;
  let navLoadedFor = -1;
  let isRemoteLeader = true;
  let multipleControllers = false;
  let globalLeaderKind = null;

  const counterEl = document.getElementById('remoteSlideCounter');
  const presentDot = document.getElementById('remotePresentDot');
  const presentLabel = document.getElementById('remotePresentLabel');
  const controlStatus = document.getElementById('remoteControlStatus');
  const controlDot = document.getElementById('remoteControlDot');
  const laserPad = document.getElementById('remoteLaserPad');
  const laserDisabledEl = document.getElementById('remoteLaserDisabled');
  const laserEnabledInput = document.getElementById('remoteLaserEnabled');
  const timebarFill = document.getElementById('remoteTimebarFill');
  const timebarTrack = document.getElementById('remoteTimebarTrack');
  const previewScale = document.getElementById('remotePreviewScale');
  const previewLabel = document.getElementById('remotePreviewLabel');
  const previewStage = document.getElementById('remotePreviewStage');
  const navScale = document.getElementById('remoteNavScale');
  const navLabel = document.getElementById('remoteNavLabel');
  const navStage = document.getElementById('remoteNavStage');
  const timerDisplay = document.getElementById('remoteTimerDisplay');
  const timerPauseBtn = document.getElementById('remoteTimerPauseBtn');
  const clockDigital = document.getElementById('remoteClockDigital');
  const clockHour = document.getElementById('remoteClockHour');
  const clockMinute = document.getElementById('remoteClockMinute');
  const clockSecond = document.getElementById('remoteClockSecond');

  function pad(n) { return n < 10 ? '0' + n : '' + n; }

  function apiUrl(full) {
    return 'live.php?id=' + encodeURIComponent(R.id) + (full ? '&full=1' : '');
  }

  function post(body) {
    return fetch(apiUrl(false), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({
        csrf_token: R.csrfToken,
        client_id: remoteClientId,
      }, body)),
    }).then(function (r) { return r.json(); }).catch(function () { return null; });
  }

  function updateLeaderUI() {
    const show = multipleControllers;
    if (controlStatus) {
      controlStatus.hidden = !show;
      controlStatus.classList.toggle('is-master', show && isRemoteLeader);
      controlStatus.classList.toggle('is-follower', show && !isRemoteLeader);
    }
    if (!controlDot || !show) return;
    controlDot.classList.toggle('is-master', isRemoteLeader);
    controlDot.classList.toggle('is-follower', !isRemoteLeader);
  }

  function applyLeaderState(leader, controlClients) {
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
    if (active && leaderId && leaderId !== remoteClientId) {
      isRemoteLeader = false;
    } else if (!active || leaderId === remoteClientId || !leaderId) {
      isRemoteLeader = true;
    }
    updateLeaderUI();
  }

  function claimRemoteLeader() {
    return post({
      action: 'claim_leader',
      client_kind: 'remote',
    }).then(function (data) {
      if (!data || !data.ok) return false;
      isRemoteLeader = !!data.is_leader;
      updateLeaderUI();
      return isRemoteLeader;
    });
  }

  function withRemoteLeader(fn) {
    if (isRemoteLeader) { fn(); return; }
    claimRemoteLeader().then(function (ok) { if (ok) fn(); });
  }

  function haptic() {
    if (navigator.vibrate) navigator.vibrate(12);
  }

  function updateCounter() {
    if (!counterEl) return;
    counterEl.textContent = R.slideCount
      ? (currentIndex + 1) + ' / ' + R.slideCount
      : '—';
  }

  function setPresentStatus(active) {
    if (presentDot) {
      presentDot.classList.toggle('connected', active);
      presentDot.classList.toggle('waiting', !active);
    }
    if (presentLabel) {
      presentLabel.textContent = active ? R.i18n.presentActive : R.i18n.waitingPresent;
    }
  }

  function isLaserEnabled() {
    return R.laser.enabled !== false && (R.presentLayout?.laserPointerEnabled !== false);
  }

  function applyLaserUi() {
    const on = isLaserEnabled();
    if (laserDisabledEl) laserDisabledEl.hidden = on;
    if (laserPad) laserPad.hidden = !on;
    if (laserEnabledInput) laserEnabledInput.checked = on;
    if (!on && activeMode === 'laser') setMode('nav');
  }

  function saveLaserEnabled(on) {
    if (!R.presentLayout) R.presentLayout = {};
    R.presentLayout.laserPointerEnabled = !!on;
    R.laser.enabled = !!on;
    applyLaserUi();
    fetch('user_api.php?action=set_present_layout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ layout: R.presentLayout, csrf_token: R.csrfToken }),
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (data.ok && data.layout) {
        R.presentLayout = data.layout;
        R.laser.enabled = data.layout.laserPointerEnabled !== false;
        applyLaserUi();
      }
    }).catch(function () {});
  }

  function setMode(mode) {
    activeMode = mode;
    document.querySelectorAll('[data-remote-mode]').forEach(function (btn) {
      const on = btn.getAttribute('data-remote-mode') === mode;
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll('[data-remote-panel]').forEach(function (panel) {
      const on = panel.getAttribute('data-remote-panel') === mode;
      panel.classList.toggle('active', on);
      panel.hidden = !on;
    });
    if (mode === 'preview') loadPreview();
    if (mode === 'nav') loadCurrentSlide();
    if (mode === 'laser') sendLaser(false, null, null);
  }

  function sendStep(direction) {
    if (!isRemoteLeader) {
      withRemoteLeader(function () { sendStep(direction); });
      return;
    }
    haptic();
    post({ action: 'step', direction: direction }).then(function (data) {
      if (data && data.ok && data.is_leader === false) {
        isRemoteLeader = false;
        updateLeaderUI();
      }
      if (data && data.ok && data.live && typeof data.live.index === 'number') {
        if (data.live.index !== currentIndex) {
          currentIndex = data.live.index;
          previewLoadedFor = -1;
          navLoadedFor = -1;
          updateCounter();
          if (activeMode === 'preview') loadPreview();
          if (activeMode === 'nav') loadCurrentSlide();
        }
      }
    });
  }

  function sendLaser(active, x, y) {
    if (!isLaserEnabled()) return;
    if (!isRemoteLeader) return;
    post({
      action: 'laser',
      active: !!active,
      x: active ? x : null,
      y: active ? y : null,
      slide_index: currentIndex,
      color: R.laser.color,
      size: R.laser.size,
      trail: R.laser.trail,
    });
  }

  let lastLaserSend = 0;
  function sendLaserThrottled(active, x, y) {
    const now = performance.now();
    if (active && now - lastLaserSend < 28) return;
    lastLaserSend = now;
    sendLaser(active, x, y);
  }

  function updateRemoteTimebar() {
    const progress = getTimerProgress();
    if (timebarFill) {
      timebarFill.style.height = progress.pct + '%';
      timebarFill.style.background = progress.color;
      timebarFill.classList.toggle('full', progress.ratio >= 1);
    }
    if (timebarTrack) {
      timebarTrack.classList.toggle('full', progress.ratio >= 1);
    }
  }

  function renderTimer() {
    if (!timerDisplay) return;
    const totalH = Math.floor(timerSeconds / 3600);
    const m = Math.floor((timerSeconds % 3600) / 60);
    const s = timerSeconds % 60;
    timerDisplay.textContent = (totalH > 0 ? pad(totalH) + ':' : '') + pad(m) + ':' + pad(s);
    updateTimerRing();
    updateRemoteTimebar();
  }

  function defaultTimebarStops() {
    return [
      { pct: 0, color: '#4caf6b' },
      { pct: 60, color: '#d9c23a' },
      { pct: 90, color: '#dd8a2e' },
      { pct: 100, color: '#d9483a' },
    ];
  }

  function colorForPct(pct) {
    const stops = (R.timebarStops && R.timebarStops.length) ? R.timebarStops : defaultTimebarStops();
    let color = stops[0].color;
    stops.forEach(function (s) { if (pct >= s.pct) color = s.color; });
    return color;
  }

  function getTimerProgress() {
    const durationMin = Math.max(1, R.presentationDuration || 30);
    const totalSeconds = durationMin * 60;
    const ratio = timerSeconds / totalSeconds;
    const pct = Math.min(100, ratio * 100);
    return { pct: pct, ratio: ratio, color: colorForPct(pct), litDots: Math.min(60, Math.round((pct / 100) * 60)) };
  }

  function buildTimerStopMarks() {
    const marksG = document.getElementById('remoteTimerStopMarks');
    if (!marksG) return;
    marksG.innerHTML = '';
    const stops = (R.timebarStops && R.timebarStops.length) ? R.timebarStops : defaultTimebarStops();
    stops.forEach(function (stop, i) {
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
      line.setAttribute('class', 'remote-timer-stop-tick');
      marksG.appendChild(line);
    });
  }

  function initTimerRing() {
    const ringG = document.getElementById('remoteTimerRingDots');
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
      dot.setAttribute('class', 'remote-timer-ring-dot');
      dot.dataset.idx = String(i);
      ringG.appendChild(dot);
    }

    buildTimerStopMarks();
  }

  function updateTimerRing() {
    const progress = getTimerProgress();
    const wrap = document.getElementById('remoteTimerRingWrap');
    if (wrap) {
      wrap.style.setProperty('--remote-timer-color', progress.color);
      wrap.classList.toggle('full', progress.ratio >= 1);
    }
    document.querySelectorAll('.remote-timer-ring-dot').forEach(function (dot) {
      const idx = parseInt(dot.dataset.idx, 10);
      const lit = idx < progress.litDots;
      dot.classList.toggle('lit', lit);
      if (lit) dot.style.fill = progress.color;
      else dot.style.fill = '';
    });
  }

  function startTimer() {
    if (timerInterval) return;
    timerInterval = setInterval(function () {
      timerSeconds++;
      renderTimer();
    }, 1000);
    timerRunning = true;
    if (timerPauseBtn) timerPauseBtn.innerHTML = ICON_PAUSE;
  }

  function pauseTimer() {
    clearInterval(timerInterval);
    timerInterval = null;
    timerRunning = false;
    if (timerPauseBtn) timerPauseBtn.innerHTML = ICON_PLAY;
  }

  function initAnalogClock() {
    const ticksG = document.getElementById('remoteAnalogTicks');
    const labelsG = document.getElementById('remoteAnalogLabels');
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
      tick.setAttribute('class', 'remote-analog-tick');
      ticksG.appendChild(tick);
    }

    for (let h = 1; h <= 12; h++) {
      const angle = (h / 12) * 2 * Math.PI - Math.PI / 2;
      const label = document.createElementNS(SVG_NS, 'text');
      label.setAttribute('x', (50 + 34 * Math.cos(angle)).toFixed(2));
      label.setAttribute('y', (50 + 34 * Math.sin(angle)).toFixed(2));
      label.setAttribute('text-anchor', 'middle');
      label.setAttribute('dominant-baseline', 'middle');
      label.setAttribute('class', 'remote-analog-label');
      label.textContent = String(h);
      labelsG.appendChild(label);
    }
  }

  function updateClock() {
    const now = new Date();
    const h = now.getHours();
    const m = now.getMinutes();
    const s = now.getSeconds();
    if (clockDigital) {
      clockDigital.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
    }
    if (clockHour) clockHour.setAttribute('transform', 'rotate(' + (((h % 12) + m / 60) * 30) + ' 50 50)');
    if (clockMinute) clockMinute.setAttribute('transform', 'rotate(' + ((m + s / 60) * 6) + ' 50 50)');
    if (clockSecond) clockSecond.setAttribute('transform', 'rotate(' + (s * 6) + ' 50 50)');
  }

  function layoutSlidePreview(stageEl, scaleEl) {
    if (!stageEl || !scaleEl) return;
    const sw = R.slideWidth || 1920;
    const sh = R.slideHeight || 1080;
    const maxW = stageEl.clientWidth || 320;
    const maxH = stageEl.clientHeight || 180;
    const scale = Math.min(maxW / sw, maxH / sh, 1);
    scaleEl.style.width = sw + 'px';
    scaleEl.style.height = sh + 'px';
    scaleEl.style.transform = 'scale(' + scale + ')';
  }

  function loadCurrentSlide() {
    if (!navScale || !R.slideCount) return;
    if (navLoadedFor === currentIndex) {
      layoutSlidePreview(navStage, navScale);
      return;
    }
    navLoadedFor = currentIndex;
    if (navLabel) {
      navLabel.textContent = (R.i18n.navCurrent || 'Aktuelle Folie') + ' (' + (currentIndex + 1) + ')';
    }
    fetch('remote_slide.php?id=' + encodeURIComponent(R.id) + '&slide=' + currentIndex)
      .then(function (r) { return r.text(); })
      .then(function (html) {
        navScale.innerHTML = html;
        layoutSlidePreview(navStage, navScale);
      })
      .catch(function () {
        navScale.innerHTML = '';
      });
  }

  function loadPreview() {
    if (!previewScale || !R.slideCount) return;
    const nextIdx = Math.min(currentIndex + 1, R.slideCount - 1);
    if (previewLoadedFor === nextIdx) {
      layoutSlidePreview(previewStage, previewScale);
      return;
    }
    previewLoadedFor = nextIdx;
    const isLast = nextIdx === currentIndex;
    if (previewLabel) {
      previewLabel.textContent = isLast ? R.i18n.previewLast : R.i18n.previewNext.replace('{n}', String(nextIdx + 1));
    }
    fetch('remote_slide.php?id=' + encodeURIComponent(R.id) + '&slide=' + nextIdx)
      .then(function (r) { return r.text(); })
      .then(function (html) {
        previewScale.innerHTML = html;
        layoutSlidePreview(previewStage, previewScale);
      })
      .catch(function () {
        previewScale.innerHTML = '';
      });
  }

  function poll() {
    fetch(apiUrl(true))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) return;

        setPresentStatus(!!(data.sessions && data.sessions.present && data.sessions.present.active));

        if (data.present_leader) {
          applyLeaderState(data.present_leader, data.control_clients);
        }

        if (data.live && typeof data.live.index === 'number') {
          if (data.live.index !== currentIndex) {
            currentIndex = data.live.index;
            previewLoadedFor = -1;
            navLoadedFor = -1;
            if (activeMode === 'preview') loadPreview();
            if (activeMode === 'nav') loadCurrentSlide();
          }
          updateCounter();
        }

        if (data.command && data.command.cmd_ts > lastCommandTs) {
          lastCommandTs = data.command.cmd_ts;
          previewLoadedFor = -1;
          navLoadedFor = -1;
        }
      })
      .catch(function () {});
  }

  document.querySelectorAll('[data-remote-mode]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setMode(btn.getAttribute('data-remote-mode') || 'nav');
    });
  });

  document.getElementById('remotePrevBtn')?.addEventListener('click', function () {
    sendStep('prev');
  });
  document.getElementById('remoteNextBtn')?.addEventListener('click', function () {
    sendStep('next');
  });

  timerPauseBtn?.addEventListener('click', function () {
    if (timerRunning) pauseTimer();
    else startTimer();
  });
  document.getElementById('remoteTimerResetBtn')?.addEventListener('click', function () {
    timerSeconds = 0;
    renderTimer();
    if (!timerRunning) startTimer();
  });

  if (laserPad) {
    function normFromEvent(e) {
      const rect = laserPad.getBoundingClientRect();
      if (rect.width < 1 || rect.height < 1) return null;
      return {
        x: Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width)),
        y: Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height)),
      };
    }

    laserPad.addEventListener('pointerdown', function (e) {
      if (activeMode !== 'laser' || !isLaserEnabled()) return;
      e.preventDefault();
      withRemoteLeader(function () {
        laserPad.setPointerCapture(e.pointerId);
        laserPad.classList.add('active');
        const p = normFromEvent(e);
        if (p) sendLaser(true, p.x, p.y);
      });
    });

    laserPad.addEventListener('pointermove', function (e) {
      if (activeMode !== 'laser') return;
      const p = normFromEvent(e);
      if (p) sendLaserThrottled(true, p.x, p.y);
    });

    function endLaser(e) {
      if (activeMode !== 'laser') return;
      laserPad.classList.remove('active');
      try { laserPad.releasePointerCapture(e.pointerId); } catch (err) { /* ignore */ }
      sendLaser(false, null, null);
    }

    laserPad.addEventListener('pointerup', endLaser);
    laserPad.addEventListener('pointercancel', endLaser);
  }

  laserEnabledInput?.addEventListener('change', function () {
    saveLaserEnabled(!!laserEnabledInput.checked);
  });

  window.addEventListener('resize', function () {
    layoutSlidePreview(previewStage, previewScale);
    layoutSlidePreview(navStage, navScale);
  });

  initAnalogClock();
  initTimerRing();
  updateCounter();
  renderTimer();
  updateRemoteTimebar();
  startTimer();
  updateClock();
  clockInterval = setInterval(updateClock, 1000);
  applyLaserUi();
  setMode('nav');
  loadCurrentSlide();
  post({ action: 'remote_heartbeat' }).then(function (data) {
    if (data && data.ok) {
      if (data.is_leader === false) isRemoteLeader = false;
      else if (data.is_leader === true) isRemoteLeader = true;
      updateLeaderUI();
    }
  });
  poll();
  setInterval(function () {
    post({ action: 'remote_heartbeat' }).then(function (data) {
      if (data && data.ok) {
        if (data.is_leader === false) isRemoteLeader = false;
        else if (data.is_leader === true) isRemoteLeader = true;
        updateLeaderUI();
      }
    });
    poll();
  }, 800);
})();
