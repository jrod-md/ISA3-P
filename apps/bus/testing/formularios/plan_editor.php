<?php use Marketplace\Bus\Repository\TestPlanRepository; ?>
<p class="form-note">Todos los campos son obligatorios. Responsable es la persona indicada en el plan; la cuenta creadora se registra por separado.</p>
<form method="post" class="case-form" id="test-plan-form"><?php csrf_field(); ?>
<?php $sectionNumber = 0; foreach (TestPlanRepository::SECTIONS as $section => $fields): ?>
<?php if ($section === 'Cierre'): require TESTING_PATH . '/formularios/plan_cronograma.php'; $sectionNumber++; endif; $sectionNumber++; ?>
<section class="panel form-section"><div class="form-section-title"><span><?= str_pad((string) $sectionNumber, 2, '0', STR_PAD_LEFT) ?></span><div><h2><?= e($section) ?></h2></div></div>
<?php if ($section === 'Información general'): ?><div class="field-grid"><?php foreach ($fields as $field): ?><div class="field"><label for="<?= e($field) ?>"><?= e(TestPlanRepository::FIELDS[$field]) ?> *</label><input id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === 'fecha' ? 'date' : 'text' ?>" <?= $field === 'fecha' ? 'min="1000-01-01" max="9999-12-31"' : 'maxlength="' . TestPlanRepository::limit($field) . '"' ?> required value="<?= e($values[$field]) ?>"></div><?php endforeach; ?></div>
<?php else: foreach ($fields as $field): ?><div class="field"><label for="<?= e($field) ?>"><?= e(TestPlanRepository::FIELDS[$field]) ?> *</label><textarea id="<?= e($field) ?>" name="<?= e($field) ?>" rows="4" maxlength="<?= TestPlanRepository::limit($field) ?>" required><?= e($values[$field]) ?></textarea></div><?php endforeach; endif; ?></section>
<?php endforeach; ?>
<div class="form-actions"><a class="button-secondary" href="<?= e(url($returnPath)) ?>">Cancelar</a><button class="button" type="submit"><?= $plan ? 'Guardar cambios' : 'Registrar plan' ?></button></div></form>
<script defer src="<?= e(url('/assets/test-plan.js')) ?>"></script>
