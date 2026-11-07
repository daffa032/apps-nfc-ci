<?php
/**
 * Floating actions at the bottom-right of every public card: "Save contact" for all designs and,
 * next to it, the "open QR and share" button (PRD QR-01) when $shareQrUrl is given (Pro cards;
 * owner previews and Basic cards pass none). Both sit in one row so they never overlap.
 * Expects $vcard; the contact file is read by assets/cards/js/floating-actions.js.
 */
?>
<div class="nc-fab-bar">
    <button type="button" class="nc-fab-save" data-fab="vcard">
        <span class="nc-fab-save-text"><?= esc(lang('Card.saveContact')) ?></span>
        <span class="nc-fab-save-icon" aria-hidden="true"><i class="fas fa-address-book"></i></span>
    </button>
    <?= $this->include('partials/cards/qr-fab') ?>
</div>
<script id="ncFabVcard" type="text/plain"><?= esc($vcard) ?></script>
