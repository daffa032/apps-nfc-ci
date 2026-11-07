<?php
/**
 * The two original cards, White and Black (nama_file index-white.php / index-dark.php), rebuilt on
 * the shared card structure: frame-start/frame-end and the slots in digital_card/slots/, like
 * layout.php and the Pro designs. The look (stacked panels, round contact icons, centred contact
 * list, icon-only social buttons, captioned gallery) comes from assets/cards/css/classic.css;
 * $layout is "classic-white" or "classic-dark".
 */
$p = $dataPersonal;
$name = (string) ($p['nama'] ?? '');
$initials = mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim(strip_tags($name))) ?: [], 0, 2)))) ?: '?';
$channels = ['instagram' => ['Instagram', 'fab fa-instagram'], 'facebook' => ['Facebook', 'fab fa-facebook'], 'twitter' => ['X', 'nfc-icon-x'], 'tiktok' => ['TikTok', 'fab fa-tiktok'], 'website' => [lang('Card.website'), 'fas fa-globe']];
$hasSocial = array_filter(array_intersect_key($socialMediaLinks, $channels)) !== [];
$this->setData(['p' => $p, 'name' => $name, 'initials' => $initials, 'channels' => $channels, 'hasSocial' => $hasSocial]);
?>
<?= $this->include('digital_card/frame-start') ?>
        <section class="nc-classic-panel nc-classic-main">
            <header class="nc-head">
<?= $this->include('digital_card/slots/photo') ?>
                <h1 data-preview-field="nama"><?= esc($name !== '' ? $name : lang('Card.notAvailable')) ?></h1>
                <p class="nc-title" data-preview-field="job_title"<?= ($p['job_title'] ?? '') === '' ? ' hidden' : '' ?>><?= esc($p['job_title'] ?? '') ?></p>
                <p class="nc-company" data-preview-field="company_name"><?= esc(($p['company_name'] ?? '') !== '' ? $p['company_name'] : lang('Card.notAvailable')) ?></p>
            </header>
            <h2 class="nc-classic-heading"><?= esc(lang('Card.contact')) ?></h2>
<?= $this->include('digital_card/slots/contact') ?>
        </section>

        <section class="nc-classic-panel nc-classic-social">
<?= $this->include('digital_card/slots/social') ?>
        </section>

        <section class="nc-classic-panel nc-classic-gallery">
<?= $this->include('digital_card/slots/gallery') ?>
        </section>

        <section class="nc-classic-panel nc-classic-actions">
<?= $this->include('digital_card/slots/actions') ?>
        </section>
<?= $this->include('digital_card/frame-end') ?>
