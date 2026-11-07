<?php
/**
 * Plan badge (PRD CARD-02). Expects $planCode ('basic' or 'pro') and $planLabel; optional
 * $badgeText overrides the shown text (e.g. "Paket Pro"). Pro is gold/orange with dark text, so the
 * label, not just the colour, says which plan it is; Basic keeps the neutral tag.
 */
$isPro = ($planCode ?? 'basic') === 'pro';
?>
<span class="dash-tag <?= $isPro ? 'nfc-badge-pro' : 'dash-tag-neutral' ?>"><?php if ($isPro): ?><i class="fas fa-crown" aria-hidden="true"></i> <?php endif; ?><?= esc($badgeText ?? $planLabel) ?></span>
