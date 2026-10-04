<?php
require_once dirname(__DIR__) . '/testing/includes/bootstrap.php';
$title = 'Buscador del proyecto'; $active = 'buscador'; $publicPage = true;
require TESTING_PATH . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">NUESTRO PROYECTO</span><h1>Marketplace Search Bus</h1><p class="muted">Busca productos en tres proveedores y documenta las pruebas del sistema.</p></div></div>
    <section class="panel search-panel" aria-labelledby="search-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Bus de Web Services</p>
                <h2 id="search-title">Búsqueda unificada</h2>
            </div>
            <span class="live-chip"><span aria-hidden="true"></span> Servicios locales</span>
        </div>

        <form id="search-form">
            <div class="search-row">
                <label class="search-box">
                    <span class="sr-only">Producto o palabra clave</span>
                    <input id="q" name="q" type="search" maxlength="120" placeholder="Laptop, monitor, mouse…">
                </label>
                <button id="search-button" class="button primary" type="submit">Buscar productos</button>
            </div>
            <div class="filter-grid">
                <label>Categoría
                    <select name="category">
                        <option value="">Todas</option>
                        <option value="computers">Computadoras</option>
                        <option value="phones">Teléfonos</option>
                        <option value="audio">Audio</option>
                        <option value="gaming">Gaming</option>
                        <option value="accessories">Accesorios</option>
                        <option value="home-office">Oficina en casa</option>
                    </select>
                </label>
                <label>Precio mínimo
                    <input name="min_price" type="number" min="0" max="100000" step="0.01" placeholder="0.00">
                </label>
                <label>Precio máximo
                    <input name="max_price" type="number" min="0" max="100000" step="0.01" placeholder="900.00">
                </label>
                <label>Proveedor
                    <select name="provider">
                        <option value="all">Todos</option>
                        <option value="alpha">Alpha</option>
                        <option value="beta">Beta</option>
                        <option value="gamma">Gamma</option>
                    </select>
                </label>
                <label>Orden
                    <select name="sort">
                        <option value="default">Predeterminado</option>
                        <option value="price_asc">Precio: menor primero</option>
                        <option value="price_desc">Precio: mayor primero</option>
                        <option value="name_asc">Nombre A–Z</option>
                    </select>
                </label>
                <label>Stock mínimo
                    <input name="min_stock" type="number" min="0" max="100000" step="1" placeholder="Cualquiera">
                </label>
            </div>
        </form>
        <div id="message" class="message" role="status" aria-live="polite" hidden></div>
    </section>

    <section id="search-meta" class="panel meta-panel" hidden aria-label="Metadatos de búsqueda">
        <div><span>ID de búsqueda</span><strong id="search-id">—</strong></div>
        <div><span>Expira en</span><strong id="countdown">—</strong></div>
        <div><span>Resultados</span><strong id="result-count">0</strong></div>
        <div class="provider-statuses" id="provider-statuses"></div>
        <?php if ($current): ?><div class="search-case-action"><a id="document-search" class="text-link" href="<?= e(url('/casos/nuevo')) ?>">Registrar un caso sobre esta búsqueda →</a></div><?php endif; ?>
    </section>

    <section aria-labelledby="results-title">
        <div class="results-heading">
            <div>
                <p class="eyebrow">Catálogo agregado</p>
                <h2 id="results-title">Resultados</h2>
            </div>
        </div>
        <div id="results" class="product-grid">
            <div class="empty-state">
                <span>⌕</span>
                <h3>Realiza una búsqueda</h3>
                <p>El Bus consultará Alpha, Beta y Gamma por HTTP y normalizará sus respuestas.</p>
            </div>
        </div>
    </section>
<script src="<?= e(url('/assets/search.js')) ?>" defer></script>
<?php require TESTING_PATH . '/includes/footer.php'; ?>
