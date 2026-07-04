(function () {
  'use strict';

  let api = null;
  let currentPage = 1;
  let lastQuery = '';
  let searching = false;
  let lastHits = [];
  let lastTotal = 0;
  let lastLightboxHit = null;
  const perPage = 24;

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function t(key, params) {
    const cfg = api?.iconifyConfig?.i18n || {};
    let text = cfg[key] || key;
    if (params) {
      Object.keys(params).forEach((k) => {
        text = text.replace('{' + k + '}', params[k]);
      });
    }
    return text;
  }

  function getSelectedColor() {
    return $('iconifyColor')?.value || api?.defaultIconColor || '#3a6c8d';
  }

  function previewUrl(hit, color) {
    if (!hit?.id) return '';
    let url = 'icons.php?action=preview&iconId=' + encodeURIComponent(hit.id) + '&height=128';
    if (color) url += '&color=' + encodeURIComponent(color);
    return url;
  }

  function setStatus(msg, isError) {
    const el = $('iconifyStatus');
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('pixabay-status-error', !!isError);
  }

  function closeIconifyModal() {
    closeIconifyLightbox();
    const modal = $('iconifyModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function closeIconifyLightbox() {
    lastLightboxHit = null;
    const lb = $('iconifyLightbox');
    if (!lb) return;
    lb.classList.remove('open');
    lb.setAttribute('aria-hidden', 'true');
    const media = $('iconifyLightboxMedia');
    if (media) media.innerHTML = '';
  }

  function openIconifyLightbox(hit) {
    const lb = $('iconifyLightbox');
    const mediaEl = $('iconifyLightboxMedia');
    const metaEl = $('iconifyLightboxMeta');
    const actionsEl = $('iconifyLightboxActions');
    if (!lb || !mediaEl || !metaEl || !actionsEl || !hit) return;

    const src = previewUrl(hit, getSelectedColor());
    if (!src) return;

    lastLightboxHit = hit;
    mediaEl.innerHTML = '<img src="' + escapeHtml(src) + '" alt="" class="iconify-lightbox-img">';
    metaEl.textContent = hit.id || '';

    actionsEl.innerHTML =
      '<button type="button" class="button button-sm iconify-lightbox-use-obj">' + escapeHtml(t('useObject')) + '</button>';

    actionsEl.querySelector('.iconify-lightbox-use-obj')?.addEventListener('click', () => {
      importHit(hit);
      closeIconifyLightbox();
    });

    lb.classList.add('open');
    lb.setAttribute('aria-hidden', 'false');
  }

  function openIconifyModal() {
    const modal = $('iconifyModal');
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    $('iconifyQuery')?.focus();
  }

  function refreshPreviewColors() {
    if (lastHits.length) {
      renderResults(lastHits, lastTotal);
    }
    if (lastLightboxHit && $('iconifyLightbox')?.classList.contains('open')) {
      openIconifyLightbox(lastLightboxHit);
    }
  }

  async function runSearch(page) {
    if (!api?.iconifyConfig?.enabled) return;
    const q = ($('iconifyQuery')?.value || '').trim();
    if (!q) {
      setStatus(t('enterQuery'), true);
      return;
    }
    if (searching) return;
    searching = true;
    currentPage = page || 1;
    lastQuery = q;
    setStatus(t('searching'));

    const payload = {
      action: 'search',
      id: api.id,
      q,
      page: currentPage,
      per_page: perPage,
      prefix: $('iconifyPrefix')?.value || '',
    };

    try {
      const res = await fetch('icons.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || t('errorGeneric'));
      renderResults(json.hits || [], json.total || 0);
      setStatus(json.hits?.length ? t('resultCount', { count: json.hits.length, total: json.total || json.hits.length }) : t('noResults'));
    } catch (e) {
      setStatus(e.message || t('errorGeneric'), true);
      renderResults([], 0);
    } finally {
      searching = false;
    }
  }

  function renderResults(hits, total) {
    const grid = $('iconifyGrid');
    if (!grid) return;

    lastTotal = total || 0;
    const color = getSelectedColor();

    if (!hits.length) {
      lastHits = [];
      grid.innerHTML = '<p class="pixabay-empty">' + escapeHtml(t('noResults')) + '</p>';
      $('iconifyPager').hidden = true;
      return;
    }

    lastHits = hits;
    grid.innerHTML = hits.map((hit, idx) => {
      const thumb = previewUrl(hit, color);
      const label = hit.name || hit.id || '';
      return (
        '<div class="pixabay-card iconify-card" data-idx="' + idx + '">' +
          '<button type="button" class="pixabay-card-thumb iconify-card-thumb" title="' + escapeHtml(t('previewHint')) + '" aria-label="' + escapeHtml(t('previewHint')) + '">' +
            (thumb ? '<img src="' + escapeHtml(thumb) + '" alt="" loading="lazy">' : '') +
          '</button>' +
          '<div class="pixabay-card-meta">' +
            '<span class="pixabay-card-user">' + escapeHtml(label) + '</span>' +
            '<span class="pixabay-card-size">' + escapeHtml(hit.prefix || '') + '</span>' +
          '</div>' +
          '<div class="pixabay-card-actions">' +
            '<button type="button" class="button button-sm iconify-use-obj">' + escapeHtml(t('useObject')) + '</button>' +
          '</div>' +
        '</div>'
      );
    }).join('');

    grid.querySelectorAll('.iconify-card').forEach((card) => {
      const hit = lastHits[parseInt(card.dataset.idx, 10)];
      if (!hit) return;
      card.querySelector('.iconify-card-thumb')?.addEventListener('click', (e) => {
        e.stopPropagation();
        openIconifyLightbox(hit);
      });
      card.querySelector('.iconify-use-obj')?.addEventListener('click', (e) => {
        e.stopPropagation();
        importHit(hit);
      });
    });

    const pager = $('iconifyPager');
    if (pager) {
      const hasMore = currentPage * perPage < total;
      pager.hidden = total <= hits.length && currentPage <= 1;
      const prev = $('iconifyPrev');
      const next = $('iconifyNext');
      if (prev) prev.disabled = currentPage <= 1;
      if (next) next.disabled = !hasMore && hits.length < perPage;
    }
  }

  async function importHit(hit) {
    if (!api?.applyIconify || !hit?.id) return;
    setStatus(t('importing'));
    try {
      const res = await fetch('icons.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'import',
          id: api.id,
          csrf_token: api.csrfToken,
          iconId: hit.id,
        }),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || t('errorGeneric'));
      await api.applyIconify(json.url, json.iconId, getSelectedColor());
      if (typeof api.refreshMediaLibrary === 'function') {
        api.refreshMediaLibrary();
      }
      closeIconifyModal();
    } catch (e) {
      setStatus(e.message || t('errorGeneric'), true);
    }
  }

  function bindColorControls() {
    const colorInput = $('iconifyColor');
    colorInput?.addEventListener('input', refreshPreviewColors);
    colorInput?.addEventListener('change', refreshPreviewColors);
    $('iconifyColorPalette')?.querySelectorAll('.brand-swatch').forEach((btn) => {
      btn.addEventListener('click', () => {
        const input = $('iconifyColor');
        if (!input) return;
        input.value = btn.dataset.color;
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  }

  function bindEvents() {
    $('iconifySearchBtn')?.addEventListener('click', () => runSearch(1));
    $('iconifyQuery')?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        runSearch(1);
      }
    });
    $('iconifyPrefix')?.addEventListener('change', () => {
      if (lastQuery) runSearch(1);
    });
    $('iconifyPrev')?.addEventListener('click', () => {
      if (currentPage > 1) runSearch(currentPage - 1);
    });
    $('iconifyNext')?.addEventListener('click', () => runSearch(currentPage + 1));

    $('iconifyOpenBtn')?.addEventListener('click', openIconifyModal);
    $('iconifyModalClose')?.addEventListener('click', closeIconifyModal);
    $('iconifyLightboxClose')?.addEventListener('click', closeIconifyLightbox);
    $('iconifyLightboxBackdrop')?.addEventListener('click', closeIconifyLightbox);

    bindColorControls();

    const modal = $('iconifyModal');
    modal?.addEventListener('click', (e) => {
      if (e.target === modal) closeIconifyModal();
    });

    document.addEventListener('keydown', (e) => {
      const lightbox = $('iconifyLightbox');
      if (e.key === 'Escape' && lightbox?.classList.contains('open')) {
        e.preventDefault();
        closeIconifyLightbox();
        return;
      }
      if (e.key === 'Escape' && modal?.classList.contains('open')) {
        closeIconifyModal();
      }
    });
  }

  function init(editorApi) {
    api = editorApi;
    if (!api?.iconifyConfig?.enabled) return;
    bindEvents();
  }

  window.SlideForgeIconify = { init, openIconifyModal, closeIconifyModal, closeIconifyLightbox };
})();
