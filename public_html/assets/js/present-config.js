/**
 * Gemeinsame Logik für das «Präsentieren»-Konfigurationsmenü (Editor + Präsentationsmodus).
 */
(function (global) {
  'use strict';

  function initPresentConfig(opts) {
    const id = opts.id;
    const csrfToken = opts.csrfToken;
    const i18n = opts.i18n || {};

    function setDisplayOption(field, value) {
      const meta = global.SF_BOOTSTRAP?.ribbon?.meta;
      if (meta) {
        if (!meta.displayOptions) meta.displayOptions = {};
        meta.displayOptions[field] = !!value;
      }
      fetch('api.php?action=set_display_options&id=' + encodeURIComponent(id), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ [field]: value, csrf_token: csrfToken }),
      }).then(() => {
        if (typeof opts.onDisplayOptionChange === 'function') {
          opts.onDisplayOptionChange(field, value);
        }
      }).catch(() => {});
    }

    function syncDisplayCommandButtons(cmdId, on) {
      document.querySelectorAll('[data-ribbon-command="' + cmdId + '"]').forEach((btn) => {
        btn.classList.toggle('active', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }

    function bindDisplayCommand(cmdId, field) {
      document.addEventListener('click', (e) => {
        const btn = e.target.closest?.('[data-ribbon-command="' + cmdId + '"]');
        if (!btn || btn.getAttribute('aria-disabled') === 'true' || btn.disabled) return;
        e.preventDefault();
        const next = btn.getAttribute('aria-pressed') !== 'true';
        syncDisplayCommandButtons(cmdId, next);
        setDisplayOption(field, next);
      });
    }

    function syncPublicLinkCopyBtn() {
      const btn = document.getElementById('copyPublicLinkBtn');
      const input = document.getElementById('presentPublicLinkInput');
      const toggle = document.getElementById('publicLinkToggle');
      if (btn) btn.disabled = !toggle?.checked || !input?.value;
    }

    bindDisplayCommand('show_progress', 'show_progress');
    bindDisplayCommand('show_controls', 'show_controls');

    document.getElementById('copyPublicLinkBtn')?.addEventListener('click', (e) => {
      const input = document.getElementById('presentPublicLinkInput');
      const toggle = document.getElementById('publicLinkToggle');
      if (!toggle?.checked || !input?.value) return;
      const btn = e.currentTarget;
      const original = i18n.copyLink || btn.textContent;
      const copied = () => {
        btn.textContent = i18n.copied || 'OK';
        setTimeout(() => { btn.textContent = original; }, 1500);
      };
      navigator.clipboard.writeText(input.value).then(copied).catch(() => {
        const tmp = document.createElement('textarea');
        tmp.value = input.value;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        tmp.remove();
        copied();
      });
    });

    function copyRemoteUrl(btn) {
      const input = document.getElementById('presentRemoteLinkInput');
      if (!input || !input.value) return;
      const original = i18n.copyRemote || btn.textContent;
      const copied = () => {
        btn.textContent = i18n.copied || 'OK';
        setTimeout(() => { btn.textContent = original; }, 1500);
      };
      navigator.clipboard.writeText(input.value).then(copied).catch(() => {
        const tmp = document.createElement('textarea');
        tmp.value = input.value;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        tmp.remove();
        copied();
      });
    }

    document.getElementById('copyRemoteLinkBtn')?.addEventListener('click', (e) => {
      copyRemoteUrl(e.currentTarget);
    });

    document.getElementById('copyRemoteLinkPanelBtn')?.addEventListener('click', (e) => {
      copyRemoteUrl(e.currentTarget);
    });

    document.getElementById('publicLinkToggle')?.addEventListener('change', async (e) => {
      const enabled = e.target.checked;
      const input = document.getElementById('presentPublicLinkInput');
      const prevUrl = input?.value || '';
      if (!enabled && input) input.value = '';
      syncPublicLinkCopyBtn();
      try {
        const res = await fetch('api.php?action=toggle_public_link&id=' + encodeURIComponent(id), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ enabled: enabled, csrf_token: csrfToken }),
        });
        const json = await res.json();
        if (!json.ok) {
          e.target.checked = !enabled;
          if (input) input.value = prevUrl;
          syncPublicLinkCopyBtn();
          return;
        }
        if (json.enabled && json.url) {
          if (input) input.value = json.url;
        } else if (input) {
          input.value = '';
        }
        syncPublicLinkCopyBtn();
      } catch (err) {
        e.target.checked = !enabled;
        if (input) input.value = prevUrl;
        syncPublicLinkCopyBtn();
      }
    });

    syncPublicLinkCopyBtn();
    document.addEventListener('sf:ribbon-rendered', () => syncPublicLinkCopyBtn());

    let audienceWin = null;
    let presentScreens = [];

    function screenLabel(sc, idx) {
      if (sc.label && sc.label.trim()) return sc.label.trim();
      if (sc.isPrimary) return i18n.screenPrimary || 'Primary';
      if (presentScreens.length === 2 && !sc.isPrimary) return i18n.screenSecondary || 'Secondary';
      return (i18n.screenN || 'Screen {n}').replace('{n}', String(idx + 1));
    }

    function mapScreenDetailed(s, i) {
      return {
        index: i,
        label: s.label || '',
        isPrimary: !!s.isPrimary,
        left: s.availLeft ?? s.left ?? 0,
        top: s.availTop ?? s.top ?? 0,
        width: s.availWidth ?? s.width ?? 800,
        height: s.availHeight ?? s.height ?? 600,
        detailed: s,
      };
    }

    function detectScreensFallback() {
      const s = window.screen;
      const primary = {
        index: 0,
        label: '',
        isPrimary: true,
        left: s.availLeft ?? 0,
        top: s.availTop ?? 0,
        width: s.availWidth ?? s.width ?? 800,
        height: s.availHeight ?? s.height ?? 600,
        detailed: null,
      };
      const screens = [primary];
      const totalW = window.screen.width || primary.width;
      if (totalW > primary.width + 40) {
        screens.push({
          index: 1,
          label: '',
          isPrimary: false,
          left: primary.left + primary.width,
          top: primary.top,
          width: primary.width,
          height: primary.height,
          detailed: null,
        });
      } else if (primary.left !== 0) {
        screens.push({
          index: 1,
          label: '',
          isPrimary: false,
          left: 0,
          top: primary.top,
          width: primary.width,
          height: primary.height,
          detailed: null,
        });
      }
      return screens;
    }

    async function refreshScreensFromGesture() {
      if (window.getScreenDetails) {
        try {
          const details = await window.getScreenDetails();
          presentScreens = details.screens.map(mapScreenDetailed);
          populateScreenSelect();
          return;
        } catch (e) { /* Berechtigung verweigert */ }
      }
      presentScreens = detectScreensFallback();
      populateScreenSelect();
    }

    async function detectScreens() {
      if (window.getScreenDetails) {
        try {
          const details = await window.getScreenDetails();
          return details.screens.map(mapScreenDetailed);
        } catch (e) { /* still initializing without gesture */ }
      }
      return detectScreensFallback();
    }

    function populateScreenSelect() {
      const select = document.getElementById('presentScreenSelect');
      const hint = document.getElementById('presentScreenHint');
      if (!select) return;
      select.innerHTML = '';
      presentScreens.forEach((sc, i) => {
        const opt = document.createElement('option');
        opt.value = String(i);
        opt.textContent = screenLabel(sc, i);
        select.appendChild(opt);
      });
      const defaultIdx = presentScreens.findIndex((sc) => !sc.isPrimary);
      select.value = String(defaultIdx >= 0 ? defaultIdx : 0);
      if (hint) {
        const text = presentScreens.length > 1
          ? (i18n.screenMultiHint || '')
          : (i18n.screenSingle || '');
        hint.textContent = text;
        /* Im Ribbon nur als Tooltip; Platz sparen. */
        const inRibbon = !!select.closest('.ribbon-present-display-inner');
        hint.hidden = inRibbon || !text;
        select.title = text;
      }
    }

    function windowFeaturesForScreen(sc) {
      return [
        'left=' + Math.round(sc.left),
        'top=' + Math.round(sc.top),
        'width=' + Math.round(sc.width),
        'height=' + Math.round(sc.height),
        'menubar=no',
        'toolbar=no',
        'location=no',
        'status=no',
        'scrollbars=no',
        'resizable=yes',
      ].join(',');
    }

    function repositionAudienceWindow(win, sc, screenIndex) {
      if (!win || win.closed || !sc) return;
      const left = Math.round(sc.left);
      const top = Math.round(sc.top);
      const width = Math.round(sc.width);
      const height = Math.round(sc.height);
      const move = () => {
        try {
          win.moveTo(left, top);
          win.resizeTo(width, height);
          win.focus();
        } catch (e) {}
      };
      move();
      [50, 150, 400, 900].forEach((ms) => setTimeout(move, ms));
      const notify = () => {
        try {
          win.postMessage({ type: 'sf_present', screenIndex }, window.location.origin);
        } catch (e) {}
      };
      notify();
      [120, 400, 1000].forEach((ms) => setTimeout(notify, ms));
    }

    async function openAudienceWindow(url, screenIndex) {
      await refreshScreensFromGesture();
      let idx;
      if (typeof screenIndex === 'number') {
        idx = screenIndex;
      } else {
        const select = document.getElementById('presentScreenSelect');
        if (select && select.value !== '') {
          idx = parseInt(select.value, 10);
        } else {
          /* Ohne Select (Editor): wie bisheriger Dropdown-Default — Nicht-Primär bevorzugen. */
          const secondary = presentScreens.findIndex((sc) => !sc.isPrimary);
          idx = secondary >= 0 ? secondary : 0;
        }
      }
      const sc = presentScreens[idx] || presentScreens[0];
      if (!sc) return null;
      const sep = url.indexOf('?') >= 0 ? '&' : '?';
      const targetUrl = url + sep + 'screen=' + encodeURIComponent(String(idx));

      if (audienceWin && !audienceWin.closed) {
        repositionAudienceWindow(audienceWin, sc, idx);
        return audienceWin;
      }

      audienceWin = window.open(targetUrl, 'sf_audience_' + id, windowFeaturesForScreen(sc));
      if (audienceWin) {
        repositionAudienceWindow(audienceWin, sc, idx);
      }
      return audienceWin;
    }

    async function initPresentScreens() {
      presentScreens = await detectScreens();
      if (!presentScreens.length) {
        presentScreens = detectScreensFallback();
      }
      populateScreenSelect();
    }

    window.addEventListener('message', (e) => {
      if (e.origin !== window.location.origin) return;
      if (!e.data || e.data.type !== 'sf_present_reposition') return;
      const idx = typeof e.data.screenIndex === 'number' ? e.data.screenIndex : 0;
      const sc = presentScreens[idx] || presentScreens[0];
      if (audienceWin && !audienceWin.closed && sc) {
        repositionAudienceWindow(audienceWin, sc, idx);
      }
    });

    const localBtn = document.getElementById('presentLocalBtn');
    const screenSelect = document.getElementById('presentScreenSelect');
    if (localBtn || screenSelect) {
      initPresentScreens();
    }
    if (localBtn) {
      localBtn.addEventListener('click', async () => {
        const url = 'present_audience.php?id=' + encodeURIComponent(id);
        await openAudienceWindow(url);
        localBtn.textContent = i18n.localReopen || localBtn.textContent;
      });
    }

    return {
      refreshScreens: refreshScreensFromGesture,
      openAudienceWindow,
    };
  }

  global.SlideForgePresentConfig = { init: initPresentConfig };
})(window);
