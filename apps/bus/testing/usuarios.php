<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();
$users = db()->query('SELECT u.id, u.nombre, u.correo, u.rol, u.creado_en, COUNT(c.id) AS casos FROM usuarios u LEFT JOIN casos_prueba c ON c.usuario_id = u.id GROUP BY u.id, u.nombre, u.correo, u.rol, u.creado_en ORDER BY u.id')->fetchAll();
$title = 'Usuarios'; $active = 'usuarios'; require TESTING_PATH . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">ADMINISTRACIÓN</span><h1>Usuarios registrados</h1><p class="muted">Consulta las cuentas y la participación del equipo.</p></div><span class="count-label"><?= count($users) ?> cuentas</span></div>
<section class="panel"><div class="table-scroll"><table><caption class="sr-only">Usuarios registrados</caption><thead><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Rol</th><th>Casos</th><th>Registro</th></tr></thead><tbody>
<?php foreach ($users as $account): ?><tr><td><?= (int) $account['id'] ?></td><td><?= e($account['nombre']) ?></td><td><?= e($account['correo']) ?></td><td><span class="badge <?= $account['rol'] === 'admin' ? 'badge-admin' : 'badge-pending' ?>"><?= $account['rol'] === 'admin' ? 'Administrador' : 'Tester' ?></span></td><td><?= (int) $account['casos'] ?></td><td><?= e(date_display($account['creado_en'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require TESTING_PATH . '/includes/footer.php'; ?>
