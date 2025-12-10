<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengawasanUploadTemp extends Model
{
    protected $table = 'pengawasan_upload_temps';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid', 'user_id', 'file_path', 'original_name', 'mime', 'size',
    ];
}