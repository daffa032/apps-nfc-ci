<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Self-service renewals through Midtrans Snap (PRD BI-01..03, AD-01; TECHNICAL "Midtrans").
 *
 * payment_orders keeps a snapshot of the plan price/duration at checkout, so a later price change
 * never alters an order. request_key is unique per user, so a double click or retry reuses one
 * order. subscription_id is set exactly once when a verified payment is applied.
 *
 * payment_events is append-only history of what the gateway reported (notification or status
 * check). The fingerprint is unique, so the same notification delivered twice is stored once.
 * No server key, signature or full payload is stored.
 */
class CreatePaymentOrders extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'order_code'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'user_id'                => ['type' => 'INT'],
            'plan_id'                => ['type' => 'INT', 'unsigned' => true],
            'plan_code'              => ['type' => 'VARCHAR', 'constraint' => 32],
            'plan_version'           => ['type' => 'INT', 'unsigned' => true],
            'duration_months'        => ['type' => 'SMALLINT', 'unsigned' => true],
            'amount_idr'             => ['type' => 'INT', 'unsigned' => true],
            'currency'               => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'IDR'],
            'status'                 => ['type' => 'VARCHAR', 'constraint' => 20],
            'gateway_status'         => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'payment_type'           => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'gateway_transaction_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'snap_token'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'redirect_url'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'request_key'            => ['type' => 'CHAR', 'constraint' => 32],
            'expires_at'             => ['type' => 'DATETIME'],
            'paid_at'                => ['type' => 'DATETIME', 'null' => true],
            'applied_at'             => ['type' => 'DATETIME', 'null' => true],
            'subscription_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'needs_review'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'last_checked_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'             => ['type' => 'DATETIME'],
            'updated_at'             => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('order_code');
        $this->forge->addUniqueKey(['user_id', 'request_key']);
        $this->forge->addUniqueKey('subscription_id');
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addForeignKey('user_id', 'table_user', 'id_user', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('plan_id', 'plans', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('subscription_id', 'subscriptions', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('payment_orders');

        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'order_id'               => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'order_code'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'source'                 => ['type' => 'VARCHAR', 'constraint' => 16],
            'transaction_status'     => ['type' => 'VARCHAR', 'constraint' => 32],
            'fraud_status'           => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'status_code'            => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true],
            'gross_amount'           => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'payment_type'           => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'gateway_transaction_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'fingerprint'            => ['type' => 'CHAR', 'constraint' => 64],
            'outcome'                => ['type' => 'VARCHAR', 'constraint' => 32],
            'received_at'            => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('fingerprint');
        $this->forge->addKey(['order_id', 'received_at']);
        $this->forge->addForeignKey('order_id', 'payment_orders', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('payment_events');
    }

    public function down()
    {
        $this->forge->dropTable('payment_events', true);
        $this->forge->dropTable('payment_orders', true);
    }
}
