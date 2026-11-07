<?php
/**
 * Card slot partial ({{actions}}). Shared by digital_card/layout.php and uploaded HTML templates so
 * every layout renders the same data and buttons. Expects the card variables from
 * PublicCardService::present() plus $p (dataPersonal) and $shareUrl. "Save contact" is the floating
 * button of partials/cards/floating-actions.php, so it is not repeated here.
 */
?>
            <div class="nc-bottom">
                <button type="button" class="nc-btn" data-action="share"><i class="fas fa-share-alt" aria-hidden="true"></i> <?= esc(lang('Card.shareCard')) ?></button>
            </div>
            <div class="nc-share-fallback" hidden>
                <label for="ncShareUrl"><?= esc(lang('Card.copyLink')) ?></label>
                <input id="ncShareUrl" type="text" readonly value="<?= esc($shareUrl, 'attr') ?>">
            </div>
            <p class="nc-status" role="status" aria-live="polite"></p>
