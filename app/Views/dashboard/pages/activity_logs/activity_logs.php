<?php
$query = static fn (array $changes) => '?' . http_build_query(array_filter(array_merge($filters, $changes), static fn ($value) => $value !== '' && $value !== null));
?>
<?= $this->extend('layouts/dashboard') ?>

<?= $this->section('content') ?>
    <main class="container-fluid px-4 pb-4 dash-home">
        <header class="dash-page-head">
            <p class="dash-eyebrow"><?= esc(lang('Audit.eyebrow')) ?></p>
            <h1><?= esc(lang('Audit.title')) ?></h1>
            <p class="text-muted mb-0"><?= esc(lang('Audit.lead')) ?></p>
        </header>

        <form class="dash-panel mb-4" method="get" action="<?= esc(base_url('dashboard/activity-logs'), 'attr') ?>" aria-label="<?= esc(lang('Audit.filter'), 'attr') ?>">
            <div class="dash-log-filters">
                <div class="dash-field">
                    <label for="logAction"><?= esc(lang('Audit.action')) ?></label>
                    <select class="form-select" id="logAction" name="action">
                        <option value=""><?= esc(lang('Audit.allActions')) ?></option>
                        <?php foreach ($actions as $code => $label): ?>
                            <option value="<?= esc($code, 'attr') ?>"<?= $filters['action'] === $code ? ' selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dash-field">
                    <label for="logOutcome"><?= esc(lang('Audit.outcome')) ?></label>
                    <select class="form-select" id="logOutcome" name="outcome">
                        <option value=""><?= esc(lang('Audit.allOutcomes')) ?></option>
                        <?php foreach ($outcomes as $code => $label): ?>
                            <option value="<?= esc($code, 'attr') ?>"<?= $filters['outcome'] === $code ? ' selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dash-field">
                    <label for="logActor"><?= esc(lang('Audit.actor')) ?></label>
                    <input class="form-control" id="logActor" name="actor" type="search" maxlength="64" value="<?= esc($filters['actor'], 'attr') ?>">
                </div>
                <div class="dash-field">
                    <label for="logTarget"><?= esc(lang('Audit.target')) ?></label>
                    <input class="form-control" id="logTarget" name="target" type="search" maxlength="32" value="<?= esc($filters['target'], 'attr') ?>">
                </div>
                <div class="dash-field">
                    <label for="logFrom"><?= esc(lang('Audit.from')) ?></label>
                    <input class="form-control" id="logFrom" name="from" type="date" value="<?= esc($filters['from'], 'attr') ?>">
                </div>
                <div class="dash-field">
                    <label for="logTo"><?= esc(lang('Audit.to')) ?></label>
                    <input class="form-control" id="logTo" name="to" type="date" value="<?= esc($filters['to'], 'attr') ?>">
                </div>
            </div>
            <div class="dash-actions">
                <button type="submit" class="btn btn-primary"><?= esc(lang('Audit.apply')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/activity-logs'), 'attr') ?>"><?= esc(lang('Audit.reset')) ?></a>
            </div>
        </form>

        <section class="dash-panel" aria-labelledby="logTableTitle">
            <div class="dash-panel-head">
                <h2 id="logTableTitle"><?= esc(lang('Audit.activity')) ?></h2>
                <span class="text-muted small"><?= esc(lang('Audit.records', [number_format($total, 0, ',', '.')])) ?></span>
            </div>
            <?php if ($rows === []): ?>
                <p class="text-muted mb-0"><?= esc(lang('Audit.empty')) ?></p>
            <?php else: ?>
                <div class="dash-table-wrap">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col"><?= esc(lang('Audit.colTime')) ?></th>
                                <th scope="col"><?= esc(lang('Audit.colActor')) ?></th>
                                <th scope="col"><?= esc(lang('Audit.colAction')) ?></th>
                                <th scope="col"><?= esc(lang('Audit.colTarget')) ?></th>
                                <th scope="col"><?= esc(lang('Audit.colOutcome')) ?></th>
                                <th scope="col"><?= esc(lang('Audit.colRef')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td class="text-nowrap"><?= esc(format_datetime_wib($row['occurred_at'])) ?></td>
                                    <td><?= $row['actor_id'] === null ? '<span class="text-muted">' . esc($row['actor_type'] === 'anonymous' ? lang('Audit.guest') : $row['actor_type']) . '</span>' : esc($row['actor_username'] ?? ('#' . $row['actor_id'])) ?></td>
                                    <td>
                                        <?= esc($actions[$row['action']] ?? $row['action']) ?>
                                        <?php if ($row['changed_fields'] !== null): ?>
                                            <br><span class="small text-muted"><?= esc(str_replace(',', ', ', $row['changed_fields'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= esc(trim(($row['target_type'] ?? '') . ' ' . ($row['target_id'] ?? '')) ?: '-') ?></td>
                                    <td>
                                        <span class="dash-tag<?= $row['outcome'] === 'success' ? '' : ' dash-tag-warn' ?>"><?= esc($outcomes[$row['outcome']] ?? $row['outcome']) ?></span>
                                        <?php if ($row['reason'] !== null): ?>
                                            <br><span class="small text-muted"><?= esc($row['reason']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><code><?= esc($row['request_id']) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pages > 1): ?>
                    <nav class="dash-actions mt-3" aria-label="<?= esc(lang('Audit.pages'), 'attr') ?>">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/activity-logs') . $query(['page' => $page - 1]), 'attr') ?>"><?= esc(lang('Audit.previous')) ?></a>
                        <?php endif; ?>
                        <span class="align-self-center small text-muted"><?= esc(lang('Audit.pageOf', [$page, $pages])) ?></span>
                        <?php if ($page < $pages): ?>
                            <a class="btn btn-outline-secondary" href="<?= esc(base_url('dashboard/activity-logs') . $query(['page' => $page + 1]), 'attr') ?>"><?= esc(lang('Audit.next')) ?></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
<?= $this->endSection() ?>
