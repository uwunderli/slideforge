(function () {
  const el = document.getElementById('demoResetCountdown');
  if (!el) return;
  const target = parseInt(el.dataset.resetAt || '0', 10) * 1000;
  if (!target) return;

  function pad(n) {
    return String(n).padStart(2, '0');
  }

  function tick() {
    const diff = target - Date.now();
    if (diff <= 0) {
      el.textContent = '00:00:00';
      return;
    }
    const totalSec = Math.floor(diff / 1000);
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
  }

  tick();
  setInterval(tick, 1000);
})();
