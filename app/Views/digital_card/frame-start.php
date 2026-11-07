<?php
/**
 * Opening of a public card page: document head, scripts, and the card article. Shared by
 * digital_card/layout.php, classic.php, pro.php and custom.php. Expects $name, $layout (body class
 * suffix), $shareUrl, $locale and the event meta variables.
 */
?>
<!DOCTYPE html>
<html lang="<?= esc($locale ?? 'id', 'attr') ?>">
<head>
    <meta charset="UTF-8">
    <?= $this->include('partials/shared/favicon') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($name !== '' ? lang('Card.pageTitleNamed', [$name]) : lang('Card.pageTitle')) ?></title>
    <link href="<?= esc(base_url('assets/dashboard/vendor/fontawesome/css/all.min.css'), 'attr') ?>" rel="stylesheet">
    <link href="<?= esc(base_url('assets/cards/css/layouts.css'), 'attr') ?>" rel="stylesheet">
    <?php if (!empty($shareQrUrl)): ?><link href="<?= esc(base_url('assets/cards/css/share.css'), 'attr') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (str_starts_with((string) $layout, 'pro-')): ?><link href="<?= esc(base_url('assets/cards/css/pro.css'), 'attr') ?>" rel="stylesheet"><?php endif; ?>
    <?php if (str_starts_with((string) $layout, 'classic-')): ?><link href="<?= esc(base_url('assets/cards/css/classic.css'), 'attr') ?>" rel="stylesheet"><?php endif; ?>
    <link href="<?= esc(base_url('assets/shared/css/lang-switch.css'), 'attr') ?>" rel="stylesheet">
    <link href="<?= esc(base_url('assets/shared/css/brand-icons.css'), 'attr') ?>" rel="stylesheet">
    <link href="<?= esc(base_url('assets/cards/css/photo-dialog.css'), 'attr') ?>" rel="stylesheet">
    <link href="<?= esc(base_url('assets/cards/css/floating-actions.css'), 'attr') ?>" rel="stylesheet">
<?= $this->include('partials/cards/event-meta') ?>
</head>
<body class="nc-page nc-<?= esc($layout, 'attr') ?><?= str_starts_with((string) $layout, 'pro-') ? ' nc-pro' : '' ?><?= !empty($shareQrUrl) ? ' nc-has-qr-fab' : '' ?>">
    <?php if (!empty($langSwitch)): ?><div class="nfc-lang-corner"><?= $this->include('partials/shared/lang-switch') ?></div><?php endif; ?>
    <article class="nc-card" id="digitalCard" data-share-url="<?= esc($shareUrl, 'attr') ?>" data-share-title="<?= esc($name, 'attr') ?>">
