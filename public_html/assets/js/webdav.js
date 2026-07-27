(function () {
  'use strict';

  let api = null;
  let activeDriveId = '';
  let activeDriveLabel = '';
  let currentPath = '';
  let loading = false;
  let lastFiles = [];

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function t(key, params) {
    const cfg = api?.webdavConfig?.i18n || {};
    let text = cfg[key] || key;
    if (params) {
      Object.keys(params).forEach((k) => {
        text = text.replace('{' + k + '}', params[k]);
      });
    }
    return text;
  }

  function mediaKind(entry) {
    return entry?.mediaKind || entry?.kind || 'image';
  }

  function objectMode(kind) {
    if (kind === 'video') return 'object-video';
    if (kind === 'audio') return 'object-audio';
    return 'object-image';
  }

  function backgroundMode(kind) {
    return kind === 'video' ? 'background-video' : 'background-image';
  }

  function canUseBackground(kind) {
    return kind === 'image' || kind === 'video';
  }

  function setStatus(msg, isError, withSpinner) {
    const el = $('webdavStatus');
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('pixabay-status-error', !!isError);
    el.classList.toggle('webdav-status-loading', !!(withSpinner && msg));
  }

  function showGridLoading() {
    const grid = $('webdavGrid');
    const bar = $('webdavFolderBar');
    if (bar) {
      bar.hidden = true;
      bar.innerHTML = '';
    }
    if (!grid) return;
    grid.innerHTML =
      '<div class="webdav-grid-loading" role="status" aria-live="polite">'
        + '<span class="sf-spinner" aria-hidden="true"></span>'
        + '<span>' + escapeHtml(t('loading')) + '</span>'
      + '</div>';
  }

  function bindThumbLoading(root) {
    root?.querySelectorAll('.pixabay-card-thumb.webdav-thumb-pending').forEach((thumb) => {
      const img = thumb.querySelector('img');
      if (!img) {
        thumb.classList.remove('webdav-thumb-pending');
        thumb.classList.add('webdav-thumb-ready');
        return;
      }
      const done = () => {
        thumb.classList.remove('webdav-thumb-pending');
        thumb.classList.add('webdav-thumb-ready');
      };
      if (img.complete && img.naturalWidth > 0) {
        done();
        return;
      }
      img.addEventListener('load', done, { once: true });
      img.addEventListener('error', done, { once: true });
    });
  }

  function previewUrl(path) {
    if (!activeDriveId || !path) return '';
    return 'webdav.php?action=preview&drive_id=' + encodeURIComponent(activeDriveId)
      + '&path=' + encodeURIComponent(path);
  }

  function streamUrl(path) {
    if (!activeDriveId || !path) return '';
    return 'webdav.php?action=stream&drive_id=' + encodeURIComponent(activeDriveId)
      + '&path=' + encodeURIComponent(path);
  }

  function kindLabel(kind) {
    if (kind === 'video') return t('kindVideo');
    if (kind === 'audio') return t('kindAudio');
    return '';
  }

  function renderThumb(entry) {
    const kind = mediaKind(entry);
    if (kind === 'image') {
      const thumb = previewUrl(entry.path);
      return (
        '<span class="webdav-thumb-spinner sf-spinner" aria-hidden="true"></span>'
        + (thumb ? '<img src="' + escapeHtml(thumb) + '" alt="" loading="lazy" decoding="async">' : '')
      );
    }
    const badge = kind === 'video' ? '▶' : '♪';
    return (
      '<span class="webdav-media-placeholder webdav-media-placeholder--' + kind + '" aria-hidden="true">'
        + '<span class="webdav-media-placeholder-icon">' + badge + '</span>'
        + '<span class="webdav-media-placeholder-label">' + escapeHtml(kindLabel(kind)) + '</span>'
      + '</span>'
    );
  }

  function renderActions(entry) {
    const kind = mediaKind(entry);
    const objBtn = '<button type="button" class="button button-sm webdav-use-obj">' + escapeHtml(t('useObject')) + '</button>';
    if (!canUseBackground(kind)) {
      return objBtn;
    }
    return objBtn
      + '<button type="button" class="button button-ghost button-sm webdav-use-bg">' + escapeHtml(t('useBackground')) + '</button>';
  }

  function bindActionButtons(scope, entry) {
    const kind = mediaKind(entry);
    scope.querySelector('.webdav-use-obj')?.addEventListener('click', (e) => {
      e.stopPropagation();
      importFile(entry.path, objectMode(kind), entry.size || 0);
    });
    scope.querySelector('.webdav-use-bg')?.addEventListener('click', (e) => {
      e.stopPropagation();
      importFile(entry.path, backgroundMode(kind), entry.size || 0);
    });
  }

  function closeWebdavModal() {
    closeWebdavLightbox();
    const modal = $('webdavModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function closeWebdavLightbox() {
    const lb = $('webdavLightbox');
    if (!lb) return;
    lb.classList.remove('open');
    lb.setAttribute('aria-hidden', 'true');
    const media = $('webdavLightboxMedia');
    if (media) media.innerHTML = '';
  }

  function openWebdavLightbox(entry) {
    const lb = $('webdavLightbox');
    const mediaEl = $('webdavLightboxMedia');
    const metaEl = $('webdavLightboxMeta');
    const actionsEl = $('webdavLightboxActions');
    if (!lb || !mediaEl || !metaEl || !actionsEl || !entry) return;

    const kind = mediaKind(entry);
    if (kind === 'video') {
      mediaEl.innerHTML = '<video src="' + escapeHtml(streamUrl(entry.path)) + '" controls preload="metadata"></video>';
    } else if (kind === 'audio') {
      mediaEl.innerHTML = '<audio src="' + escapeHtml(streamUrl(entry.path)) + '" controls preload="metadata"></audio>';
    } else {
      const src = previewUrl(entry.path);
      if (!src) return;
      const isSvg = /\.svg$/i.test(entry.name || entry.path || '');
      mediaEl.innerHTML = isSvg
        ? '<img src="' + escapeHtml(src) + '" alt="" class="webdav-lightbox-img">'
        : '<img src="' + escapeHtml(src) + '" alt="">';
    }

    const metaParts = [entry.name || ''];
    if (entry.size) metaParts.push(formatBytes(entry.size));
    if (kind !== 'image') metaParts.push(kindLabel(kind));
    metaEl.textContent = metaParts.filter(Boolean).join(' · ');

    actionsEl.innerHTML = renderActions(entry);
    bindActionButtons(actionsEl, entry);

    lb.classList.add('open');
    lb.setAttribute('aria-hidden', 'false');
  }

  function openWebdavModal(driveId, driveLabel) {
    const modal = $('webdavModal');
    if (!modal) return;
    activeDriveId = driveId || '';
    activeDriveLabel = driveLabel || '';
    currentPath = '';
    const title = $('webdavModalTitle');
    if (title) {
      title.textContent = activeDriveLabel || t('title');
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    loadDirectory('');
  }

  function renderBreadcrumb() {
    const nav = $('webdavBreadcrumb');
    if (!nav) return;
    const parts = currentPath ? currentPath.split('/') : [];
    let html = '<button type="button" class="webdav-crumb" data-path="">' + escapeHtml(t('root')) + '</button>';
    let acc = '';
    parts.forEach((part) => {
      acc = acc ? acc + '/' + part : part;
      const path = acc;
      html += '<span class="webdav-crumb-sep">/</span>'
        + '<button type="button" class="webdav-crumb" data-path="' + escapeHtml(path) + '">' + escapeHtml(part) + '</button>';
    });
    nav.innerHTML = html;
    nav.querySelectorAll('.webdav-crumb').forEach((btn) => {
      btn.addEventListener('click', () => loadDirectory(btn.dataset.path || ''));
    });
  }

  function formatBytes(n) {
    if (!n) return '';
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function renderFolders(dirs) {
    const bar = $('webdavFolderBar');
    if (!bar) return;
    if (!dirs.length) {
      bar.hidden = true;
      bar.innerHTML = '';
      return;
    }
    bar.hidden = false;
    bar.innerHTML = dirs.map((entry) => (
      '<button type="button" class="webdav-folder-btn" data-path="' + escapeHtml(entry.path) + '">'
        + '<span class="webdav-folder-icon" aria-hidden="true">📁</span>'
        + '<span class="webdav-folder-name">' + escapeHtml(entry.name) + '</span>'
      + '</button>'
    )).join('');
    bar.querySelectorAll('.webdav-folder-btn').forEach((btn) => {
      btn.addEventListener('click', () => loadDirectory(btn.dataset.path || ''));
    });
  }

  function renderFiles(files) {
    const grid = $('webdavGrid');
    if (!grid) return;

    if (!files.length) {
      lastFiles = [];
      grid.innerHTML = '<p class="pixabay-empty">' + escapeHtml(t('emptyFolder')) + '</p>';
      return;
    }

    lastFiles = files;
    grid.innerHTML = files.map((entry, idx) => {
      const kind = mediaKind(entry);
      const meta = formatBytes(entry.size);
      const shortName = entry.name.length > 22 ? entry.name.slice(0, 19) + '…' : entry.name;
      const thumbClass = kind === 'image'
        ? 'pixabay-card-thumb webdav-thumb-pending'
        : 'pixabay-card-thumb webdav-thumb-ready webdav-thumb-media';
      const previewTitle = kind === 'image' ? t('previewHint') : (entry.name || '');
      return (
        '<div class="pixabay-card" data-idx="' + idx + '">'
          + '<button type="button" class="' + thumbClass + '" title="' + escapeHtml(previewTitle) + '" aria-label="' + escapeHtml(previewTitle) + '">'
            + renderThumb(entry)
          + '</button>'
          + '<div class="pixabay-card-meta">'
            + '<span class="pixabay-card-user" title="' + escapeHtml(entry.name) + '">' + escapeHtml(shortName) + '</span>'
            + (meta ? '<span class="pixabay-card-size">' + escapeHtml(meta) + '</span>' : '')
          + '</div>'
          + '<div class="pixabay-card-actions">' + renderActions(entry) + '</div>'
        + '</div>'
      );
    }).join('');

    grid.querySelectorAll('.pixabay-card').forEach((card) => {
      const entry = lastFiles[parseInt(card.dataset.idx, 10)];
      if (!entry) return;
      card.querySelector('.pixabay-card-thumb')?.addEventListener('click', (e) => {
        e.stopPropagation();
        openWebdavLightbox(entry);
      });
      bindActionButtons(card, entry);
    });
    bindThumbLoading(grid);
  }

  function renderEntries(entries) {
    const dirs = entries.filter((e) => e.type === 'directory');
    const files = entries.filter((e) => e.type === 'file');
    renderFolders(dirs);
    renderFiles(files);
  }

  async function loadDirectory(path) {
    if (!api?.webdavConfig?.enabled || !activeDriveId || loading) return;
    loading = true;
    showGridLoading();
    setStatus(t('loading'), false, true);
    try {
      const res = await fetch('webdav.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'browse',
          drive_id: activeDriveId,
          path: path || '',
        }),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || t('errorGeneric'));
      currentPath = json.path || '';
      renderBreadcrumb();
      renderEntries(json.entries || []);
      setStatus('');
    } catch (e) {
      setStatus(e.message || t('errorGeneric'), true);
      renderFolders([]);
      $('webdavGrid').innerHTML = '<p class="pixabay-empty">' + escapeHtml(t('errorGeneric')) + '</p>';
    } finally {
      loading = false;
    }
  }

  async function importFile(path, mode, size) {
    if (!api?.applyWebdav || !activeDriveId || !path) return;
    setStatus(t('importing'), false, true);
    try {
      const res = await fetch('webdav.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'import',
          id: api.id,
          csrf_token: api.csrfToken,
          drive_id: activeDriveId,
          path,
          size: size || 0,
        }),
      });
      const text = await res.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch (_) {
        throw new Error(t('errorGeneric'));
      }
      if (!json.ok) throw new Error(json.error || t('errorGeneric'));
      await api.applyWebdav(mode || 'object-image', json.url, json.kind || 'image');
      if (typeof api.refreshMediaLibrary === 'function') {
        api.refreshMediaLibrary();
      }
      closeWebdavModal();
    } catch (e) {
      setStatus(e.message || t('errorGeneric'), true);
    }
  }

  function bindEvents() {
    $('webdavModalClose')?.addEventListener('click', closeWebdavModal);
    $('webdavLightboxClose')?.addEventListener('click', closeWebdavLightbox);
    const modal = $('webdavModal');
    SFModalBackdrop?.bindDismiss($('webdavLightboxBackdrop'), closeWebdavLightbox);
    SFModalBackdrop?.bindDismiss(modal, closeWebdavModal);

    document.addEventListener('keydown', (e) => {
      const lightbox = $('webdavLightbox');
      if (e.key === 'Escape' && lightbox?.classList.contains('open')) {
        e.preventDefault();
        closeWebdavLightbox();
        return;
      }
      if (e.key === 'Escape' && modal?.classList.contains('open')) {
        closeWebdavModal();
      }
    });

    document.querySelectorAll('.webdav-drive-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        openWebdavModal(btn.dataset.driveId || '', btn.dataset.driveLabel || '');
      });
    });
  }

  function init(options) {
    api = options || {};
    if (!api.webdavConfig?.enabled) return;
    bindEvents();
  }

  window.SlideForgeWebdav = {
    init,
    openWebdavModal,
    closeWebdavModal,
    closeWebdavLightbox,
  };
})();
