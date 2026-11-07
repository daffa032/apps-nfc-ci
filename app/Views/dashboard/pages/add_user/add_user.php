<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Accounts.addUserEyebrow')) ?></p>
            <h1><?= esc(lang('Accounts.addUserTitle')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Accounts.addUserLead')) ?></p>
        </header>

        <?php
        $this->setVar('formAction', base_url('dashboard/save_user'))
            ->setVar('formTitle', lang('Accounts.accountData'))
            ->setVar('submitLabel', lang('Accounts.saveUser'))
            ->setVar('backUrl', base_url('dashboard/user_table'))
            ->setVar('backLabel', lang('Accounts.backUsers'))
            ->setVar('withRole', false)
            ->setVar('account', null);
        ?>
        <?= $this->include('partials/shared/account-form') ?>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/busy-submit.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
