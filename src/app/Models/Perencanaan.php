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

    /**
     * Kolom yang boleh di-mass assign.
     * (Tidak perlu 'id' karena akan di-generate otomatis pada creating)
     */
    protected $fillable = [
        'uuidUsulan',
        'nilaiHPS',
        'catatanSurvey',
        'lembarKontrol', // JSON array of file UUIDs (FINAL)
    ];

    protected $casts = [
        'lembarKontrol' => 'array',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // Generate UUID untuk kolom "id" saat create jika belum ada
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
