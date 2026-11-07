<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Active template slot limits as data instead of code (PRD MA-02): one row that a Super Admin edits
 * in Master Templates. Starts at 4 active Basic (free) and 20 active in total.
 */
class CreateTemplateSlotLimits extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'TINYINT', 'unsigned' => true],
            'max_active_basic' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false],
            'max_active_total' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false],
            'updated_by' => ['type' => 'INT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('template_slot_limits', true);

        if ($this->db->table('template_slot_limits')->countAllResults() === 0) {
            $this->db->table('template_slot_limits')->insert([
                'id' => 1, 'max_active_basic' => 4, 'max_active_total' => 20, 'updated_by' => null, 'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('template_slot_limits', true);
    }
}
