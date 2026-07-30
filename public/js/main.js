(function () {
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  const navToggle = document.getElementById('navToggle');
  const mainNav = document.getElementById('mainNav');
  if (navToggle && mainNav) {
    navToggle.addEventListener('click', () => {
      const isOpen = mainNav.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', String(isOpen));
    });
    mainNav.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        mainNav.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  const form = document.getElementById('registerForm');
  const feedback = document.getElementById('formFeedback');
  const submitBtn = document.getElementById('submitBtn');

  if (!form) return;

  function setFeedback(message, type) {
    feedback.textContent = message;
    feedback.className = 'form-feedback' + (type ? ' ' + type : '');
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    setFeedback('', '');

    const data = {
      prenom: form.prenom.value.trim(),
      nom: form.nom.value.trim(),
      email: form.email.value.trim(),
      telephone: form.telephone.value.trim(),
      nombre_personnes: form.nombre_personnes.value || 1,
      message: form.message.value.trim(),
    };

    if (!data.prenom || !data.nom || !data.email) {
      setFeedback('Merci de renseigner votre prénom, nom et email.', 'error');
      return;
    }

    submitBtn.disabled = true;
    const originalLabel = submitBtn.querySelector('.btn-label').textContent;
    submitBtn.querySelector('.btn-label').textContent = 'Envoi en cours...';

    try {
      const res = await fetch('/api/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      const result = await res.json();

      if (!res.ok) {
        setFeedback(result.error || "Une erreur est survenue, merci de réessayer.", 'error');
        return;
      }

      setFeedback('Merci ! Votre inscription est confirmée, un email vous a été envoyé.', 'success');
      form.reset();
      form.nombre_personnes.value = 1;
      if (window.cojepPixel) window.cojepPixel.trackRegistration();
    } catch (err) {
      setFeedback('Impossible de contacter le serveur. Réessayez dans un instant.', 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.querySelector('.btn-label').textContent = originalLabel;
    }
  });
})();
