(function (global) {
  'use strict';

  async function translateToEnglish(text) {
    const res = await fetch('media_translate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ text }),
    });
    const json = await res.json();
    if (!json.ok) {
      throw new Error(json.error || 'Translate failed');
    }
    return String(json.translated || '').trim();
  }

  function setEnglishHint(hintEl, hintText, englishQuery, originalQuery) {
    if (!hintEl) return;
    if (englishQuery && englishQuery !== originalQuery && hintText) {
      hintEl.textContent = hintText.replace('{query}', englishQuery);
      hintEl.hidden = false;
    } else {
      hintEl.textContent = '';
      hintEl.hidden = true;
    }
  }

  global.SFMediaSearchTranslate = {
    translateToEnglish,
    setEnglishHint,
  };
})(window);
