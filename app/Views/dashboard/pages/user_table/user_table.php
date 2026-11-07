<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('styles') ?>
    <link href="<?= esc(asset_url('dashboard/vendor/simple-datatables/style.min.css'), 'attr') ?>" rel="stylesheet" />
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Accounts.adminTools')) ?></p>
            <h1><?= esc(lang('Dashboard.navUserTable')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Accounts.userTableLead')) ?></p>
        </header>

        <section class="dash-panel" aria-labelledby="userTableTitle">
            <div class="dash-panel-head">
                <h2 id="userTableTitle"><?= esc(lang('Accounts.userData')) ?></h2>
                <a class="btn btn-primary" href="<?= esc(base_url('dashboard/add_user'), 'attr') ?>"><?= esc(lang('Accounts.addUser')) ?></a>
            </div>
            <div class="dash-table-wrap">
                <table id="datatablesSimple" class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= esc(lang('Accounts.username')) ?></th>
                            <th scope="col"><?= esc(lang('Accounts.cardLink')) ?></th>
                            <th scope="col"><?= esc(lang('Accounts.email')) ?></th>
                            <th scope="col"><?= esc(lang('Accounts.registered')) ?></th>
                            <th scope="col"><?= esc(lang('Accounts.activeUntil')) ?></th>
                            <th scope="col"><?= esc(lang('Accounts.status')) ?></th>
                            <?php if ($accessLevel === 'Super Admin'): ?>
                                <th scope="col"><?= esc(lang('Accounts.validity')) ?></th>
                            <?php endif; ?>
                            <th scope="col"><?= esc(lang('Accounts.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user):
                            // Status from subscriptions: no period at all, ended, or active (end is exclusive, UTC).
                            $lastDay = subscription_last_day($user['ends_at'] ?? null);
                            $neverActive = $lastDay === null;
                            $isExpired = !$neverActive && $user['ends_at'] <= $nowUtc;
                            $suspended = ($user['account_status'] ?? 'enabled') === 'suspended';
                        ?>
                            <tr>
                                <td><?= esc($user['username']) ?></td>
                                <td>
                                    <?php $cardUrl = base_url(rawurlencode((string) $user['username'])); ?>
                                    <a class="text-break" href="<?= esc($cardUrl, 'attr') ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= esc(lang('Accounts.openCardFor', [$user['username']]), 'attr') ?>"><?= esc($cardUrl) ?></a>
                                </td>
                                <td><?= esc($user['email']) ?></td>
                                <td><?= esc($user['registration_date']) ?></td>
                                <td><?= esc($neverActive ? '-' : $lastDay->format('Y-m-d')) ?></td>
                                <td>
                                    <?php if ($suspended): ?>
                                        <span class="dash-tag dash-tag-warn"><?= esc(lang('Accounts.suspended')) ?></span>
                                    <?php elseif ($neverActive): ?>
                                        <span class="dash-tag dash-tag-neutral"><?= esc(lang('Accounts.notActive')) ?></span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="dash-tag dash-tag-warn"><?= esc(lang('Accounts.ended')) ?></span>
                                    <?php else: ?>
                                        <span class="dash-tag"><?= esc(lang('Accounts.active')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($accessLevel === 'Super Admin'): ?>
                                    <td>
                                        <div class="dash-table-actions">
                                            <?php if ($neverActive || $isExpired): ?>
                                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#activateModal" data-id="<?= esc((string) $user['id_user'], 'attr') ?>"><?= esc(lang('Accounts.activate')) ?></button>
                                            <?php else: ?>
                                                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addActivateModal" data-id="<?= esc((string) $user['id_user'], 'attr') ?>"><?= esc(lang('Accounts.extend')) ?></button>
                                                <form action="<?= esc(base_url('dashboard/user_table/delete_access/' . $user['id_user']), 'attr') ?>" method="post" data-confirm="<?= esc(lang('Accounts.confirmRevoke'), 'attr') ?>">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-outline-danger btn-sm"><?= esc(lang('Accounts.revoke')) ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <div class="dash-table-actions">
                                        <a href="<?= esc(base_url('dashboard/user_table/edit_user/' . $user['id_user']), 'attr') ?>" class="btn btn-outline-secondary btn-sm"><?= esc(lang('Accounts.edit')) ?></a>
                                        <?php if ($accessLevel === 'Super Admin'): ?>
                                            <?php $this->setVar('account', $user)->setVar('returnTo', 'user_table'); ?><?= $this->include('partials/shared/account-status-action') ?>
                                        <?php endif; ?>
                                        <form action="<?= esc(base_url('dashboard/user_table/delete/' . $user['id_user']), 'attr') ?>" method="post" data-confirm="<?= esc(lang('Accounts.confirmDeleteUser', [$user['username']]), 'attr') ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><?= esc(lang('Accounts.delete')) ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <?php foreach (['activateModal' => ['dashboard/user_table/activate', lang('Accounts.activateTitle'), 'activateUserId', 'expirationDate', lang('Accounts.activate')], 'addActivateModal' => ['dashboard/user_table/add_activate_user', lang('Accounts.extendTitle'), 'addActivateUserId', 'addExpirationDate', lang('Accounts.extend')]] as $modalId => [$action, $title, $idField, $selectId, $submit]): ?>
        <div class="modal fade" id="<?= esc($modalId, 'attr') ?>" tabindex="-1" aria-labelledby="<?= esc($modalId, 'attr') ?>Label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= esc(base_url($action), 'attr') ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="modal-header">
                            <h5 class="modal-title" id="<?= esc($modalId, 'attr') ?>Label"><?= esc($title) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('Accounts.close'), 'attr') ?>"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id_user" id="<?= esc($idField, 'attr') ?>">
                            <div class="dash-field mb-0">
                                <label for="<?= esc($selectId, 'attr') ?>"><?= esc(lang('Accounts.duration')) ?></label>
                                <select class="form-select" name="expiration_date" id="<?= esc($selectId, 'attr') ?>">
                                    <option value="+1 month"><?= esc(lang('Accounts.month1')) ?></option>
                                    <option value="+3 months"><?= esc(lang('Accounts.months3')) ?></option>
                                    <option value="+6 months"><?= esc(lang('Accounts.months6')) ?></option>
                                    <option value="+1 year"><?= esc(lang('Accounts.year1')) ?></option>
                                </select>
                                <p class="dash-hint"><?= esc(lang($modalId === 'activateModal' ? 'Accounts.startsToday' : 'Accounts.addsAfter')) ?></p>
                            </div>
                            <div class="dash-field mt-3 mb-0">
                                <label for="<?= esc($selectId, 'attr') ?>Reason"><?= esc(lang('Accounts.reason')) ?></label>
                                <input class="form-control" type="text" name="reason" id="<?= esc($selectId, 'attr') ?>Reason" minlength="3" maxlength="255" required aria-describedby="<?= esc($selectId, 'attr') ?>ReasonHint">
                                <p class="dash-hint" id="<?= esc($selectId, 'attr') ?>ReasonHint"><?= esc(lang('Accounts.reasonHint')) ?></p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= esc(lang('Accounts.cancel')) ?></button>
                            <button type="submit" class="btn btn-primary"><?= esc($submit) ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/vendor/simple-datatables/simple-datatables.min.js'), 'attr') ?>"></script>
    <script src="<?= esc(asset_url('dashboard/js/datatables-simple-demo.js'), 'attr') ?>"></script>
    <script src="<?= esc(asset_url('dashboard/js/user-table.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
