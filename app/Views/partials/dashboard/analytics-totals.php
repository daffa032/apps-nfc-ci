<?php
/**
 * Three monthly totals. Expects $stats from CardAnalyticsService::month().
 */
$items = [
    'views' => [lang('Analytics.views'), lang('Analytics.viewsHint')],
    'unique_visitors' => [lang('Analytics.unique'), lang('Analytics.uniqueHint')],
    'contact_clicks' => [lang('Analytics.clicks'), lang('Analytics.clicksHint')],
];
?>
<div class="dash-stats">
    <?php foreach ($items as $key => [$label, $hint]): ?>
        <div class="dash-panel">
            <span class="text-muted small"><?= esc($label) ?></span>
            <div class="dash-number" data-total="<?= esc($key, 'attr') ?>"><?= number_format($stats['totals'][$key], 0, ',', '.') ?></div>
            <span class="text-muted small"><?= esc($hint) ?></span>
        </div>
    <?php endforeach; ?>
</div>
