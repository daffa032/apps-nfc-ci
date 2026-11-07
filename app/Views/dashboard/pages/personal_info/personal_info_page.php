<?php
$channels = [
    'instagram' => ['Instagram', 'fab fa-instagram'],
    'facebook' => ['Facebook', 'fab fa-facebook'],
    'twitter' => ['X', 'nfc-icon-x'],
    'tiktok' => ['TikTok', 'fab fa-tiktok'],
    'website' => [lang('Profile.website'), 'fas fa-globe'],
];
$photo = $userPersonal['profile_image'] ?? null;
$galleryColumns = (int) ($userPersonal['gallery_columns'] ?? 1);
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main id="profilePage" class="container-fluid px-4 pb-4 dash-home"
          data-delete-image-url="<?= esc(base_url('dashboard/personal_info_page/delete'), 'attr') ?>"
          data-caption-url="<?= esc(base_url('dashboard/personal_info_page/caption'), 'attr') ?>"
          data-save-social-url="<?= esc(base_url('dashboard/personal_info_page/save_social'), 'attr') ?>"
          data-delete-social-url="<?= esc(base_url('dashboard/personal_info_page/delete_social'), 'attr') ?>"
          data-csrf-name="<?= esc(csrf_token(), 'attr') ?>"
          data-csrf-hash="<?= esc(csrf_hash(), 'attr') ?>"
          data-strings="<?= esc(json_encode(['requestFailed' => lang('Dashboard.requestFailed'), 'newPhotoPreview' => lang('Profile.newPhotoPreview'), 'newPhotoN' => lang('Profile.newPhotoN'), 'caption' => lang('Profile.caption'), 'cancel' => lang('Profile.cancel'), 'confirmDeletePhoto' => lang('Profile.confirmDeletePhoto'), 'confirmDeleteLink' => lang('Profile.confirmDeleteLink'), 'photoDeleteFailed' => lang('Profile.photoDeleteFailed'), 'photoDeleted' => lang('Profile.photoDeleted'), 'captionFailed' => lang('Profile.captionFailed'), 'captionSaved' => lang('Profile.captionSaved'), 'linkSaveFailed' => lang('Profile.linkSaveFailed')], JSON_UNESCAPED_UNICODE), 'attr') ?>">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Profile.eyebrow')) ?></p>
            <h1><?= esc(lang('Profile.title')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Profile.intro')) ?></p>
        </header>

        <div class="dash-stack">
            <section class="dash-panel" aria-labelledby="cardLanguageTitle">
                <h2 id="cardLanguageTitle"><?= esc(lang('Profile.cardLanguage')) ?></h2>
                <form action="<?= esc(base_url('dashboard/personal_info_page/language'), 'attr') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="dash-field mb-0">
                        <label for="inputCardLocale"><?= esc(lang('Profile.cardLanguageChoice')) ?></label>
                        <?php $cardLocale = (string) old('card_locale', $userPersonal['card_locale'] ?? 'id', false); ?>
                        <div class="dash-actions">
                            <select class="form-select w-auto" id="inputCardLocale" name="card_locale"<?= field_error_attrs($fieldErrors ?? [], 'card_locale', 'inputCardLocale', 'cardLocaleHint') ?>>
                                <?php foreach (['id' => lang('Locale.id'), 'en' => lang('Locale.en')] as $code => $label): ?>
                                    <option value="<?= esc($code, 'attr') ?>"<?= ($cardLocale === 'en' ? $code === 'en' : $code === 'id') ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-primary" data-busy-label="<?= esc(lang('Profile.saving'), 'attr') ?>"><?= esc(lang('Profile.saveCardLanguage')) ?></button>
                        </div>
                        <p class="dash-hint" id="cardLocaleHint"><?= esc(lang('Profile.cardLanguageHint')) ?></p>
                        <?= $this->setVar('field', 'card_locale')->setVar('inputId', 'inputCardLocale')->include('partials/feedback/field-error') ?>
                    </div>
                </form>
            </section>
            <div class="dash-editor">
            <form id="userForm" class="dash-panel dash-profile-form" action="<?= esc(base_url('dashboard/personal_info_page/save_edit_profile'), 'attr') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <h2><?= esc(lang('Profile.cardInfo')) ?></h2>
                <div class="dash-field">
                    <label for="inputProfilePicture"><?= esc(lang('Profile.photo')) ?></label>
                    <div class="dash-photo-row">
                        <img id="imagePreviewForm" class="dash-photo" src="<?= esc($photo ? base_url('uploads/profile_images/' . $photo) : '#', 'attr') ?>" alt="<?= esc(lang('Profile.photoPreview'), 'attr') ?>"<?= $photo ? '' : ' hidden' ?>>
                        <div class="dash-photo-placeholder" id="photoPlaceholder" aria-hidden="true"<?= $photo ? ' hidden' : '' ?>><i class="fas fa-user"></i></div>
                        <span class="dash-hint mt-0"><?= esc(lang('Profile.photoRules')) ?></span>
                    </div>
                    <input name="profile_image" class="form-control" id="inputProfilePicture" type="file" accept="image/jpeg,image/png,image/webp"<?= field_error_attrs($fieldErrors ?? [], 'profile_image', 'inputProfilePicture', 'photoHint') ?>>
                    <p class="dash-hint" id="photoHint"><?= esc(lang('Profile.photoKeep')) ?></p>
                    <?= $this->setVar('field', 'profile_image')->setVar('inputId', 'inputProfilePicture')->include('partials/feedback/field-error') ?>
                </div>
                <div class="dash-form-grid">
                    <div class="dash-field">
                        <label for="inputname"><?= esc(lang('Profile.name')) ?></label>
                        <input name="nama" class="form-control" id="inputname" type="text" maxlength="255" value="<?= esc((string) old('nama', $userPersonal['nama'] ?? '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'nama', 'inputname') ?>>
                        <?= $this->setVar('field', 'nama')->setVar('inputId', 'inputname')->include('partials/feedback/field-error') ?>
                    </div>
                    <div class="dash-field">
                        <label for="inputcompany"><?= esc(lang('Profile.company')) ?></label>
                        <input name="company_name" class="form-control" id="inputcompany" type="text" value="<?= esc((string) old('company_name', $userPersonal['company_name'] ?? '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'company_name', 'inputcompany') ?>>
                        <?= $this->setVar('field', 'company_name')->setVar('inputId', 'inputcompany')->include('partials/feedback/field-error') ?>
                    </div>
                    <div class="dash-field">
                        <label for="inputjob_title"><?= esc(lang('Profile.jobTitle')) ?></label>
                        <input name="job_title" class="form-control" id="inputjob_title" type="text" maxlength="100" value="<?= esc((string) old('job_title', $userPersonal['job_title'] ?? '', false), 'attr') ?>"<?= field_error_attrs($fieldErrors ?? [], 'job_title', 'inputjob_title', 'jobTitleHint') ?>>
                        <p class="dash-hint" id="jobTitleHint"><?= esc(lang('Profile.jobTitleHint')) ?></p>
                        <?= $this->setVar('field', 'job_title')->setVar('inputId', 'inputjob_title')->include('partials/feedback/field-error') ?>
                    </div>
                    <div class="dash-field">
                        <label for="inputphone"><?= esc(lang('Profile.phone')) ?></label>
                        <input name="no_hp" class="form-control" id="inputphone" type="tel" value="<?= esc((string) old('no_hp', $userPersonal['no_hp'] ?? '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'no_hp', 'inputphone') ?>>
                        <?= $this->setVar('field', 'no_hp')->setVar('inputId', 'inputphone')->include('partials/feedback/field-error') ?>
                    </div>
                    <div class="dash-field">
                        <label for="inputno_whatsapp"><?= esc(lang('Profile.whatsapp')) ?></label>
                        <input name="no_whatsapp" class="form-control" id="inputno_whatsapp" type="tel" maxlength="20" value="<?= esc((string) old('no_whatsapp', $userPersonal['no_whatsapp'] ?? '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'no_whatsapp', 'inputno_whatsapp') ?>>
                        <?= $this->setVar('field', 'no_whatsapp')->setVar('inputId', 'inputno_whatsapp')->include('partials/feedback/field-error') ?>
                    </div>
                    <div class="dash-field">
                        <label for="inputEmail"><?= esc(lang('Profile.email')) ?></label>
                        <input name="email" class="form-control" id="inputEmail" type="email" maxlength="255" value="<?= esc((string) old('email', $userPersonal['email'] ?? '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'email', 'inputEmail') ?>>
                        <?= $this->setVar('field', 'email')->setVar('inputId', 'inputEmail')->include('partials/feedback/field-error') ?>
                    </div>
                    <div class="dash-field">
                        <label for="inputgoogle_map_link"><?= esc(lang('Profile.map')) ?></label>
                        <input name="google_map_link" class="form-control" id="inputgoogle_map_link" type="url" maxlength="2048" value="<?= esc((string) old('google_map_link', $userPersonal['google_map_link'] ?? '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'google_map_link', 'inputgoogle_map_link') ?>>
                        <?= $this->setVar('field', 'google_map_link')->setVar('inputId', 'inputgoogle_map_link')->include('partials/feedback/field-error') ?>
                    </div>
                </div>
                <div class="dash-field">
                    <label for="inputaddress"><?= esc(lang('Profile.address')) ?></label>
                    <textarea name="address" class="form-control" id="inputaddress" rows="2" required<?= field_error_attrs($fieldErrors ?? [], 'address', 'inputaddress') ?>><?= esc((string) old('address', $userPersonal['address'] ?? '', false)) ?></textarea>
                    <?= $this->setVar('field', 'address')->setVar('inputId', 'inputaddress')->include('partials/feedback/field-error') ?>
                </div>
                <div class="dash-actions">
                    <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Profile.saving'), 'attr') ?>"><?= esc(lang('Profile.save')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= esc(base_url(rawurlencode($username)), 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc(lang('Profile.viewCard')) ?></a>
                </div>
            </form>

            <?php // Live preview of the current design (PRD PV-01): the saved card, with unsaved form values applied in the browser. ?>
            <section class="dash-panel dash-live-preview" aria-labelledby="livePreviewTitle" data-live-preview>
                <div class="dash-panel-head">
                    <h2 id="livePreviewTitle"><?= esc(lang('Profile.previewTitle')) ?></h2>
                    <span class="dash-tag dash-tag-warn" data-preview-unsaved hidden><?= esc(lang('Profile.unsaved')) ?></span>
                </div>
                <p class="dash-hint mt-0"><?= esc(lang('Profile.previewHint', [$brand['templateName'] !== '' ? $brand['templateName'] : '-'])) ?></p>
                <div class="dash-live-frame-wrap">
                    <iframe class="dash-live-frame" title="<?= esc(lang('Profile.previewFrame'), 'attr') ?>" src="<?= esc(base_url('dashboard/personal_info_page/preview'), 'attr') ?>" data-preview-frame></iframe>
                </div>
                <div class="dash-actions mt-3">
                    <button type="button" class="btn btn-outline-secondary" data-preview-full><?= esc(lang('Profile.viewFull')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/templates'), 'attr') ?>"><?= esc(lang('Profile.changeDesign')) ?></a>
                </div>
            </section>
            </div>

            <?= $this->include('dashboard/pages/personal_info/brand_assets') ?>

            <div class="dash-profile-lower">
                <section class="dash-panel" aria-labelledby="socialTitle">
                    <h2 id="socialTitle"><?= esc(lang('Profile.social')) ?></h2>
                    <p class="dash-hint mt-0 mb-3"><?= esc(lang('Profile.socialHint')) ?></p>
                    <div class="dash-social-grid">
                        <?php foreach ($channels as $key => [$label, $icon]): ?>
                            <div class="dash-social-field">
                                <span class="dash-social-name"><i class="<?= esc($icon, 'attr') ?>" aria-hidden="true"></i><?= esc($label) ?></span>
                                <div class="dash-field">
                                    <label class="visually-hidden" for="<?= esc($key, 'attr') ?>-link"><?= esc(lang('Profile.socialLinkOf', [$label])) ?></label>
                                    <input class="form-control" type="url" id="<?= esc($key, 'attr') ?>-link" value="<?= esc($socialMediaLinks[$key] ?? '', 'attr') ?>" placeholder="https://" maxlength="500">
                                </div>
                                <div class="dash-actions">
                                    <button type="button" class="btn btn-outline-primary" data-action="save-social" data-channel="<?= esc($key, 'attr') ?>" aria-label="<?= esc(lang('Profile.saveLinkOf', [$label]), 'attr') ?>"><?= esc(lang('Profile.saveBtn')) ?></button>
                                    <button type="button" class="btn btn-outline-danger" data-action="delete-social" data-channel="<?= esc($key, 'attr') ?>" aria-label="<?= esc(lang('Profile.deleteLinkOf', [$label]), 'attr') ?>"<?= empty($socialMediaLinks[$key]) ? ' disabled' : '' ?>><?= esc(lang('Profile.deleteBtn')) ?></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="dash-panel" aria-labelledby="galleryTitle">
                    <h2 id="galleryTitle"><?= esc(lang('Profile.gallery')) ?></h2>
                    <form class="dash-field" action="<?= esc(base_url('dashboard/personal_info_page/gallery_layout'), 'attr') ?>" method="post">
                        <?= csrf_field() ?>
                        <label for="galleryColumns"><?= esc(lang('Profile.galleryLayout')) ?></label>
                        <div class="dash-actions">
                            <select class="form-select w-auto" id="galleryColumns" name="gallery_columns" aria-describedby="galleryColumnsHint">
                                <option value="1"<?= $galleryColumns === 1 ? ' selected' : '' ?>><?= esc(lang('Profile.oneColumn')) ?></option>
                                <option value="2"<?= $galleryColumns === 2 ? ' selected' : '' ?>><?= esc(lang('Profile.twoColumns')) ?></option>
                            </select>
                            <button type="submit" class="btn btn-outline-primary"><?= esc(lang('Profile.saveLayout')) ?></button>
                        </div>
                        <p class="dash-hint" id="galleryColumnsHint"><?= esc(lang('Profile.layoutHint')) ?></p>
                    </form>

                    <?php if (empty($images)): ?>
                        <p class="dash-gallery-empty mb-3"><?= esc(lang('Profile.galleryEmpty')) ?></p>
                    <?php else: ?>
                        <div class="dash-gallery mb-3<?= $galleryColumns === 1 ? ' dash-gallery-one' : '' ?>">
                            <?php foreach ($images as $index => $image): ?>
                                <div class="dash-gallery-item image-row">
                                    <?php if ($image['images']): ?>
                                        <img src="<?= esc(base_url('uploads/multiple_images/' . $image['images']), 'attr') ?>" alt="<?= esc($image['caption'] ?: lang('Profile.galleryPhotoN', [$index + 1]), 'attr') ?>" data-action="preview-gallery">
                                    <?php else: ?>
                                        <div class="dash-gallery-placeholder" aria-hidden="true"><i class="fas fa-image fa-2x"></i></div>
                                    <?php endif; ?>
                                    <div class="dash-field">
                                        <label for="caption<?= esc((string) $image['id_images'], 'attr') ?>"><?= esc(lang('Profile.captionN', [$index + 1])) ?></label>
                                        <input class="form-control" id="caption<?= esc((string) $image['id_images'], 'attr') ?>" type="text" maxlength="255" value="<?= esc($image['caption'] ?? '', 'attr') ?>">
                                    </div>
                                    <div class="dash-actions">
                                        <button type="button" class="btn btn-outline-primary btn-sm" data-action="save-caption" data-id="<?= esc((string) $image['id_images'], 'attr') ?>" aria-label="<?= esc(lang('Profile.saveCaptionN', [$index + 1]), 'attr') ?>"><?= esc(lang('Profile.saveBtn')) ?></button>
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-row" data-id="<?= esc((string) $image['id_images'], 'attr') ?>" aria-label="<?= esc(lang('Profile.deletePhotoN', [$index + 1]), 'attr') ?>"><?= esc(lang('Profile.deleteBtn')) ?></button>
                                    </div>
                                    <p class="small text-success mt-2 mb-0" data-caption-status role="status" aria-live="polite"></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form id="uploadForm" action="<?= esc(base_url('dashboard/personal_info_page/upload'), 'attr') ?>" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div id="newImageRows" class="dash-gallery<?= $galleryColumns === 1 ? ' dash-gallery-one' : '' ?>"></div>
                        <p class="dash-hint mb-3"><?= esc(lang('Profile.uploadRules')) ?></p>
                        <div class="dash-actions">
                            <button type="button" id="addRow" class="btn btn-outline-secondary"><?= esc(lang('Profile.addPhoto')) ?></button>
                            <button type="submit" class="btn btn-primary" id="uploadSubmit" data-busy-label="<?= esc(lang('Profile.uploading'), 'attr') ?>" hidden><?= esc(lang('Profile.saveGallery')) ?></button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </main>

    <dialog class="dash-dialog dash-preview-dialog" id="fullPreview" aria-labelledby="fullPreviewTitle">
        <div class="dash-dialog-head">
            <h2 id="fullPreviewTitle"><?= esc(lang('Profile.previewTitle')) ?></h2>
            <button type="button" class="btn btn-outline-secondary" data-dialog-close><?= esc(lang('Dashboard.close')) ?></button>
        </div>
        <iframe class="dash-dialog-frame" title="<?= esc(lang('Profile.previewFrame'), 'attr') ?>" src="about:blank"></iframe>
    </dialog>

    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel"><?= esc(lang('Profile.photoPreviewTitle')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('Dashboard.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="#" alt="<?= esc(lang('Profile.photoPreviewTitle'), 'attr') ?>" class="modal-img">
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/profile-interactions.js'), 'attr') ?>"></script>
    <script src="<?= esc(asset_url('dashboard/js/logo-cropper.js'), 'attr') ?>"></script>
    <script src="<?= esc(asset_url('dashboard/js/live-preview.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
