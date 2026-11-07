<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home" id="dashboardHome" data-public-url="<?= esc($publicUrl, 'attr') ?>">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Home.eyebrow')) ?></p>
            <h1><?= esc(lang('Home.welcome', [$firstName])) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Home.intro')) ?></p>
        </header>

        <?php if ($neverActivated): ?>
            <div class="dash-notice dash-notice-warning" role="status">
                <strong><?= esc(lang('Home.notActivatedTitle')) ?></strong><br>
                <?= esc(lang('Home.notActivatedBody')) ?> <a href="<?= esc(base_url('dashboard/billing'), 'attr') ?>"><?= esc(lang('Home.howActivate')) ?></a>
            </div>
        <?php elseif (!$isActive): ?>
            <div class="dash-notice dash-notice-warning" role="status">
                <strong><?= esc(lang('Home.expiredTitle')) ?></strong><br>
                <?= esc(lang('Home.expiredBody')) ?> <a href="<?= esc(base_url('dashboard/billing'), 'attr') ?>"><?= esc(lang('Home.howRenew')) ?></a>
            </div>
        <?php endif; ?>

        <div class="dash-grid">
            <section class="dash-panel dash-hero dash-wide" aria-labelledby="dashCardTitle">
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php if ($isActive): ?>
                            <span class="dash-tag"><?= esc(lang('Home.active')) ?></span>
                        <?php elseif ($neverActivated): ?>
                            <span class="dash-tag dash-tag-warn"><?= esc(lang('Home.notActive')) ?></span>
                        <?php else: ?>
                            <span class="dash-tag dash-tag-warn"><?= esc(lang('Home.expired')) ?></span>
                        <?php endif; ?>
                        <?php if (!$isStaff): ?>
                            <?= $this->setVar('badgeText', lang('Home.planNamed', [$planLabel]))->include('partials/shared/plan-badge') ?>
                        <?php endif; ?>
                    </div>
                    <h2 id="dashCardTitle">
                        <?php if (!$hasProfile): ?>
                            <?= esc(lang('Home.completeTitle')) ?>
                        <?php elseif ($isActive): ?>
                            <?= esc(lang('Home.readyTitle')) ?>
                        <?php else: ?>
                            <?= esc(lang('Home.reactivateTitle')) ?>
                        <?php endif; ?>
                    </h2>
                    <?php if (!$isStaff): ?>
                        <p class="dash-expiry"><?= esc($expiryLabel) ?></p>
                    <?php endif; ?>
                    <p class="text-muted">
                        <?php if (!$hasProfile): ?>
                            <?= esc(lang('Home.completeBody')) ?>
                        <?php elseif ($isActive): ?>
                            <?= esc(lang('Home.readyBody')) ?>
                        <?php else: ?>
                            <?= esc(lang('Home.reactivateBody')) ?>
                        <?php endif; ?>
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!$isActive): ?>
                            <a class="btn btn-primary" href="<?= esc(base_url('dashboard/billing'), 'attr') ?>"><?= esc(lang($neverActivated ? 'Home.activateCard' : 'Home.renewNow')) ?></a>
                        <?php elseif (!$hasProfile): ?>
                            <a class="btn btn-primary" href="<?= esc(base_url('dashboard/personal_info_form'), 'attr') ?>"><?= esc(lang('Home.completeProfile')) ?></a>
                        <?php else: ?>
                            <a class="btn btn-primary" href="<?= esc(base_url('dashboard/personal_info_page'), 'attr') ?>"><?= esc(lang('Home.editProfile')) ?></a>
                            <a class="btn btn-outline-secondary" href="<?= esc($publicUrl, 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc(lang('Home.viewCard')) ?></a>
                            <button type="button" class="btn btn-outline-secondary" data-action="share"><?= esc(lang('Home.shareCard')) ?> <span aria-hidden="true">↗</span></button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dash-mini-id" aria-hidden="true">
                    <?php if ($profileImage): ?>
                        <img class="dash-avatar" src="<?= esc(base_url('uploads/profile_images/' . $profileImage), 'attr') ?>" alt="">
                    <?php else: ?>
                        <div class="dash-avatar"><?= esc($initials) ?></div>
                    <?php endif; ?>
                    <strong><?= esc($displayName) ?></strong>
                    <?php if ($company !== ''): ?>
                        <span class="dash-mini-company"><?= esc($company) ?></span>
                    <?php else: ?>
                        <div class="dash-mini-line"></div>
                    <?php endif; ?>
                    <div class="dash-mini-line dash-mini-line-short"></div>
                    <span class="dash-tag"><?= esc(lang('Home.digitalCard')) ?></span>
                </div>
            </section>

            <?php if ($isStaff): ?>
                <section class="dash-panel" aria-labelledby="dashAdminTitle">
                    <h2 id="dashAdminTitle"><?= esc(lang('Home.adminTools')) ?></h2>
                    <p class="text-muted"><?= esc(lang($accessLevel === 'Super Admin' ? 'Home.manageCustomersAdmins' : 'Home.manageCustomers')) ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/user_table'), 'attr') ?>"><?= esc(lang('Dashboard.navUserTable')) ?></a>
                        <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/add_user'), 'attr') ?>"><?= esc(lang('Dashboard.navAddUser')) ?></a>
                        <?php if ($accessLevel === 'Super Admin'): ?>
                            <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/admin_table'), 'attr') ?>"><?= esc(lang('Dashboard.navAdminTable')) ?></a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php else: ?>
                <section class="dash-panel" aria-labelledby="dashPlanTitle">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h2 id="dashPlanTitle" class="mb-0"><?= esc(lang('Home.planTitle')) ?></h2>
                        <?= $this->setVar('badgeText', null)->include('partials/shared/plan-badge') ?>
                    </div>
                    <p class="dash-expiry"><?= esc($expiryLabel) ?></p>
                    <p class="small text-muted"><?= esc(lang('Home.remaining', [$remainingDays])) ?></p>
                    <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/billing'), 'attr') ?>">
                        <?= esc(lang($isActive ? 'Home.managePlan' : ($neverActivated ? 'Home.activateCard' : 'Home.renewCard'))) ?>
                    </a>
                </section>
            <?php endif; ?>

            <section class="dash-panel" aria-labelledby="dashLinkTitle">
                <h2 id="dashLinkTitle"><?= esc(lang('Home.linkTitle')) ?></h2>
                <?php if (!$isActive): ?>
                    <p class="text-muted mb-0"><?= esc(lang('Home.linkInactive')) ?></p>
                <?php else: ?>
                    <p class="text-muted"><?= esc(lang('Home.linkHint')) ?></p>
                    <div class="dash-link-box">
                        <code id="dashPublicUrl"><?= esc($publicUrl) ?></code>
                        <button type="button" class="btn btn-outline-secondary" data-action="copy"><?= esc(lang('Home.copy')) ?></button>
                    </div>
                    <div class="dash-manual-copy" hidden>
                        <label for="dashManualCopy" class="form-label small mt-3"><?= esc(lang('Home.copyManual')) ?></label>
                        <input id="dashManualCopy" class="form-control" type="text" readonly value="<?= esc($publicUrl, 'attr') ?>">
                    </div>
                    <p class="small text-success mt-2 mb-0" data-copy-status data-copied-text="<?= esc(lang('Home.linkCopied'), 'attr') ?>" role="status" aria-live="polite"></p>
                <?php endif; ?>
            </section>
        </div>

        <section class="dash-section" aria-labelledby="dashStatsTitle">
            <h2 id="dashStatsTitle"><?= esc(lang('Home.statsTitle')) ?></h2>
            <?php if ($stats !== null): ?>
                <div data-analytics data-months="<?= esc(lang('Date.monthsShort'), 'attr') ?>" data-updated="<?= esc(lang('Analytics.updated'), 'attr') ?>" data-failed-at="<?= esc(lang('Analytics.updateFailedAt'), 'attr') ?>" data-failed="<?= esc(lang('Analytics.updateFailed'), 'attr') ?>" data-endpoint="<?= esc(base_url('dashboard/analytics/data'), 'attr') ?>">
                    <p class="text-muted small"><?= esc($stats['label']) ?> · <span data-as-of role="status" aria-live="polite"><?= esc(lang('Analytics.updated', [format_datetime_wib($stats['as_of'])])) ?></span></p>
                    <?= $this->include('partials/dashboard/analytics-totals') ?>
                </div>
                <p class="mt-3 mb-0"><a href="<?= esc(base_url('dashboard/analytics'), 'attr') ?>"><?= esc(lang('Home.statsDaily')) ?></a></p>
            <?php else: ?>
                <div class="dash-panel">
                    <p class="mb-0"><?= esc(lang('Home.statsPro')) ?><?php if ($isActive): ?> <a href="<?= esc(base_url('dashboard/analytics'), 'attr') ?>"><?= esc(lang('Home.learnMore')) ?></a><?php endif; ?></p>
                </div>
            <?php endif; ?>
        </section>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/dashboard-home.js'), 'attr') ?>"></script>
    <?php if ($stats !== null): ?>
        <script src="<?= esc(asset_url('dashboard/js/analytics.js'), 'attr') ?>"></script>
    <?php endif; ?>
<?= $this->endSection() ?>
