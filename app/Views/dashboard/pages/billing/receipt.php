<?php
/**
 * Payment receipt for one of the owner's paid orders (PRD BI-02). Expects $order (owned, paid),
 * $period (the subscription it added, or null) and $username. Printable: the dashboard chrome is
 * hidden in print by app.css, so "Cetak / simpan PDF" gives a clean page.
 */
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <div class="dash-receipt-actions">
            <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/billing'), 'attr') ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i> <?= esc(lang('Billing.backToBilling')) ?></a>
            <button type="button" class="btn btn-primary" data-print><i class="fas fa-print" aria-hidden="true"></i> <?= esc(lang('Billing.printReceipt')) ?></button>
        </div>

        <article class="dash-panel dash-receipt" aria-labelledby="receiptTitle">
            <header class="dash-receipt-head">
                <div>
                    <p class="dash-eyebrow mb-1"><?= esc(lang('Billing.receiptEyebrow')) ?></p>
                    <h1 id="receiptTitle"><?= esc(lang('Billing.receiptTitle')) ?></h1>
                    <p class="text-muted mb-0"><code><?= esc($order['order_code']) ?></code></p>
                </div>
                <?php if ($order['status'] === 'refunded'): ?>
                    <span class="dash-receipt-stamp dash-receipt-stamp-refunded"><i class="fas fa-rotate-left" aria-hidden="true"></i> <?= esc(lang('Billing.status_refunded')) ?></span>
                <?php else: ?>
                    <span class="dash-receipt-stamp"><i class="fas fa-circle-check" aria-hidden="true"></i> <?= esc(lang('Billing.status_paid')) ?></span>
                <?php endif; ?>
            </header>

            <dl class="dash-receipt-list">
                <div><dt><?= esc(lang('Billing.receiptAccount')) ?></dt><dd><?= esc($username) ?></dd></div>
                <div><dt><?= esc(lang('Billing.colPlan')) ?></dt><dd><?= esc(ucfirst($order['plan_code'])) ?> · <?= esc(lang('Master.monthsN', [(int) $order['duration_months']])) ?></dd></div>
                <div><dt><?= esc(lang('Billing.colPaidAt')) ?></dt><dd><?= esc(format_datetime_wib($order['paid_at'] ?? $order['created_at'])) ?></dd></div>
                <div><dt><?= esc(lang('Billing.paymentType')) ?></dt><dd><?= esc(lang('Billing.method_' . (in_array($order['payment_type'], ['credit_card', 'qris', 'gopay', 'bank_transfer', 'echannel', 'shopeepay', 'cstore'], true) ? $order['payment_type'] : 'other'))) ?></dd></div>
                <?php if ($period !== null): ?>
                    <div><dt><?= esc(lang('Billing.receiptPeriod')) ?></dt><dd><?= esc(format_date_id(new DateTimeImmutable($period['starts_at'], new DateTimeZone('UTC')))) ?> – <?= esc(format_date_id(subscription_last_day($period['ends_at']))) ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($order['gateway_transaction_id'])): ?>
                    <div><dt><?= esc(lang('Billing.receiptGatewayId')) ?></dt><dd><code><?= esc($order['gateway_transaction_id']) ?></code></dd></div>
                <?php endif; ?>
            </dl>

            <div class="dash-receipt-total">
                <span><?= esc(lang('Billing.receiptTotal')) ?></span>
                <strong>Rp<?= esc(number_format((int) $order['amount_idr'], 0, ',', '.')) ?></strong>
            </div>
            <p class="small text-muted mb-0"><?= esc(lang($order['status'] === 'refunded' ? 'Billing.receiptNoteRefunded' : 'Billing.receiptNote')) ?></p>
        </article>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/receipt.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
