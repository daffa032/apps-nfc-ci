<?php

declare(strict_types=1);

namespace App\Commands;

use App\Repositories\PaymentOrderRepository;
use App\Services\Payments\ApplyPaymentService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use Throwable;

/**
 * Cron every 10 minutes: php spark payments:reconcile. Asks Midtrans for the status of orders that
 * are still waiting, timed out, or paid but not yet applied, and applies the result through the same
 * locked, exactly-once path as notifications. Recovery only: notifications stay the main signal.
 */
class ReconcilePayments extends BaseCommand
{
    protected $group       = 'Payments';
    protected $name        = 'payments:reconcile';
    protected $description = 'Periksa status pembayaran Midtrans yang belum pasti dan terapkan hasilnya.';

    public function run(array $params)
    {
        $now = new DateTimeImmutable('now');
        // Each order is checked at most every 5 minutes (longer after failed checks), 50 per run,
        // with a short pause (rate limit).
        $repo    = new PaymentOrderRepository();
        $orders  = $repo->needingReconcile(gmdate('Y-m-d H:i:s'), 50);
        $service = new ApplyPaymentService();
        $counts  = [];

        foreach ($orders as $order) {
            try {
                $outcome = $service->refresh($order, $now);
            } catch (Throwable $exception) {
                $outcome = 'error';
                log_message('warning', 'Reconcile failed for {order}: {message}', ['order' => $order['order_code'], 'message' => $exception->getMessage()]);
            }
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            usleep(200000);
        }
        CLI::write('Diperiksa: ' . count($orders) . ($counts === [] ? '' : ' (' . implode(', ', array_map(static fn ($k, $v) => "{$k}: {$v}", array_keys($counts), $counts)) . ')'));

        // Alert: orders a person must look at. Logged as a warning every run so hosting log
        // monitoring (or an admin reading logs) sees it; the payments page shows the same list.
        $attention = $repo->needingAttention(gmdate('Y-m-d H:i:s'));
        if ($attention !== []) {
            $codes = implode(', ', array_column($attention, 'order_code'));
            log_message('warning', 'Payments needing attention: {count} ({codes})', ['count' => count($attention), 'codes' => $codes]);
            CLI::write('Perlu perhatian: ' . count($attention) . ' (' . $codes . ')', 'yellow');
        }
    }
}
