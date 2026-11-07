<?php
/**
 * Floating "open QR and share" button for Pro public cards (PRD QR-01). Expects optional
 * $shareQrUrl from DigitalCardController; owner previews and Basic cards pass none, so nothing prints.
 */
?>
<?php if (!empty($shareQrUrl)): ?>
    <a class="nc-qr-fab" href="<?= esc($shareQrUrl, 'attr') ?>" aria-label="<?= esc(lang('Card.openShare'), 'attr') ?>" title="<?= esc(lang('Card.openShare'), 'attr') ?>"><i class="fas fa-qrcode" aria-hidden="true"></i></a>
<?php endif; ?>
