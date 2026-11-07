<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * table_data_personal.card_locale (PRD LANG-02): the language the owner picks for their public card
 * in Personal Info. Visitors no longer get an IND/ENG switch on the card; every visitor sees the
 * card in the owner's language. NULL keeps the old behaviour (visitor's own language).
 */
class AddCardLocaleToProfiles extends Migration
{
    public function up()
    {
        $this->forge->addColumn('table_data_personal', [
            'card_locale' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true, 'after' => 'gallery_columns'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('table_data_personal', 'card_locale');
    }
}
