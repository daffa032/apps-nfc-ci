<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Old card URLs (LOG-14, PRD section 8): when staff rename an account, the previous username is kept
 * here so QR codes and NFC tags already printed keep opening the same card. Same collation as
 * table_user.username, so lookups are case-insensitive in the same way.
 */
class CreateCardAliases extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'alias' => ['type' => 'VARCHAR', 'constraint' => 255],
            'user_id' => ['type' => 'INT'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('alias');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'table_user', 'id_user', 'CASCADE', 'CASCADE');
        $this->forge->createTable('card_aliases', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_general_ci']);
    }

    public function down()
    {
        $this->forge->dropTable('card_aliases', true);
    }
}
