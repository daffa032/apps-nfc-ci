<?php
/**
 * Email call-to-action (bulletproof table button). Expects $url and $label.
 */
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;">
    <tr>
        <td style="border-radius:8px;background:<?= esc($color ?? '#0d6efd', 'attr') ?>;">
            <a href="<?= esc($url, 'attr') ?>" style="display:inline-block;padding:12px 22px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:8px;"><?= esc($label) ?></a>
        </td>
    </tr>
</table>
<p style="margin:0 0 6px;color:#6c757d;font-size:12px;"><?= esc(lang('Email.buttonFallback')) ?></p>
<p style="margin:0;font-size:12px;word-break:break-all;"><a href="<?= esc($url, 'attr') ?>" style="color:#0d6efd;"><?= esc($url) ?></a></p>
