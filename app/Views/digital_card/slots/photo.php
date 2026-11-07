<?php
/**
 * Card slot partial ({{photo}}). Shared by digital_card/layout.php and uploaded HTML templates so
 * every layout renders the same data and buttons. Expects the card variables from
 * PublicCardService::present() plus $p (dataPersonal), $name and $initials. A stored photo is a button
 * that opens digital_card/photo-dialog.php with the same image (PRD CARD-01); the monogram is not.
 */
?>
            <?php if ($p['profile_image']): ?>
                <button type="button" class="nc-avatar-button" data-action="photo" aria-haspopup="dialog" aria-label="<?= esc(lang('Card.enlargePhoto', [$name]), 'attr') ?>"><img class="nc-avatar" src="<?= esc(base_url('uploads/profile_images/' . $p['profile_image']), 'attr') ?>" alt="<?= esc(lang('Card.photoAlt', [$name]), 'attr') ?>" data-fallback="avatar"></button>
            <?php endif; ?>
            <div class="nc-avatar" aria-hidden="true"<?= $p['profile_image'] ? ' hidden' : '' ?>><?= esc($initials) ?></div>
