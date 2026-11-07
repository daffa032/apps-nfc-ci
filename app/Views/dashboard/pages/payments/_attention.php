<?php
/**
 * Orders that need a person (AD-01). Expects $attention. Empty output when there are none.
 */
?>
        <?php if ($attention !== []): ?>
            <section class="dash-notice dash-notice-warning mb-4" aria-labelledby="payAttentionTitle">
                <h2 id="payAttentionTitle" class="h6 mb-2"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> <?= esc(lang('Billing.attentionTitle', [count($attention)])) ?></h2>
                <ul class="mb-0 ps-3">
                    <?php foreach ($attention as $item): ?>
                        <li><a href="<?= esc(base_url('dashboard/payments/' . rawurlencode($item['order_code'])), 'attr') ?>"><code><?= esc($item['order_code']) ?></code></a> · <?= esc($item['username'] ?? '-') ?> · <?= esc(lang((int) $item['needs_review'] === 1 ? 'Billing.attentionReview' : ($item['status'] === 'paid' ? 'Billing.attentionNotApplied' : 'Billing.attentionStuck'))) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
