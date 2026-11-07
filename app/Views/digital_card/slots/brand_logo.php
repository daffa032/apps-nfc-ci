<?php
/**
 * Card slot partial ({{brand_logo}}, PRD BR-01). Expects $brand (only filled while the account is Pro),
 * $company and $name. Without a logo it shows the company initials as the design's fallback (NT-03);
 * a logo the owner switched off ($brand['logo_hidden']) shows nothing at all. The logo opens the
 * shared photo dialog (PRD CARD-01).
 */
$brandInitials = mb_strtoupper(mb_substr(trim(strip_tags((string) $company)), 0, 2)) ?: 'QR';
?>
<?php if (!empty($brand['brand_logo'])): ?>
    <?php $logoAlt = lang('Card.brandLogoAlt', [$company !== '' ? $company : $name]); ?>
    <button type="button" class="nc-pro-brand-logo" data-action="photo" data-photo-kind="logo" aria-haspopup="dialog" aria-label="<?= esc(lang('Card.enlargeImage', [$logoAlt]), 'attr') ?>"><img src="<?= esc(base_url('uploads/brand_assets/' . $brand['brand_logo']), 'attr') ?>" alt="<?= esc($logoAlt, 'attr') ?>" data-brand="brand_logo"></button>
<?php elseif (empty($brand['logo_hidden'])): ?>
    <span class="nc-pro-brand-text" aria-hidden="true" data-brand-fallback><?= esc($brandInitials) ?></span>
<?php endif; ?>
