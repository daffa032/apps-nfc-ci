<?php
/**
 * Refund notice. Expects $name, $orderCode, $plan, $amount, $refundedAt, $billingUrl.
 */
?>
<?= $this->extend('emails/layout') ?>
<?= $this->section('preheader') ?><?= esc(lang('Email.refundPreheader', [$orderCode])) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
    <p style="margin:0 0 6px;"><span style="display:inline-block;padding:4px 10px;background:#fff3cd;color:#8a5a00;border-radius:999px;font-size:12px;font-weight:700;"><?= esc(lang('Email.refundBadge')) ?></span></p>
    <h1 style="margin:10px 0 8px;font-size:22px;line-height:1.3;"><?= esc(lang('Email.refundTitle')) ?></h1>
    <p style="margin:0 0 4px;"><?= esc(lang('Email.greeting', [$name])) ?></p>
    <p style="margin:0;"><?= esc(lang('Email.refundLead')) ?></p>
    <?= $this->setData(['rows' => [
        [lang('Email.invoiceNo'), $orderCode],
        [lang('Email.plan'), $plan],
        [lang('Email.refundedAt'), $refundedAt],
    ], 'total' => [lang('Email.totalRefunded'), $amount]])->include('emails/partials/rows') ?>
    <p style="margin:0;"><?= esc(lang('Email.refundAccess')) ?></p>
    <?= $this->setData(['url' => $billingUrl, 'label' => lang('Email.openBilling'), 'color' => '#373d42'])->include('emails/partials/button') ?>
<?= $this->endSection() ?>
<?= $this->section('footerNote') ?><?= esc(lang('Email.refundFooter')) ?><?= $this->endSection() ?>
