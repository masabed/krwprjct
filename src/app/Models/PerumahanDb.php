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
        'fileSerahTerimaTPU',

        // STRING biasa (nullable)
        'bastTPU',

        // status 0–4
        'status_serah_terima',
        'pesan_verifikasi', // nullable
    ];

    protected $attributes = [
        'status_serah_terima' => 0,
    ];

    protected $casts = [
        'foto_gerbang'        => 'array',
        'fileSerahTerimaTPU'  => 'array',
        'status_serah_terima' => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }

            // Pastikan kolom array tidak null saat create (biar di response keluar [])
            foreach (['foto_gerbang', 'fileSerahTerimaTPU'] as $field) {
                if (is_null($model->{$field})) {
                    $model->{$field} = [];
                }
            }
        });
    }

    /**
     * STATUS SERAH TERIMA: integer 0–4
     */
    public function setStatusSerahTerimaAttribute($value): void
    {
        $int = (int) $value;

        // Batasi ke 0–4 (kalau di luar range, fallback ke 0)
        if ($int < 0 || $int > 4) {
            $int = 0;
        }

        $this->attributes['status_serah_terima'] = $int;
    }
}
