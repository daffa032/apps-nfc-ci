<?php
/**
 * Tampilan kartu (PRD TH-01..TH-03). Each tile offers Pratinjau (dialog with the owner's own card
 * in that layout, nothing saved) and Pakai (POST + CSRF). Pro layouts stay previewable for Basic.
 */
$allowedCount = count(array_filter($templates, static fn ($t) => $t['allowed'] && \App\Services\Cards\TemplateSelectionService::isActive($t)));
$planLabel = $planCode === 'pro' ? 'Pro' : 'Basic';
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Templates.eyebrow')) ?></p>
            <h1 class="dash-title-strong"><?= esc(lang('Templates.title')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Templates.intro', [$planLabel, $allowedCount])) ?></p>
        </header>

        <?php if ($templates === []): ?>
            <div class="dash-notice" role="status"><?= esc(lang('Templates.empty')) ?></div>
        <?php else: ?>
            <div class="dash-theme-grid">
                <?php foreach ($templates as $template): ?>
                    <?php
                    $selected = $template['nama_file'] === $selectedTemplate;
                    $isPro = ($template['plan_code'] ?? 'basic') === 'pro';
                    $inactive = !\App\Services\Cards\TemplateSelectionService::isActive($template);
                    $name = (string) $template['nama_template'];
                    $description = \App\Services\Cards\TemplateCatalog::description($template);
                    ?>
                    <article class="dash-theme-tile<?= $selected ? ' is-selected' : '' ?>">
                        <div class="dash-theme-stage">
                            <?php $previewImage = \App\Services\Cards\TemplateCatalog::previewImage($template); ?>
                            <?php if ($previewImage !== null): ?>
                                <img src="<?= esc(base_url('uploads/templates/' . $previewImage), 'attr') ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <div class="dash-theme-swatch dash-swatch-<?= esc(substr((string) $template['nama_file'], 7), 'attr') ?>" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="dash-theme-details">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <h2><?= esc($name) ?></h2>
                                <span class="dash-tag<?= $selected ? '' : ($isPro && !$inactive ? ' nfc-badge-pro' : ' dash-tag-neutral') ?>"><?= esc($selected ? lang('Templates.inUse') : ($inactive ? lang('Templates.inactive') : ($isPro ? 'Pro' : 'Basic'))) ?></span>
                            </div>
                            <?php if ($description !== ''): ?>
                                <p class="text-muted"><?= esc($description) ?></p>
                            <?php endif; ?>
                            <div class="dash-theme-actions">
                                <button type="button" class="btn btn-outline-secondary" data-preview-url="<?= esc(base_url('dashboard/templates/preview/' . (int) $template['id_template']), 'attr') ?>" data-preview-name="<?= esc($name, 'attr') ?>" data-preview-id="<?= (int) $template['id_template'] ?>" data-preview-allowed="<?= $template['allowed'] && !$selected ? '1' : '0' ?>" data-preview-selected="<?= $selected ? '1' : '0' ?>" aria-label="<?= esc(lang('Templates.previewOf', [$name]), 'attr') ?>"><?= esc(lang('Templates.preview')) ?></button>
                                <?php if ($selected): ?>
                                    <button type="button" class="btn btn-outline-secondary" disabled><?= esc(lang('Templates.inUse')) ?></button>
                                <?php elseif ($template['allowed']): ?>
                                    <form action="<?= esc(base_url('dashboard/templates/update_templates'), 'attr') ?>" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_template" value="<?= (int) $template['id_template'] ?>">
                                        <button type="submit" class="btn btn-outline-secondary" aria-label="<?= esc(lang('Templates.useOf', [$name]), 'attr') ?>"><?= esc(lang('Templates.use')) ?></button>
                                    </form>
                                <?php else: ?>
                                    <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/billing'), 'attr') ?>" aria-label="<?= esc(lang('Templates.proOnlyOf', [$name]), 'attr') ?>"><?= esc(lang('Templates.proPlan')) ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="dash-notice mt-4"><?= esc(lang('Templates.notice')) ?></div>

        <dialog class="dash-dialog" id="themePreview" aria-labelledby="themePreviewTitle" data-title-template="<?= esc(lang('Templates.previewTitleNamed', ['{name}']), 'attr') ?>">
            <div class="dash-dialog-head">
                <h2 id="themePreviewTitle"><?= esc(lang('Templates.previewTitle')) ?></h2>
                <button type="button" class="btn btn-outline-secondary" data-dialog-close><?= esc(lang('Dashboard.close')) ?></button>
            </div>
            <p class="small text-muted mb-2"><?= esc(lang('Templates.previewHint')) ?></p>
            <iframe class="dash-dialog-frame" title="<?= esc(lang('Templates.previewFrame'), 'attr') ?>" src="about:blank"></iframe>
            <div class="dash-dialog-actions">
                <form action="<?= esc(base_url('dashboard/templates/update_templates'), 'attr') ?>" method="post" data-preview-form hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_template" value="">
                    <button type="submit" class="btn btn-primary"><?= esc(lang('Templates.useThis')) ?></button>
                </form>
                <a class="btn btn-primary" href="<?= esc(base_url('dashboard/billing'), 'attr') ?>" data-preview-upgrade hidden><?= esc(lang('Templates.seePro')) ?></a>
            </div>
        </dialog>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/templates.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
