(function () {
  if (!('serviceWorker' in navigator)) {
    return;
  }
  window.addEventListener('load', function () {
    var base = document.querySelector('link[rel="manifest"]');
    var swUrl = 'sw.php';
    if (base && base.getAttribute('href')) {
      var manifestHref = base.getAttribute('href');
      var idx = manifestHref.lastIndexOf('/');
      if (idx > 0) {
        swUrl = manifestHref.slice(0, idx + 1) + 'sw.php';
      }
    }
    navigator.serviceWorker.register(swUrl, { scope: './' }).catch(function () {
      /* optional — PWA still usable via “Add to Home Screen” */
    });
  });
})();
