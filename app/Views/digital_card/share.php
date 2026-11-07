<?php
/**
 * Pro share page (PRD QR-02/03). Expects $username, $name, $jobTitle, $company, $photo (stored image
 * name or null), $cardUrl (canonical card URL, the only thing in the QR), $qr (App\Libraries\QrCode)
 * and $locale. The PNG images are drawn in the browser from data-qr (the same matrix as the SVG),
 * so nothing is sent to a third-party service. No Wallet buttons.
 */
$initials = mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim(strip_tags($name))) ?: [], 0, 2)))) ?: '?';
$photoUrl = $photo ? base_url('uploads/profile_images/' . $photo) : null;
$fileBase = preg_replace('/[^a-z0-9._-]+/i', '-', $username) ?: 'kartu';
?>
<!DOCTYPE html>
<html lang="<?= esc($locale, 'attr') ?>">
<head>
    <meta charset="UTF-8">
    <?= $this->include('partials/shared/favicon') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= esc(lang('Card.shareTitle', [$name !== '' ? $name : $username])) ?></title>
    <link href="<?= esc(base_url('assets/dashboard/vendor/fontawesome/css/all.min.css'), 'attr') ?>" rel="stylesheet">
    <link href="<?= esc(base_url('assets/shared/css/lang-switch.css'), 'attr') ?>" rel="stylesheet">
    <link href="<?= esc(base_url('assets/cards/css/share.css'), 'attr') ?>" rel="stylesheet">
</head>
<body class="nc-share-page">
    <main class="nc-share" id="ncShare"
          data-card-url="<?= esc($cardUrl, 'attr') ?>"
          data-file="<?= esc($fileBase, 'attr') ?>"
          data-qr="<?= esc(implode(',', $qr->rows()), 'attr') ?>"
          data-name="<?= esc($name, 'attr') ?>"
          data-title="<?= esc($jobTitle, 'attr') ?>"
          data-company="<?= esc($company, 'attr') ?>"
          data-initials="<?= esc($initials, 'attr') ?>"
          data-photo="<?= esc((string) $photoUrl, 'attr') ?>"
          data-text-copied="<?= esc(lang('Card.linkCopied'), 'attr') ?>"
          data-text-ready="<?= esc(lang('Card.imageReady'), 'attr') ?>"
          data-text-failed="<?= esc(lang('Card.imageFailed'), 'attr') ?>"
          data-text-scan="<?= esc(lang('Card.scanHint'), 'attr') ?>">
        <div class="nc-share-top">
            <a class="nc-share-close" href="<?= esc($cardUrl, 'attr') ?>" aria-label="<?= esc(lang('Card.closeShare'), 'attr') ?>">&times;</a>
            <?php if (!empty($langSwitch)): ?><?= $this->setVar('locale', $locale)->include('partials/shared/lang-switch') ?><?php endif; ?>
        </div>

        <section class="nc-share-card" aria-labelledby="ncShareName">
            <?php if ($photoUrl): ?>
                <img class="nc-share-photo" src="<?= esc($photoUrl, 'attr') ?>" alt="<?= esc(lang('Card.photoAlt', [$name]), 'attr') ?>" data-photo-fallback>
            <?php endif; ?>
            <div class="nc-share-photo" aria-hidden="true"<?= $photoUrl ? ' hidden' : '' ?>><?= esc($initials) ?></div>
            <h1 id="ncShareName"><?= esc($name !== '' ? $name : $username) ?></h1>
            <?php if ($jobTitle !== ''): ?><p class="nc-share-title"><?= esc($jobTitle) ?></p><?php endif; ?>
            <?php if ($company !== ''): ?><p class="nc-share-company"><?= esc($company) ?></p><?php endif; ?>

            <div class="nc-share-qr">
                <?= $qr->svg(lang('Card.qrLabel', [$name !== '' ? $name : $username])) ?>
            </div>
            <p class="nc-share-url"><?= esc($cardUrl) ?></p>
        </section>

        <div class="nc-share-actions">
            <button type="button" class="nc-share-btn" data-action="copy"><i class="fas fa-link" aria-hidden="true"></i> <?= esc(lang('Card.copyLinkBtn')) ?></button>
            <button type="button" class="nc-share-btn" data-action="share"><i class="fas fa-share-alt" aria-hidden="true"></i> <?= esc(lang('Card.shareBtn')) ?></button>
            <button type="button" class="nc-share-btn nc-share-btn-primary" data-action="save-card"><i class="fas fa-download" aria-hidden="true"></i> <?= esc(lang('Card.saveImage')) ?></button>
            <button type="button" class="nc-share-btn nc-share-btn-quiet" data-action="save-qr"><?= esc(lang('Card.saveQrOnly')) ?></button>
        </div>
        <div class="nc-share-fallback" hidden>
            <label for="ncShareCopy"><?= esc(lang('Card.copyLink')) ?></label>
            <input id="ncShareCopy" type="text" readonly value="<?= esc($cardUrl, 'attr') ?>">
        </div>
        <p class="nc-share-status" role="status" aria-live="polite"></p>
        <p class="nc-share-hint"><?= esc(lang('Card.saveHint')) ?></p>

        <details class="nc-share-home">
            <summary><?= esc(lang('Card.homeScreenTitle')) ?></summary>
            <p><?= esc(lang('Card.homeScreenIos')) ?></p>
            <p><?= esc(lang('Card.homeScreenAndroid')) ?></p>
        </details>
    </main>
    <script src="<?= esc(base_url('assets/shared/js/lang-switch.js'), 'attr') ?>"></script>
    <script src="<?= esc(base_url('assets/cards/js/share.js'), 'attr') ?>"></script>
</body>
</html>
