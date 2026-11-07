<?php
/**
 * Card slot partial ({{gallery}}). Shared by digital_card/layout.php and uploaded HTML templates so
 * every layout renders the same data and buttons. Expects the card variables from
 * PublicCardService::present() plus $p (dataPersonal), $images and $galleryColumns. In an
 * uploaded template ($inTemplate) an empty gallery leaves a marker so its panel can be hidden. Each
 * photo is a button that opens digital_card/photo-dialog.php (PRD CARD-01).
 */
?>
<?php if ($images !== []): ?>
    <?php if (!empty($sectionHeadings)): ?><h2><?= esc(lang('Card.galleryHeading')) ?></h2><?php endif; ?>
    <section class="nc-gallery nc-gallery-cols-<?= (int) $galleryColumns ?>" aria-label="<?= esc(lang('Card.gallery'), 'attr') ?>">
        <?php foreach ($images as $image): ?>
            <?php if (!$image['images']) { continue; } ?>
            <?php $photoAlt = $image['caption'] !== '' ? $image['caption'] : lang('Card.galleryPhoto'); ?>
            <figure><button type="button" class="nc-gallery-button" data-action="photo" data-photo-kind="gallery" aria-haspopup="dialog" aria-label="<?= esc(lang('Card.enlargeImage', [$photoAlt]), 'attr') ?>"><img src="<?= esc(base_url('uploads/multiple_images/' . $image['images']), 'attr') ?>" alt="<?= esc($photoAlt, 'attr') ?>" loading="lazy" data-fallback="figure"></button><?php if ($image['caption'] !== ''): ?><figcaption><?= esc($image['caption']) ?></figcaption><?php endif; ?></figure>
        <?php endforeach; ?>
    </section>
<?php elseif (!empty($inTemplate)): ?>
    <span class="nc-slot-empty" hidden></span>
<?php endif; ?>
