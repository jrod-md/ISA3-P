'use strict';
(() => {
    const form = document.getElementById('coverage-form');
    if (!form) return;
    const update = row => {
        const total = row.querySelector('[data-total]');
        const covered = row.querySelector('[data-cubiertos]');
        const output = row.querySelector('[data-percentage]');
        const countValid = input => /^[0-9]{1,10}$/.test(input.value) && Number(input.value) <= 4294967295;
        const valid = countValid(total) && countValid(covered);
        const exceeds = valid && Number(covered.value) > Number(total.value);
        total.setCustomValidity(total.value !== '' && !countValid(total) ? 'Ingresa un entero entre 0 y 4294967295.' : '');
        covered.setCustomValidity(exceeds ? 'Cubiertos no puede superar Total.' : (covered.value !== '' && !countValid(covered) ? 'Ingresa un entero entre 0 y 4294967295.' : ''));
        output.value = valid && !exceeds
            ? `${(Number(total.value) === 0 ? 0 : Math.round(Number(covered.value) / Number(total.value) * 10000) / 100).toFixed(2)} %`
            : '—';
    };
    form.querySelectorAll('[data-coverage-row]').forEach(row => {
        row.addEventListener('input', () => update(row));
        update(row);
    });
})();
