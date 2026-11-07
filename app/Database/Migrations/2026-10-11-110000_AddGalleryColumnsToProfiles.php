<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gallery layout per card (PRD GAL-01): 1 or 2 photos per row on every public template.
 * Existing cards default to 1: the old auto-fill grid (min 200px) fit only one photo per row in the
 * card's width, so 1 keeps every published card looking the same.
 */
class AddGalleryColumnsToProfiles extends Migration
{
    public function up()
    {
        $this->forge->addColumn('table_data_personal', [
            'gallery_columns' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 1,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('table_data_personal', 'gallery_columns');
    }
}
