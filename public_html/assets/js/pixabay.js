(function () {
  'use strict';

  let api = null;
  let targetMode = 'object-image';
  let currentPage = 1;
  let lastQuery = '';
  let searching = false;
  let lastHits = [];

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function t(key, params) {
    const cfg = api?.pixabayConfig?.i18n || {};
    let text = cfg[key] || key;
    if (params) {
      Object.keys(params).forEach((k) => {
        text = text.replace('{' + k + '}', params[k]);
      });
    }
    return text;
  }

  function mediaType() {
    const sel = $('pixabayMedia');
    return sel && sel.value === 'video' ? 'video' : 'image';
  }

  function setStatus(msg, isError) {
    const el = $('pixabayStatus');
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('pixabay-status-error', !!isError);
  }

  function setTarget(mode) {
    targetMode = mode || 'object-image';
    const hint = $('pixabayTargetHint');
    if (!hint) return;
    const map = {
      'background-image': t('targetBgImage'),
      'background-video': t('targetBgVideo'),
      'object-image': t('targetObjectImage'),
      'object-video': t('targetObjectVideo'),
    };
    hint.textContent = map[targetMode] || '';
  }

  function closePixabayModal() {
    closePixabayLightbox();
    const modal = $('pixabayModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function closePixabayLightbox() {
    const lb = $('pixabayLightbox');
    if (!lb) return;
    lb.classList.remove('open');
    lb.setAttribute('aria-hidden', 'true');
    const media = $('pixabayLightboxMedia');
    if (media) media.innerHTML = '';
  }

  function previewSrc(hit) {
    if (!hit) return '';
    if (hit.type === 'video') return hit.downloadURL || hit.previewURL || '';
    return hit.downloadURL || hit.thumbnailURL || hit.previewURL || '';
  }

  function openPixabayLightbox(hit) {
    const lb = $('pixabayLightbox');
    const mediaEl = $('pixabayLightboxMedia');
    const metaEl = $('pixabayLightboxMeta');
    const actionsEl = $('pixabayLightboxActions');
    if (!lb || !mediaEl || !metaEl || !actionsEl || !hit) return;

    const isVideo = hit.type === 'video';
    const src = previewSrc(hit);
    if (!src) return;

    if (isVideo) {
      mediaEl.innerHTML = '<video src="' + escapeHtml(src) + '" controls autoplay muted playsinline></video>';
    } else {
      mediaEl.innerHTML = '<img src="' + escapeHtml(src) + '" alt="">';
    }

    const metaParts = [];
    if (hit.user) metaParts.push(t('previewBy', { user: hit.user }));
    if (hit.width && hit.height) metaParts.push(hit.width + '×' + hit.height);
    if (isVideo && hit.duration) metaParts.push(hit.duration + 's');
    metaEl.textContent = metaParts.join(' · ');

    actionsEl.innerHTML =
      '<button type="button" class="button button-sm pixabay-lightbox-use-bg" data-kind="' + (isVideo ? 'video' : 'image') + '">' + escapeHtml(t('useBackground')) + '</button>' +
      '<button type="button" class="button button-ghost button-sm pixabay-lightbox-use-obj" data-kind="' + (isVideo ? 'video' : 'image') + '">' + escapeHtml(t('useObject')) + '</button>';

    actionsEl.querySelector('.pixabay-lightbox-use-bg')?.addEventListener('click', () => {
      importHit(hit, isVideo ? 'background-video' : 'background-image');
      closePixabayLightbox();
    });
    actionsEl.querySelector('.pixabay-lightbox-use-obj')?.addEventListener('click', () => {
      importHit(hit, isVideo ? 'object-video' : 'object-image');
      closePixabayLightbox();
    });

    lb.classList.add('open');
    lb.setAttribute('aria-hidden', 'false');
  }

  function openPixabayModal(mode) {
    setTarget(mode);
    const media = targetMode.includes('video') ? 'video' : 'image';
    const mediaSel = $('pixabayMedia');
    if (mediaSel) mediaSel.value = media;
    updateFilterVisibility();

    const modal = $('pixabayModal');
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    $('pixabayQuery')?.focus();
  }

  /** @deprecated alias – öffnet das Modal (nicht mehr den Sidebar-Tab) */
  function openPixabayTab(mode) {
    openPixabayModal(mode);
  }

  function updateFilterVisibility() {
    const isVideo = mediaType() === 'video';
    const imgFilters = $('pixabayImageFilters');
    const vidFilters = $('pixabayVideoFilters');
    if (imgFilters) imgFilters.hidden = isVideo;
    if (vidFilters) vidFilters.hidden = !isVideo;
  }

  async function runSearch(page) {
    if (!api?.pixabayConfig?.enabled) return;
    const q = ($('pixabayQuery')?.value || '').trim();
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
      media: mediaType(),
      lang: api.pixabayConfig.lang || 'de',
      image_type: $('pixabayImageType')?.value || 'all',
      orientation: $('pixabayOrientation')?.value || 'all',
      video_type: $('pixabayVideoType')?.value || 'all',
    };

    try {
      const res = await fetch('pixabay.php', {
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
    const grid = $('pixabayGrid');
    if (!grid) return;

    if (!hits.length) {
      lastHits = [];
      grid.innerHTML = '<p class="pixabay-empty">' + escapeHtml(t('noResults')) + '</p>';
      $('pixabayPager').hidden = true;
      return;
    }

    lastHits = hits;
    grid.innerHTML = hits.map((hit, idx) => {
      const thumb = hit.thumbnailURL || hit.previewURL || '';
      const isVideo = hit.type === 'video';
      const meta = isVideo
        ? (hit.duration ? hit.duration + 's' : '')
        : (hit.width && hit.height ? hit.width + '×' + hit.height : '');
      return (
        '<div class="pixabay-card" data-idx="' + idx + '">' +
          '<button type="button" class="pixabay-card-thumb" title="' + escapeHtml(t('previewHint')) + '" aria-label="' + escapeHtml(t('previewHint')) + '">' +
            (thumb ? '<img src="' + escapeHtml(thumb) + '" alt="" loading="lazy">' : '') +
            (isVideo ? '<span class="pixabay-card-badge">▶</span>' : '') +
          '</button>' +
          '<div class="pixabay-card-meta">' +
            '<span class="pixabay-card-user">' + escapeHtml(hit.user || '') + '</span>' +
            (meta ? '<span class="pixabay-card-size">' + escapeHtml(meta) + '</span>' : '') +
          '</div>' +
          '<div class="pixabay-card-actions">' +
            '<button type="button" class="button button-sm pixabay-use-bg" data-kind="' + (isVideo ? 'video' : 'image') + '">' + escapeHtml(t('useBackground')) + '</button>' +
            '<button type="button" class="button button-ghost button-sm pixabay-use-obj" data-kind="' + (isVideo ? 'video' : 'image') + '">' + escapeHtml(t('useObject')) + '</button>' +
          '</div>' +
        '</div>'
      );
    }).join('');

    grid.querySelectorAll('.pixabay-card').forEach((card) => {
      const hit = lastHits[parseInt(card.dataset.idx, 10)];
      if (!hit) return;
      card.querySelector('.pixabay-card-thumb')?.addEventListener('click', (e) => {
        e.stopPropagation();
        openPixabayLightbox(hit);
      });
      card.querySelector('.pixabay-use-bg')?.addEventListener('click', (e) => {
        e.stopPropagation();
        importHit(hit, hit.type === 'video' ? 'background-video' : 'background-image');
      });
      card.querySelector('.pixabay-use-obj')?.addEventListener('click', (e) => {
        e.stopPropagation();
        importHit(hit, hit.type === 'video' ? 'object-video' : 'object-image');
      });
    });

    const pager = $('pixabayPager');
    if (pager) {
      pager.hidden = total <= hits.length && currentPage <= 1;
      const prev = $('pixabayPrev');
      const next = $('pixabayNext');
      if (prev) prev.disabled = currentPage <= 1;
      if (next) next.disabled = hits.length < 20;
    }
  }

  async function importHit(hit, mode) {
    if (!api?.applyPixabay) return;
    setStatus(t('importing'));
    try {
      const res = await fetch('pixabay.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'import',
          id: api.id,
          csrf_token: api.csrfToken,
          media: hit.type,
          downloadURL: hit.downloadURL,
          pixabayId: hit.id,
        }),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || t('errorGeneric'));
      await api.applyPixabay(mode || targetMode, json.url, json.kind);
      if (typeof api.refreshMediaLibrary === 'function') {
        api.refreshMediaLibrary();
      }
      closePixabayModal();
    } catch (e) {
      setStatus(e.message || t('errorGeneric'), true);
    }
  }

  function bindEvents() {
    $('pixabaySearchBtn')?.addEventListener('click', () => runSearch(1));
    $('pixabayQuery')?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        runSearch(1);
      }
    });
    $('pixabayMedia')?.addEventListener('change', () => {
      updateFilterVisibility();
      if (lastQuery) runSearch(1);
    });
    $('pixabayPrev')?.addEventListener('click', () => {
      if (currentPage > 1) runSearch(currentPage - 1);
    });
    $('pixabayNext')?.addEventListener('click', () => runSearch(currentPage + 1));

    $('pixabayOpenBtn')?.addEventListener('click', () => openPixabayModal('object-image'));
    $('pixabayModalClose')?.addEventListener('click', closePixabayModal);
    $('pixabayLightboxClose')?.addEventListener('click', closePixabayLightbox);
    $('pixabayLightboxBackdrop')?.addEventListener('click', closePixabayLightbox);

    const modal = $('pixabayModal');
    modal?.addEventListener('click', (e) => {
      if (e.target === modal) closePixabayModal();
    });

    document.addEventListener('keydown', (e) => {
      const lightbox = $('pixabayLightbox');
      if (e.key === 'Escape' && lightbox?.classList.contains('open')) {
        e.preventDefault();
        closePixabayLightbox();
        return;
      }
      if (e.key === 'Escape' && modal?.classList.contains('open')) {
        closePixabayModal();
      }
    });

    document.querySelectorAll('[data-pixabay-open]').forEach((btn) => {
      btn.addEventListener('click', () => openPixabayModal(btn.dataset.pixabayOpen));
    });
  }

  function init(editorApi) {
    api = editorApi;
    if (!api?.pixabayConfig?.enabled) return;
    setTarget('object-image');
    updateFilterVisibility();
    bindEvents();
  }

  window.SlideForgePixabay = { init, openPixabayTab, openPixabayModal, setTarget, closePixabayModal, closePixabayLightbox };
})();
