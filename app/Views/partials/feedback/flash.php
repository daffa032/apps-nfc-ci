<?php
/**
 * One-time result of the previous action. Expects $feedback from flash_feedback(), or null.
 * Rendered as a toast in the bottom-right corner (PRD "Umpan balik tindakan"); the shared script
 * shows it on load and reuses the same region for AJAX results via window.nfcToast().
 */
$feedback ??= null;
?>
<div class="nfc-toast-region" id="nfcToastRegion"
     data-message="<?= esc($feedback['message'] ?? '', 'attr') ?>"
     data-message-type="<?= esc($feedback['type'] ?? '', 'attr') ?>"
     data-title-success="<?= esc(lang('Dashboard.toastSuccess'), 'attr') ?>"
     data-title-error="<?= esc(lang('Dashboard.toastError'), 'attr') ?>"></div>
<template id="nfcToastTemplate">
    <div class="toast nfc-toast" aria-atomic="true">
        <div class="nfc-toast-body">
            <i class="fas nfc-toast-icon" aria-hidden="true"></i>
            <div class="nfc-toast-text">
                <strong class="nfc-toast-title"></strong>
                <p class="nfc-toast-message"></p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="<?= esc(lang('Dashboard.close'), 'attr') ?>"></button>
        </div>
    </div>
</template>
