<?php
/**
 * Password reset link. Expects $resetUrl and $minutes. Never shows whether other accounts exist.
 */
?>
<?= $this->extend('emails/layout') ?>
<?= $this->section('preheader') ?><?= esc(lang('Email.resetPreheader', [$minutes])) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
    <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;"><?= esc(lang('Email.resetTitle')) ?></h1>
    <p style="margin:0;"><?= esc(lang('Email.resetLead', [$minutes])) ?></p>
    <?= $this->setData(['url' => $resetUrl, 'label' => lang('Email.resetButton'), 'color' => '#0d6efd'])->include('emails/partials/button') ?>
    <p style="margin:18px 0 0;"><?= esc(lang('Email.resetIgnore')) ?></p>
<?= $this->endSection() ?>
<?= $this->section('footerNote') ?><?= esc(lang('Email.resetFooter')) ?><?= $this->endSection() ?>
