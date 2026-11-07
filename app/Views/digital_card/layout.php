<?php
/**
 * Shared public card for the UI/UX 2026 layouts (profesional, hangat, portofolio, gelap).
 * Expects the PublicCardService::present() data plus $layout (one of TemplateCatalog layouts)
 * and $shareUrl (canonical public URL).
 * Every layout shows the same data: name, company, photo, phone, WhatsApp, email, address/map,
 * social links, gallery, save contact and share (AUDIT-DESIGN "Enam layout kartu publik"). The
 * parts live in digital_card/slots/, which uploaded HTML templates reuse.
 */
$p = $dataPersonal;
$name = (string) ($p['nama'] ?? '');
$initials = mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim(strip_tags($name))) ?: [], 0, 2)))) ?: '?';
$channels = ['instagram' => ['Instagram', 'fab fa-instagram'], 'facebook' => ['Facebook', 'fab fa-facebook'], 'twitter' => ['X', 'nfc-icon-x'], 'tiktok' => ['TikTok', 'fab fa-tiktok'], 'website' => [lang('Card.website'), 'fas fa-globe']];
$hasSocial = array_filter(array_intersect_key($socialMediaLinks, $channels)) !== [];
$this->setData(['p' => $p, 'name' => $name, 'initials' => $initials, 'channels' => $channels, 'hasSocial' => $hasSocial]);
?>
<?= $this->include('digital_card/frame-start') ?>
        <header class="nc-head">
<?= $this->include('digital_card/slots/photo') ?>
            <div>
                <h1 data-preview-field="nama"><?= esc($name) ?></h1>
                <p class="nc-title" data-preview-field="job_title"<?= ($p['job_title'] ?? '') === '' ? ' hidden' : '' ?>><?= esc($p['job_title'] ?? '') ?></p>
                <p class="nc-company" data-preview-field="company_name"<?= ($p['company_name'] ?? '') === '' ? ' hidden' : '' ?>><?= esc($p['company_name'] ?? '') ?></p>
            </div>
        </header>

        <div class="nc-body">
            <?php if ($layout === 'portofolio'): ?><?= $this->include('digital_card/slots/gallery') ?><?php endif; ?>

<?= $this->include('digital_card/slots/contact') ?>
<?= $this->include('digital_card/slots/social') ?>
            <?php if ($layout !== 'portofolio'): ?><?= $this->include('digital_card/slots/gallery') ?><?php endif; ?>

<?= $this->include('digital_card/slots/actions') ?>
        </div>
<?= $this->include('digital_card/frame-end') ?>
