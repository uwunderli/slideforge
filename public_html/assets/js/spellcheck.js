(function () {
  'use strict';

  let api = null;
  let issues = [];
  let panelOpen = false;
  let checking = false;
  let pendingPresentUrl = null;
  let pendingPresentResolve = null;

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function t(key, params) {
    const cfg = api?.spellConfig?.i18n || {};
    let text = cfg[key] || key;
    if (params) {
      Object.keys(params).forEach((k) => {
        text = text.replace('{' + k + '}', params[k]);
      });
    }
    return text;
  }

  function setStatus(msg, isError) {
    const el = $('spellStatus');
    if (!el) return;
    el.textContent = msg || '';
    el.classList.toggle('spell-status-error', !!isError);
  }

  function setProceedVisible(visible) {
    const btn = $('spellProceedBtn');
    if (!btn) return;
    btn.hidden = !visible;
  }

  function clearPendingPresent() {
    pendingPresentUrl = null;
    pendingPresentResolve = null;
    setProceedVisible(false);
  }

  function openPanel() {
    const panel = $('spellPanel');
    if (!panel) return;
    panel.hidden = false;
    panel.classList.add('open');
    panelOpen = true;
  }

  function closePanel() {
    const panel = $('spellPanel');
    if (!panel) return;
    panel.classList.remove('open');
    panel.hidden = true;
    panelOpen = false;
    if (pendingPresentResolve) {
      pendingPresentResolve(false);
      clearPendingPresent();
    }
  }

  function labelForIssue(issue) {
    if (issue.kind === 'title') return t('kindTitle');
    if (issue.kind === 'notes') return t('kindNotes', { n: (issue.slideIndex ?? 0) + 1 });
    return t('kindObject', { n: (issue.slideIndex ?? 0) + 1 });
  }

  function renderChecking() {
    const box = $('spellResults');
    if (!box) return;
    box.innerHTML = '<p class="spell-empty spell-checking">' + escapeHtml(t('checking')) + '</p>';
    setStatus(t('checking'));
    setProceedVisible(false);
  }

  function spellFooterHtml() {
    return '<div class="spell-panel-footer">' +
      '<button type="button" class="button button-sm spell-rerun-btn">' + escapeHtml(t('run')) + '</button>' +
      '</div>';
  }

  function bindSpellFooter(box) {
    box.querySelectorAll('.spell-rerun-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        clearPendingPresent();
        runCheck();
      });
    });
  }

  function renderIssues() {
    if (checking) return;
    const box = $('spellResults');
    if (!box) return;
    if (!issues.length) {
      box.innerHTML = '<p class="spell-empty">' + escapeHtml(t('noIssues')) + '</p>' +
        spellFooterHtml();
      bindSpellFooter(box);
      setStatus(t('noIssuesShort'));
      setProceedVisible(false);
      if (pendingPresentResolve && pendingPresentUrl) {
        const url = pendingPresentUrl;
        pendingPresentResolve(true);
        clearPendingPresent();
        window.location.href = url;
      }
      return;
    }
    setStatus(t('issueCount', { count: issues.length }));
    if (pendingPresentUrl) setProceedVisible(true);
    box.innerHTML = (pendingPresentUrl
      ? '<p class="spell-before-present-hint">' + escapeHtml(t('beforePresentHint')) + '</p>'
      : '') + issues.map((issue, idx) => {
      const suggestion = issue.suggestions?.[0] || '';
      const wrong = issue.wrong || '';
      return (
        '<article class="spell-issue" data-issue-index="' + idx + '">' +
          '<div class="spell-issue-meta">' + escapeHtml(labelForIssue(issue)) + '</div>' +
          '<div class="spell-issue-wrong"><s>' + escapeHtml(wrong) + '</s></div>' +
          (issue.message ? '<div class="spell-issue-msg">' + escapeHtml(issue.message) + '</div>' : '') +
          (suggestion ? '<div class="spell-issue-suggest">' + escapeHtml(t('suggestion')) + ': <strong>' + escapeHtml(suggestion) + '</strong></div>' : '') +
          '<div class="spell-issue-actions">' +
            (suggestion ? '<button type="button" class="button button-sm spell-apply-btn" data-issue-index="' + idx + '">' + escapeHtml(t('apply')) + '</button>' : '') +
            '<button type="button" class="button button-ghost button-sm spell-goto-btn" data-issue-index="' + idx + '">' + escapeHtml(t('goto')) + '</button>' +
            '<button type="button" class="button button-ghost button-sm spell-ignore-btn" data-issue-index="' + idx + '">' + escapeHtml(t('ignore')) + '</button>' +
          '</div>' +
        '</article>'
      );
    }).join('') + spellFooterHtml();

    bindSpellFooter(box);
    box.querySelectorAll('.spell-apply-btn').forEach((btn) => {
      btn.addEventListener('click', () => { applyFix(parseInt(btn.dataset.issueIndex, 10)); });
    });
    box.querySelectorAll('.spell-goto-btn').forEach((btn) => {
      btn.addEventListener('click', () => gotoIssue(parseInt(btn.dataset.issueIndex, 10)));
    });
    box.querySelectorAll('.spell-ignore-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        issues.splice(parseInt(btn.dataset.issueIndex, 10), 1);
        renderIssues();
      });
    });
  }

  async function runCheck() {
    if (!api) return { ok: false, issueCount: 0 };
    const btn = $('spellRunBtn');
    if (btn) btn.disabled = true;
    checking = true;
    issues = [];
    renderChecking();

    try {
      await api.syncCurrentSlide();
      const res = await fetch('spellcheck.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'check',
          id: api.id,
          language: api.spellConfig?.lang || '',
        }),
      });
      const data = await res.json();
      if (!data.ok) {
        setStatus(data.error || t('errorGeneric'), true);
        $('spellResults').innerHTML = '<p class="spell-empty spell-error">' + escapeHtml(data.error || t('errorGeneric')) + '</p>';
        return { ok: false, issueCount: 0, error: data.error };
      }
      issues = data.issues || [];
      checking = false;
      renderIssues();
      return { ok: true, issueCount: issues.length };
    } catch (e) {
      console.error(e);
      setStatus(t('errorGeneric'), true);
      return { ok: false, issueCount: 0 };
    } finally {
      checking = false;
      if (btn) btn.disabled = false;
    }
  }

  async function applyFix(index) {
    const issue = issues[index];
    if (!issue || !api) return;
    const suggestion = issue.suggestions?.[0];
    if (!suggestion) return;

    const ok = await api.applyCorrection(issue, suggestion);
    if (!ok) return;

    issues.splice(index, 1);
    renderIssues();
  }

  function gotoIssue(index) {
    const issue = issues[index];
    if (!issue || !api) return;
    api.gotoIssue(issue);
  }

  function proceedToPresent() {
    if (!pendingPresentUrl || !pendingPresentResolve) return;
    const url = pendingPresentUrl;
    pendingPresentResolve(true);
    clearPendingPresent();
    window.location.href = url;
  }

  async function ensureCleanBeforePresent(href) {
    if (!api?.spellConfig?.beforePresent) return true;
    openPanel();
    clearPendingPresent();
    const result = await runCheck();
    if (!result.ok || result.issueCount === 0) {
      clearPendingPresent();
      return true;
    }
    pendingPresentUrl = href;
    renderIssues();
    return new Promise((resolve) => {
      pendingPresentResolve = resolve;
    });
  }

  function bindUI() {
    $('spellcheckBtn')?.addEventListener('click', () => {
      clearPendingPresent();
      openPanel();
      runCheck();
    });
    $('spellPanelClose')?.addEventListener('click', closePanel);
    $('spellRunBtn')?.addEventListener('click', () => {
      clearPendingPresent();
      runCheck();
    });
    $('spellProceedBtn')?.addEventListener('click', proceedToPresent);
  }

  window.SlideForgeSpellcheck = {
    init(editorApi) {
      api = editorApi;
      if (!editorApi.spellConfig?.enabled) {
        $('spellcheckBtn')?.remove();
        $('spellPanel')?.remove();
        return;
      }
      bindUI();
    },
    ensureCleanBeforePresent,
  };
})();
