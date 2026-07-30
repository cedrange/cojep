(function () {
  const tableBody = document.getElementById('regTableBody');
  const statCount = document.getElementById('statCount');
  const statPersonnes = document.getElementById('statPersonnes');
  const searchInput = document.getElementById('searchInput');
  const logoutBtn = document.getElementById('logoutBtn');

  let debounceTimer = null;

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatDate(iso) {
    try {
      const d = new Date(iso.replace(' ', 'T') + 'Z');
      return d.toLocaleString('fr-BE', { dateStyle: 'medium', timeStyle: 'short' });
    } catch (e) {
      return iso;
    }
  }

  function renderRows(rows) {
    if (!rows.length) {
      tableBody.innerHTML = '<tr><td colspan="9" class="empty-row">Aucune inscription trouvée.</td></tr>';
      return;
    }

    tableBody.innerHTML = rows
      .map(
        (r) => `
      <tr data-id="${r.id}">
        <td>${escapeHtml(r.prenom)}</td>
        <td>${escapeHtml(r.nom)}</td>
        <td>${escapeHtml(r.email)}</td>
        <td>${escapeHtml(r.telephone) || '—'}</td>
        <td>${escapeHtml(r.nombre_personnes)}</td>
        <td>${escapeHtml(r.message) || '—'}</td>
        <td>${r.email_envoye ? '<span class="badge badge-ok">Envoyé</span>' : '<span class="badge badge-pending">En attente</span>'}</td>
        <td>${formatDate(r.created_at)}</td>
        <td><button class="btn-delete" data-id="${r.id}">Supprimer</button></td>
      </tr>
    `
      )
      .join('');
  }

  async function loadRegistrations(query) {
    tableBody.innerHTML = '<tr><td colspan="9" class="empty-row">Chargement...</td></tr>';
    try {
      const url = '/admin/api/registrations' + (query ? '?q=' + encodeURIComponent(query) : '');
      const res = await fetch(url);
      if (res.status === 401) {
        window.location.href = '/admin';
        return;
      }
      const data = await res.json();
      statCount.textContent = data.count;
      statPersonnes.textContent = data.totalPersonnes;
      renderRows(data.rows);
    } catch (err) {
      tableBody.innerHTML = '<tr><td colspan="9" class="empty-row">Erreur de chargement.</td></tr>';
    }
  }

  fetch('/admin/api/session')
    .then((r) => r.json())
    .then((data) => {
      if (!data.isAdmin) {
        window.location.href = '/admin';
        return;
      }
      loadRegistrations('');
    })
    .catch(() => {
      window.location.href = '/admin';
    });

  searchInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadRegistrations(searchInput.value.trim()), 300);
  });

  tableBody.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-delete');
    if (!btn) return;
    const id = btn.dataset.id;
    if (!confirm('Supprimer définitivement cette inscription ?')) return;

    btn.disabled = true;
    try {
      const res = await fetch('/admin/api/registrations/' + id, { method: 'DELETE' });
      if (res.ok) {
        loadRegistrations(searchInput.value.trim());
      } else {
        alert('Suppression impossible.');
        btn.disabled = false;
      }
    } catch (err) {
      alert('Erreur réseau.');
      btn.disabled = false;
    }
  });

  logoutBtn.addEventListener('click', async () => {
    await fetch('/admin/api/logout', { method: 'POST' });
    window.location.href = '/admin';
  });
})();
