<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('styles') ?>
    <link href="<?= esc(asset_url('dashboard/vendor/simple-datatables/style.min.css'), 'attr') ?>" rel="stylesheet" />
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Accounts.adminTools')) ?></p>
            <h1><?= esc(lang('Dashboard.navAdminTable')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Accounts.adminTableLead')) ?></p>
        </header>

        <section class="dash-panel" aria-labelledby="adminTableTitle">
            <div class="dash-panel-head">
                <h2 id="adminTableTitle"><?= esc(lang('Accounts.adminList')) ?></h2>
                <a class="btn btn-primary" href="<?= esc(base_url('dashboard/add_admin'), 'attr') ?>"><?= esc(lang('Accounts.addAdmin')) ?></a>
            </div>
            <div class="dash-table-wrap">
                <table id="datatablesSimple" class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= esc(lang('Accounts.username')) ?></th>
                            <th scope="col"><?= esc(lang('Accounts.email')) ?></th>
                            <th scope="col"><?= esc(lang('Accounts.registered')) ?></th>
                            <th scope="col"><?= esc(lang('Accounts.role')) ?></th>
                            <th scope="col"><?= esc(lang('Accounts.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= esc($user['username']) ?></td>
                                <td><?= esc($user['email']) ?></td>
                                <td><?= esc($user['registration_date']) ?></td>
                                <td>
                                    <span class="dash-tag dash-tag-neutral"><?= esc($user['access_level']) ?></span>
                                    <?php if (($user['account_status'] ?? 'enabled') === 'suspended'): ?>
                                        <span class="dash-tag dash-tag-warn"><?= esc(lang('Accounts.suspended')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dash-table-actions">
                                        <?php if ((int) $user['id_user'] === $currentUserId): ?>
                                            <a href="<?= esc(base_url('dashboard/setting/' . $encryptedId), 'attr') ?>" class="btn btn-outline-primary btn-sm"><?= esc(lang('Accounts.myAccount')) ?></a>
                                        <?php else: ?>
                                            <a href="<?= esc(base_url('dashboard/admin_table/edit_admin/' . $user['id_user']), 'attr') ?>" class="btn btn-outline-secondary btn-sm"><?= esc(lang('Accounts.edit')) ?></a>
                                            <?php $this->setVar('account', $user)->setVar('returnTo', 'admin_table'); ?><?= $this->include('partials/shared/account-status-action') ?>
                                            <form action="<?= esc(base_url('dashboard/admin_table/delete/' . $user['id_user']), 'attr') ?>" method="post" data-confirm="<?= esc(lang('Accounts.confirmDeleteAdmin', [$user['username']]), 'attr') ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><?= esc(lang('Accounts.delete')) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/vendor/simple-datatables/simple-datatables.min.js'), 'attr') ?>"></script>
    <script src="<?= esc(asset_url('dashboard/js/datatables-simple-demo.js'), 'attr') ?>"></script>
    <script src="<?= esc(asset_url('dashboard/js/user-table.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
