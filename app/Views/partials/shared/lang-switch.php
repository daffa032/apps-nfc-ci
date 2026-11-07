<?php
/**
 * Language dropdown with flags (PRD LANG-02). Expects $locale ('id' or 'en'). Each option is a
 * plain link to ?lang=…; LocaleFilter stores the choice and returns to the same page, so it works
 * without JavaScript. <details> gives keyboard support; shared/js/lang-switch.js closes it after
 * a choice, on Escape and on outside clicks.
 */
$locale = ($locale ?? 'id') === 'en' ? 'en' : 'id';
$flags = ['id' => ['id', 'IND'], 'en' => ['gb', 'ENG']];
?>
<details class="nfc-lang">
    <summary class="nfc-lang-toggle" aria-label="<?= esc(lang('Locale.current', [lang('Locale.' . $locale)]), 'attr') ?>">
        <img src="<?= esc(base_url('assets/shared/img/flags/' . $flags[$locale][0] . '.svg'), 'attr') ?>" alt="" width="20" height="14">
        <span><?= esc($flags[$locale][1]) ?></span>
    </summary>
    <ul class="nfc-lang-menu" aria-label="<?= esc(lang('Locale.label'), 'attr') ?>">
        <?php foreach ($flags as $code => [$flag, $short]): ?>
            <li>
                <a href="?lang=<?= esc($code, 'url') ?>" hreflang="<?= esc($code, 'attr') ?>" lang="<?= esc($code, 'attr') ?>"<?= $code === $locale ? ' aria-current="true"' : '' ?>>
                    <img src="<?= esc(base_url('assets/shared/img/flags/' . $flag . '.svg'), 'attr') ?>" alt="" width="20" height="14">
                    <span><?= esc($short) ?></span>
                    <span class="nfc-lang-name"><?= esc(lang('Locale.' . $code)) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</details>
