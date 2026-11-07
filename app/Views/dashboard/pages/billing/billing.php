<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Billing.eyebrow')) ?></p>
            <h1><?= esc(lang($isActive ? 'Billing.titleRenew' : ($neverActivated ? 'Billing.titleActivate' : 'Billing.titleExpired'))) ?></h1>
            <p class="text-muted mb-0">
                <?php if ($isActive): ?>
                    <?= esc(lang('Billing.leadActive')) ?>
                <?php else: ?>
                    <?= esc(lang('Billing.leadInactive')) ?>
                <?php endif; ?>
            </p>
        </header>

        <?php if (!empty($orderNotYours)): ?>
            <div class="dash-notice dash-notice-warning" role="status"><?= esc(lang('Billing.orderNotYours', [$username])) ?></div>
        <?php endif; ?>
        <?php if (!empty($returnedOrder)): ?>
            <?php
            $r = $returnedOrder;
            $key = match (true) {
                (int) $r['needs_review'] === 1 => 'Billing.returnReview',
                $r['status'] === 'paid' => 'Billing.returnPaid',
                $r['status'] === 'refunded' => 'Billing.returnRefunded',
                $r['status'] === 'checking' => 'Billing.returnChecking',
                in_array($r['status'], ['failed', 'cancelled', 'expired'], true) => 'Billing.returnClosed',
                default => 'Billing.returnPending',
            };
            ?>
            <div class="dash-notice<?= $r['status'] === 'paid' ? '' : ' dash-notice-warning' ?>" role="status">
                <?= esc(lang($key, [$r['order_code'], lang('Billing.status_' . $r['status'])])) ?>
                <?php if (in_array($r['status'], ['pending', 'checking', 'creating'], true)): ?>
                    <form class="d-inline" method="post" action="<?= esc(base_url('dashboard/billing/check/' . rawurlencode($r['order_code'])), 'attr') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary ms-2" data-busy-label="<?= esc(lang('Billing.checkStatus'), 'attr') ?>"><?= esc(lang('Billing.checkStatus')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($accessLevel === 'User'): ?>
            <section class="dash-panel mb-4" aria-labelledby="payOnlineTitle">
                <h2 id="payOnlineTitle"><?= esc(lang('Billing.payTitle')) ?></h2>
                <?php if (!$gatewayOn): ?>
                    <p class="text-muted mb-0"><?= esc(lang('Billing.gatewayOff')) ?></p>
                <?php elseif ($offers === []): ?>
                    <p class="text-muted mb-0"><?= esc(lang('Billing.noOffers')) ?></p>
                <?php else: ?>
                    <p class="text-muted"><?= esc(lang('Billing.payLead')) ?></p>
                    <div class="dash-plan-offers">
                        <?php foreach ($offers as $offer): ?>
                            <form class="dash-plan-offer" method="post" action="<?= esc(base_url('dashboard/billing/checkout'), 'attr') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="plan_code" value="<?= esc($offer['code'], 'attr') ?>">
                                <input type="hidden" name="request_key" value="<?= esc($requestKey, 'attr') ?>">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <?php // Own renderer: the shared one would keep $planCode/$planLabel of the last offer for the account's badge below. ?>
                                    <?= \Config\Services::renderer(null, null, false)->setData(['planCode' => $offer['code'], 'planLabel' => $offer['label'], 'badgeText' => null])->render('partials/shared/plan-badge') ?>
                                    <strong class="dash-plan-price"><?= esc(lang('Billing.priceFor', [number_format($offer['price_idr'], 0, ',', '.'), $offer['duration_months']])) ?></strong>
                                </div>
                                <p class="small text-muted mt-2 mb-3"><?= esc(lang(!$isActive ? 'Billing.effectiveNow' : ($planCode === $offer['code'] ? 'Billing.effectiveRenew' : 'Billing.effectiveChange'))) ?></p>
                                <button type="submit" class="btn btn-primary w-100" data-busy-label="<?= esc(lang('Billing.paying'), 'attr') ?>"><?= esc(lang('Billing.payNow', [$offer['label']])) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-muted mt-3 mb-0"><?= esc(lang('Billing.priceFinal')) ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div class="dash-grid">
            <section class="dash-panel" aria-labelledby="billingPlanTitle">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <h2 id="billingPlanTitle" class="mb-0"><?= esc(lang('Billing.currentPlan')) ?></h2>
                    <?php if ($isActive): ?>
                        <span class="dash-tag"><?= esc(lang('Home.active')) ?></span>
                    <?php elseif ($neverActivated): ?>
                        <span class="dash-tag dash-tag-warn"><?= esc(lang('Home.notActive')) ?></span>
                    <?php else: ?>
                        <span class="dash-tag dash-tag-warn"><?= esc(lang('Home.expired')) ?></span>
                    <?php endif; ?>
                </div>
                <p class="dash-expiry"><?= $this->setVar('badgeText', lang('Home.planNamed', [$planLabel]))->include('partials/shared/plan-badge') ?></p>
                <p class="mb-1"><?= esc($expiryLabel) ?></p>
                <p class="small text-muted"><?= esc(lang('Billing.planFeatures')) ?></p>
            </section>

            <section class="dash-panel" aria-labelledby="billingHowTitle">
                <h2 id="billingHowTitle"><?= esc(lang('Billing.manualTitle')) ?></h2>
                <ol class="dash-steps">
                    <li><?= str_replace('{0}', '<strong>' . esc($username) . '</strong>', esc(lang('Billing.step1'))) ?></li>
                    <li><?= esc(lang('Billing.step2')) ?></li>
                    <li><?= esc(lang('Billing.step3')) ?></li>
                </ol>
                <?php if ($transferLines !== []): ?>
                    <div class="dash-transfer">
                        <p class="small fw-semibold mb-1"><?= esc(lang('Billing.transfer')) ?></p>
                        <?php foreach ($transferLines as $line): ?>
                            <p class="small mb-0"><?= esc($line) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php if ($whatsappUrl !== null): ?>
                        <a class="btn btn-primary" href="<?= esc($whatsappUrl, 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc(lang('Billing.contactWhatsapp')) ?></a>
                    <?php endif; ?>
                    <?php if ($emailUrl !== null): ?>
                        <a class="btn btn-outline-secondary" href="<?= esc($emailUrl, 'attr') ?>"><?= esc(lang('Billing.contactEmail')) ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($whatsappUrl === null && $emailUrl === null): ?>
                    <p class="small text-muted mt-3 mb-0"><?= esc(lang('Billing.noContact')) ?></p>
                <?php endif; ?>
            </section>
        </div>

        <?php if ($accessLevel === 'User' && $waitingOrders !== []): ?>
            <section class="dash-panel mt-4" aria-labelledby="billingWaitingTitle">
                <h2 id="billingWaitingTitle"><?= esc(lang('Billing.waitingTitle')) ?></h2>
                <p class="small text-muted"><?= esc(lang('Billing.waitingLead')) ?></p>
                <div class="dash-table-wrap">
                    <table class="table align-middle mb-0">
                        <thead><tr><th scope="col"><?= esc(lang('Billing.colOrder')) ?></th><th scope="col"><?= esc(lang('Billing.colPlan')) ?></th><th scope="col"><?= esc(lang('Billing.colAmount')) ?></th><th scope="col"><?= esc(lang('Billing.colStatus')) ?></th><th scope="col"><?= esc(lang('Billing.expiresAt')) ?></th><th scope="col"><span class="visually-hidden"><?= esc(lang('Accounts.actions')) ?></span></th></tr></thead>
                        <tbody>
                            <?php foreach ($waitingOrders as $order): ?>
                                <tr>
                                    <td><code><?= esc($order['order_code']) ?></code></td>
                                    <td><?= esc(ucfirst($order['plan_code'])) ?> · <?= esc(lang('Master.monthsN', [(int) $order['duration_months']])) ?></td>
                                    <td>Rp<?= esc(number_format((int) $order['amount_idr'], 0, ',', '.')) ?></td>
                                    <td><span class="dash-tag dash-tag-warn"><?= esc(lang('Billing.status_' . $order['status'])) ?></span></td>
                                    <td class="text-nowrap"><?= esc(format_datetime_wib($order['expires_at'])) ?></td>
                                    <td class="text-nowrap">
                                        <?php if ($order['redirect_url'] && in_array((string) parse_url($order['redirect_url'], PHP_URL_HOST), ['app.midtrans.com', 'app.sandbox.midtrans.com'], true)): ?>
                                            <a class="btn btn-sm btn-primary" href="<?= esc($order['redirect_url'], 'attr') ?>"><?= esc(lang('Billing.continuePay')) ?></a>
                                        <?php endif; ?>
                                        <form class="d-inline" method="post" action="<?= esc(base_url('dashboard/billing/cancel/' . rawurlencode($order['order_code'])), 'attr') ?>" data-confirm="<?= esc(lang('Billing.cancelConfirm', [$order['order_code']]), 'attr') ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= esc(lang('Billing.cancelPay')) ?></button>
                                        </form>
                                        <form class="d-inline" method="post" action="<?= esc(base_url('dashboard/billing/check/' . rawurlencode($order['order_code'])), 'attr') ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= esc(lang('Billing.checkStatus')) ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($accessLevel === 'User'): ?>
            <section class="dash-panel mt-4" aria-labelledby="billingHistoryTitle">
                <h2 id="billingHistoryTitle"><?= esc(lang('Billing.historyTitle')) ?></h2>
                <?php if ($orders === []): ?>
                    <p class="text-muted mb-0"><?= esc(lang('Billing.historyEmpty')) ?></p>
                <?php else: ?>
                    <div class="dash-table-wrap">
                        <table class="table align-middle mb-0">
                            <thead><tr><th scope="col"><?= esc(lang('Billing.colOrder')) ?></th><th scope="col"><?= esc(lang('Billing.colPlan')) ?></th><th scope="col"><?= esc(lang('Billing.colAmount')) ?></th><th scope="col"><?= esc(lang('Billing.colPaidAt')) ?></th><th scope="col"><?= esc(lang('Billing.paymentType')) ?></th><th scope="col"><span class="visually-hidden"><?= esc(lang('Billing.receiptTitle')) ?></span></th></tr></thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><code><?= esc($order['order_code']) ?></code><?php if ($order['status'] === 'refunded'): ?> <span class="dash-tag dash-tag-warn"><?= esc(lang('Billing.status_refunded')) ?></span><?php endif; ?></td>
                                        <td><?= esc(ucfirst($order['plan_code'])) ?> · <?= esc(lang('Master.monthsN', [(int) $order['duration_months']])) ?></td>
                                        <td>Rp<?= esc(number_format((int) $order['amount_idr'], 0, ',', '.')) ?></td>
                                        <td class="text-nowrap"><?= esc(format_datetime_wib($order['paid_at'] ?? $order['created_at'])) ?></td>
                                        <td><?= esc(lang('Billing.method_' . (in_array($order['payment_type'], ['credit_card', 'qris', 'gopay', 'bank_transfer', 'echannel', 'shopeepay', 'cstore'], true) ? $order['payment_type'] : 'other'))) ?></td>
                                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= esc(base_url('dashboard/billing/receipt/' . rawurlencode($order['order_code'])), 'attr') ?>"><i class="fas fa-receipt" aria-hidden="true"></i> <?= esc(lang('Billing.viewReceipt')) ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/busy-submit.js'), 'attr') ?>"></script>
    <script src="<?= esc(asset_url('dashboard/js/user-table.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
