<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerumahanUpload extends Model
{
    protected $table = 'perumahan_uploads';

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid', 'user_id', 'file_path', 'original_name', 'mime', 'size'
    ];
}
