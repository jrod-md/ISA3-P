const adminMessage = document.querySelector('#admin-message');
const body = document.querySelector('#searches-body');

function notify(text, type = 'info') {
  adminMessage.textContent = text;
  adminMessage.className = `message ${type}`;
  adminMessage.hidden = false;
}

async function api(path, options = {}) {
  const response = await fetch(window.marketplaceUrl(path), { credentials: 'same-origin', ...options, headers: { 'Content-Type': 'application/json', ...(options.headers || {}) } });
  const payload = await response.json();
  if (!response.ok) {
    if (response.status === 401) location.href = window.marketplaceUrl('/admin');
    throw new Error(payload.error?.message || 'Operación fallida');
  }
  return payload;
}

function formatCriteria(criteria) {
  return Object.entries(criteria)
    .filter(([key, value]) => value !== null && value !== '' && !['provider', 'sort', 'limit'].includes(key))
    .map(([key, value]) => `${key}: ${value}`)
    .join(' · ') || 'Sin filtros';
}

function renderSearches(searches) {
  body.replaceChildren();
  document.querySelector('#active-count').textContent = String(searches.length);
  document.querySelector('#admin-empty').hidden = searches.length !== 0;
  for (const search of searches) {
    const row = document.createElement('tr');
    const values = [search.id, formatCriteria(search.criteria), new Date(search.created_at).toLocaleString('es-PA'), new Date(search.expires_at).toLocaleString('es-PA'), `${search.seconds_remaining}s`, String(search.result_count)];
    values.forEach((value, index) => {
      const cell = document.createElement('td');
      cell.textContent = value;
      if (index === 0) cell.className = 'mono';
      row.append(cell);
    });
    const action = document.createElement('td');
    const remove = document.createElement('button');
    remove.className = 'button danger small';
    remove.textContent = 'Eliminar';
    remove.addEventListener('click', async () => {
      if (!window.confirm(`¿Eliminar la búsqueda ${search.id}? El caché asociado también será destruido.`)) return;
      try {
        await api(`/api/admin/searches/${encodeURIComponent(search.id)}`, { method: 'DELETE' });
        notify('Búsqueda y caché eliminados.', 'success');
        await loadSearches();
      } catch (error) { notify(error.message, 'error'); }
    });
    action.append(remove);
    row.append(action);
    body.append(row);
  }
}

async function loadSearches() {
  const payload = await api('/api/admin/searches');
  renderSearches(payload.searches);
}

document.querySelector('#refresh-button').addEventListener('click', () => loadSearches().catch(error => notify(error.message, 'error')));
document.querySelector('#cleanup-button').addEventListener('click', async () => {
  if (!window.confirm('¿Eliminar ahora todas las búsquedas cuyo TTL ya expiró?')) return;
  try {
    const result = await api('/api/admin/searches/expired', { method: 'DELETE' });
    notify(`Se eliminaron ${result.deleted} búsquedas expiradas.`, 'success');
    await loadSearches();
  } catch (error) { notify(error.message, 'error'); }
});
document.querySelector('#delete-all-button').addEventListener('click', async () => {
  if (!window.confirm('¿ELIMINAR TODAS las búsquedas temporales y sus cachés? Esta acción no se puede deshacer.')) return;
  try {
    const result = await api('/api/admin/searches', { method: 'DELETE' });
    notify(`Se eliminaron ${result.deleted} búsquedas y sus cachés.`, 'success');
    await loadSearches();
  } catch (error) { notify(error.message, 'error'); }
});
loadSearches().catch(error => notify(error.message, 'error'));
