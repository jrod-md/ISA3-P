'use strict';
(() => {
    const form = document.getElementById('test-plan-form');
    if (!form) return;
    const body = form.querySelector('[data-plan-rows]');
    const message = document.getElementById('plan-message');
    const update = () => {
        [...body.rows].forEach((row, index) => {
            row.querySelectorAll('[data-plan-field]').forEach(input => {
                input.name = `cronograma[${index}][${input.dataset.planField}]`;
                input.setAttribute('aria-label', `${input.dataset.label} · fila ${index + 1}`);
            });
            const start = row.querySelector('[data-plan-field="fecha_inicio"]');
            const end = row.querySelector('[data-plan-field="fecha_fin"]');
            end.setCustomValidity(start.value && end.value && end.value < start.value ? 'Fecha de Fin no puede ser anterior a Fecha de Inicio.' : '');
        });
    };
    form.addEventListener('input', update);
    form.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-plan-add')) {
            if (body.rows.length >= 30) { message.textContent = 'Máximo 30 actividades.'; return; }
            const row = document.getElementById('plan-row').content.firstElementChild.cloneNode(true);
            body.append(row); row.querySelector('input').focus(); message.textContent = '';
        }
        if (button.hasAttribute('data-plan-remove')) {
            if (body.rows.length === 1) { message.textContent = 'Conserva al menos una actividad.'; return; }
            button.closest('tr').remove(); message.textContent = '';
        }
        update();
    });
    form.addEventListener('submit', update);
    update();
})();
