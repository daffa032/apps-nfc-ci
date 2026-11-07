<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Master.eyebrow')) ?></p>
            <h1><?= esc(lang('Master.plansTitle')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Master.plansLead')) ?></p>
        </header>

        <div class="dash-grid">
            <?php foreach ($plans as $plan): ?>
                <form class="dash-panel" method="post" action="<?= esc(base_url('dashboard/master/plans/' . $plan['code']), 'attr') ?>" aria-labelledby="plan<?= esc($plan['code'], 'attr') ?>Title">
                    <?= csrf_field() ?>
                    <div class="dash-panel-head">
                        <h2 id="plan<?= esc($plan['code'], 'attr') ?>Title"><?= esc($plan['label']) ?></h2>
                        <span class="dash-tag<?= (int) $plan['enabled'] === 1 ? '' : ' dash-tag-neutral' ?>"><?= esc(lang((int) $plan['enabled'] === 1 ? 'Master.active' : 'Master.inactive')) ?></span>
                    </div>
                    <p class="small text-muted"><?= esc(lang('Master.version', [(int) $plan['version']])) ?> · <?= esc($plan['price_idr'] === null ? lang('Master.priceUnset') : lang('Master.pricePer', [number_format((int) $plan['price_idr'], 0, ',', '.'), (int) $plan['duration_months']])) ?></p>
                    <div class="dash-form-grid">
                        <div class="dash-field">
                            <label for="price<?= esc($plan['code'], 'attr') ?>"><?= esc(lang('Master.price')) ?></label>
                            <?php $planErrors = ($fieldErrorsPlan ?? null) === $plan['code'] ? ($fieldErrors ?? []) : []; ?>
                            <input class="form-control" id="price<?= esc($plan['code'], 'attr') ?>" name="price_idr" inputmode="numeric" maxlength="12" placeholder="<?= esc(lang('Master.priceExample'), 'attr') ?>" value="<?= esc($plan['price_idr'] === null ? '' : (string) $plan['price_idr'], 'attr') ?>"<?= field_error_attrs($planErrors, 'price_idr', 'price' . $plan['code']) ?>>
                            <?= $this->setVar('fieldErrors', $planErrors)->setVar('field', 'price_idr')->setVar('inputId', 'price' . $plan['code'])->include('partials/feedback/field-error') ?>
                        </div>
                        <div class="dash-field">
                            <label for="duration<?= esc($plan['code'], 'attr') ?>"><?= esc(lang('Master.duration')) ?></label>
                            <select class="form-select" id="duration<?= esc($plan['code'], 'attr') ?>" name="duration_months"<?= field_error_attrs($planErrors, 'duration_months', 'duration' . $plan['code']) ?>>
                                <option value=""><?= esc(lang('Master.notSet')) ?></option>
                                <?php foreach ($durations as $months): ?>
                                    <option value="<?= $months ?>"<?= (int) $plan['duration_months'] === $months ? ' selected' : '' ?>><?= esc(lang('Master.monthsN', [$months])) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $this->setVar('fieldErrors', $planErrors)->setVar('field', 'duration_months')->setVar('inputId', 'duration' . $plan['code'])->include('partials/feedback/field-error') ?>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="enabled<?= esc($plan['code'], 'attr') ?>" name="enabled" value="1"<?= (int) $plan['enabled'] === 1 ? ' checked' : '' ?>>
                        <label class="form-check-label" for="enabled<?= esc($plan['code'], 'attr') ?>"><?= esc(lang('Master.offer')) ?></label>
                    </div>
                    <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Master.saving'), 'attr') ?>"><?= esc(lang('Master.saveVersion')) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
        <div class="dash-notice mt-4"><?= esc(lang('Master.plansNotice')) ?></div>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/busy-submit.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
