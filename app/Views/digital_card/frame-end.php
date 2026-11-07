<?php
/**
 * Closing of a public card page (see frame-start.php). Expects $vcard.
 */
?>
        <footer class="nc-foot"><?= esc(lang('Card.footer')) ?></footer>
    </article>
<?= $this->include('digital_card/photo-dialog') ?>
<?= $this->include('partials/cards/floating-actions') ?>

    <script id="ncStrings" type="application/json"><?= json_encode(['linkCopied' => lang('Card.linkCopied')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
    <script src="<?= esc(base_url('assets/cards/js/card.js'), 'attr') ?>"></script>
    <script src="<?= esc(base_url('assets/cards/js/photo-dialog.js'), 'attr') ?>"></script>
    <script src="<?= esc(base_url('assets/cards/js/floating-actions.js'), 'attr') ?>"></script>
    <script src="<?= esc(base_url('assets/shared/js/lang-switch.js'), 'attr') ?>"></script>
</body>
</html>
