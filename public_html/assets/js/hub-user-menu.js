/**
 * HubUserMenu — Öffnen/Schliessen des Header-Benutzermenüs.
 * Quelle: hub/public/assets/js/hub-user-menu.js — Module vendorn, nicht forken.
 *
 * Markup:
 *   <div class="hub-user-menu" data-hub-user-menu>
 *     <button type="button" class="hub-user-chip" data-hub-user-menu-trigger …>
 *     <div class="hub-user-menu__dropdown" data-hub-user-menu-dropdown role="menu">
 *
 * API:
 *   HubUserMenu.bind(root)
 *   HubUserMenu.bindAll(scope?)
 *   HubUserMenu.closeAll()
 */
(function (global) {
  'use strict';

  function dropdownOf(root) {
    return root.querySelector('[data-hub-user-menu-dropdown], .hub-user-menu__dropdown, .user-menu-dropdown');
  }

  function triggerOf(root) {
    return root.querySelector('[data-hub-user-menu-trigger], .hub-user-chip, .user-menu-trigger');
  }

  function setOpen(root, open) {
    const trigger = triggerOf(root);
    const dropdown = dropdownOf(root);
    if (!dropdown) return;
    dropdown.classList.toggle('is-open', open);
    dropdown.classList.toggle('open', open);
    if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function isOpen(root) {
    const dropdown = dropdownOf(root);
    return !!(dropdown && (dropdown.classList.contains('is-open') || dropdown.classList.contains('open')));
  }

  function closeAll(except) {
    document.querySelectorAll('.hub-user-menu').forEach((root) => {
      if (except && root === except) return;
      setOpen(root, false);
    });
  }

  function bind(root) {
    if (!root || root.dataset.hubUserMenuBound === '1') return root;
    const trigger = triggerOf(root);
    const dropdown = dropdownOf(root);
    if (!trigger || !dropdown) return root;
    root.dataset.hubUserMenuBound = '1';

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const willOpen = !isOpen(root);
      closeAll();
      if (willOpen) setOpen(root, true);
    });

    return root;
  }

  function bindAll(scope) {
    const ctx = scope && scope.querySelectorAll ? scope : document;
    ctx.querySelectorAll('.hub-user-menu').forEach((el) => bind(el));
  }

  if (!global.__hubUserMenuDocBound) {
    global.__hubUserMenuDocBound = true;
    document.addEventListener('click', (e) => {
      if (e.target.closest && e.target.closest('.hub-user-menu')) return;
      // Native <select> option pickers fire a document click outside the menu;
      // closing then aborts change/submit (Theme/Sprache lassen sich nicht umschalten).
      const ae = document.activeElement;
      if (
        ae
        && typeof ae.closest === 'function'
        && ae.closest('.hub-user-menu select, .hub-user-menu input, .hub-user-menu textarea')
      ) {
        return;
      }
      closeAll();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAll();
    });
  }

  global.HubUserMenu = {
    bind,
    bindAll,
    closeAll,
    setOpen,
    isOpen
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => bindAll());
  } else {
    bindAll();
  }
})(typeof window !== 'undefined' ? window : globalThis);
