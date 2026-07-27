(function () {
  'use strict';

  let api = null;
  let currentPage = 1;
  let lastQuery = '';
  let searching = false;
  let lastHits = [];
  let lastTotal = 0;
  let lastTotalPages = 0;
  let lastSearchOpts = null;
  let lastLightboxHit = null;

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function t(key, params) {
    const cfg = api?.openclipartConfig?.i18n || {};
    let text = cfg[key] || key;
    if (params) {
      Object.keys(params).forEach((k) => {
        text = text.replace('{' + k + '}', params[k]);
      });
    }
    return text;
  }

  function previewUrl(hit) {
    if (hit?.previewURL) return hit.previewURL;
    if (!hit?.id) return '';
    return 'https://openclipart.org/image/800px/' + encodeURIComponent(hit.id);
  }

  function setStatus(msg, isError) {
    const el = $('openclipartStatus');
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('pixabay-status-error', !!isError);
  }

  function closeOpenclipartModal() {
    closeOpenclipartLightbox();
    const modal = $('openclipartModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function closeOpenclipartLightbox() {
    lastLightboxHit = null;
    const lb = $('openclipartLightbox');
    if (!lb) return;
    lb.classList.remove('open');
    lb.setAttribute('aria-hidden', 'true');
    const media = $('openclipartLightboxMedia');
    if (media) media.innerHTML = '';
  }

  function openOpenclipartLightbox(hit) {
    const lb = $('openclipartLightbox');
    const mediaEl = $('openclipartLightboxMedia');
    const metaEl = $('openclipartLightboxMeta');
    const actionsEl = $('openclipartLightboxActions');
    if (!lb || !mediaEl || !metaEl || !actionsEl || !hit) return;

    const src = previewUrl(hit);
    if (!src) return;

    lastLightboxHit = hit;
    mediaEl.innerHTML = '<img src="' + escapeHtml(src) + '" alt="" class="openclipart-lightbox-img">';
    metaEl.textContent = hit.name || hit.id || '';

    actionsEl.innerHTML =
      '<button type="button" class="button button-sm openclipart-lightbox-use-obj">' + escapeHtml(t('useObject')) + '</button>';

    actionsEl.querySelector('.openclipart-lightbox-use-obj')?.addEventListener('click', () => {
      importHit(hit);
      closeOpenclipartLightbox();
    });

    lb.classList.add('open');
    lb.setAttribute('aria-hidden', 'false');
  }

  function openOpenclipartModal() {
    const modal = $('openclipartModal');
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    $('openclipartQuery')?.focus();
  }

  async function runSearch(page, opts) {
    if (arguments.length >= 2) {
      lastSearchOpts = opts || null;
      opts = opts || {};
    } else {
      opts = lastSearchOpts || {};
    }
    if (!api?.openclipartConfig?.enabled) return;
    const originalQuery = ($('openclipartQuery')?.value || '').trim();
    const q = (opts.searchQuery != null ? String(opts.searchQuery) : originalQuery).trim();
    if (!q) {
      setStatus(t('enterQuery'), true);
      return;
    }
    if (searching) return;
    searching = true;
    currentPage = page || 1;
    lastQuery = q;
    setStatus(t('searching'));

    if (window.SFMediaSearchTranslate) {
      SFMediaSearchTranslate.setEnglishHint(
        $('openclipartSearchEnglishHint'),
        t('searchEnglishHint'),
        opts.englishQuery || '',
        originalQuery
      );
    }

    const payload = {
      action: 'search',
      id: api.id,
      q,
      page: currentPage,
    };

    try {
      const res = await fetch('clipart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || t('errorGeneric'));
      lastTotalPages = json.totalPages || 0;
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
    const grid = $('openclipartGrid');
    if (!grid) return;

    lastTotal = total || 0;

    if (!hits.length) {
      lastHits = [];
      grid.innerHTML = '<p class="pixabay-empty">' + escapeHtml(t('noResults')) + '</p>';
      $('openclipartPager').hidden = true;
      return;
    }

    lastHits = hits;
    grid.innerHTML = hits.map((hit, idx) => {
      const thumb = previewUrl(hit);
      const label = hit.name || hit.id || '';
      return (
        '<div class="pixabay-card openclipart-card" data-idx="' + idx + '">' +
          '<button type="button" class="pixabay-card-thumb openclipart-card-thumb" title="' + escapeHtml(t('previewHint')) + '" aria-label="' + escapeHtml(t('previewHint')) + '">' +
            (thumb ? '<img src="' + escapeHtml(thumb) + '" alt="" loading="lazy">' : '') +
          '</button>' +
          '<div class="pixabay-card-meta">' +
            '<span class="pixabay-card-user">' + escapeHtml(label) + '</span>' +
          '</div>' +
          '<div class="pixabay-card-actions">' +
            '<button type="button" class="button button-sm openclipart-use-obj">' + escapeHtml(t('useObject')) + '</button>' +
          '</div>' +
        '</div>'
      );
    }).join('');

    grid.querySelectorAll('.openclipart-card').forEach((card) => {
      const hit = lastHits[parseInt(card.dataset.idx, 10)];
      if (!hit) return;
      card.querySelector('.openclipart-card-thumb')?.addEventListener('click', (e) => {
        e.stopPropagation();
        openOpenclipartLightbox(hit);
      });
      card.querySelector('.openclipart-use-obj')?.addEventListener('click', (e) => {
        e.stopPropagation();
        importHit(hit);
      });
    });

    const pager = $('openclipartPager');
    if (pager) {
      const hasMore = lastTotalPages > 0 ? currentPage < lastTotalPages : hits.length >= 25;
      pager.hidden = currentPage <= 1 && !hasMore;
      const prev = $('openclipartPrev');
      const next = $('openclipartNext');
      if (prev) prev.disabled = currentPage <= 1;
      if (next) next.disabled = !hasMore;
    }
  }

  async function importHit(hit) {
    if (!api?.applyOpenclipart || !hit?.id) return;
    setStatus(t('importing'));
    try {
      const res = await fetch('clipart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'import',
          id: api.id,
          csrf_token: api.csrfToken,
          clipartId: hit.id,
        }),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || t('errorGeneric'));
      await api.applyOpenclipart(json.url);
      if (typeof api.refreshMediaLibrary === 'function') {
        api.refreshMediaLibrary();
      }
      closeOpenclipartModal();
    } catch (e) {
      setStatus(e.message || t('errorGeneric'), true);
    }
  }

  async function runSearchEnglish(page) {
    const q = ($('openclipartQuery')?.value || '').trim();
    if (!q) {
      setStatus(t('enterQuery'), true);
      return;
    }
    if (!window.SFMediaSearchTranslate) {
      setStatus(t('errorGeneric'), true);
      return;
    }
    setStatus(t('translating'));
    try {
      const en = await SFMediaSearchTranslate.translateToEnglish(q);
      if (window.SFMediaSearchTranslate) {
        SFMediaSearchTranslate.setEnglishHint(
          $('openclipartSearchEnglishHint'),
          t('searchEnglishHint'),
          en,
          q
        );
      }
      await runSearch(page, { searchQuery: en, englishQuery: en });
    } catch (e) {
      setStatus(e.message || t('errorGeneric'), true);
    }
  }

  function bindEvents() {
    $('openclipartSearchBtn')?.addEventListener('click', () => {
      if (window.SFMediaSearchTranslate) {
        SFMediaSearchTranslate.setEnglishHint($('openclipartSearchEnglishHint'), t('searchEnglishHint'), '', ($('openclipartQuery')?.value || '').trim());
      }
      runSearch(1, {});
    });
    $('openclipartSearchEnglishBtn')?.addEventListener('click', () => runSearchEnglish(1));
    $('openclipartQuery')?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (window.SFMediaSearchTranslate) {
          SFMediaSearchTranslate.setEnglishHint($('openclipartSearchEnglishHint'), t('searchEnglishHint'), '', ($('openclipartQuery')?.value || '').trim());
        }
        runSearch(1, {});
      }
    });
    $('openclipartPrev')?.addEventListener('click', () => {
      if (currentPage > 1) runSearch(currentPage - 1);
    });
    $('openclipartNext')?.addEventListener('click', () => runSearch(currentPage + 1));

    $('openclipartModalClose')?.addEventListener('click', closeOpenclipartModal);
    $('openclipartLightboxClose')?.addEventListener('click', closeOpenclipartLightbox);
    SFModalBackdrop?.bindDismiss($('openclipartLightboxBackdrop'), closeOpenclipartLightbox);

    const modal = $('openclipartModal');
    SFModalBackdrop?.bindDismiss(modal, closeOpenclipartModal);

    document.addEventListener('keydown', (e) => {
      const lightbox = $('openclipartLightbox');
      if (e.key === 'Escape' && lightbox?.classList.contains('open')) {
        e.preventDefault();
        closeOpenclipartLightbox();
        return;
      }
      if (e.key === 'Escape' && modal?.classList.contains('open')) {
        closeOpenclipartModal();
      }
    });
  }

  function init(editorApi) {
    api = editorApi;
    const enabled = api?.openclipartConfig?.enabled ?? !!document.getElementById('openclipartOpenBtn');
    if (!enabled) return;
    bindEvents();
  }

  window.SlideForgeOpenclipart = { init, openOpenclipartModal, closeOpenclipartModal, closeOpenclipartLightbox };
})();
