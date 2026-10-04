'use strict';

const menu = document.querySelector('.menu-toggle');
menu?.addEventListener('click', () => {
    const expanded = menu.getAttribute('aria-expanded') !== 'true';
    menu.setAttribute('aria-expanded', String(expanded));
    document.getElementById('navegacion').classList.toggle('is-open', expanded);
});

const technique = document.getElementById('tecnica');
const subtechnique = document.getElementById('subtecnica');
const catalogElement = document.getElementById('technique-catalog');
if (technique && subtechnique && catalogElement) {
    const catalog = JSON.parse(catalogElement.textContent);
    const help = document.getElementById('technique-help');
    const update = (preserve) => {
        const selected = preserve ? subtechnique.value : '';
        subtechnique.replaceChildren(new Option('Selecciona una subtécnica', ''));
        for (const item of catalog[technique.value] || []) {
            subtechnique.add(new Option(item, item, false, item === selected));
        }
        subtechnique.disabled = !technique.value;
        help.textContent = technique.value === 'Caja Negra'
            ? 'Caja Negra: evalúa el comportamiento mediante entradas y salidas, sin examinar el código interno.'
            : technique.value === 'Caja Blanca'
                ? 'Caja Blanca: evalúa la estructura interna del código y la cobertura de su ejecución.'
                : 'Caja Negra evalúa entradas y salidas sin examinar el código. Caja Blanca evalúa la estructura interna del código.';
    };
    technique.addEventListener('change', () => update(false));
    update(true);
}

document.getElementById('session-logout')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
        const response = await fetch(window.marketplaceUrl('/api/admin/logout'), {method: 'POST', credentials: 'same-origin'});
        if (!response.ok) throw new Error('No se pudo cerrar la sesión. Inténtalo de nuevo.');
        location.href = window.marketplaceUrl('/admin');
    } catch (error) {
        const message = document.getElementById('session-message');
        message.textContent = error.message;
        message.hidden = false;
        button.disabled = false;
    }
});
