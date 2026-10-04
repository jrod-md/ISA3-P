'use strict';
(() => {
    const form = document.getElementById('project-evaluation-form');
    if (!form) return;
    if (form.dataset.type === 'portafolio') {
        const body = form.querySelector('[data-evidence-rows]');
        const message = document.getElementById('evidence-message');
        const renumber = () => {
            [...body.rows].forEach((row, index) => {
                row.querySelectorAll('[data-evidence-field]').forEach(input => {
                    input.name = `filas[${index}][${input.dataset.evidenceField}]`;
                    input.setAttribute('aria-label', `Evidencia ${index + 1} · ${input.dataset.label}`);
                });
                row.querySelector('[data-evidence-remove]').disabled = body.rows.length === 1;
            });
            form.querySelector('[data-evidence-add]').disabled = body.rows.length >= 30;
            message.textContent = `${body.rows.length} de 30 evidencias`;
        };
        form.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button) return;
            if (button.hasAttribute('data-evidence-add') && body.rows.length < 30) {
                const row = document.getElementById('evidence-row').content.firstElementChild.cloneNode(true);
                body.append(row); renumber(); row.querySelector('input').focus();
            }
            if (button.hasAttribute('data-evidence-remove') && body.rows.length > 1) {
                button.closest('tr').remove(); renumber();
            }
        });
        renumber();
    } else {
        const result = document.getElementById('evaluation-result');
        const calculate = field => {
            const inputs = [...form.querySelectorAll(`[data-score="${field}"]`)];
            if (inputs.length !== 6 || inputs.some(input => !/^[1-5]$/.test(input.value))) return null;
            return inputs.reduce((sum, input) => sum + Number(input.value), 0);
        };
        const update = () => {
            if (form.dataset.type === 'rubrica') {
                const total = calculate('puntuacion');
                result.textContent = total === null ? 'Total: completa los seis criterios' : `Total: ${total} / 30`;
            } else {
                const auto = calculate('autoevaluacion'), co = calculate('coevaluacion');
                result.textContent = `Promedio Autoevaluación: ${auto === null ? 'pendiente' : (auto / 6).toFixed(2)} · Promedio Coevaluación: ${co === null ? 'pendiente' : (co / 6).toFixed(2)}`;
            }
        };
        form.addEventListener('change', update); update();
    }
})();
