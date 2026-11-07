<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Settings.eyebrow')) ?></p>
            <h1><?= esc(lang('Settings.title')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Settings.lead')) ?></p>
        </header>

        <div class="dash-settings-grid">
            <section class="dash-panel" aria-labelledby="accountSummaryTitle">
                <h2 id="accountSummaryTitle"><?= esc(lang('Settings.summary')) ?></h2>
                <div class="dash-list-row"><span><?= esc(lang('Settings.username')) ?></span><strong><?= esc($user['username']) ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Settings.role')) ?></span><strong><?= esc($accessLevel) ?></strong></div>
                <?php if ($accessLevel === 'User'): ?>
                    <div class="dash-list-row"><span><?= esc(lang('Settings.plan')) ?></span><?= $this->setVar('badgeText', null)->include('partials/shared/plan-badge') ?></div>
                    <div class="dash-list-row">
                        <span><?= esc(lang('Settings.status')) ?></span>
                        <?php if ($cardActive): ?>
                            <span class="dash-tag"><?= esc(lang('Settings.active')) ?></span>
                        <?php else: ?>
                            <span class="dash-tag dash-tag-warn"><?= esc(lang('Settings.inactive')) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="dash-list-row"><span><?= esc(lang('Settings.validity')) ?></span><strong><?= esc($expiryLabel ?? '-') ?></strong></div>
                    <div class="dash-actions mt-3">
                        <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/billing'), 'attr') ?>"><?= esc(lang('Settings.managePlan')) ?></a>
                        <?php if ($cardActive): ?>
                            <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/personal_info_page'), 'attr') ?>"><?= esc(lang('Settings.editCard')) ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <form id="userForm" class="dash-panel" action="<?= esc(base_url('dashboard/setting/update/' . $encryptedId), 'attr') ?>" method="post">
                <?= csrf_field() ?>
                <h2><?= esc(lang('Settings.formTitle')) ?></h2>
                <div class="dash-field">
                    <label for="inputUsername"><?= esc(lang('Settings.username')) ?></label>
                    <input class="form-control" id="inputUsername" type="text" value="<?= esc($user['username'], 'attr') ?>" readonly aria-describedby="usernameHint">
                    <p class="dash-hint" id="usernameHint"><?= esc(lang('Settings.usernameHint')) ?></p>
                </div>
                <div class="dash-field">
                    <label for="inputEmail"><?= esc(lang('Settings.email')) ?></label>
                    <input name="email" class="form-control" id="inputEmail" type="email" autocomplete="email" value="<?= esc((string) old('email', $user['email'], false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'email', 'inputEmail') ?>>
                    <?= $this->setVar('field', 'email')->setVar('inputId', 'inputEmail')->include('partials/feedback/field-error') ?>
                    <p class="dash-hint"><?= esc(lang('Settings.emailHint')) ?></p>
                </div>
                <div class="dash-field">
                    <label for="currentPassword"><?= esc(lang('Settings.currentPassword')) ?></label>
                    <input name="current_password" class="form-control" id="currentPassword" type="password" autocomplete="current-password"<?= field_error_attrs($fieldErrors ?? [], 'current_password', 'currentPassword', 'currentHint') ?>>
                    <p class="dash-hint" id="currentHint"><?= esc(lang('Settings.currentHint')) ?></p>
                    <?= $this->setVar('field', 'current_password')->setVar('inputId', 'currentPassword')->include('partials/feedback/field-error') ?>
                </div>
                <div class="dash-form-grid">
                    <div class="dash-field">
                        <label for="password"><?= esc(lang('Settings.newPassword')) ?></label>
                        <input name="password" class="form-control" id="password" type="password" minlength="8" autocomplete="new-password"<?= field_error_attrs($fieldErrors ?? [], 'password', 'password') ?>>
                        <?= $this->setVar('field', 'password')->setVar('inputId', 'password')->include('partials/feedback/field-error') ?>
                    </div>
                    <div class="dash-field">
                        <label for="confirmPassword"><?= esc(lang('Settings.repeatNew')) ?></label>
                        <input name="repeat_password" class="form-control" id="confirmPassword" type="password" autocomplete="new-password"<?= field_error_attrs($fieldErrors ?? [], 'repeat_password', 'confirmPassword') ?>>
                        <?= $this->setVar('field', 'repeat_password')->setVar('inputId', 'confirmPassword')->include('partials/feedback/field-error') ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Settings.saving'), 'attr') ?>"><?= esc(lang('Settings.save')) ?></button>
            </form>
        </div>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/busy-submit.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
