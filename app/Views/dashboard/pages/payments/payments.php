<?php
$query = static fn (array $changes) => '?' . http_build_query(array_filter(array_merge($filters, $changes), static fn ($value) => $value !== '' && $value !== null));
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Billing.adminEyebrow')) ?></p>
            <h1><?= esc(lang('Billing.adminTitle')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Billing.adminLead')) ?></p>
        </header>

        <div class="dash-live-bar" data-payments-live data-live-url="<?= esc(base_url('dashboard/payments/live') . $query([]), 'attr') ?>" data-sync-url="<?= esc(base_url('dashboard/payments/sync'), 'attr') ?>" data-csrf-header="<?= esc(config('Security')->headerName, 'attr') ?>" data-csrf-hash="<?= esc(csrf_hash(), 'attr') ?>" data-text-live="<?= esc(lang('Billing.liveOn'), 'attr') ?>" data-text-updated="<?= esc(lang('Billing.liveUpdated'), 'attr') ?>" data-text-paused="<?= esc(lang('Billing.livePaused'), 'attr') ?>" data-text-failed="<?= esc(lang('Billing.liveFailed'), 'attr') ?>" data-text-new="<?= esc(lang('Billing.liveChanged'), 'attr') ?>">
            <span class="dash-live-dot" aria-hidden="true"></span>
            <span data-live-status role="status" aria-live="polite"><?= esc(lang('Billing.liveOn')) ?></span>
            <label class="form-check form-switch m-0 ms-auto">
                <input class="form-check-input" type="checkbox" role="switch" data-live-toggle checked>
                <span class="form-check-label small"><?= esc(lang('Billing.liveToggle')) ?></span>
            </label>
        </div>

        <div data-live-region="summary"><?= $this->include('dashboard/pages/payments/_summary') ?></div>
        <div data-live-region="attention"><?= $this->include('dashboard/pages/payments/_attention') ?></div>

        <form class="dash-panel mb-4" method="get" action="<?= esc(base_url('dashboard/payments'), 'attr') ?>" aria-label="<?= esc(lang('Audit.filter'), 'attr') ?>">
            <div class="dash-log-filters">
                <div class="dash-field">
                    <label for="payStatus"><?= esc(lang('Billing.filterStatus')) ?></label>
                    <select class="form-select" id="payStatus" name="status">
                        <option value=""><?= esc(lang('Billing.allStatuses')) ?></option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= esc($status, 'attr') ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= esc(lang('Billing.status_' . $status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dash-field">
                    <label for="paySearch"><?= esc(lang('Billing.filterSearch')) ?></label>
                    <input class="form-control" id="paySearch" name="q" type="search" maxlength="50" value="<?= esc($filters['q'], 'attr') ?>">
                </div>
                <div class="dash-field">
                    <label for="payFrom"><?= esc(lang('Audit.from')) ?></label>
                    <input class="form-control" id="payFrom" name="from" type="date" value="<?= esc($filters['from'], 'attr') ?>">
                </div>
                <div class="dash-field">
                    <label for="payTo"><?= esc(lang('Audit.to')) ?></label>
                    <input class="form-control" id="payTo" name="to" type="date" value="<?= esc($filters['to'], 'attr') ?>">
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="payReview" name="review" value="1"<?= $filters['review'] === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="payReview"><?= esc(lang('Billing.filterReview')) ?></label>
            </div>
            <div class="dash-actions">
                <button type="submit" class="btn btn-primary"><?= esc(lang('Audit.apply')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/payments'), 'attr') ?>"><?= esc(lang('Audit.reset')) ?></a>
            </div>
        </form>

        <div data-live-region="table"><?= $this->include('dashboard/pages/payments/_table') ?></div>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/payments-live.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
