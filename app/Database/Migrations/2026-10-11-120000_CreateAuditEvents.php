<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Append-only audit trail (PRD AUD-01/AUD-02, TECHNICAL "Feedback UI dan audit log server").
 * Stores who did what to which target and the outcome; never passwords, tokens, file contents,
 * full email addresses, or URLs. changed_fields holds field names only.
 */
class CreateAuditEvents extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'request_id'     => ['type' => 'CHAR', 'constraint' => 16],
            'actor_id'       => ['type' => 'INT', 'null' => true],
            'actor_type'     => ['type' => 'VARCHAR', 'constraint' => 16],
            'action'         => ['type' => 'VARCHAR', 'constraint' => 48],
            'target_type'    => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'target_id'      => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'outcome'        => ['type' => 'VARCHAR', 'constraint' => 8],
            'reason'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'changed_fields' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'occurred_at'    => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('occurred_at');
        $this->forge->addKey(['actor_id', 'occurred_at']);
        $this->forge->addKey(['action', 'occurred_at']);
        $this->forge->addKey(['target_type', 'target_id']);
        $this->forge->addKey('outcome');
        $this->forge->createTable('audit_events', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('audit_events', true);
    }
}
