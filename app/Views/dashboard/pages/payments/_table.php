<?php
/**
 * Payments table with paging for the current filters. Expects $rows, $total, $page, $pages and
 * $filters. Rendered by payments.php and by PaymentAdminController::live().
 */
$query ??= static fn (array $changes) => '?' . http_build_query(array_filter(array_merge($filters, $changes), static fn ($value) => $value !== '' && $value !== null));
?>
        <section class="dash-panel" aria-labelledby="payTableTitle">
            <div class="dash-panel-head">
                <h2 id="payTableTitle"><?= esc(lang('Billing.adminTitle')) ?></h2>
                <span class="text-muted small"><?= esc(lang('Audit.records', [number_format($total, 0, ',', '.')])) ?></span>
            </div>
            <?php if ($rows === []): ?>
                <p class="text-muted mb-0"><?= esc(lang('Billing.noPayments')) ?></p>
            <?php else: ?>
                <div class="dash-table-wrap">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col"><?= esc(lang('Billing.colOrder')) ?></th>
                                <th scope="col"><?= esc(lang('Billing.colUser')) ?></th>
                                <th scope="col"><?= esc(lang('Billing.colPlan')) ?></th>
                                <th scope="col"><?= esc(lang('Billing.colAmount')) ?></th>
                                <th scope="col"><?= esc(lang('Billing.colStatus')) ?></th>
                                <th scope="col"><?= esc(lang('Billing.colDate')) ?></th>
                                <th scope="col"><?= esc(lang('Billing.colChecked')) ?></th>
                                <th scope="col"><span class="visually-hidden"><?= esc(lang('Accounts.actions')) ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><code><?= esc($row['order_code']) ?></code></td>
                                    <td><?= esc($row['username'] ?? ('#' . $row['user_id'])) ?></td>
                                    <td><?= esc(ucfirst($row['plan_code'])) ?> · <?= esc(lang('Master.monthsN', [(int) $row['duration_months']])) ?></td>
                                    <td>Rp<?= esc(number_format((int) $row['amount_idr'], 0, ',', '.')) ?></td>
                                    <td>
                                        <span class="dash-tag<?= $row['status'] === 'paid' ? '' : ' dash-tag-neutral' ?>"><?= esc(lang('Billing.status_' . $row['status'])) ?></span>
                                        <?php if ((int) $row['needs_review'] === 1): ?><span class="dash-tag dash-tag-warn"><?= esc(lang('Billing.review')) ?></span><?php endif; ?>
                                    </td>
                                    <td class="text-nowrap"><?= esc(format_datetime_wib($row['created_at'])) ?></td>
                                    <td class="text-nowrap small"><?= $row['last_checked_at'] ? esc(format_datetime_wib($row['last_checked_at'])) : '—' ?></td>
                                    <td><a class="btn btn-sm btn-outline-secondary" href="<?= esc(base_url('dashboard/payments/' . rawurlencode($row['order_code'])), 'attr') ?>"><?= esc(lang('Billing.detail')) ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pages > 1): ?>
                    <nav class="dash-actions mt-3" aria-label="<?= esc(lang('Audit.pages'), 'attr') ?>">
                        <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/payments') . $query(['page' => $page - 1]), 'attr') ?>"><?= esc(lang('Audit.previous')) ?></a><?php endif; ?>
                        <span class="align-self-center small text-muted"><?= esc(lang('Audit.pageOf', [$page, $pages])) ?></span>
                        <?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/payments') . $query(['page' => $page + 1]), 'attr') ?>"><?= esc(lang('Audit.next')) ?></a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
