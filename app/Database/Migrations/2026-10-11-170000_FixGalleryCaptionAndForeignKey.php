<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gallery integrity (PRD GAL-01, LOG-12).
 *
 * - caption was VARCHAR(30) while the form and GalleryService allow 255; with the connection's
 *   non-strict mode MySQL cut longer captions silently and the owner still saw "saved".
 * - Rows whose profile no longer exists are invisible everywhere; they are removed, then a
 *   cascading foreign key stops new ones. The image files are left on disk for manual review.
 *
 * down() restores the column size (captions longer than 30 characters are cut) and drops the key;
 * deleted orphan rows come back only from the backup taken before migrating.
 */
class FixGalleryCaptionAndForeignKey extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('table_multiple_images', [
            'caption' => ['name' => 'caption', 'type' => 'VARCHAR', 'constraint' => 255, 'null' => false, 'default' => ''],
        ]);

        $images   = $this->db->prefixTable('table_multiple_images');
        $profiles = $this->db->prefixTable('table_data_personal');
        $this->db->query("DELETE m FROM {$images} m LEFT JOIN {$profiles} d ON d.id_data = m.id_data WHERE d.id_data IS NULL");
        $this->db->query("ALTER TABLE {$images} ADD INDEX idx_multiple_images_profile (id_data), ADD CONSTRAINT fk_multiple_images_profile FOREIGN KEY (id_data) REFERENCES {$profiles} (id_data) ON DELETE CASCADE ON UPDATE CASCADE");
    }

    public function down()
    {
        $images = $this->db->prefixTable('table_multiple_images');
        $this->db->query("ALTER TABLE {$images} DROP FOREIGN KEY fk_multiple_images_profile, DROP INDEX idx_multiple_images_profile");
        $this->db->query("UPDATE {$images} SET caption = LEFT(caption, 30)");
        $this->forge->modifyColumn('table_multiple_images', [
            'caption' => ['name' => 'caption', 'type' => 'VARCHAR', 'constraint' => 30, 'null' => false],
        ]);
    }
}
