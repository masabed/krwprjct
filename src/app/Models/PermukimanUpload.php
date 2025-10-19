<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermukimanUpload extends Model
{
    protected $table = 'permukiman_uploads';
    protected $fillable = [
        'uuid','user_id','file_path','original_name','mime_type','size'
    ];
}
