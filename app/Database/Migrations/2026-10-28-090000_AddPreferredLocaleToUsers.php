<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * table_user.preferred_locale (PRD LANG-02): the language an account last chose with the IND/ENG
 * switch. Emails sent by cron (receipt, refund, reminder) have no browser request, so they use it.
 * NULL means "never chose", which falls back to Indonesian.
 */
class AddPreferredLocaleToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('table_user', [
            'preferred_locale' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true, 'after' => 'session_version'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('table_user', 'preferred_locale');
    }
}
