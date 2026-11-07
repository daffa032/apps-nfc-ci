<?php
/**
 * Enlarged image for public cards (PRD CARD-01): profile photo, gallery photos and the brand logo.
 * Every enlargeable image sits in a <button data-action="photo"> printed by its slot; photo-dialog.js
 * copies that button's own image (and caption) into this dialog, so only images already on the card
 * are shown and nothing comes from the URL.
 */
?>
<dialog class="nc-photo-dialog" id="ncPhotoDialog" aria-label="<?= esc(lang('Card.largePhoto'), 'attr') ?>">
    <button type="button" class="nc-photo-close" data-photo-close aria-label="<?= esc(lang('Card.close'), 'attr') ?>">&times;</button>
    <figure>
        <img src="data:," alt="" data-photo-image>
        <figcaption data-photo-caption hidden></figcaption>
    </figure>
</dialog>
