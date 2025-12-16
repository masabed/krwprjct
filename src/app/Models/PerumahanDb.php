<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PerumahanDb extends Model
{
    protected $table = 'perumahans_db';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'namaPerumahan',
        'developerPerumahan',
        'tahunDibangun',
        'luasPerumahan',
        'jenisPerumahan',
        'kecamatan',
        'kelurahan',
        'alamatPerumahan',
        'rwPerumahan',
        'rtPerumahan',
        'titikLokasi',

        // JSON arrays (UUID list)
        'foto_gerbang',

        // STRING biasa (nullable)
        'pesan_verifikasi', // nullable
    ];

    protected $casts = [
        'foto_gerbang' => 'array',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }

            // Pastikan kolom array tidak null saat create (biar di response keluar [])
            if (is_null($model->foto_gerbang)) {
                $model->foto_gerbang = [];
            }
        });
    }
}
