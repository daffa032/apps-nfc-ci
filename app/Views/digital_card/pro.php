<?php
/**
 * Pro card designs (PRD NT-01..07). The layout of each built-in design is its source HTML from
 * docs/ui-ux-2026/templates/pro (copied to app/Views/digital_card/pro/<design>.html), split by
 * TemplateHtmlSanitizer into $segments exactly like an uploaded template; uploads built on a Pro
 * design use the same view. Card data and buttons come only from digital_card/slots/.
 *
 * Expects the PublicCardService::present() data plus $segments and $proDesign ("aurora" …). $brand
 * holds the logo / header background this design may show (BR-01; empty unless the account is Pro).
 */
$p = $dataPersonal;
$name = (string) ($p['nama'] ?? '');
$company = (string) ($p['company_name'] ?? '');
$jobTitle = (string) ($p['job_title'] ?? '');
$brand ??= ['brand_logo' => null, 'header_background' => null, 'logo_hidden' => false];
$initials = mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim(strip_tags($name))) ?: [], 0, 2)))) ?: '?';
$channels = ['instagram' => ['Instagram', 'fab fa-instagram'], 'facebook' => ['Facebook', 'fab fa-facebook'], 'twitter' => ['X', 'nfc-icon-x'], 'tiktok' => ['TikTok', 'fab fa-tiktok'], 'website' => [lang('Card.website'), 'fas fa-globe']];
$hasSocial = array_filter(array_intersect_key($socialMediaLinks, $channels)) !== [];
$this->setData(['p' => $p, 'name' => $name, 'company' => $company, 'initials' => $initials, 'channels' => $channels, 'hasSocial' => $hasSocial, 'mapInAddress' => true, 'inTemplate' => true, 'sectionHeadings' => true, 'brand' => $brand]);
?>
<?= $this->include('digital_card/frame-start') ?>
        <div class="nc-pro-template">
            <?php foreach ($segments as $segment): ?>
                <?php if (isset($segment['html'])): ?>
                    <?= $segment['html'] /* rebuilt from the parsed DOM and escaped by TemplateHtmlSanitizer */ ?>
                <?php elseif ($segment['slot'] === 'name'): ?>
                    <span data-preview-field="nama"><?= esc($name) ?></span>
                <?php elseif ($segment['slot'] === 'company'): ?>
                    <span data-preview-field="company_name"><?= esc($company) ?></span>
                <?php elseif ($segment['slot'] === 'job_title'): ?>
                    <span data-preview-field="job_title"><?= esc($jobTitle) ?></span>
                <?php else: ?>
                    <?= $this->include('digital_card/slots/' . $segment['slot']) ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
<?= $this->include('digital_card/frame-end') ?>
