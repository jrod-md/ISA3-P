<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$current = require_login(); $case = case_by_id();
require TESTING_PATH . '/includes/case_handler.php';
$title = 'Editar ' . code_case((int) $case['id']); $active = 'casos';
require TESTING_PATH . '/includes/header.php';
require TESTING_PATH . '/includes/case_form.php';
require TESTING_PATH . '/includes/footer.php';
