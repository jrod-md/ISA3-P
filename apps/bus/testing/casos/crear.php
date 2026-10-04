<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$current = require_login(); $case = null;
require TESTING_PATH . '/includes/case_handler.php';
$title = 'Nuevo caso de prueba'; $active = 'casos';
require TESTING_PATH . '/includes/header.php';
require TESTING_PATH . '/includes/case_form.php';
require TESTING_PATH . '/includes/footer.php';
