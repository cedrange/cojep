(function () {
  const form = document.getElementById('loginForm');
  const feedback = document.getElementById('loginFeedback');
  const btn = document.getElementById('loginBtn');

  fetch('/admin/api/session')
    .then((r) => r.json())
    .then((data) => {
      if (data.isAdmin) window.location.href = '/admin/dashboard';
    })
    .catch(() => {});

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    feedback.textContent = '';
    btn.disabled = true;

    try {
      const res = await fetch('/admin/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username: form.username.value.trim(),
          password: form.password.value,
        }),
      });
      const data = await res.json();

      if (!res.ok) {
        feedback.textContent = data.error || 'Connexion impossible.';
        btn.disabled = false;
        return;
      }

      window.location.href = '/admin/dashboard';
    } catch (err) {
      feedback.textContent = 'Impossible de contacter le serveur.';
      btn.disabled = false;
    }
  });
})();
