<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RutilahuUpload extends Model
{
    protected $table = 'rutilahu_uploads';

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['uuid','user_id','file_path'];
}
