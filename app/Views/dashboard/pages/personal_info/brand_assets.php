<?php
/**
 * Brand logo / header background for the current design (PRD BR-01). Expects $brand from
 * BrandAssetService::pageFor(). Controls appear only for slots the design has; Basic sees a short
 * note with a link instead of inputs, and the server enforces the same rules on POST.
 */
$slots = $brand['slots'];
$labels = ['brand_logo' => lang('Profile.brandLogo'), 'header_background' => lang('Profile.headerBackground')];
?>
<section class="dash-panel" aria-labelledby="brandTitle">
    <h2 id="brandTitle"><?= esc(lang('Profile.brandTitle')) ?></h2>
    <?php if ($slots === []): ?>
        <p class="text-muted mb-0"><?= esc(lang('Profile.brandNoSlot')) ?> <a href="<?= esc(base_url('dashboard/templates'), 'attr') ?>"><?= esc(lang('Profile.changeDesign')) ?></a></p>
    <?php elseif (!$brand['isPro']): ?>
        <p class="text-muted mb-0"><?= esc(lang('Profile.brandUpsell')) ?> <a href="<?= esc(base_url('dashboard/billing'), 'attr') ?>"><?= esc(lang('Profile.seePro')) ?></a></p>
    <?php else: ?>
        <p class="dash-hint mt-0"><?= esc(lang('Profile.brandHint', [$brand['templateName']])) ?></p>
        <?php if (!in_array('header_background', $slots, true)): ?>
            <p class="dash-hint dash-brand-note"><i class="fas fa-circle-info" aria-hidden="true"></i><?= esc(lang('Profile.brandNoHeader')) ?></p>
        <?php endif; ?>
        <form action="<?= esc(base_url('dashboard/personal_info_page/brand'), 'attr') ?>" method="post" enctype="multipart/form-data" data-brand-form data-no-file="<?= esc(lang('Profile.noFileChosen'), 'attr') ?>">
            <?= csrf_field() ?>
            <div class="dash-brand-grid">
                <?php foreach ($slots as $slot): $inputId = 'brand_' . $slot; $current = $brand[$slot]; $shape = $slot === 'brand_logo' ? $brand['logoFrame']['shape'] : 'cover'; ?>
                    <div class="dash-brand-slot" data-brand-slot="<?= esc($slot, 'attr') ?>">
                        <div class="dash-brand-preview dash-brand-preview-<?= esc($shape, 'attr') ?>">
                            <img src="<?= $current ? esc(base_url('uploads/brand_assets/' . $current), 'attr') : '' ?>" alt="<?= esc(lang('Profile.currentAsset') . ': ' . $labels[$slot], 'attr') ?>" data-brand-thumb<?= $current ? '' : ' hidden' ?>>
                            <span class="dash-brand-empty" data-brand-empty<?= $current ? ' hidden' : '' ?>><i class="fas fa-image" aria-hidden="true"></i><?= esc(lang('Profile.noAsset')) ?></span>
                        </div>
                        <div class="dash-brand-controls">
                            <h3 class="dash-brand-label"><?= esc($labels[$slot]) ?></h3>
                            <input class="visually-hidden dash-brand-file" type="file" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($slot, 'attr') ?>" accept="image/jpeg,image/png,image/webp" data-brand-input="<?= esc($slot, 'attr') ?>"<?= field_error_attrs($fieldErrors ?? [], $slot, $inputId, $inputId . 'Hint') ?>>
                            <div class="dash-brand-pick">
                                <label class="btn btn-outline-primary btn-sm" for="<?= esc($inputId, 'attr') ?>"><i class="fas fa-upload" aria-hidden="true"></i><?= esc(lang($current ? 'Profile.replaceAsset' : 'Profile.chooseAsset')) ?></label>
                                <span class="dash-brand-filename" data-brand-filename aria-live="polite"><?= esc(lang('Profile.noFileChosen')) ?></span>
                            </div>
                            <p class="dash-hint" id="<?= esc($inputId, 'attr') ?>Hint"><?= esc(lang('Profile.brandRules')) ?></p>
                            <?= $this->setVar('field', $slot)->setVar('inputId', $inputId)->include('partials/feedback/field-error') ?>
                            <?php if ($current): ?>
                                <div class="form-check form-switch dash-brand-visible">
                                    <input type="hidden" name="visible[<?= esc($slot, 'attr') ?>]" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="visible[<?= esc($slot, 'attr') ?>]" value="1" id="<?= esc($inputId, 'attr') ?>Visible" data-brand-visible="<?= esc($slot, 'attr') ?>"<?= $brand['visible'][$slot] ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="<?= esc($inputId, 'attr') ?>Visible"><?= esc(lang('Profile.showAsset', [mb_strtolower($labels[$slot])])) ?></label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="dash-brand-actions">
                <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Profile.saving'), 'attr') ?>"><?= esc(lang('Profile.saveBrand')) ?></button>
            </div>
        </form>
        <?php if (in_array('brand_logo', $slots, true)): ?>
            <dialog class="dash-dialog dash-crop-dialog" id="logoCropper" aria-labelledby="logoCropperTitle" data-crop-shape="<?= esc($brand['logoFrame']['shape'], 'attr') ?>" data-crop-ratio="<?= esc((string) $brand['logoFrame']['ratio'], 'attr') ?>">
                <div class="dash-dialog-head">
                    <h2 id="logoCropperTitle"><?= esc(lang('Profile.cropTitle')) ?></h2>
                </div>
                <p class="dash-hint mt-0" id="logoCropperHint"><?= esc(lang('Profile.cropHint', [lang('Profile.cropShape_' . $brand['logoFrame']['shape'])])) ?></p>
                <canvas class="dash-crop-stage" tabindex="0" aria-describedby="logoCropperHint" aria-label="<?= esc(lang('Profile.cropArea'), 'attr') ?>" data-crop-canvas></canvas>
                <div class="dash-field mb-0">
                    <label for="logoCropZoom"><?= esc(lang('Profile.cropZoom')) ?></label>
                    <input class="form-range" type="range" id="logoCropZoom" min="0.3" max="4" step="0.01" value="1" data-crop-zoom>
                </div>
                <div class="dash-dialog-actions">
                    <button type="button" class="btn btn-outline-secondary" data-crop-reset><?= esc(lang('Profile.cropReset')) ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-crop-cancel><?= esc(lang('Profile.cancel')) ?></button>
                    <button type="button" class="btn btn-primary" data-crop-apply><?= esc(lang('Profile.cropApply')) ?></button>
                </div>
            </dialog>
        <?php endif; ?>
    <?php endif; ?>
</section>
