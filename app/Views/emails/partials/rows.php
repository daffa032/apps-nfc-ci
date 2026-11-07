<?php
/**
 * Label/value table for invoice-style emails. Expects $rows (list of [label, value]) and optional
 * $total ([label, value]) shown as the highlighted last row.
 */
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;border:1px solid #e3e7ec;border-radius:10px;border-collapse:separate;">
    <?php foreach ($rows as $i => [$label, $value]): ?>
        <tr>
            <td style="padding:10px 14px;color:#6c757d;font-size:14px;<?= $i > 0 ? 'border-top:1px solid #eef1f4;' : '' ?>"><?= esc($label) ?></td>
            <td align="right" style="padding:10px 14px;font-size:14px;font-weight:700;<?= $i > 0 ? 'border-top:1px solid #eef1f4;' : '' ?>"><?= esc($value) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!empty($total)): ?>
        <tr>
            <td style="padding:14px;background:#f8f9fb;border-top:1px solid #e3e7ec;font-size:15px;border-radius:0 0 0 10px;"><?= esc($total[0]) ?></td>
            <td align="right" style="padding:14px;background:#f8f9fb;border-top:1px solid #e3e7ec;font-size:20px;font-weight:700;border-radius:0 0 10px 0;"><?= esc($total[1]) ?></td>
        </tr>
    <?php endif; ?>
</table>
