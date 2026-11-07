<?php
/**
 * Create/edit form for a User or Admin account. Field names match the existing controllers.
 * Expects: $formAction, $formTitle, $submitLabel, $backUrl, $backLabel, $withRole (bool),
 * $account (row or null for create), optional $fieldErrors. Old input wins over $account after a
 * failed submit.
 */
$account ??= null;
$fieldErrors ??= [];
$isEdit = $account !== null;
$role = (string) old('access_level', $account['access_level'] ?? 'Admin', false);
?>
<form id="userForm" class="dash-panel dash-form-wrap" action="<?= esc($formAction, 'attr') ?>" method="post">
    <?= csrf_field() ?>
    <h2><?= esc($formTitle) ?></h2>
    <div class="dash-form-grid">
        <div class="dash-field">
            <label for="inputUsername"><?= esc(lang('Accounts.username')) ?></label>
            <input name="username" class="form-control" id="inputUsername" type="text" maxlength="255" autocomplete="off" value="<?= esc((string) old('username', $account['username'] ?? '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors, 'username', 'inputUsername') ?>>
            <?= $this->setVar('field', 'username')->setVar('inputId', 'inputUsername')->include('partials/feedback/field-error') ?>
        </div>
        <div class="dash-field">
            <label for="inputEmail"><?= esc(lang('Accounts.email')) ?></label>
            <input name="email" class="form-control" id="inputEmail" type="email" maxlength="255" value="<?= esc((string) old('email', $account['email'] ?? '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors, 'email', 'inputEmail') ?>>
            <?= $this->setVar('field', 'email')->setVar('inputId', 'inputEmail')->include('partials/feedback/field-error') ?>
        </div>
    </div>
    <?php if ($withRole): ?>
        <div class="dash-field">
            <label for="adminRole"><?= esc(lang('Accounts.role')) ?></label>
            <select name="access_level" id="adminRole" class="form-select" required<?= field_error_attrs($fieldErrors, 'access_level', 'adminRole') ?>>
                <option value="Admin" <?= $role === 'Admin' ? 'selected' : '' ?>>Admin</option>
                <option value="Super Admin" <?= $role === 'Super Admin' ? 'selected' : '' ?>>Super Admin</option>
            </select>
            <?= $this->setVar('field', 'access_level')->setVar('inputId', 'adminRole')->include('partials/feedback/field-error') ?>
        </div>
    <?php endif; ?>
    <div class="dash-form-grid">
        <div class="dash-field">
            <label for="password"><?= esc(lang($isEdit ? 'Accounts.newPassword' : 'Accounts.password')) ?></label>
            <input name="password" class="form-control" id="password" type="password" minlength="8" autocomplete="new-password"<?= $isEdit ? '' : ' required' ?><?= field_error_attrs($fieldErrors, 'password', 'password', 'passwordHint') ?>>
            <p class="dash-hint" id="passwordHint"><?= esc(lang($isEdit ? 'Accounts.passwordKeep' : 'Accounts.passwordMin')) ?></p>
            <?= $this->setVar('field', 'password')->setVar('inputId', 'password')->include('partials/feedback/field-error') ?>
        </div>
        <div class="dash-field">
            <label for="confirmPassword"><?= esc(lang('Accounts.repeatPassword')) ?></label>
            <input name="repeat_password" class="form-control" id="confirmPassword" type="password" autocomplete="new-password"<?= $isEdit ? '' : ' required' ?><?= field_error_attrs($fieldErrors, 'repeat_password', 'confirmPassword') ?>>
            <?= $this->setVar('field', 'repeat_password')->setVar('inputId', 'confirmPassword')->include('partials/feedback/field-error') ?>
        </div>
    </div>
    <div class="dash-actions">
        <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Accounts.saving'), 'attr') ?>"><?= esc($submitLabel) ?></button>
        <a class="btn btn-outline-secondary" href="<?= esc($backUrl, 'attr') ?>"><?= esc($backLabel) ?></a>
    </div>
</form>
