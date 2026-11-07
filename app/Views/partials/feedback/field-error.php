<?php
/**
 * Message under one input (PRD FB-02). Expects $fieldErrors (field => message), $field and $inputId.
 * The id matches field_error_attrs() so the input's aria-describedby points here.
 */
$fieldErrors ??= [];
?>
<?php if (isset($fieldErrors[$field])): ?>
    <p class="dash-field-error" id="<?= esc($inputId . 'Error', 'attr') ?>"><?= esc($fieldErrors[$field]) ?></p>
<?php endif; ?>
