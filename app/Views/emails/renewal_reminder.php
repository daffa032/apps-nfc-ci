<?php
/**
 * Renewal reminder (H-7, H-1). Expects $name, $lastDay, $days, $billingUrl.
 */
?>
<?= $this->extend('emails/layout') ?>
<?= $this->section('preheader') ?><?= esc(lang($days === 1 ? 'Email.reminderPreheaderTomorrow' : 'Email.reminderPreheader', [$lastDay, $days])) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
    <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;"><?= esc(lang($days === 1 ? 'Email.reminderTitleTomorrow' : 'Email.reminderTitle', [$days])) ?></h1>
    <p style="margin:0 0 4px;"><?= esc(lang('Email.greeting', [$name])) ?></p>
    <p style="margin:0;"><?= esc(lang('Email.reminderLead', [$lastDay])) ?></p>
    <?= $this->setData(['url' => $billingUrl, 'label' => lang('Email.renewNow'), 'color' => '#0d6efd'])->include('emails/partials/button') ?>
    <p style="margin:18px 0 0;"><?= esc(lang('Email.reminderNoLoss')) ?></p>
<?= $this->endSection() ?>
<?= $this->section('footerNote') ?><?= esc(lang('Email.reminderFooter')) ?><?= $this->endSection() ?>
