(function () {
  // TODO: remplacer par l'ID du Pixel Meta (Events Manager > Sources de données > votre Pixel)
  var PIXEL_ID = 'YOUR_PIXEL_ID';
  var CONSENT_KEY = 'cojep_cookie_consent';

  function loadPixel() {
    if (window.fbq) return;

    !(function (f, b, e, v, n, t, s) {
      if (f.fbq) return;
      n = f.fbq = function () {
        n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
      };
      if (!f._fbq) f._fbq = n;
      n.push = n;
      n.loaded = true;
      n.version = '2.0';
      n.queue = [];
      t = b.createElement(e);
      t.async = true;
      t.src = v;
      s = b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t, s);
    })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

    fbq('init', PIXEL_ID);
    fbq('track', 'PageView');
  }

  function trackRegistration() {
    if (window.fbq) fbq('track', 'CompleteRegistration');
  }

  window.cojepPixel = { loadPixel: loadPixel, trackRegistration: trackRegistration };

  if (localStorage.getItem(CONSENT_KEY) === 'accepted') {
    loadPixel();
  }
})();
