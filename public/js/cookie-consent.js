(function () {
  var CONSENT_KEY = 'cojep_cookie_consent';
  var banner = document.getElementById('cookieConsent');
  if (!banner) return;

  var existing = localStorage.getItem(CONSENT_KEY);
  if (!existing) {
    banner.hidden = false;
  }

  var acceptBtn = document.getElementById('cookieAccept');
  var refuseBtn = document.getElementById('cookieRefuse');

  acceptBtn.addEventListener('click', function () {
    localStorage.setItem(CONSENT_KEY, 'accepted');
    banner.hidden = true;
    if (window.cojepPixel) window.cojepPixel.loadPixel();
  });

  refuseBtn.addEventListener('click', function () {
    localStorage.setItem(CONSENT_KEY, 'refused');
    banner.hidden = true;
  });
})();
