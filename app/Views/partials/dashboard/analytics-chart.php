<?php
/**
 * Daily views bar chart plus table for one month. Expects $stats from CardAnalyticsService::month().
 * Bar heights use 20 CSS steps (dash-bar-0..20) so the view needs no inline style.
 */
$max = max(1, ...array_column($stats['days'], 'views'));
$level = static fn (int $views): int => $views === 0 ? 0 : max(1, (int) round($views / $max * 20));
?>
<div class="dash-chart-scroll">
    <div class="dash-chart" role="img" aria-label="<?= esc(lang('Analytics.chartLabel', [$stats['label']]), 'attr') ?>" data-chart>
        <?php foreach ($stats['days'] as $day): ?>
            <div class="dash-chart-day<?= $day['is_today'] ? ' is-today' : '' ?><?= $day['is_future'] ? ' is-future' : '' ?>" data-date="<?= esc($day['date'], 'attr') ?>">
                <span data-value><?= $day['views'] ?></span>
                <div class="dash-chart-bar dash-bar-<?= $level($day['views']) ?>"></div>
                <span class="dash-chart-label"><?= $day['day'] ?></span>
                <span class="dash-chart-note"><?= $day['is_today'] ? esc(lang('Analytics.today')) : '' ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<details class="mt-3">
    <summary><?= esc(lang('Analytics.showTable')) ?></summary>
    <div class="table-responsive">
        <table class="table dash-table mb-0">
            <caption><?= esc(lang('Analytics.tableCaption', [$stats['label']])) ?></caption>
            <thead>
                <tr><th scope="col"><?= esc(lang('Analytics.colDate')) ?></th><th scope="col"><?= esc(lang('Analytics.colViews')) ?></th><th scope="col"><?= esc(lang('Analytics.colUnique')) ?></th><th scope="col"><?= esc(lang('Analytics.colClicks')) ?></th><th scope="col"><?= esc(lang('Analytics.colNote')) ?></th></tr>
            </thead>
            <tbody data-table>
                <?php foreach ($stats['days'] as $day): ?>
                    <tr<?= $day['is_today'] ? ' class="is-today"' : '' ?> data-date="<?= esc($day['date'], 'attr') ?>">
                        <td<?= $day['is_today'] ? ' aria-current="date"' : '' ?>><?= $day['day'] ?> <?= esc($stats['label']) ?></td>
                        <td data-field="views"><?= $day['views'] ?></td>
                        <td data-field="unique_visitors"><?= $day['unique_visitors'] ?></td>
                        <td data-field="contact_clicks"><?= $day['contact_clicks'] ?></td>
                        <td><?= esc($day['is_today'] ? lang('Analytics.today') : ($day['is_future'] ? lang('Analytics.notYet') : '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
