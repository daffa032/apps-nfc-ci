<?php
/**
 * Contact-click tracking for Pro cards. Expects optional $eventUrl and $eventToken; prints nothing
 * when they are absent, so Basic cards load no tracking script.
 */
?>
<?php if (!empty($eventUrl) && !empty($eventToken)): ?>
    <meta name="nc-event-url" content="<?= esc($eventUrl, 'attr') ?>">
    <meta name="nc-event-token" content="<?= esc($eventToken, 'attr') ?>">
    <script src="<?= esc(base_url('assets/cards/js/events.js'), 'attr') ?>" defer></script>
<?php endif; ?>
