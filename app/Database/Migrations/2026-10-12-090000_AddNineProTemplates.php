<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Nine Pro card designs from PRD 1.2 (NT-01..07). They are added inactive, so nothing changes for
 * current cards until a Super Admin enables them in Master Templates (slot limit 2 Basic / 15 total).
 */
class AddNineProTemplates extends Migration
{
    private const LAYOUTS = [
        ['Aurora', 'layout-pro-aurora', 'Sampul merek lebar dengan potret bundar yang menonjol.'],
        ['Meridian', 'layout-pro-meridian', 'Identitas terbelah dua dengan potret tegak di sisi kanan.'],
        ['Atelier', 'layout-pro-atelier', 'Tipografi editorial, potret asimetris, galeri sebelum kontak.'],
        ['Vista', 'layout-pro-vista', 'Sampul lanskap berlapis dengan potret mengambang.'],
        ['Ledger', 'layout-pro-ledger', 'Hitam dan emas dengan rincian kontak terstruktur.'],
        ['Kanvas', 'layout-pro-kanvas', 'Foto miring dan galeri karya sebagai pembuka.'],
        ['Halo', 'layout-pro-halo', 'Ruang putih lapang, potret di tengah, aksi kontak besar.'],
        ['Rona', 'layout-pro-rona', 'Potret tinggi bergradasi hangat dengan aksi kontak bundar.'],
        ['Sorot', 'layout-pro-sorot', 'Potret editorial di panel gelap dengan kontak berikon.'],
    ];

    public function up()
    {
        $order = (int) ($this->db->table('templates')->selectMax('display_order')->get()->getRow()->display_order ?? 0);
        foreach (self::LAYOUTS as [$name, $file, $description]) {
            if ($this->db->table('templates')->where('nama_file', $file)->countAllResults() > 0) {
                continue;
            }
            $this->db->table('templates')->insert([
                'nama_template' => $name,
                'nama_file' => $file,
                'images_template' => '',
                'plan_code' => 'pro',
                'is_active' => 0,
                'display_order' => ++$order,
                'description' => $description,
            ]);
        }
    }

    public function down()
    {
        $files = array_column(self::LAYOUTS, 1);
        // A card that uses one of these designs would lose its layout; ask for a conscious switch first.
        $inUse = $this->db->table('table_data_personal')->whereIn('templates', $files)->countAllResults();
        if ($inUse > 0) {
            throw new RuntimeException($inUse . ' card(s) still use a Pro design from this migration; switch them to another template before rolling back.');
        }
        $this->db->table('templates')->whereIn('nama_file', $files)->delete();
    }
}
