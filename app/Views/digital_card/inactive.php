<!DOCTYPE html>
<html lang="<?= esc($locale ?? 'id', 'attr') ?>">
<head>
    <meta charset="utf-8">
    <?= $this->include('partials/shared/favicon') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= esc(lang('Card.inactiveTitle')) ?></title>
    <link href="<?= esc(base_url('assets/cards/css/inactive.css'), 'attr') ?>" rel="stylesheet">
    <link href="<?= esc(base_url('assets/shared/css/lang-switch.css'), 'attr') ?>" rel="stylesheet">
</head>
<body>
    <?php if (!empty($langSwitch)): ?><div class="nfc-lang-corner"><?= $this->include('partials/shared/lang-switch') ?></div><?php endif; ?>
    <main class="card-inactive">
        <h1><?= esc(lang('Card.inactiveHeading')) ?></h1>
        <p><?= esc(lang('Card.inactiveBody')) ?></p>
        <a class="card-inactive-login" href="<?= esc(base_url('login'), 'attr') ?>"><?= esc(lang('Card.inactiveLogin')) ?></a>
    </main>
    <script src="<?= esc(base_url('assets/shared/js/lang-switch.js'), 'attr') ?>"></script>
</body>
</html>
