<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * payment_orders.refund_notice_sent_at: the refund email went out once (billing:notify).
 */
class AddRefundNoticeSentAt extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payment_orders', [
            'refund_notice_sent_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'receipt_sent_at'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('payment_orders', 'refund_notice_sent_at');
    }
}
