(function () {
  'use strict';

  const root = document.getElementById('dashboardSections');
  if (!root) return;

  const i18n = window.SF_DASHBOARD_I18N || {};
  const csrf = root.dataset.csrf || '';
  const archivedTab = root.dataset.tab === 'archive';

  let dragPresentationId = null;
  let dragSectionId = null;

  async function api(action, payload) {
    const res = await fetch('dashboard.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ action, csrf_token: csrf, ...payload }),
    });
    const json = await res.json();
    if (!json.ok) {
      throw new Error(json.error || i18n.errorGeneric || 'Error');
    }
    return json;
  }

  function sectionEl(id) {
    return root.querySelector('.dashboard-section[data-section-id="' + id + '"]');
  }

  function updateSectionCount(section) {
    const count = section.querySelectorAll('.dashboard-presentation-card').length;
    const countEl = section.querySelector('.dashboard-section-count');
    if (countEl) countEl.textContent = '(' + count + ')';
    const items = section.querySelector('.dashboard-section-items');
    let empty = items?.querySelector('.dashboard-section-empty');
    if (!items) return;
    if (count === 0) {
      if (!empty) {
        empty = document.createElement('div');
        empty.className = 'dashboard-section-empty';
        empty.textContent = i18n.sectionEmpty || '';
        items.insertBefore(empty, items.firstChild);
      }
    } else if (empty) {
      empty.remove();
    }
  }

  function insertIndex(container, clientY) {
    const cards = [...container.querySelectorAll('.dashboard-presentation-card:not(.dragging)')];
    for (let i = 0; i < cards.length; i++) {
      const rect = cards[i].getBoundingClientRect();
      const mid = rect.top + rect.height / 2;
      if (clientY < mid) return i;
    }
    return cards.length;
  }

  function applyViewMode(mode) {
    root.dataset.viewMode = mode;
    root.classList.remove('dashboard-view-grid', 'dashboard-view-list');
    root.classList.add(mode === 'list' ? 'dashboard-view-list' : 'dashboard-view-grid');
    root.querySelectorAll('.dashboard-view-btn').forEach((btn) => {
      const active = btn.dataset.view === mode;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function bindViewToggle() {
    root.closest('.dashboard-desktop')?.querySelectorAll('.dashboard-view-btn').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const mode = btn.dataset.view === 'list' ? 'list' : 'grid';
        applyViewMode(mode);
        try {
          await api('set_tab_view', { archived: archivedTab, view_mode: mode });
        } catch (e) {
          await SFDialog.alert(e.message);
          location.reload();
        }
      });
    });
  }

  async function toggleSectionCollapse(section) {
    const collapsed = !section.classList.contains('is-collapsed');
    section.classList.toggle('is-collapsed', collapsed);
    const btn = section.querySelector('.dashboard-section-collapse');
    btn?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    try {
      await api('section_prefs', { section_id: section.dataset.sectionId, collapsed });
    } catch (e) {
      section.classList.toggle('is-collapsed', !collapsed);
      btn?.setAttribute('aria-expanded', !collapsed ? 'false' : 'true');
      await SFDialog.alert(e.message);
    }
  }

  function bindCollapse(section) {
    section.querySelector('.dashboard-section-header')?.addEventListener('click', (e) => {
      if (e.target.closest('.dashboard-section-actions, .dashboard-section-drag')) return;
      toggleSectionCollapse(section);
    });
  }

  function bindRename(section) {
    section.querySelector('.dashboard-section-rename')?.addEventListener('click', async () => {
      const titleEl = section.querySelector('.dashboard-section-title');
      const current = titleEl?.textContent?.trim() || '';
      const title = await SFDialog.prompt(i18n.renameSectionPrompt || 'Name:', current);
      if (title === null) return;
      try {
        const res = await api('section_rename', { section_id: section.dataset.sectionId, title });
        if (titleEl && res.section?.title) {
          titleEl.textContent = res.section.title;
        } else if (titleEl && title.trim()) {
          titleEl.textContent = title.trim();
        }
      } catch (e) {
        await SFDialog.alert(e.message);
      }
    });
  }

  function bindDelete(section) {
    section.querySelector('.dashboard-section-delete')?.addEventListener('click', async () => {
      if (!(await SFDialog.confirm(i18n.confirmDeleteSection || 'Delete section?', { danger: true }))) return;
      try {
        await api('section_delete', { section_id: section.dataset.sectionId });
        section.remove();
      } catch (e) {
        await SFDialog.alert(e.message);
      }
    });
  }

  function bindSectionDrag(section) {
    const handle = section.querySelector('.dashboard-section-drag');
    if (!handle) return;
    handle.addEventListener('dragstart', (e) => {
      dragSectionId = section.dataset.sectionId;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', 'section:' + dragSectionId);
      section.classList.add('dragging-section');
    });
    handle.addEventListener('dragend', () => {
      dragSectionId = null;
      section.classList.remove('dragging-section');
      root.querySelectorAll('.dashboard-section-drop-target').forEach((el) => el.classList.remove('dashboard-section-drop-target'));
    });
  }

  function isInteractiveDragTarget(el) {
    return !!el.closest('.slide-card .actions, .slide-card form, .slide-card button, .slide-card input, .slide-card select, .slide-card textarea');
  }

  function bindPresentationDrag(card) {
    card.addEventListener('dragstart', (e) => {
      if (isInteractiveDragTarget(e.target)) {
        e.preventDefault();
        return;
      }
      dragPresentationId = card.dataset.presentationId;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', 'presentation:' + dragPresentationId);
      card.classList.add('dragging');
    });
    card.addEventListener('dragend', () => {
      dragPresentationId = null;
      card.classList.remove('dragging');
      root.querySelectorAll('.dashboard-section-items, .dashboard-section-body').forEach((el) => el.classList.remove('drop-target'));
    });
  }

  function handlePresentationDrop(section, items, clientY) {
    if (!dragPresentationId) return;
    const card = root.querySelector('.dashboard-presentation-card[data-presentation-id="' + dragPresentationId + '"]');
    const fromSection = card?.closest('.dashboard-section');
    const index = insertIndex(items, clientY);
    if (card) {
      const empty = items.querySelector('.dashboard-section-empty');
      if (empty) empty.remove();
      const siblings = [...items.querySelectorAll('.dashboard-presentation-card:not(.dragging)')];
      const ref = siblings[index] || null;
      if (ref) items.insertBefore(card, ref);
      else items.appendChild(card);
    }
    updateSectionCount(section);
    if (fromSection && fromSection !== section) updateSectionCount(fromSection);
    return api('item_move', {
      presentation_id: dragPresentationId,
      section_id: section.dataset.sectionId,
      sort_order: index,
    });
  }

  function bindDropZone(section) {
    const body = section.querySelector('.dashboard-section-body');
    const items = section.querySelector('.dashboard-section-items');
    if (!body || !items) return;

    function allowDrop(e) {
      if (!dragPresentationId && !dragSectionId) return;
      e.preventDefault();
    }

    [body, items].forEach((zone) => {
      zone.addEventListener('dragover', (e) => {
        allowDrop(e);
        if (dragPresentationId) {
          items.classList.add('drop-target');
          body.classList.add('drop-target');
        }
        if (dragSectionId) {
          section.classList.add('dashboard-section-drop-target');
        }
      });
      zone.addEventListener('dragleave', (e) => {
        if (!zone.contains(e.relatedTarget)) {
          zone.classList.remove('drop-target');
        }
      });
      zone.addEventListener('drop', async (e) => {
        e.preventDefault();
        items.classList.remove('drop-target');
        body.classList.remove('drop-target');
        section.classList.remove('dashboard-section-drop-target');

        if (dragPresentationId) {
          try {
            await handlePresentationDrop(section, items, e.clientY);
          } catch (err) {
            await SFDialog.alert(err.message);
            location.reload();
          }
          return;
        }

        if (dragSectionId && dragSectionId !== section.dataset.sectionId) {
          const dragged = sectionEl(dragSectionId);
          if (!dragged) return;
          section.parentElement.insertBefore(dragged, section);
          const ids = [...root.querySelectorAll('.dashboard-section:not([data-shared-inbox="1"])')].map((el) => el.dataset.sectionId);
          try {
            await api('section_reorder', { section_ids: ids });
          } catch (err) {
            await SFDialog.alert(err.message);
            location.reload();
          }
        }
      });
    });

    section.querySelector('.dashboard-section-header')?.addEventListener('dragover', (e) => {
      if (!dragSectionId) return;
      e.preventDefault();
      section.classList.add('dashboard-section-drop-target');
    });
    section.querySelector('.dashboard-section-header')?.addEventListener('drop', (e) => {
      if (!dragSectionId || dragSectionId === section.dataset.sectionId) return;
      e.preventDefault();
      section.classList.remove('dashboard-section-drop-target');
      const dragged = sectionEl(dragSectionId);
      if (!dragged) return;
      section.parentElement.insertBefore(dragged, section);
      const ids = [...root.querySelectorAll('.dashboard-section:not([data-shared-inbox="1"])')].map((el) => el.dataset.sectionId);
      api('section_reorder', { section_ids: ids }).catch(async (err) => {
        await SFDialog.alert(err.message);
        location.reload();
      });
    });
  }

  function bindSection(section) {
    const isSharedInbox = section.dataset.sharedInbox === '1';
    bindCollapse(section);
    if (!isSharedInbox) {
      bindRename(section);
      bindDelete(section);
      bindSectionDrag(section);
    }
    bindDropZone(section);
    section.querySelectorAll('.dashboard-presentation-card').forEach(bindPresentationDrag);
  }

  root.querySelectorAll('.dashboard-section').forEach(bindSection);
  bindViewToggle();

  document.getElementById('dashboardAddSectionBtn')?.addEventListener('click', async () => {
    const title = await SFDialog.prompt(i18n.addSectionPrompt || 'Section name:');
    if (title === null || !title.trim()) return;
    try {
      await api('section_create', { title: title.trim() });
      location.reload();
    } catch (e) {
      await SFDialog.alert(e.message);
    }
  });
})();
