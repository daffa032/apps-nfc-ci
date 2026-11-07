<?php 

namespace App\Models;

use CodeIgniter\Model;

class ImageModel extends Model
{
    protected $table = 'table_multiple_images';
    protected $primaryKey = 'id_images';
    protected $allowedFields = ['id_data', 'images', 'caption'];
}
