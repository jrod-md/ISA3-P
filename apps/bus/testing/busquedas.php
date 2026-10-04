<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();
$title = 'Búsquedas temporales'; $active = 'busquedas';
$ttl = Marketplace\Bus\Config\Config::int('SEARCH_TTL_SECONDS', 60);
require TESTING_PATH . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">ADMINISTRACIÓN DEL BUS</span><h1>Búsquedas temporales</h1><p class="muted">Consulta las búsquedas del proyecto y administra su caché.</p></div><a class="button" href="<?= e(url('/')) ?>">Abrir buscador →</a></div>
<section id="admin-panel">
    <section class="panel admin-summary">
        <div><h2><span id="active-count">0</span> búsquedas almacenadas</h2><p class="muted">Caducan después de <?= (int) $ttl ?> segundos. El proceso de limpieza retira las vencidas.</p></div>
        <div class="button-group"><button id="refresh-button" class="button-secondary" type="button">Actualizar</button><button id="cleanup-button" class="button-secondary" type="button">Limpiar expiradas</button><button id="delete-all-button" class="button button-danger" type="button">Eliminar todas</button></div>
    </section>
    <div id="admin-message" class="message" role="status" aria-live="polite" hidden></div>
    <section class="panel table-panel"><div class="table-scroll"><table><caption class="sr-only">Búsquedas temporales del Bus</caption><thead><tr><th>ID</th><th>Criterios</th><th>Creada</th><th>Expira</th><th>Restante</th><th>Resultados</th><th>Acción</th></tr></thead><tbody id="searches-body"></tbody></table></div>
        <div id="admin-empty" class="empty-state" hidden><span class="empty-symbol" aria-hidden="true">⌕</span><h3>No hay búsquedas temporales</h3><p>Realiza una búsqueda para revisar aquí su sesión y resultados.</p><a class="button-secondary" href="<?= e(url('/')) ?>">Buscar productos</a></div>
    </section>
</section>
<script src="<?= e(url('/assets/admin.js')) ?>" defer></script>
<?php require TESTING_PATH . '/includes/footer.php'; ?>
