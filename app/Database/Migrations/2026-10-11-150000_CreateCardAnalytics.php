<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Daily visit aggregates per card (PRD section 6, TECHNICAL "Analytics dan cache").
 * card_daily_metrics: one row per card per WIB day. card_daily_visitors: a daily HMAC of
 * network metadata (never the raw IP or user agent) used to estimate unique visitors per day.
 */
class CreateCardAnalytics extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'card_user_id'    => ['type' => 'INT'],
            'date_wib'        => ['type' => 'DATE'],
            'views'           => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'unique_visitors' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'contact_clicks'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addPrimaryKey(['card_user_id', 'date_wib']);
        $this->forge->addForeignKey('card_user_id', 'table_user', 'id_user', 'CASCADE', 'CASCADE');
        $this->forge->createTable('card_daily_metrics', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addField([
            'card_user_id' => ['type' => 'INT'],
            'date_wib'     => ['type' => 'DATE'],
            'visitor_hash' => ['type' => 'CHAR', 'constraint' => 64],
        ]);
        $this->forge->addPrimaryKey(['card_user_id', 'date_wib', 'visitor_hash']);
        $this->forge->addKey('date_wib');
        $this->forge->addForeignKey('card_user_id', 'table_user', 'id_user', 'CASCADE', 'CASCADE');
        $this->forge->createTable('card_daily_visitors', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('card_daily_visitors', true);
        $this->forge->dropTable('card_daily_metrics', true);
    }
}
