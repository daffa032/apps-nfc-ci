<?php

namespace App\Models;

use CodeIgniter\Model;

class SocialMediaModel extends Model
{
    protected $table = 'sosial_media';
    protected $primaryKey = 'id_sosial_media';
    protected $allowedFields = ['id_data', 'instagram', 'facebook', 'twitter', 'tiktok', 'website'];
}
