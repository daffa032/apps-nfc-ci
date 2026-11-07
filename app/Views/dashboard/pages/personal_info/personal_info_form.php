<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main id="profilePage" class="container-fluid px-4 pb-4 dash-home" data-strings="<?= esc(json_encode(['requestFailed' => lang('Dashboard.requestFailed'), 'newPhotoPreview' => lang('Profile.newPhotoPreview'), 'newPhotoN' => lang('Profile.newPhotoN'), 'caption' => lang('Profile.caption'), 'cancel' => lang('Profile.cancel'), 'confirmDeletePhoto' => lang('Profile.confirmDeletePhoto'), 'confirmDeleteLink' => lang('Profile.confirmDeleteLink'), 'photoDeleteFailed' => lang('Profile.photoDeleteFailed'), 'photoDeleted' => lang('Profile.photoDeleted'), 'captionFailed' => lang('Profile.captionFailed'), 'captionSaved' => lang('Profile.captionSaved'), 'linkSaveFailed' => lang('Profile.linkSaveFailed')], JSON_UNESCAPED_UNICODE), 'attr') ?>">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Profile.eyebrow')) ?></p>
            <h1><?= esc(lang('Profile.firstTitle')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Profile.firstIntro')) ?></p>
        </header>

        <form id="userForm" class="dash-panel dash-profile-form" action="<?= esc(base_url('dashboard/add_profile'), 'attr') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <h2><?= esc(lang('Profile.cardInfo')) ?></h2>
            <div class="dash-field">
                <label for="inputProfilePicture"><?= esc(lang('Profile.photo')) ?></label>
                <div class="dash-photo-row">
                    <img id="imagePreviewForm" class="dash-photo" src="#" alt="<?= esc(lang('Profile.photoPreview'), 'attr') ?>" hidden>
                    <div class="dash-photo-placeholder" id="photoPlaceholder" aria-hidden="true"><i class="fas fa-user"></i></div>
                    <span class="dash-hint mt-0"><?= esc(lang('Profile.photoRules')) ?></span>
                </div>
                <input name="profile_image" class="form-control" id="inputProfilePicture" type="file" accept="image/jpeg,image/png,image/webp" required<?= field_error_attrs($fieldErrors ?? [], 'profile_image', 'inputProfilePicture') ?>>
                <?= $this->setVar('field', 'profile_image')->setVar('inputId', 'inputProfilePicture')->include('partials/feedback/field-error') ?>
            </div>
            <div class="dash-form-grid">
                <div class="dash-field">
                    <label for="inputname"><?= esc(lang('Profile.name')) ?></label>
                    <input name="nama" class="form-control" id="inputname" type="text" maxlength="255" value="<?= esc((string) old('nama', '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'nama', 'inputname') ?>>
                    <?= $this->setVar('field', 'nama')->setVar('inputId', 'inputname')->include('partials/feedback/field-error') ?>
                </div>
                <div class="dash-field">
                    <label for="inputcompany"><?= esc(lang('Profile.company')) ?></label>
                    <input name="company_name" class="form-control" id="inputcompany" type="text" value="<?= esc((string) old('company_name', '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'company_name', 'inputcompany') ?>>
                    <?= $this->setVar('field', 'company_name')->setVar('inputId', 'inputcompany')->include('partials/feedback/field-error') ?>
                </div>
                <div class="dash-field">
                    <label for="inputjob_title"><?= esc(lang('Profile.jobTitle')) ?></label>
                    <input name="job_title" class="form-control" id="inputjob_title" type="text" maxlength="100" value="<?= esc((string) old('job_title', '', false), 'attr') ?>"<?= field_error_attrs($fieldErrors ?? [], 'job_title', 'inputjob_title', 'jobTitleHint') ?>>
                    <p class="dash-hint" id="jobTitleHint"><?= esc(lang('Profile.jobTitleHint')) ?></p>
                    <?= $this->setVar('field', 'job_title')->setVar('inputId', 'inputjob_title')->include('partials/feedback/field-error') ?>
                </div>
                <div class="dash-field">
                    <label for="inputphone"><?= esc(lang('Profile.phone')) ?></label>
                    <input name="no_hp" class="form-control" id="inputphone" type="tel" value="<?= esc((string) old('no_hp', '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'no_hp', 'inputphone') ?>>
                    <?= $this->setVar('field', 'no_hp')->setVar('inputId', 'inputphone')->include('partials/feedback/field-error') ?>
                </div>
                <div class="dash-field">
                    <label for="inputno_whatsapp"><?= esc(lang('Profile.whatsapp')) ?></label>
                    <input name="no_whatsapp" class="form-control" id="inputno_whatsapp" type="tel" maxlength="20" value="<?= esc((string) old('no_whatsapp', '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'no_whatsapp', 'inputno_whatsapp') ?>>
                    <?= $this->setVar('field', 'no_whatsapp')->setVar('inputId', 'inputno_whatsapp')->include('partials/feedback/field-error') ?>
                </div>
                <div class="dash-field">
                    <label for="inputEmail"><?= esc(lang('Profile.email')) ?></label>
                    <input name="email" class="form-control" id="inputEmail" type="email" maxlength="255" value="<?= esc((string) old('email', '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'email', 'inputEmail') ?>>
                    <?= $this->setVar('field', 'email')->setVar('inputId', 'inputEmail')->include('partials/feedback/field-error') ?>
                </div>
                <div class="dash-field">
                    <label for="inputgoogle_map_link"><?= esc(lang('Profile.map')) ?></label>
                    <input name="google_map_link" class="form-control" id="inputgoogle_map_link" type="url" maxlength="2048" value="<?= esc((string) old('google_map_link', '', false), 'attr') ?>" required<?= field_error_attrs($fieldErrors ?? [], 'google_map_link', 'inputgoogle_map_link') ?>>
                    <?= $this->setVar('field', 'google_map_link')->setVar('inputId', 'inputgoogle_map_link')->include('partials/feedback/field-error') ?>
                </div>
            </div>
            <div class="dash-field">
                <label for="inputaddress"><?= esc(lang('Profile.address')) ?></label>
                <textarea name="address" class="form-control" id="inputaddress" rows="2" required<?= field_error_attrs($fieldErrors ?? [], 'address', 'inputaddress') ?>><?= esc((string) old('address', '', false)) ?></textarea>
                <?= $this->setVar('field', 'address')->setVar('inputId', 'inputaddress')->include('partials/feedback/field-error') ?>
            </div>
            <div class="dash-actions">
                <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Profile.saving'), 'attr') ?>"><?= esc(lang('Profile.save')) ?></button>
            </div>
        </form>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/profile-interactions.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
