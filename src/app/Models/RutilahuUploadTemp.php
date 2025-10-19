<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RutilahuUploadTemp extends Model
{
    protected $table = 'rutilahu_upload_temps';

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['uuid','user_id','file_path'];
}
