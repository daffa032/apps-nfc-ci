<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Payments\BillingNotificationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;

/**
 * Cron every hour: php spark billing:notify. Sends payment receipts, refund notices and renewal
 * reminders (H-7, H-1).
 * Each email goes out once; a failed send is retried on the next run. Does nothing until SMTP
 * (email.* in .env) is configured.
 */
class SendBillingNotifications extends BaseCommand
{
    protected $group = 'Payments';
    protected $name = 'billing:notify';
    protected $description = 'Kirim email kwitansi, pemberitahuan refund, dan pengingat perpanjangan (H-7, H-1).';

    public function run(array $params)
    {
        $result = (new BillingNotificationService())->run(new DateTimeImmutable('now'));
        if ($result['skipped']) {
            CLI::write('Email belum dikonfigurasi (email.fromEmail / email.SMTPHost); tidak ada yang dikirim.', 'yellow');

            return;
        }
        CLI::write('Kwitansi: ' . $result['receipts'] . ', refund: ' . $result['refunds'] . ', pengingat: ' . $result['reminders']);
    }
}
