/**
 * ChurchForge Status — zentrale Flash-Leiste (#cf-status-host).
 * Fixed unter dem Header: kein Layout-Sprung.
 * Busy-Overlay bleibt separat (.hub-page-busy / Modul-Busy).
 */
(function (global) {
  'use strict';

  const HOST_ID = 'cf-status-host';
  const TIMERS = { ok: 4000, info: 6000, warn: 8000, error: 0, progress: 0 };
  const ICONS = { ok: '✓', error: '!', info: 'i', warn: '!', progress: '…' };

  let hideTimer = null;

  function ensureHost() {
    let host = document.getElementById(HOST_ID);
    if (host) return host;
    host = document.createElement('div');
    host.id = HOST_ID;
    host.className = 'cf-status-host';
    host.setAttribute('aria-live', 'polite');
    const header = document.querySelector('.hub-header, .cf-app-shell');
    if (header && header.parentNode) {
      header.insertAdjacentElement('afterend', host);
    } else {
      document.body.prepend(host);
    }
    return host;
  }

  function clearTimer() {
    if (hideTimer != null) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
  }

  function hide() {
    clearTimer();
    const host = document.getElementById(HOST_ID);
    if (!host) return;
    host.innerHTML = '';
    host.hidden = true;
    host.classList.remove('is-active');
  }

  /**
   * @param {'ok'|'error'|'info'|'warn'|'progress'} kind
   * @param {string} message
   * @param {string} [detail]
   * @param {{ autoHide?: number|false, sticky?: boolean }} [opts]
   */
  function show(kind, message, detail, opts) {
    const host = ensureHost();
    clearTimer();
    if (TIMERS[kind] == null && (message == null || TIMERS[message] != null)) {
      const swapped = kind;
      kind = TIMERS[message] != null ? message : 'info';
      message = swapped;
    }
    const k = TIMERS[kind] != null ? kind : 'info';
    const sticky = !!(opts && opts.sticky);
    const autoHideOpt = opts && Object.prototype.hasOwnProperty.call(opts, 'autoHide')
      ? opts.autoHide
      : undefined;

    const el = document.createElement('div');
    el.className = 'hub-status hub-status--' + k;
    el.setAttribute('role', k === 'error' ? 'alert' : 'status');
    const detailHtml = detail
      ? '<span class="hub-status__detail">' + escapeHtml(String(detail)) + '</span>'
      : '';
    const progressHtml = k === 'progress'
      ? '<div class="hub-status__bar" aria-hidden="true"><span data-cf-status-bar style="width:0%"></span></div>'
      : '';
    el.innerHTML =
      '<span class="hub-status__icon" aria-hidden="true">' + (ICONS[k] || 'i') + '</span>' +
      '<div class="hub-status__body">' +
      '<strong>' + escapeHtml(String(message || '')) + '</strong>' +
      detailHtml +
      progressHtml +
      '</div>' +
      '<button type="button" class="hub-status__close" aria-label="Schliessen">×</button>';

    host.innerHTML = '';
    host.appendChild(el);
    host.hidden = false;
    host.classList.add('is-active');
    el.querySelector('.hub-status__close')?.addEventListener('click', hide);

    if (sticky || autoHideOpt === false) return el;

    let ms = typeof autoHideOpt === 'number' ? autoHideOpt : TIMERS[k];
    if (ms > 0) {
      hideTimer = setTimeout(hide, ms);
    }
    return el;
  }

  function setProgress(pct) {
    const bar = document.querySelector('#' + HOST_ID + ' [data-cf-status-bar]');
    if (!bar) return;
    const n = Math.max(0, Math.min(100, Math.round(Number(pct) || 0)));
    bar.style.width = n + '%';
  }

  function escapeHtml(s) {
    return s
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hide();
  });

  global.CfStatus = { show: show, hide: hide, setProgress: setProgress, ensureHost: ensureHost };
})(typeof window !== 'undefined' ? window : globalThis);
