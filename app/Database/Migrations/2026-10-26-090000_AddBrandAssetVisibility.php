<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Show/hide switch per brand asset (PRD BR-01). Hiding keeps the stored file, so the owner can show
 * it again without uploading it a second time. Existing assets stay visible.
 */
class AddBrandAssetVisibility extends Migration
{
    public function up()
    {
        $this->forge->addColumn('card_brand_assets', [
            'brand_logo_visible' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1, 'after' => 'brand_logo'],
            'header_background_visible' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1, 'after' => 'header_background'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('card_brand_assets', ['brand_logo_visible', 'header_background_visible']);
    }
}
