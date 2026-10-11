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

// Pestañas de la ficha: sin JavaScript, las secciones se muestran una tras otra.
for (const tabs of document.querySelectorAll('[data-tabs]')) {
    const links = Array.from(tabs.querySelectorAll('a[href^="#"]'));
    const panels = links.map(link => document.getElementById(link.hash.slice(1)));
    const select = (hash, focus) => {
        const index = Math.max(0, links.findIndex(link => link.hash === hash));
        links.forEach((link, i) => {
            link.setAttribute('aria-selected', String(i === index));
            link.tabIndex = i === index ? 0 : -1;
            panels[i].hidden = i !== index;
        });
        if (focus) links[index].focus();
    };
    tabs.setAttribute('role', 'tablist');
    links.forEach((link, i) => {
        link.setAttribute('role', 'tab');
        link.setAttribute('aria-controls', panels[i].id);
        panels[i].setAttribute('role', 'tabpanel');
        link.addEventListener('click', event => {
            event.preventDefault();
            history.replaceState(null, '', link.hash);
            select(link.hash, false);
        });
        link.addEventListener('keydown', event => {
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
            const next = links[(i + (event.key === 'ArrowRight' ? 1 : links.length - 1)) % links.length];
            history.replaceState(null, '', next.hash);
            select(next.hash, true);
        });
    });
    document.body.classList.add('tabs-ready');
    select(location.hash, false);
    // El ancla ya eligió la pestaña; evita dejar la página desplazada hasta el panel.
    if (links.some(link => link.hash === location.hash)) window.scrollTo(0, 0);
}

// Estado real del Bus en la barra superior, leído de su /health.
const health = document.getElementById('bus-health');
if (health) {
    const text = document.getElementById('bus-health-text');
    fetch(window.marketplaceUrl('/health'), {headers: {Accept: 'application/json'}})
        .then(response => response.ok ? response.json() : Promise.reject())
        .then(data => {
            const ok = data.status === 'ok';
            health.dataset.state = ok ? 'ok' : 'down';
            text.textContent = ok ? 'en línea' : 'con errores';
        })
        .catch(() => { health.dataset.state = 'down'; text.textContent = 'sin respuesta'; });
}
