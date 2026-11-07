<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Uploaded HTML templates (PRD MA-02, MA-03). Each upload is stored as an immutable, normalised
 * version; templates.current_version_id points to the version cards render. Built-in layouts keep
 * current_version_id NULL. Templates are never hard-deleted, so versions are never orphaned.
 */
class CreateTemplateVersions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'template_id' => ['type' => 'INT'],
            'version'     => ['type' => 'SMALLINT', 'unsigned' => true],
            'html'        => ['type' => 'MEDIUMTEXT'],
            'checksum'    => ['type' => 'CHAR', 'constraint' => 64],
            'created_by'  => ['type' => 'INT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['template_id', 'version']);
        $this->forge->addForeignKey('template_id', 'templates', 'id_template', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('template_versions', true, ['ENGINE' => 'InnoDB']);

        $this->forge->addColumn('templates', [
            'current_version_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('templates', 'current_version_id');
        $this->forge->dropTable('template_versions', true);
    }
}
