<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Social links were VARCHAR(64) while the form allows 500 characters; longer URLs (Facebook,
 * TikTok share links) were cut silently and opened the wrong page. SocialLinkService now limits
 * URLs to 500 characters as well. down() cuts back to 64.
 */
class WidenSocialLinkColumns extends Migration
{
    private const CHANNELS = ['instagram', 'facebook', 'twitter', 'tiktok', 'website'];

    public function up()
    {
        $this->resize(500);
    }

    public function down()
    {
        $table = $this->db->prefixTable('sosial_media');

        foreach (self::CHANNELS as $channel) {
            $this->db->query("UPDATE {$table} SET {$channel} = LEFT({$channel}, 64) WHERE {$channel} IS NOT NULL");
        }
        $this->resize(64);
    }

    private function resize(int $length): void
    {
        $fields = [];

        foreach (self::CHANNELS as $channel) {
            $fields[$channel] = ['name' => $channel, 'type' => 'VARCHAR', 'constraint' => $length, 'null' => true];
        }
        $this->forge->modifyColumn('sosial_media', $fields);
    }
}
