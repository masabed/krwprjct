<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermukimanUploadTemp extends Model
{
    protected $table = 'permukiman_upload_temps';
    protected $fillable = [
        'uuid','user_id','file_path','original_name','mime_type','size'
    ];
}
