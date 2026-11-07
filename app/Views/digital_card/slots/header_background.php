<?php
/**
 * Card slot partial ({{header_background}}, PRD BR-01). Expects $brand. Decorative, so empty alt;
 * without an image the design's own colours show (NT-03).
 */
?>
<?php if (!empty($brand['header_background'])): ?>
    <img class="nc-pro-cover" src="<?= esc(base_url('uploads/brand_assets/' . $brand['header_background']), 'attr') ?>" alt="" data-brand="header_background">
<?php endif; ?>
