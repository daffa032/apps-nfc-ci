<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Template catalog per plan (PRD TH-01, MA-02): which plan may use a template, whether it is offered
 * for new selections, and display order. Adds the four prototype layouts as inactive Pro templates so
 * a Super Admin decides when to offer them. Existing rows stay active Basic, so nothing changes for
 * current cards.
 */
class AddPlanAndStatusToTemplates extends Migration
{
    private const NEW_LAYOUTS = [
        ['Profesional', 'layout-profesional', 'Header perusahaan, identitas rata kiri, kontak terstruktur.'],
        ['Hangat', 'layout-hangat', 'Identitas di tengah, rincian dalam panel lembut.'],
        ['Portofolio', 'layout-portofolio', 'Galeri foto ditampilkan sebelum rincian kontak.'],
        ['Gelap', 'layout-gelap', 'Permukaan gelap kontras tinggi, aksi utama lebar.'],
    ];

    public function up()
    {
        $this->forge->addColumn('templates', [
            'plan_code' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => false, 'default' => 'basic'],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'display_order' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false, 'default' => 0],
            'description' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
        ]);

        $this->db->query("UPDATE {$this->db->prefixTable('templates')} SET display_order = id_template");

        $order = (int) ($this->db->table('templates')->selectMax('display_order')->get()->getRow()->display_order ?? 0);
        foreach (self::NEW_LAYOUTS as [$name, $file, $description]) {
            $exists = $this->db->table('templates')->where('nama_file', $file)->countAllResults() > 0;
            if (!$exists) {
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
    }

    public function down()
    {
        $this->db->table('templates')->whereIn('nama_file', array_column(self::NEW_LAYOUTS, 1))->delete();
        $this->forge->dropColumn('templates', ['plan_code', 'is_active', 'display_order', 'description']);
    }
}
