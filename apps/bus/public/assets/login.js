'use strict';
const loginForm = document.getElementById('login-form');
const loginMessage = document.getElementById('login-message');
const loginButton = document.getElementById('login-submit');
document.querySelector('.password-toggle').addEventListener('click', event => {
    const field = document.getElementById('password');
    const visible = field.type === 'password';
    field.type = visible ? 'text' : 'password';
    event.currentTarget.textContent = visible ? 'Ocultar' : 'Mostrar';
    event.currentTarget.setAttribute('aria-pressed', String(visible));
});
loginForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (loginButton.disabled) return;
    loginMessage.hidden = true;
    loginButton.disabled = true;
    loginButton.textContent = 'Ingresando…';
    try {
        const data = new FormData(loginForm);
        const response = await fetch(window.marketplaceUrl('/api/admin/login'), {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({username: data.get('username'), password: data.get('password')})
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error?.message || 'No se pudo iniciar sesión.');
        location.href = window.marketplaceUrl('/dashboard');
    } catch (error) {
        loginMessage.textContent = error instanceof TypeError ? 'No se pudo conectar. Comprueba que el proyecto esté ejecutándose e inténtalo de nuevo.' : error.message;
        loginMessage.hidden = false;
        loginButton.disabled = false;
        loginButton.textContent = 'Iniciar sesión';
    }
});
