<?php
/**
 * Master layout for every app email (PRD BI-02, reset password). Table-based, inline styles only,
 * no remote images or scripts, so it renders the same in Gmail, Outlook and phone clients. Each
 * email view extends this and fills: preheader (inbox preview line), content, and optional
 * footerNote. Expects $locale and $year.
 */
?>
<!DOCTYPE html>
<html lang="<?= esc($locale ?? 'id', 'attr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title><?= esc($subject ?? 'QR-CODE.ID') ?></title>
</head>
<body style="margin:0;padding:0;background:#eef1f4;font-family:Arial,'Helvetica Neue',Helvetica,sans-serif;color:#212529;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;"><?= $this->renderSection('preheader') ?></div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef1f4;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
                    <tr>
                        <td style="padding:18px 24px;background:#373d42;border-radius:12px 12px 0 0;">
                            <span style="color:#ffffff;font-size:18px;font-weight:700;letter-spacing:.02em;">QR-CODE.ID</span>
                            <span style="color:#b8c4cc;font-size:12px;padding-left:8px;"><?= esc(lang('Email.tagline')) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:4px;background:#1da1b7;line-height:4px;font-size:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px;background:#ffffff;font-size:15px;line-height:1.55;">
                            <?= $this->renderSection('content') ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 24px;background:#f8f9fa;border-radius:0 0 12px 12px;border-top:1px solid #e3e7ec;color:#6c757d;font-size:12px;line-height:1.5;">
                            <?= $this->renderSection('footerNote') ?>
                            <p style="margin:8px 0 0;"><?= esc(lang('Email.footerAuto')) ?></p>
                            <p style="margin:4px 0 0;">&copy; <?= (int) $year ?> QR-CODE.ID</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
