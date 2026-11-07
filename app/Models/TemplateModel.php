<?php

namespace App\Models;

use CodeIgniter\Model;

class TemplateModel extends Model
{
    protected $table      = 'templates';           // Nama tabel di database
    protected $primaryKey = 'id_template';    // Primary key dari tabel

    // Kolom yang bisa diisi
    protected $allowedFields = [
        'nama_template',
        'nama_file',
        'images_template',
        'plan_code',
        'is_active',
        'display_order',
        'description',
    ];
}
