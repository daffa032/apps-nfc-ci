<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Job title on the card (PRD BR-02) and brand assets per profile and template (PRD BR-01). Assets
 * are kept per template, so switching designs never deletes the files of another design.
 */
class AddJobTitleAndBrandAssets extends Migration
{
    public function up()
    {
        $this->forge->addColumn('table_data_personal', [
            'job_title' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'company_name'],
        ]);

        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'profile_id'        => ['type' => 'INT', 'null' => false],
            'template_file'     => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'brand_logo'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'header_background' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['profile_id', 'template_file']);
        $this->forge->addForeignKey('profile_id', 'table_data_personal', 'id_data', 'CASCADE', 'CASCADE');
        $this->forge->createTable('card_brand_assets');
    }

    public function down()
    {
        $this->forge->dropTable('card_brand_assets', true);
        $this->forge->dropColumn('table_data_personal', 'job_title');
    }
}
