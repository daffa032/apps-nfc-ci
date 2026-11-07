<?php
/**
 * Dashboard top navigation. Expects $username, $encryptedId and $locale. The light/dark switch
 * (dashboard/js/theme.js) and the language dropdown sit just left of the account menu (PRD LANG-02).
 */
?>
        <nav class="sb-topnav navbar navbar-expand navbar-dark" aria-label="<?= esc(lang('Dashboard.topNav'), 'attr') ?>">
            <a class="navbar-brand ps-3" href="<?= esc(base_url('dashboard'), 'attr') ?>">QR-CODE.ID
                <span class="dash-brand-subtitle">Admin Panel</span>
            </a>
            <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" type="button" aria-label="<?= esc(lang('Dashboard.toggleMenu'), 'attr') ?>" aria-controls="layoutSidenav_nav"><i class="fas fa-bars" aria-hidden="true"></i></button>
            <button class="dash-theme-toggle ms-auto" type="button" data-theme-toggle aria-pressed="false" aria-label="<?= esc(lang('Dashboard.themeToggle'), 'attr') ?>" title="<?= esc(lang('Dashboard.themeToDark'), 'attr') ?>" data-label-dark="<?= esc(lang('Dashboard.themeToDark'), 'attr') ?>" data-label-light="<?= esc(lang('Dashboard.themeToLight'), 'attr') ?>"><i class="fas fa-moon" aria-hidden="true"></i><i class="fas fa-sun" aria-hidden="true"></i></button>
            <div class="dash-topnav-lang"><?= $this->include('partials/shared/lang-switch') ?></div>
            <ul class="navbar-nav me-3 me-lg-4">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-user fa-fw" aria-hidden="true"></i> <span class="dash-topnav-username"><?= esc($username) ?></span></a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <?php /* Phone width: the header has no room for the switch, so it lives here (same data-theme-toggle). */ ?>
                        <li class="dash-theme-item-wrap">
                            <button class="dropdown-item dash-theme-item" type="button" data-theme-toggle aria-pressed="false" data-label-dark="<?= esc(lang('Dashboard.themeToDark'), 'attr') ?>" data-label-light="<?= esc(lang('Dashboard.themeToLight'), 'attr') ?>"><i class="fas fa-moon fa-fw" aria-hidden="true"></i><i class="fas fa-sun fa-fw" aria-hidden="true"></i> <?= esc(lang('Dashboard.themeToggle')) ?></button>
                        </li>
                        <li><a class="dropdown-item" href="<?= esc(base_url('dashboard/setting/' . $encryptedId), 'attr') ?>"><?= esc(lang('Dashboard.setting')) ?></a></li>
                        <li>
                            <form action="<?= esc(base_url('logout'), 'attr') ?>" method="post">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item"><?= esc(lang('Dashboard.logout')) ?></button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
