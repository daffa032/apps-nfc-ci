<?php
/**
 * Payment receipt / invoice email. Expects $name, $orderCode, $plan, $amount, $paidAt, $method,
 * $periodLabel, $receiptUrl.
 */
?>
<?= $this->extend('emails/layout') ?>
<?= $this->section('preheader') ?><?= esc(lang('Email.receiptPreheader', [$orderCode, $amount])) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
    <p style="margin:0 0 6px;"><span style="display:inline-block;padding:4px 10px;background:#d1e7dd;color:#146c43;border-radius:999px;font-size:12px;font-weight:700;">&#10003; <?= esc(lang('Email.receiptBadge')) ?></span></p>
    <h1 style="margin:10px 0 8px;font-size:22px;line-height:1.3;"><?= esc(lang('Email.receiptTitle')) ?></h1>
    <p style="margin:0 0 4px;"><?= esc(lang('Email.greeting', [$name])) ?></p>
    <p style="margin:0;"><?= esc(lang('Email.receiptLead')) ?></p>
    <?= $this->setData(['rows' => [
        [lang('Email.invoiceNo'), $orderCode],
        [lang('Email.plan'), $plan],
        [lang('Email.paidAt'), $paidAt],
        [lang('Email.method'), $method],
        [lang('Email.activeUntil'), $periodLabel],
    ], 'total' => [lang('Email.totalPaid'), $amount]])->include('emails/partials/rows') ?>
    <?= $this->setData(['url' => $receiptUrl, 'label' => lang('Email.viewReceipt'), 'color' => '#0d6efd'])->include('emails/partials/button') ?>
    <p style="margin:18px 0 0;"><?= esc(lang('Email.receiptSameLink')) ?></p>
<?= $this->endSection() ?>
<?= $this->section('footerNote') ?><?= esc(lang('Email.receiptFooter')) ?><?= $this->endSection() ?>
