<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerencanaanUploadTemp extends Model
{
    protected $table = 'perencanaan_upload_temps';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid','user_id','file_path','original_name','mime','size'
    ];
}
