<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Accounts.editAdminEyebrow')) ?></p>
            <h1><?= esc(lang('Accounts.editAdminTitle')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Accounts.editAdminLead')) ?></p>
        </header>

        <?php
        $this->setVar('formAction', base_url('dashboard/admin_table/update_admin/' . $user['id_user']))
            ->setVar('formTitle', lang('Accounts.adminData'))
            ->setVar('submitLabel', lang('Accounts.saveChanges'))
            ->setVar('backUrl', base_url('dashboard/admin_table'))
            ->setVar('backLabel', lang('Accounts.backAdmins'))
            ->setVar('withRole', true)
            ->setVar('account', $user);
        ?>
        <?= $this->include('partials/shared/account-form') ?>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/busy-submit.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
