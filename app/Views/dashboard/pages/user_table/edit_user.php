<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Accounts.editUserEyebrow')) ?></p>
            <h1><?= esc(lang('Accounts.editUserTitle')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Accounts.editUserLead')) ?></p>
        </header>

        <?php
        $this->setVar('formAction', base_url('dashboard/user_table/update_user/' . $user['id_user']))
            ->setVar('formTitle', lang('Accounts.accountData'))
            ->setVar('submitLabel', lang('Accounts.saveChanges'))
            ->setVar('backUrl', base_url('dashboard/user_table'))
            ->setVar('backLabel', lang('Accounts.backUsers'))
            ->setVar('withRole', false)
            ->setVar('account', $user);
        ?>
        <?= $this->include('partials/shared/account-form') ?>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/busy-submit.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
