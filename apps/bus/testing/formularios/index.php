<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login(); $title = 'Formularios'; $active = 'formularios';
require TESTING_PATH . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">GESTIÓN DE PRUEBAS</span><h1>Formularios</h1><p class="muted">Accede a los casos, su documentación, el plan del proyecto y sus evaluaciones.</p></div><span class="count-label">10 de 10 disponibles</span></div>
<section class="panel"><div class="form-summary"><?php require TESTING_PATH . '/includes/form_summary.php'; ?></div></section>
<?php require TESTING_PATH . '/includes/footer.php'; ?>
