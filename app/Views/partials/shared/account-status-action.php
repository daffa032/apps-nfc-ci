<?php
/**
 * Suspend / restore button for one account row. Super Admin only; the route re-checks the role.
 * Expects $account (row with id_user, account_status), $currentUserId and $returnTo
 * ('user_table' or 'admin_table').
 */
$isSuspended = ($account['account_status'] ?? 'enabled') === 'suspended';
if ((int) $account['id_user'] === (int) $currentUserId) {
    return;
}
?>
<form action="<?= esc(base_url('dashboard/accounts/' . $account['id_user'] . ($isSuspended ? '/restore' : '/suspend')), 'attr') ?>" method="post" class="d-inline"
      data-confirm="<?= esc(lang($isSuspended ? 'Accounts.confirmRestore' : 'Accounts.confirmSuspend'), 'attr') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= esc($returnTo, 'attr') ?>">
    <button type="submit" class="btn btn-sm <?= $isSuspended ? 'btn-outline-success' : 'btn-outline-danger' ?>"><?= esc(lang($isSuspended ? 'Accounts.restore' : 'Accounts.suspend')) ?></button>
</form>
