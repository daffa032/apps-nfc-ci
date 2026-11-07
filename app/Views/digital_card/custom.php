<?php
/**
 * Public card from an uploaded HTML template (PRD MA-02, MA-03). $segments comes from
 * TemplateHtmlSanitizer::segments(): sanitised HTML chunks and placeholder slots. Card data and
 * buttons come only from digital_card/slots/, so a template arranges the card but cannot add code.
 * Expects the same data as digital_card/layout.php plus $segments; $layout is "custom".
 */
$p = $dataPersonal;
$name = (string) ($p['nama'] ?? '');
$initials = mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim(strip_tags($name))) ?: [], 0, 2)))) ?: '?';
$channels = ['instagram' => ['Instagram', 'fab fa-instagram'], 'facebook' => ['Facebook', 'fab fa-facebook'], 'twitter' => ['X', 'nfc-icon-x'], 'tiktok' => ['TikTok', 'fab fa-tiktok'], 'website' => [lang('Card.website'), 'fas fa-globe']];
$hasSocial = array_filter(array_intersect_key($socialMediaLinks, $channels)) !== [];
$this->setData(['p' => $p, 'name' => $name, 'company' => (string) ($p['company_name'] ?? ''), 'initials' => $initials, 'channels' => $channels, 'hasSocial' => $hasSocial, 'inTemplate' => true, 'brand' => $brand ?? ['brand_logo' => null, 'header_background' => null, 'logo_hidden' => false]]);
$jobTitle = (string) ($p['job_title'] ?? '');
?>
<?= $this->include('digital_card/frame-start') ?>
        <div class="nc-body nc-template">
            <?php foreach ($segments as $segment): ?>
                <?php if (isset($segment['html'])): ?>
                    <?= $segment['html'] /* already rebuilt from the parsed DOM and escaped by TemplateHtmlSanitizer */ ?>
                <?php elseif ($segment['slot'] === 'name'): ?>
                    <span data-preview-field="nama"><?= esc($name) ?></span>
                <?php elseif ($segment['slot'] === 'company'): ?>
                    <span data-preview-field="company_name"><?= esc((string) ($p['company_name'] ?? '')) ?></span>
                <?php elseif ($segment['slot'] === 'job_title'): ?>
                    <span data-preview-field="job_title"><?= esc($jobTitle) ?></span>
                <?php else: ?>
                    <?= $this->include('digital_card/slots/' . $segment['slot']) ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
<?= $this->include('digital_card/frame-end') ?>
