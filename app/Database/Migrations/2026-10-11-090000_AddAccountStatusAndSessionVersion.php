<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Account status is separate from card expiry (PRD section 5): a suspended account cannot sign in
 * or show its card even when paid. session_version lets the app revoke existing sessions by
 * bumping one number instead of tracking session files.
 *
 * Additive only. Existing accounts start as enabled with version 1, so nothing changes for them.
 */
class AddAccountStatusAndSessionVersion extends Migration
{
    public function up()
    {
        $this->forge->addColumn('table_user', [
            'account_status' => [
                'type' => 'VARCHAR',
                'constraint' => 16,
                'null' => false,
                'default' => 'enabled',
            ],
            'session_version' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => false,
                'default' => 1,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('table_user', ['account_status', 'session_version']);
    }
}
