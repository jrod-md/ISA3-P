<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin(); $case = case_by_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    db()->prepare('DELETE FROM casos_prueba WHERE id = ?')->execute([$case['id']]);
    delete_evidence($case['evidencia_archivo']);
    flash('Caso ' . code_case((int) $case['id']) . ' eliminado.');
    redirect('/casos');
}
$title = 'Eliminar caso'; $active = 'casos'; require TESTING_PATH . '/includes/header.php';
?>
<a class="back-link" href="<?= e(url('/casos/ver?id=' . $case['id'])) ?>">← Volver al caso</a>
<div class="page-heading"><div><span class="eyebrow">CONFIRMAR ELIMINACIÓN</span><h1>Eliminar <?= e(code_case((int) $case['id'])) ?></h1><p class="muted"><?= e($case['modulo']) ?></p></div></div>
<section class="panel confirmation"><h2>Este registro se eliminará permanentemente</h2><p>También se eliminarán su archivo de evidencia y los Formularios 2–5 asociados. Los incidentes se conservan sin caso asociado. Esta acción no se puede deshacer.</p><form method="post"><?php csrf_field(); ?><div class="form-actions"><a class="button-secondary" href="<?= e(url('/casos/ver?id=' . $case['id'])) ?>">Cancelar</a><button class="button button-danger" type="submit">Eliminar caso</button></div></form></section>
<?php require TESTING_PATH . '/includes/footer.php'; ?>
