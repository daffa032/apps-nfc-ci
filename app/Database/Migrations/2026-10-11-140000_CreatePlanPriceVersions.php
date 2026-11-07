<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Price history per plan (PRD MA-01): every change creates a new version. Orders will store the
 * version they were created with, so later price changes never alter a pending or paid order.
 */
class CreatePlanPriceVersions extends Migration
{
    public function up()
    {
        $this->forge->addColumn('plans', [
            'version' => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 1],
        ]);

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'plan_id'         => ['type' => 'INT', 'unsigned' => true],
            'version'         => ['type' => 'INT', 'unsigned' => true],
            'price_idr'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'duration_months' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'enabled'         => ['type' => 'TINYINT', 'constraint' => 1],
            'actor_id'        => ['type' => 'INT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['plan_id', 'version']);
        $this->forge->addForeignKey('plan_id', 'plans', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('plan_price_versions', true, ['ENGINE' => 'InnoDB']);

        $now = gmdate('Y-m-d H:i:s');

        foreach ($this->db->table('plans')->get()->getResultArray() as $plan) {
            $this->db->table('plan_price_versions')->insert([
                'plan_id'         => $plan['id'], 'version' => 1, 'price_idr' => $plan['price_idr'],
                'duration_months' => $plan['duration_months'], 'enabled' => $plan['enabled'], 'created_at' => $now,
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('plan_price_versions', true);
        $this->forge->dropColumn('plans', 'version');
    }
}
