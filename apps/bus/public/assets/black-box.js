'use strict';
(() => {
    const form = document.getElementById('black-box-form');
    if (!form) return;
    const decision = form.dataset.type === 'decision';
    const message = document.getElementById('matrix-message');
    const clone = id => document.getElementById(id).content.firstElementChild.cloneNode(true);
    const renumber = () => {
        if (!decision) {
            [...form.querySelector('[data-rows]').rows].forEach((row, index) => {
                row.querySelectorAll('[data-field]').forEach(input => {
                    input.name = `filas[${index}][${input.dataset.field}]`;
                    input.setAttribute('aria-label', `${input.dataset.field.replaceAll('_', ' ')} · fila ${index + 1}`);
                });
            });
        } else {
            form.querySelectorAll('[data-rule-name]').forEach(input => { input.name = 'reglas[]'; });
            form.querySelectorAll('[data-group]').forEach(body => {
                [...body.rows].forEach((row, index) => {
                    row.querySelector('[data-description]').name = `${body.dataset.group}[${index}][texto]`;
                    row.querySelectorAll('[data-cell]').forEach((input, rule) => {
                        input.name = `${body.dataset.group}[${index}][valores][${rule}]`;
                        input.setAttribute('aria-label', `${body.dataset.group} ${index + 1} · regla ${rule + 1}`);
                    });
                });
            });
        }
    };
    form.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        message.textContent = '';
        if (button.hasAttribute('data-add-row')) {
            const body = form.querySelector('[data-rows]');
            if (body.rows.length >= 30) { message.textContent = 'Máximo 30 filas.'; return; }
            const row = clone('matrix-row'); body.append(row); row.querySelector('textarea').focus();
        }
        if (button.hasAttribute('data-remove-row')) {
            const row = button.closest('tr');
            if (row.parentElement.rows.length === 1) { message.textContent = 'Conserva al menos una fila en cada sección.'; return; }
            row.remove();
        }
        if (button.hasAttribute('data-add-element')) {
            const kind = button.dataset.addElement;
            const body = form.querySelector(`[data-group="${kind}"]`);
            if (body.rows.length >= 20) { message.textContent = 'Máximo 20 filas por sección.'; return; }
            const row = clone(`decision-${kind}`);
            form.querySelectorAll('[data-rule]').forEach(() => row.insertBefore(clone(`cell-${kind}`), row.lastElementChild));
            body.append(row); row.querySelector('input').focus();
        }
        if (button.hasAttribute('data-add-rule')) {
            const rules = form.querySelectorAll('[data-rule]');
            if (rules.length >= 20) { message.textContent = 'Máximo 20 reglas.'; return; }
            const cell = clone('decision-rule'); cell.querySelector('input').value = `Regla ${rules.length + 1}`;
            const head = form.querySelector('thead tr'); head.insertBefore(cell, head.lastElementChild);
            form.querySelectorAll('[data-group]').forEach(body => {
                [...body.rows].forEach(row => row.insertBefore(clone(`cell-${body.dataset.group}`), row.lastElementChild));
            });
            cell.querySelector('input').focus();
        }
        if (button.hasAttribute('data-remove-rule')) {
            const rules = [...form.querySelectorAll('[data-rule]')];
            if (rules.length === 1) { message.textContent = 'Conserva al menos una regla.'; return; }
            const cell = button.closest('th'); const index = rules.indexOf(cell);
            form.querySelectorAll('[data-group] tr').forEach(row => row.querySelectorAll('[data-value]')[index].remove());
            cell.remove();
        }
        renumber();
    });
    form.addEventListener('submit', renumber);
    renumber();
})();
