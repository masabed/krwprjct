<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PSUUpload extends Model
{
    protected $table = 'psu_uploads';

    protected $primaryKey   = 'uuid';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'uuid',
        'user_id',
        'file_path',
        'original_name',
        'mime',
        'size',
    ];
}
