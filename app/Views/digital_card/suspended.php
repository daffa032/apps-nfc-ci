<!DOCTYPE html>
<html lang="<?= esc($locale ?? 'id', 'attr') ?>">
<head>
    <meta charset="utf-8">
    <?= $this->include('partials/shared/favicon') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= esc(lang('Card.suspendedTitle')) ?></title>
    <link href="<?= esc(base_url('assets/cards/css/inactive.css'), 'attr') ?>" rel="stylesheet">
    <link href="<?= esc(base_url('assets/shared/css/lang-switch.css'), 'attr') ?>" rel="stylesheet">
</head>
<body>
    <?php if (!empty($langSwitch)): ?><div class="nfc-lang-corner"><?= $this->include('partials/shared/lang-switch') ?></div><?php endif; ?>
    <main class="card-inactive">
        <p class="card-inactive-icon" aria-hidden="true">&#9888;</p>
        <h1><?= esc(lang('Card.suspendedHeading')) ?></h1>
        <p><?= esc(lang('Card.suspendedBody')) ?></p>
        <p class="card-inactive-note"><?= esc(lang('Card.suspendedNote')) ?></p>
    </main>
    <script src="<?= esc(base_url('assets/shared/js/lang-switch.js'), 'attr') ?>"></script>
</body>
</html>
