const form = document.querySelector('#search-form');
const button = document.querySelector('#search-button');
const message = document.querySelector('#message');
const results = document.querySelector('#results');
const meta = document.querySelector('#search-meta');
let countdownTimer = null;
// Al volver desde un caso, conservar los filtros sin repetir automáticamente la búsqueda.
for (const [name, value] of new URLSearchParams(location.search)) {
  const field = form.elements.namedItem(name);
  if (field instanceof HTMLInputElement) field.value = value.slice(0, field.maxLength > 0 ? field.maxLength : 120);
  else if (field instanceof HTMLSelectElement && Array.from(field.options).some(option => option.value === value)) field.value = value;
}

function showMessage(text, type = 'info') {
  message.textContent = text;
  message.className = `message ${type}`;
  message.hidden = false;
}

function clearMessage() {
  message.hidden = true;
  message.textContent = '';
}

function createText(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  node.textContent = text;
  return node;
}

function renderResults(items) {
  results.replaceChildren();
  if (!items.length) {
    const empty = createText('div', 'empty-state', 'No se encontraron productos con esos filtros.');
    results.append(empty);
    return;
  }

  for (const item of items) {
    const card = document.createElement('article');
    card.className = 'product-card';
    const top = document.createElement('div');
    top.className = 'card-top';
    top.append(createText('span', `provider-badge ${item.provider}`, item.provider.toUpperCase()));
    top.append(createText('span', 'stock', `${item.stock} en stock`));
    card.append(top);
    card.append(createText('p', 'category', item.category));
    card.append(createText('h3', '', item.title));
    card.append(createText('p', 'description', item.description || 'Sin descripción'));
    const bottom = document.createElement('div');
    bottom.className = 'card-bottom';
    bottom.append(createText('strong', 'price', new Intl.NumberFormat('es-PA', { style: 'currency', currency: item.currency }).format(item.price)));
    bottom.append(createText('span', 'brand', item.brand || 'Marca sin especificar'));
    card.append(bottom);
    results.append(card);
  }
}

function renderMetadata(data) {
  meta.hidden = false;
  document.querySelector('#search-id').textContent = data.search_id;
  document.querySelector('#result-count').textContent = String(data.total);
  const statuses = document.querySelector('#provider-statuses');
  statuses.replaceChildren();
  for (const [name, status] of Object.entries(data.providers)) {
    const chip = createText('span', `status-chip ${status.status}`, `${name}: ${status.status === 'ok' ? `${status.count} OK` : 'ERROR'}`);
    statuses.append(chip);
  }
  startCountdown(data.expires_at, data.search_id);
}

function startCountdown(expiresAt, searchId) {
  if (countdownTimer) clearInterval(countdownTimer);
  const display = document.querySelector('#countdown');
  const tick = () => {
    const seconds = Math.max(0, Math.ceil((new Date(expiresAt).getTime() - Date.now()) / 1000));
    display.textContent = `00:${String(seconds).padStart(2, '0')}`;
    if (seconds === 0) {
      clearInterval(countdownTimer);
      display.textContent = 'Expirada · pendiente de limpieza';
      meta.classList.add('expired');
      window.setTimeout(async () => {
        const response = await fetch(window.marketplaceUrl(`/api/search/${encodeURIComponent(searchId)}`));
        if (response.status === 404) display.textContent = 'Destruida por el worker';
      }, 6000);
    }
  };
  meta.classList.remove('expired');
  tick();
  countdownTimer = window.setInterval(tick, 1000);
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  clearMessage();
  const data = new FormData(form);
  const min = data.get('min_price');
  const max = data.get('max_price');
  if (min && max && Number(min) > Number(max)) {
    showMessage('El precio mínimo no puede ser mayor que el precio máximo.', 'error');
    return;
  }
  const params = new URLSearchParams();
  for (const [key, value] of data.entries()) {
    if (String(value).trim() !== '') params.set(key, String(value));
  }
  button.disabled = true;
  button.textContent = 'Consultando servicios…';
  try {
    const response = await fetch(window.marketplaceUrl(`/api/search?${params.toString()}`), { headers: { Accept: 'application/json' } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error?.message || 'No se pudo completar la búsqueda');
    renderResults(payload.results);
    renderMetadata(payload);
    const documentSearch = document.getElementById('document-search');
    if (documentSearch) {
      const observations = `${payload.total} resultados. Proveedores: ${Object.entries(payload.providers).map(([name, status]) => `${name}: ${status.status}, ${status.count ?? 0} resultados`).join('; ')}. Precios: ${payload.results.map(item => `${Number(item.price).toFixed(2)} ${item.currency}`).join(', ')}.`;
      const caseParams = new URLSearchParams({origen: 'buscador', datos_entrada: params.toString(), resultado_obtenido: observations});
      documentSearch.href = window.marketplaceUrl(`/casos/nuevo?${caseParams.toString()}`);
    }
    if (payload.warnings.length) showMessage(payload.warnings.join(' · '), 'warning');
  } catch (error) {
    showMessage(error.message === 'Failed to fetch' ? 'El Bus no está disponible.' : error.message, 'error');
  } finally {
    button.disabled = false;
    button.textContent = 'Buscar productos';
  }
});
