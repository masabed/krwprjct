<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Perencanaan extends Model
{
    protected $table = 'perencanaans';

    // Primary key UUID (string, non-incrementing)
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuidUsulan',
        'nilaiHPS',
        'catatanSurvey',
        'lembarKontrol', // JSON array of file UUIDs (FINAL)
        'dokumentasi',   // JSON array of up to 5 image UUIDs
    ];

    protected $casts = [
        'lembarKontrol' => 'array',
        'dokumentasi'   => 'array',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
