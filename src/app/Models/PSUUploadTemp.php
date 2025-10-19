<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PSUUploadTemp extends Model
{
    protected $table = 'psu_upload_temps';

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

    // Tidak auto-generate uuid di model; uuid dikirim dari controller saat upload.
}
