<?php
/**
 * Dashboard sidebar. Expects $username and $accessLevel (role read by the controller), and
 * optionally $cardActive and $currentPath. Hiding a menu is not access control; routes stay
 * protected by filters. The current page is marked with aria-current (prototype UI/UX 2026).
 */
$cardActive ??= true;
$currentPath ??= '';
$isCurrent = static function (string $path) use ($currentPath): bool {
    // The Dashboard home is a prefix of every page, so it only matches exactly.
    return $currentPath === $path || ($path !== 'dashboard' && str_starts_with($currentPath, $path . '/'));
};
$link = static function (string $path, string $icon, string $label) use ($isCurrent): string {
    return '<a class="nav-link" href="' . esc(base_url($path), 'attr') . '"' . ($isCurrent($path) ? ' aria-current="page"' : '') . '>'
        . '<div class="sb-nav-link-icon"><i class="' . esc($icon, 'attr') . '"></i></div>' . esc($label) . '</a>';
};
$group = static function (string $id, string $icon, string $label, array $items) use ($isCurrent): string {
    $open = array_filter(array_keys($items), $isCurrent) !== [];
    $html = '<a class="nav-link' . ($open ? '' : ' collapsed') . '" href="#' . $id . '" data-bs-toggle="collapse" data-bs-target="#' . $id . '" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="' . $id . '">'
        . '<div class="sb-nav-link-icon"><i class="' . esc($icon, 'attr') . '"></i></div>' . esc($label)
        . '<div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div></a>'
        . '<div class="collapse' . ($open ? ' show' : '') . '" id="' . $id . '"><nav class="sb-sidenav-menu-nested nav">';
    foreach ($items as $path => $itemLabel) {
        $html .= '<a class="nav-link" href="' . esc(base_url($path), 'attr') . '"' . ($isCurrent($path) ? ' aria-current="page"' : '') . '>' . esc($itemLabel) . '</a>';
    }

    return $html . '</nav></div>';
};
?>
            <div id="layoutSidenav_nav">
                <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion" aria-label="<?= esc(lang('Dashboard.mainMenu'), 'attr') ?>">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading"><?= esc(lang('Dashboard.navHome')) ?></div>
                            <?= $link('dashboard', 'fas fa-tachometer-alt', lang('Dashboard.navDashboard')) ?>
                            <div class="sb-sidenav-menu-heading"><?= esc(lang('Dashboard.navCard')) ?></div>
                            <?php if ($cardActive): ?>
                                <?= $link('dashboard/personal_info_page', 'fas fa-user', lang('Dashboard.navPersonalInfo')) ?>
                                <?= $link('dashboard/templates', 'fas fa-columns', lang('Dashboard.navTemplates')) ?>
                                <?= $link('dashboard/analytics', 'fas fa-chart-bar', lang('Dashboard.navAnalytics')) ?>
                            <?php endif; ?>
                            <?php if ($accessLevel === 'User'): ?>
                                <?= $link('dashboard/billing', 'fas fa-credit-card', lang('Dashboard.navBilling')) ?>
                            <?php endif; ?>

                            <?php if ($accessLevel === 'Super Admin' || $accessLevel === 'Admin'): ?>
                                <div class="sb-sidenav-menu-heading"><?= esc(lang('Dashboard.navAdminTools')) ?></div>
                                <?= $group('navUserData', 'fas fa-table', lang('Dashboard.navUserData'), ['dashboard/add_user' => lang('Dashboard.navAddUser'), 'dashboard/user_table' => lang('Dashboard.navUserTable')]) ?>
                                <?= $link('dashboard/payments', 'fas fa-receipt', lang('Dashboard.navPayments')) ?>
                            <?php endif; ?>
                            <?php if ($accessLevel === 'Super Admin'): ?>
                                <?= $group('navAdminData', 'fas fa-book-open', lang('Dashboard.navAdminData'), ['dashboard/add_admin' => lang('Dashboard.navAddAdmin'), 'dashboard/admin_table' => lang('Dashboard.navAdminTable')]) ?>
                                <?= $link('dashboard/activity-logs', 'fas fa-history', lang('Dashboard.navActivityLogs')) ?>
                                <?= $group('navMasterData', 'fas fa-cogs', lang('Dashboard.navMasterData'), ['dashboard/master/plans' => lang('Dashboard.navPlanPrices'), 'dashboard/master/templates' => lang('Dashboard.navMasterTemplates')]) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="sb-sidenav-footer">
                        <a class="btn btn-primary w-100" href="<?= esc(base_url(rawurlencode((string) $username)), 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc(lang('Dashboard.viewSite')) ?></a>
                    </div>
                </nav>
            </div>
