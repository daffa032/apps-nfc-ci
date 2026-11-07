<?php
/**
 * Dashboard shell used by every page under dashboard/pages.
 * Controllers pass $username, $accessLevel, $encryptedId (see ResolvesCurrentUser) and $feedback.
 * Pages fill the `content`, `styles` (vendor CSS, loaded before the theme), and `scripts` sections.
 */
?>
<!DOCTYPE html>
<html lang="<?= esc($locale ?? 'id', 'attr') ?>">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>QR-Code.id Admin Panel</title>
        <?= $this->include('partials/shared/favicon') ?>
        <?php // Sets data-theme before first paint so dark mode does not flash light. ?>
        <script src="<?= esc(asset_url('dashboard/js/theme-init.js'), 'attr') ?>"></script>
        <?php // Urbanist arrives with the CSS instead of after it, so text does not switch fonts on load. ?>
        <link rel="preload" href="<?= esc(base_url('assets/shared/fonts/urbanist/urbanist-latin-wght-normal.woff2'), 'attr') ?>" as="font" type="font/woff2" crossorigin />
        <link rel="preload" href="<?= esc(base_url('assets/dashboard/vendor/fontawesome/webfonts/fa-solid-900.woff2'), 'attr') ?>" as="font" type="font/woff2" crossorigin />
        <?php // Page vendor CSS (e.g. Simple DataTables) loads first so the theme and app CSS can override it. ?>
        <?= $this->renderSection('styles') ?>
        <link href="<?= esc(asset_url('dashboard/css/styles.css'), 'attr') ?>" rel="stylesheet" />
        <link href="<?= esc(asset_url('dashboard/vendor/fontawesome/css/all.min.css'), 'attr') ?>" rel="stylesheet" />
        <link href="<?= esc(asset_url('shared/css/fonts.css'), 'attr') ?>" rel="stylesheet" />
        <link href="<?= esc(asset_url('shared/css/tokens.css'), 'attr') ?>" rel="stylesheet" />
        <link href="<?= esc(asset_url('shared/css/feedback.css'), 'attr') ?>" rel="stylesheet" />
        <link href="<?= esc(asset_url('shared/css/lang-switch.css'), 'attr') ?>" rel="stylesheet" />
        <link href="<?= esc(asset_url('shared/css/brand-icons.css'), 'attr') ?>" rel="stylesheet" />
        <link href="<?= esc(asset_url('dashboard/css/app.css'), 'attr') ?>" rel="stylesheet" />
        <link href="<?= esc(asset_url('dashboard/css/theme.css'), 'attr') ?>" rel="stylesheet" />
    </head>
    <body class="sb-nav-fixed">
        <?= $this->include('partials/dashboard/topnav') ?>
        <div id="layoutSidenav">
            <?= $this->include('partials/dashboard/sidebar') ?>
            <div id="layoutSidenav_content">
                <?= $this->renderSection('content') ?>
                <?= $this->include('partials/dashboard/footer') ?>
                <?= $this->include('partials/feedback/flash') ?>
            </div>
        </div>
        <script src="<?= esc(asset_url('dashboard/vendor/bootstrap/bootstrap.bundle.min.js'), 'attr') ?>"></script>
        <script src="<?= esc(asset_url('dashboard/js/scripts.js'), 'attr') ?>"></script>
        <script src="<?= esc(asset_url('shared/js/feedback.js'), 'attr') ?>"></script>
        <script src="<?= esc(asset_url('shared/js/lang-switch.js'), 'attr') ?>"></script>
        <script src="<?= esc(asset_url('dashboard/js/theme.js'), 'attr') ?>"></script>
        <script src="<?= esc(asset_url('dashboard/js/motion.js'), 'attr') ?>"></script>
        <?= $this->renderSection('scripts') ?>
    </body>
</html>
