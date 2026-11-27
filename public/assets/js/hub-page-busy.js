/**
 * HubPageBusy — zentriertes Busy-Overlay mit Statusbar (V2.0a).
 * Quelle: hub/public/assets/js/hub-page-busy.js — Module vendorn / hub_asset(), nicht forken.
 *
 * Zwei Kanäle: Flash oben = CfStatus; Blocker = HubPageBusy (nie mischen).
 *
 * API:
 *   HubPageBusy.show({ title, detail?, soft?, progress?, phase?, indeterminate?, dismissLabel? })
 *   HubPageBusy.setProgress(pct, phase?)
 *   HubPageBusy.setIndeterminate(phase?)
 *   HubPageBusy.startSoftProgress({ waiting?, longer?, almost? })
 *   HubPageBusy.hide()
 *   HubPageBusy.ensure() → HTMLElement
 *
 * Klassen: .hub-page-busy (+ is-indeterminate), .hub-spinner, …
 * Demo: Lab → Statusmeldungen.
 */
(function (global) {
  'use strict';

  var HOST_ID = 'hubPageBusy';
  var softTimer = null;
  var root = null;

  var DEFAULT_PHASES = {
    waiting: 'Bitte warten…',
    longer: 'Dauert länger…',
    almost: 'Gleich fertig…',
  };

  function clearSoft() {
    if (softTimer) {
      clearInterval(softTimer);
      softTimer = null;
    }
  }

  function ensure() {
    var el = document.getElementById(HOST_ID);
    if (el) {
      root = el;
      return el;
    }
    el = document.createElement('div');
    el.id = HOST_ID;
    el.className = 'hub-page-busy';
    el.hidden = true;
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.setAttribute('aria-busy', 'false');
    el.innerHTML =
      '<div class="hub-page-busy__card">' +
      '  <div class="hub-page-busy__row">' +
      '    <span class="hub-spinner" aria-hidden="true"></span>' +
      '    <span data-hub-busy-title></span>' +
      '  </div>' +
      '  <p class="hub-page-busy__detail" data-hub-busy-detail hidden></p>' +
      '  <div class="hub-page-busy__progress">' +
      '    <div class="hub-page-busy__progress-row">' +
      '      <span data-hub-busy-phase></span>' +
      '      <span class="hub-page-busy__pct" data-hub-busy-pct>0%</span>' +
      '    </div>' +
      '    <div class="hub-page-busy__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-hub-busy-bar>' +
      '      <div class="hub-page-busy__fill" data-hub-busy-fill></div>' +
      '    </div>' +
      '  </div>' +
      '  <button type="button" class="hub-page-busy__dismiss" data-hub-busy-dismiss hidden aria-label="Schliessen">×</button>' +
      '</div>';
    document.body.appendChild(el);
    var dismiss = el.querySelector('[data-hub-busy-dismiss]');
    if (dismiss) {
      dismiss.addEventListener('click', hide);
    }
    root = el;
    return el;
  }

  function setProgress(pct, phase) {
    clearSoft();
    var el = ensure();
    var n = Math.max(0, Math.min(100, Math.round(Number(pct) || 0)));
    var fill = el.querySelector('[data-hub-busy-fill]');
    var bar = el.querySelector('[data-hub-busy-bar]');
    var pctEl = el.querySelector('[data-hub-busy-pct]');
    var phaseEl = el.querySelector('[data-hub-busy-phase]');
    el.classList.remove('is-indeterminate');
    if (fill) fill.style.width = n + '%';
    if (bar) bar.setAttribute('aria-valuenow', String(n));
    if (pctEl) pctEl.textContent = n + '%';
    if (phaseEl && phase != null) phaseEl.textContent = String(phase);
  }

  /** Update title/detail without resetting progress (phase change). */
  function setMessage(title, detail) {
    var el = ensure();
    if (el.hidden) {
      show({ title: title, detail: detail, soft: true });
      return;
    }
    var titleEl = el.querySelector('[data-hub-busy-title]');
    var detailEl = el.querySelector('[data-hub-busy-detail]');
    if (title != null && titleEl) titleEl.textContent = String(title);
    if (detailEl && detail != null) {
      detailEl.textContent = String(detail);
      detailEl.hidden = String(detail) === '';
    }
  }

  function setIndeterminate(phase) {
    clearSoft();
    var el = ensure();
    el.classList.add('is-indeterminate');
    var phaseEl = el.querySelector('[data-hub-busy-phase]');
    var pctEl = el.querySelector('[data-hub-busy-pct]');
    var bar = el.querySelector('[data-hub-busy-bar]');
    if (phaseEl && phase != null) phaseEl.textContent = String(phase);
    if (pctEl) pctEl.textContent = '';
    if (bar) bar.removeAttribute('aria-valuenow');
  }

  /**
   * Soft asymptote ~4→90% with phase labels (ClipForge / AF pattern).
   * @param {{ waiting?: string, longer?: string, almost?: string }} [phases]
   */
  function startSoftProgress(phases) {
    clearSoft();
    var labels = Object.assign({}, DEFAULT_PHASES, phases || {});
    var started = Date.now();
    setProgress(4, labels.waiting);
    softTimer = setInterval(function () {
      var t = (Date.now() - started) / 1000;
      var next = Math.min(90, Math.round(4 + 86 * (1 - Math.exp(-t / 3.2))));
      var label = labels.waiting;
      if (next >= 70) label = labels.almost;
      else if (next >= 35) label = labels.longer;
      setProgress(next, label);
    }, 200);
  }

  /**
   * @param {{
   *   title?: string,
   *   detail?: string,
   *   soft?: boolean,
   *   progress?: number,
   *   phase?: string,
   *   indeterminate?: boolean,
   *   phases?: { waiting?: string, longer?: string, almost?: string },
   *   dismissLabel?: string
   * }} [opts]
   */
  function show(opts) {
    opts = opts || {};
    hide();
    if (global.CfStatus && typeof global.CfStatus.hide === 'function') {
      global.CfStatus.hide();
    }
    var el = ensure();
    var titleEl = el.querySelector('[data-hub-busy-title]');
    var detailEl = el.querySelector('[data-hub-busy-detail]');
    var dismiss = el.querySelector('[data-hub-busy-dismiss]');
    if (titleEl) titleEl.textContent = opts.title != null ? String(opts.title) : '';
    if (detailEl) {
      var detail = opts.detail != null ? String(opts.detail) : '';
      detailEl.textContent = detail;
      detailEl.hidden = detail === '';
    }
    if (dismiss) {
      if (opts.dismissLabel) {
        var label = String(opts.dismissLabel);
        dismiss.setAttribute('aria-label', label);
        dismiss.textContent = '×';
        dismiss.title = label;
        dismiss.hidden = false;
      } else {
        dismiss.hidden = true;
        dismiss.removeAttribute('title');
        dismiss.setAttribute('aria-label', 'Schliessen');
        dismiss.textContent = '×';
      }
    }
    el.hidden = false;
    el.setAttribute('aria-busy', 'true');
    document.body.style.cursor = 'wait';

    if (opts.indeterminate) {
      setIndeterminate(opts.phase);
    } else if (opts.soft) {
      startSoftProgress(opts.phases);
    } else if (opts.progress != null) {
      setProgress(opts.progress, opts.phase);
    } else {
      setProgress(0, opts.phase || DEFAULT_PHASES.waiting);
    }
  }

  function hide() {
    clearSoft();
    var el = root || document.getElementById(HOST_ID);
    if (!el) return;
    el.hidden = true;
    el.setAttribute('aria-busy', 'false');
    el.classList.remove('is-indeterminate');
    document.body.style.cursor = '';
  }

  global.HubPageBusy = {
    ensure: ensure,
    show: show,
    hide: hide,
    setProgress: setProgress,
    setMessage: setMessage,
    setIndeterminate: setIndeterminate,
    startSoftProgress: startSoftProgress,
  };
})(typeof window !== 'undefined' ? window : globalThis);
