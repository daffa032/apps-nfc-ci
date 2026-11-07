<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Three more Pro card designs (Ombak, Siluet, Bingkai), each with a brand logo and header background
 * slot. Added inactive like the first nine, so a Super Admin enables them in Master Templates within
 * the active slot limit.
 */
class AddOmbakSiluetBingkaiTemplates extends Migration
{
    private const LAYOUTS = [
        ['Ombak', 'layout-pro-ombak', 'Sampul navy bergelombang, potret bundar, kontak ikon koral.'],
        ['Siluet', 'layout-pro-siluet', 'Panel arang dan emas, nama kapital besar, potret lengkung monokrom.'],
        ['Bingkai', 'layout-pro-bingkai', 'Foto dan panel identitas berdampingan dengan strip kontak amber.'],
    ];

    public function up()
    {
        $order = (int) ($this->db->table('templates')->selectMax('display_order')->get()->getRow()->display_order ?? 0);

        foreach (self::LAYOUTS as [$name, $file, $description]) {
            if ($this->db->table('templates')->where('nama_file', $file)->countAllResults() > 0) {
                continue;
            }
            $this->db->table('templates')->insert([
                'nama_template'   => $name,
                'nama_file'       => $file,
                'images_template' => '',
                'plan_code'       => 'pro',
                'is_active'       => 0,
                'display_order'   => ++$order,
                'description'     => $description,
            ]);
        }
    }

    public function down()
    {
        $files = array_column(self::LAYOUTS, 1);
        $inUse = $this->db->table('table_data_personal')->whereIn('templates', $files)->countAllResults();
        if ($inUse > 0) {
            throw new RuntimeException($inUse . ' card(s) still use a Pro design from this migration; switch them to another template before rolling back.');
        }
        $this->db->table('templates')->whereIn('nama_file', $files)->delete();
    }
}
