<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Closing the loop on payments (PRD AD-01/02, BI-02):
 * - payment_orders.reviewed_* : who resolved an order flagged "needs review", how and why.
 * - payment_orders.check_failures : consecutive failed gateway checks, so reconcile backs off.
 * - payment_orders.receipt_sent_at : the paid-confirmation email went out once.
 * - subscription_reminders : one row per period and reminder kind (H-7, H-1), so a reminder is
 *   never sent twice even when cron overlaps.
 */
class AddPaymentResolutionAndReminders extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payment_orders', [
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'needs_review'],
            'reviewed_by' => ['type' => 'INT', 'null' => true, 'after' => 'reviewed_at'],
            'review_action' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'reviewed_by'],
            'review_note' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'review_action'],
            'check_failures' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false, 'default' => 0, 'after' => 'last_checked_at'],
            'receipt_sent_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'check_failures'],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'subscription_id' => ['type' => 'INT', 'unsigned' => true],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 8],
            'sent_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['subscription_id', 'kind']);
        $this->forge->addForeignKey('subscription_id', 'subscriptions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('subscription_reminders', true);
    }

    public function down()
    {
        $this->forge->dropTable('subscription_reminders', true);
        $this->forge->dropColumn('payment_orders', ['reviewed_at', 'reviewed_by', 'review_action', 'review_note', 'check_failures', 'receipt_sent_at']);
    }
}
