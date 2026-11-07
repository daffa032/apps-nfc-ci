<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <?php if (!$isPro): ?>
            <header class="dash-page-head">
                <p class="dash-eyebrow"><?= esc(lang('Analytics.eyebrow')) ?></p>
                <h1><?= esc(lang('Analytics.upsellTitle')) ?></h1>
                <p class="text-muted mb-0"><?= esc(lang('Analytics.upsellLead')) ?></p>
            </header>
            <section class="dash-panel" aria-labelledby="statsUpsellTitle">
                <h2 id="statsUpsellTitle"><?= esc(lang('Analytics.upsellHeading')) ?></h2>
                <p class="text-muted"><?= esc(lang('Analytics.upsellBody')) ?></p>
                <?php if ($accessLevel === 'User'): ?>
                    <a class="btn btn-primary" href="<?= esc(base_url('dashboard/billing'), 'attr') ?>"><?= esc(lang('Analytics.seePro')) ?></a>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <header class="dash-page-head">
                <p class="dash-eyebrow"><?= esc(lang('Analytics.eyebrowPro')) ?></p>
                <h1><?= esc(lang('Analytics.title')) ?></h1>
                <p class="text-muted mb-0"><?= esc(lang('Analytics.lead')) ?></p>
            </header>

            <div data-analytics data-months="<?= esc(lang('Date.monthsShort'), 'attr') ?>" data-updated="<?= esc(lang('Analytics.updated'), 'attr') ?>" data-failed-at="<?= esc(lang('Analytics.updateFailedAt'), 'attr') ?>" data-failed="<?= esc(lang('Analytics.updateFailed'), 'attr') ?>" data-endpoint="<?= esc(base_url('dashboard/analytics/data?month=' . $stats['month']), 'attr') ?>">
                <form class="dash-stats-head" method="get" action="<?= esc(base_url('dashboard/analytics'), 'attr') ?>">
                    <div class="dash-field">
                        <label for="statsMonth"><?= esc(lang('Analytics.month')) ?></label>
                        <select class="form-select" id="statsMonth" name="month" data-auto-submit>
                            <?php foreach ($stats['months'] as $option): ?>
                                <option value="<?= esc($option['value'], 'attr') ?>"<?= $option['value'] === $stats['month'] ? ' selected' : '' ?>><?= esc($option['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <noscript><button type="submit" class="btn btn-outline-secondary"><?= esc(lang('Analytics.show')) ?></button></noscript>
                    <span class="text-muted small" data-as-of role="status" aria-live="polite"><?= esc(lang('Analytics.updated', [format_datetime_wib($stats['as_of'])])) ?></span>
                </form>

                <?= $this->include('partials/dashboard/analytics-totals') ?>

                <section class="dash-panel mt-4" aria-labelledby="statsChartTitle">
                    <h2 id="statsChartTitle"><?= esc(lang('Analytics.chartTitle', [$stats['label']])) ?></h2>
                    <p class="text-muted small"><?= esc(lang('Analytics.chartHint')) ?></p>
                    <?= $this->include('partials/dashboard/analytics-chart') ?>
                </section>
            </div>

            <?php if ($stats['totals']['views'] === 0): ?>
                <div class="dash-notice mt-4"><?= esc(lang('Analytics.noVisits')) ?></div>
            <?php endif; ?>
            <div class="dash-notice mt-4">
                <strong><?= esc(lang('Analytics.howToRead')) ?></strong>
                <p class="small mb-0 mt-2"><?= esc(lang('Analytics.howToReadBody')) ?></p>
            </div>
        <?php endif; ?>
    </main>
<?= $this->endSection() ?>

<?php if ($isPro): ?>
<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/analytics.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
<?php endif; ?>
