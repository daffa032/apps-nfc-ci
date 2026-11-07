<?php
/**
 * Card slot partial ({{social}}). Shared by digital_card/layout.php and uploaded HTML templates so
 * every layout renders the same data and buttons. Expects the card variables from
 * PublicCardService::present() plus $p (dataPersonal), $channels and $hasSocial. In an uploaded
 * template ($inTemplate) the template supplies the heading, and an empty slot leaves a marker so
 * its panel can be hidden.
 */
?>
            <?php if ($hasSocial): ?>
                <?php if (empty($inTemplate) || !empty($sectionHeadings)): ?><h2><?= esc(lang('Card.socialLinks')) ?></h2><?php endif; ?>
                <div class="nc-social">
                    <?php foreach ($channels as $key => [$label, $icon]): ?>
                        <?php if (!empty($socialMediaLinks[$key])): ?>
                            <a class="nc-btn" href="<?= esc($socialMediaLinks[$key], 'attr') ?>" target="_blank" rel="noopener noreferrer" data-label="<?= esc($label, 'attr') ?>"><i class="<?= esc($icon, 'attr') ?>" aria-hidden="true"></i> <?= esc($label) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!empty($inTemplate)): ?>
                <span class="nc-slot-empty" hidden></span>
            <?php endif; ?>
