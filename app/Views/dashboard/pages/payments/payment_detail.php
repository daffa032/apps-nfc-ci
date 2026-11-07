<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Billing.adminTitle')) ?></p>
            <h1><?= esc(lang('Billing.detailTitle', [$order['order_code']])) ?></h1>
            <p class="text-muted mb-0"><a href="<?= esc(base_url('dashboard/payments'), 'attr') ?>"><?= esc(lang('Billing.backToList')) ?></a></p>
        </header>

        <div class="dash-settings-grid">
            <section class="dash-panel" aria-labelledby="payOrderTitle">
                <h2 id="payOrderTitle"><?= esc(lang('Billing.snapshot')) ?></h2>
                <div class="dash-list-row"><span><?= esc(lang('Billing.colUser')) ?></span><strong><?= esc($buyer) ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.colPlan')) ?></span><strong><?= esc(ucfirst($order['plan_code'])) ?> · <?= esc(lang('Master.monthsN', [(int) $order['duration_months']])) ?> (<?= esc(lang('Billing.versionOf', [(int) $order['plan_version']])) ?>)</strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.colAmount')) ?></span><strong>Rp<?= esc(number_format((int) $order['amount_idr'], 0, ',', '.')) ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.colStatus')) ?></span><strong><?= esc(lang('Billing.status_' . $order['status'])) ?><?= (int) $order['needs_review'] === 1 ? ' · ' . esc(lang('Billing.review')) : '' ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.gatewayStatus')) ?></span><strong><?= esc($order['gateway_status'] ?? '—') ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.paymentType')) ?></span><strong><?= esc($order['payment_type'] ?? '—') ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.colDate')) ?></span><strong><?= esc(format_datetime_wib($order['created_at'])) ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.expiresAt')) ?></span><strong><?= esc(format_datetime_wib($order['expires_at'])) ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.colPaidAt')) ?></span><strong><?= $order['paid_at'] ? esc(format_datetime_wib($order['paid_at'])) : '—' ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.appliedAt')) ?></span><strong><?= $order['applied_at'] ? esc(format_datetime_wib($order['applied_at'])) : '—' ?></strong></div>
                <div class="dash-list-row"><span><?= esc(lang('Billing.colChecked')) ?></span><strong><?= $order['last_checked_at'] ? esc(format_datetime_wib($order['last_checked_at'])) : '—' ?></strong></div>
                <?php if ($order['reviewed_at']): ?>
                    <div class="dash-list-row"><span><?= esc(lang('Billing.reviewedBy')) ?></span><strong><?= esc($reviewer ?? '—') ?> · <?= esc(format_datetime_wib($order['reviewed_at'])) ?></strong></div>
                    <div class="dash-list-row"><span><?= esc(lang('Billing.reviewAction')) ?></span><strong><?= esc(lang('Billing.resolveAction_' . $order['review_action'])) ?> — <?= esc($order['review_note'] ?? '') ?></strong></div>
                <?php endif; ?>
                <form class="mt-3" method="post" action="<?= esc(base_url('dashboard/payments/' . rawurlencode($order['order_code']) . '/check'), 'attr') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-primary" data-busy-label="<?= esc(lang('Billing.checkNow'), 'attr') ?>"><?= esc(lang('Billing.checkNow')) ?></button>
                </form>
            </section>

            <?php if ((int) $order['needs_review'] === 1): ?>
                <section class="dash-panel dash-resolve" aria-labelledby="payResolveTitle">
                    <h2 id="payResolveTitle"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> <?= esc(lang('Billing.resolveTitle')) ?></h2>
                    <p class="dash-hint mt-0"><?= esc(lang(match (true) {
                        $order['status'] === 'refunded' => 'Billing.reviewWhyRefund',
                        $order['status'] === 'paid' && $order['applied_at'] === null => 'Billing.reviewWhyPaidNoAccess',
                        default => 'Billing.reviewWhyAmount',
                    })) ?></p>
                    <?php if ($canResolve): ?>
                        <form method="post" action="<?= esc(base_url('dashboard/payments/' . rawurlencode($order['order_code']) . '/resolve'), 'attr') ?>">
                            <?= csrf_field() ?>
                            <fieldset class="dash-field">
                                <legend class="form-label"><?= esc(lang('Billing.resolveChoose')) ?></legend>
                                <?php foreach (['grant', 'revoke', 'dismiss'] as $i => $action):
                                    $allowed = match ($action) {
                                        'grant' => $order['status'] === 'paid' && $order['applied_at'] === null,
                                        'revoke' => $order['subscription_id'] !== null,
                                        default => true,
                                    }; ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="action" id="resolve<?= esc($action, 'attr') ?>" value="<?= esc($action, 'attr') ?>" required<?= $allowed ? '' : ' disabled' ?>>
                                        <label class="form-check-label" for="resolve<?= esc($action, 'attr') ?>"><strong><?= esc(lang('Billing.resolveAction_' . $action)) ?></strong> <span class="text-muted small">— <?= esc(lang('Billing.resolveHelp_' . $action)) ?></span></label>
                                    </div>
                                <?php endforeach; ?>
                            </fieldset>
                            <div class="dash-field">
                                <label for="resolveNote"><?= esc(lang('Billing.resolveNote')) ?></label>
                                <textarea class="form-control" id="resolveNote" name="note" rows="2" minlength="5" maxlength="255" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Master.saving'), 'attr') ?>"><?= esc(lang('Billing.resolveSubmit')) ?></button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted mb-0"><?= esc(lang('Billing.resolveSuperOnly')) ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="dash-panel" aria-labelledby="payEventsTitle">
                <h2 id="payEventsTitle"><?= esc(lang('Billing.events')) ?></h2>
                <?php if ($events === []): ?>
                    <p class="text-muted mb-0"><?= esc(lang('Billing.eventsEmpty')) ?></p>
                <?php else: ?>
                    <div class="dash-table-wrap">
                        <table class="table align-middle mb-0">
                            <thead><tr><th scope="col"><?= esc(lang('Audit.colTime')) ?></th><th scope="col"><?= esc(lang('Billing.colSource')) ?></th><th scope="col"><?= esc(lang('Billing.gatewayStatus')) ?></th><th scope="col"><?= esc(lang('Billing.colOutcome')) ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= esc(format_datetime_wib($event['received_at'])) ?></td>
                                        <td><?= esc($event['source']) ?></td>
                                        <td><?= esc($event['transaction_status']) ?><?= $event['fraud_status'] ? ' / ' . esc($event['fraud_status']) : '' ?></td>
                                        <td><?= esc($event['outcome']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/busy-submit.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
