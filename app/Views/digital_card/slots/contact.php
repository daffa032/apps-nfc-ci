<?php
/**
 * Card slot partial ({{contact}}). Shared by digital_card/layout.php and uploaded HTML templates so
 * every layout renders the same data and buttons. Expects the card variables from
 * PublicCardService::present() plus $p (dataPersonal). With $mapInAddress (Pro designs, PRD NT-06) the
 * "Lihat lokasi" button sits inside the address block so it never drifts away from the address.
 */
?>
            <div class="nc-actions">
                <?php if ($p['no_whatsapp']): ?>
                    <a class="nc-btn nc-btn-primary" href="https://wa.me/<?= esc($p['no_whatsapp'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp" aria-hidden="true"></i> <?= esc(lang('Card.whatsapp')) ?></a>
                <?php endif; ?>
                <?php if ($p['phone_number']): ?>
                    <a class="nc-btn" href="tel:+<?= esc($p['phone_number'], 'attr') ?>"><i class="fas fa-phone" aria-hidden="true"></i> <?= esc(lang('Card.call')) ?></a>
                <?php endif; ?>
                <?php if ($p['email']): ?>
                    <a class="nc-btn" href="mailto:<?= esc($p['email'], 'attr') ?>"><i class="far fa-envelope" aria-hidden="true"></i> <?= esc(lang('Card.sendEmail')) ?></a>
                <?php endif; ?>
            </div>

            <?php if ($p['email']): ?>
                <div class="nc-detail"><strong><?= esc(lang('Card.email')) ?></strong><span><?= esc($p['email']) ?></span></div>
            <?php endif; ?>
            <?php if (($p['no_hp'] ?? '') !== ''): ?>
                <div class="nc-detail"><strong><?= esc(lang('Card.phone')) ?></strong><span><?= esc($p['no_hp']) ?></span></div>
            <?php endif; ?>
            <?php if (($p['address'] ?? '') !== ''): ?>
                <div class="nc-detail nc-address"><strong><?= esc(lang('Card.address')) ?></strong><span><?= esc($p['address']) ?></span><?php if (!empty($mapInAddress) && $p['google_map_link']): ?>
                    <a class="nc-btn nc-map-btn" href="<?= esc($p['google_map_link'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-pin" aria-hidden="true"></i> <?= esc(lang('Card.viewLocation')) ?></a>
                <?php endif; ?></div>
            <?php endif; ?>
            <?php if ($p['google_map_link'] && (empty($mapInAddress) || ($p['address'] ?? '') === '')): ?>
                <div class="nc-actions nc-actions-inline">
                    <a class="nc-btn" href="<?= esc($p['google_map_link'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-pin" aria-hidden="true"></i> <?= esc(lang('Card.viewLocation')) ?></a>
                </div>
            <?php endif; ?>
