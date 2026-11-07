<?php 

namespace App\Models;

use CodeIgniter\Model;

class UserPersonalModel extends Model
{
    protected $table = 'table_data_personal';
    protected $primaryKey = 'id_data';

    protected $allowedFields = [
        'id_user', 'nama', 'company_name', 'no_hp', 'email', 'no_whatsapp', 'address', 'google_map_link', 'profile_image', 'templates',
        'gallery_columns', 'job_title', 'card_locale',
    ];
}
