<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Master.eyebrow')) ?></p>
            <h1><?= esc(lang('Master.templatesTitle')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Master.templatesLead')) ?></p>
        </header>

        <section class="dash-panel mb-4" aria-labelledby="slotLimitsTitle">
            <h2 id="slotLimitsTitle"><?= esc(lang('Master.slotCapacity')) ?></h2>
            <p class="dash-hint mt-0"><?= esc(lang('Master.limitsHint')) ?></p>
            <form action="<?= esc(base_url('dashboard/master/templates/limits'), 'attr') ?>" method="post" data-slot-form>
                <?= csrf_field() ?>
                <div class="dash-slot-grid">
                    <?php foreach ([
                        ['basic', 'max_active_basic', 'maxActiveBasic', $activeBasic, 'Master.slotBasicTitle', 'Master.slotBasicDesc', 'fas fa-gift'],
                        ['total', 'max_active_total', 'maxActiveTotal', $activeTotal, 'Master.slotTotalTitle', 'Master.slotTotalDesc', 'fas fa-layer-group'],
                    ] as [$key, $field, $inputId, $used, $title, $desc, $icon]): $limit = (int) $limits[$key]; $left = max(0, $limit - $used); ?>
                        <div class="dash-slot-card" data-slot-card data-used="<?= (int) $used ?>">
                            <div class="dash-slot-card-head">
                                <span class="dash-slot-icon" aria-hidden="true"><i class="<?= esc($icon, 'attr') ?>"></i></span>
                                <div>
                                    <h3><?= esc(lang($title)) ?></h3>
                                    <p><?= esc(lang($desc)) ?></p>
                                </div>
                            </div>
                            <p class="dash-slot-usage"><strong><?= (int) $used ?></strong> <span><?= esc(lang('Master.slotOf')) ?> <span data-slot-limit><?= $limit ?></span> <?= esc(lang('Master.slotActive')) ?></span></p>
                            <progress class="dash-slot-bar<?= $left === 0 ? ' is-full' : '' ?>" value="<?= (int) $used ?>" max="<?= max(1, $limit) ?>" aria-label="<?= esc(lang($title), 'attr') ?>" data-slot-bar></progress>
                            <p class="dash-slot-left<?= $left === 0 ? ' is-full' : '' ?>" data-slot-left data-text-left="<?= esc(lang('Master.slotLeft'), 'attr') ?>" data-text-full="<?= esc(lang('Master.slotFull'), 'attr') ?>"><?= esc($left === 0 ? lang('Master.slotFull') : lang('Master.slotLeft', [$left])) ?></p>
                            <div class="dash-field mb-0">
                                <label for="<?= esc($inputId, 'attr') ?>"><?= esc(lang($key === 'basic' ? 'Master.maxBasic' : 'Master.maxTotal')) ?></label>
                                <div class="input-group dash-slot-stepper">
                                    <button type="button" class="btn btn-outline-secondary" data-step="-1" aria-label="<?= esc(lang('Master.decrease'), 'attr') ?>"><i class="fas fa-minus" aria-hidden="true"></i></button>
                                    <input class="form-control text-center" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($field, 'attr') ?>" type="number" inputmode="numeric" min="<?= max(1, (int) $used) ?>" max="<?= \App\Services\Cards\TemplateCatalog::MAX_SLOT_LIMIT ?>" required value="<?= esc((string) old($field, (string) $limit, false), 'attr') ?>"<?= field_error_attrs($fieldErrors, $field, $inputId) ?>>
                                    <button type="button" class="btn btn-outline-secondary" data-step="1" aria-label="<?= esc(lang('Master.increase'), 'attr') ?>"><i class="fas fa-plus" aria-hidden="true"></i></button>
                                </div>
                                <?= $this->setVar('field', $field)->setVar('inputId', $inputId)->include('partials/feedback/field-error') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="dash-slot-actions">
                    <p class="dash-hint m-0" data-slot-dirty hidden><i class="fas fa-circle-info" aria-hidden="true"></i> <?= esc(lang('Master.limitsUnsaved')) ?></p>
                    <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Master.saving'), 'attr') ?>"><?= esc(lang('Master.saveLimits')) ?></button>
                </div>
            </form>
        </section>

        <section class="dash-panel mb-4" aria-labelledby="addTemplateTitle">
            <h2 id="addTemplateTitle"><?= esc(lang('Master.addTemplate')) ?></h2>
            <p class="text-muted small"><?= esc(lang('Master.uploadRules', [implode(', ', array_map(static fn ($t) => '<' . $t . '>', $allowedTags)), implode(', ', $allowedClasses), implode(' ', array_map(static fn ($p) => '{{' . $p . '}}', $placeholders))])) ?></p>
            <p class="text-muted small"><?= esc(lang('Master.optionalPlaceholders', [implode(' ', array_map(static fn ($p) => '{{' . $p . '}}', $optionalPlaceholders))])) ?></p>
            <form class="dash-template-form" action="<?= esc(base_url('dashboard/master/templates/create'), 'attr') ?>" method="post" enctype="multipart/form-data" data-template-form data-preview-url="<?= esc(base_url('dashboard/master/templates/preview'), 'attr') ?>">
                <?= csrf_field() ?>
                <div class="dash-template-fields">
                    <div class="dash-form-grid">
                        <div class="dash-field">
                            <label for="newTemplateName"><?= esc(lang('Master.templateName')) ?></label>
                            <input class="form-control" id="newTemplateName" name="nama_template" maxlength="32" required value="<?= esc($renameTemplateId > 0 ? '' : (string) old('nama_template', '', false), 'attr') ?>"<?= $renameTemplateId > 0 ? '' : field_error_attrs($fieldErrors, 'nama_template', 'newTemplateName') ?>>
                            <?php if ($renameTemplateId === 0): ?><?= $this->setVar('field', 'nama_template')->setVar('inputId', 'newTemplateName')->include('partials/feedback/field-error') ?><?php endif; ?>
                        </div>
                        <div class="dash-field">
                            <label for="newTemplatePlan"><?= esc(lang('Master.plan')) ?></label>
                            <select class="form-select" id="newTemplatePlan" name="plan_code"<?= field_error_attrs($fieldErrors, 'plan_code', 'newTemplatePlan') ?>>
                                <option value="pro"<?= old('plan_code', 'pro', false) === 'pro' ? ' selected' : '' ?>>Pro</option>
                                <option value="basic"<?= old('plan_code', 'pro', false) === 'basic' ? ' selected' : '' ?>>Basic</option>
                            </select>
                            <?= $this->setVar('field', 'plan_code')->setVar('inputId', 'newTemplatePlan')->include('partials/feedback/field-error') ?>
                        </div>
                    </div>
                    <div class="dash-field">
                        <label for="newTemplateDescription"><?= esc(lang('Master.description')) ?></label>
                        <input class="form-control" id="newTemplateDescription" name="description" maxlength="160" value="<?= esc((string) old('description', '', false), 'attr') ?>"<?= field_error_attrs($fieldErrors, 'description', 'newTemplateDescription') ?>>
                        <?= $this->setVar('field', 'description')->setVar('inputId', 'newTemplateDescription')->include('partials/feedback/field-error') ?>
                    </div>
                    <div class="dash-field">
                        <label for="newTemplateFile"><?= esc(lang('Master.htmlFile')) ?></label>
                        <input class="form-control" id="newTemplateFile" name="template_file" type="file" accept=".html,text/html" required<?= field_error_attrs($fieldErrors, 'template_file', 'newTemplateFile', 'newTemplateFileHint') ?>>
                        <p class="dash-hint" id="newTemplateFileHint"><?= esc(lang('Master.htmlFileHint')) ?></p>
                        <?= $this->setVar('field', 'template_file')->setVar('inputId', 'newTemplateFile')->include('partials/feedback/field-error') ?>
                    </div>
                    <fieldset class="dash-field">
                        <legend class="form-label"><?= esc(lang('Master.initialStatus')) ?></legend>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active" id="newTemplateInactive" value="0"<?= old('is_active', '0', false) !== '1' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="newTemplateInactive"><?= esc(lang('Master.startInactive')) ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active" id="newTemplateActive" value="1"<?= old('is_active', '0', false) === '1' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="newTemplateActive"><?= esc(lang('Master.startActive')) ?></label>
                        </div>
                    </fieldset>
                    <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Master.saving'), 'attr') ?>" data-submit-label data-label-active="<?= esc(lang('Master.addActive'), 'attr') ?>" data-label-inactive="<?= esc(lang('Master.addInactive'), 'attr') ?>"><?= esc(lang('Master.addInactive')) ?></button>
                </div>
                <div class="dash-template-preview">
                    <p class="small fw-semibold mb-2"><?= esc(lang('Master.previewSample')) ?></p>
                    <p class="small text-muted mb-2" data-preview-status role="status" aria-live="polite" data-text-pick="<?= esc(lang('Master.pickFile'), 'attr') ?>" data-text-rules="<?= esc(lang('Master.fileRules'), 'attr') ?>" data-text-checking="<?= esc(lang('Master.checking'), 'attr') ?>" data-text-valid="<?= esc(lang('Master.valid'), 'attr') ?>" data-text-failed="<?= esc(lang('Master.previewFailed'), 'attr') ?>"><?= esc(lang('Master.pickFile')) ?></p>
                    <iframe class="dash-template-frame" title="<?= esc(lang('Master.previewFrame'), 'attr') ?>" sandbox src="about:blank" hidden></iframe>
                </div>
            </form>
            <details class="mt-3">
                <summary><?= esc(lang('Master.sampleFile')) ?></summary>
                <pre class="dash-code"><code><?= esc($sampleTemplate) ?></code></pre>
            </details>
        </section>

        <section class="dash-panel" aria-labelledby="masterTemplatesTitle">
            <h2 id="masterTemplatesTitle" class="visually-hidden"><?= esc(lang('Master.templateList')) ?></h2>
            <div class="dash-table-wrap">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= esc(lang('Master.colTemplate')) ?></th>
                            <th scope="col"><?= esc(lang('Master.colPlan')) ?></th>
                            <th scope="col"><?= esc(lang('Master.colOrder')) ?></th>
                            <th scope="col"><?= esc(lang('Master.colStatus')) ?></th>
                            <th scope="col"><?= esc(lang('Master.colActions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): $id = (int) $template['id_template']; $active = (int) ($template['is_active'] ?? 1) === 1; ?>
                            <tr>
                                <td>
                                    <strong><?= esc($template['nama_template']) ?></strong>
                                    <?php if (\App\Services\Cards\TemplateCatalog::isCustom($template['nama_file'])): ?><span class="dash-tag dash-tag-neutral ms-1">HTML</span><?php endif; ?>
                                    <?php $description = \App\Services\Cards\TemplateCatalog::description($template); ?><?php if ($description !== ''): ?><br><span class="small text-muted"><?= esc($description) ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <label class="visually-hidden" for="plan<?= $id ?>"><?= esc(lang('Master.planOf', [$template['nama_template']])) ?></label>
                                    <select class="form-select form-select-sm" id="plan<?= $id ?>" name="plan_code" form="template<?= $id ?>">
                                        <option value="basic"<?= ($template['plan_code'] ?? 'basic') === 'basic' ? ' selected' : '' ?>>Basic</option>
                                        <option value="pro"<?= ($template['plan_code'] ?? 'basic') === 'pro' ? ' selected' : '' ?>>Pro</option>
                                    </select>
                                </td>
                                <td>
                                    <label class="visually-hidden" for="order<?= $id ?>"><?= esc(lang('Master.orderOf', [$template['nama_template']])) ?></label>
                                    <input class="form-control form-control-sm" id="order<?= $id ?>" name="display_order" form="template<?= $id ?>" type="number" min="0" max="999" value="<?= (int) ($template['display_order'] ?? 0) ?>">
                                </td>
                                <td><span class="dash-tag<?= $active ? '' : ' dash-tag-neutral' ?>"><?= esc(lang($active ? 'Master.active' : 'Master.inactive')) ?></span></td>
                                <td>
                                    <div class="dash-table-actions">
                                        <form id="template<?= $id ?>" method="post" action="<?= esc(base_url('dashboard/master/templates/' . $id), 'attr') ?>" class="dash-table-actions">
                                            <?= csrf_field() ?>
                                            <button type="submit" name="is_active" value="<?= $active ? '1' : '0' ?>" class="btn btn-outline-secondary btn-sm"><?= esc(lang('Master.save')) ?></button>
                                            <button type="submit" name="is_active" value="<?= $active ? '0' : '1' ?>" class="btn btn-sm <?= $active ? 'btn-outline-danger' : 'btn-primary' ?>"><?= esc(lang($active ? 'Master.disable' : 'Master.enable')) ?></button>
                                        </form>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-rename-open="rename<?= $id ?>" aria-label="<?= esc(lang('Master.renameOf', [$template['nama_template']]), 'attr') ?>"><i class="fas fa-pen" aria-hidden="true"></i> <?= esc(lang('Master.rename')) ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php // One rename dialog per template; a failed rename reopens its dialog with the field error. ?>
        <?php foreach ($templates as $template): $id = (int) $template['id_template']; $isRetry = $renameTemplateId === $id; $inputId = 'renameInput' . $id; ?>
            <dialog class="dash-dialog dash-rename-dialog" id="rename<?= $id ?>" aria-labelledby="rename<?= $id ?>Title"<?= $isRetry ? ' data-rename-reopen' : '' ?>>
                <form method="post" action="<?= esc(base_url('dashboard/master/templates/' . $id . '/rename'), 'attr') ?>">
                    <?= csrf_field() ?>
                    <div class="dash-dialog-head">
                        <h2 id="rename<?= $id ?>Title"><?= esc(lang('Master.renameTitle')) ?></h2>
                        <button type="button" class="btn-close" data-rename-close aria-label="<?= esc(lang('Master.cancel'), 'attr') ?>"></button>
                    </div>
                    <p class="dash-hint mt-0"><?= esc(lang('Master.renameHint')) ?></p>
                    <div class="dash-field">
                        <label for="<?= esc($inputId, 'attr') ?>"><?= esc(lang('Master.newName')) ?></label>
                        <input class="form-control" id="<?= esc($inputId, 'attr') ?>" name="nama_template" maxlength="32" required value="<?= esc($isRetry ? (string) old('nama_template', (string) $template['nama_template'], false) : (string) $template['nama_template'], 'attr') ?>"<?= $isRetry ? field_error_attrs($fieldErrors, 'nama_template', $inputId) : '' ?>>
                        <?php if ($isRetry): ?><?= $this->setVar('field', 'nama_template')->setVar('inputId', $inputId)->include('partials/feedback/field-error') ?><?php endif; ?>
                    </div>
                    <div class="dash-dialog-actions">
                        <button type="button" class="btn btn-outline-secondary" data-rename-close><?= esc(lang('Master.cancel')) ?></button>
                        <button type="submit" class="btn btn-primary" data-busy-label="<?= esc(lang('Master.saving'), 'attr') ?>"><?= esc(lang('Master.saveName')) ?></button>
                    </div>
                </form>
            </dialog>
        <?php endforeach; ?>
        <div class="dash-notice mt-4"><?= esc(lang('Master.templatesNotice', [$limits['basic'], $limits['total']])) ?></div>
    </main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= esc(asset_url('dashboard/js/busy-submit.js'), 'attr') ?>"></script>
    <script src="<?= esc(asset_url('dashboard/js/master-templates.js'), 'attr') ?>"></script>
<?= $this->endSection() ?>
