<?php

// app/Models/SAPDUpload.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SAPDUpload extends Model
{
    protected $table = 'sapd_uploads';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['uuid', 'user_id', 'file_path'];
}
