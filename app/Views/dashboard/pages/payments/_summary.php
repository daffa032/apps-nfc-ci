<?php
/**
 * Payments summary cards (paid this month, waiting, needs review). Expects $summary. Rendered by
 * payments.php and by PaymentAdminController::live() for the live refresh.
 */
?>
        <div class="dash-stats mb-4">
            <div class="dash-panel">
                <span class="text-muted small"><?= esc(lang('Billing.sumPaid')) ?></span>
                <div class="dash-number">Rp<?= esc(number_format($summary['paidGross'], 0, ',', '.')) ?></div>
                <span class="text-muted small"><?= esc(lang('Billing.sumPaidHint', [$summary['paidCount']])) ?></span>
            </div>
            <div class="dash-panel">
                <span class="text-muted small"><?= esc(lang('Billing.sumWaiting')) ?></span>
                <div class="dash-number"><?= (int) $summary['waiting'] ?></div>
            </div>
            <div class="dash-panel">
                <span class="text-muted small"><?= esc(lang('Billing.sumReview')) ?></span>
                <div class="dash-number"><?= (int) $summary['review'] ?></div>
            </div>
        </div>
