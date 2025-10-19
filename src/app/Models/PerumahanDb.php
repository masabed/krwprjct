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

        'status_serah_terima',
        'pesan_verifikasi', // nullable
    ];

    protected $attributes = [
        'status_serah_terima' => 0,
    ];

    protected $casts = [
        'foto_gerbang'        => 'array',
        'fileSerahTerimaTPU'  => 'array',
        // bastTPU: string nullable (jangan di-cast)
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
            // Pastikan kolom array tidak null saat create (biar tampil sebagai [])
            foreach (['foto_gerbang', 'fileSerahTerimaTPU'] as $field) {
                if (is_null($model->{$field})) {
                    $model->{$field} = [];
                }
            }
            // bastTPU biarkan null jika tidak diisi (string)
        });
    }

    /** ====== STATUS SERAH TERIMA disimpan/tampil sebagai 0/1 ====== */
    public function getStatusSerahTerimaAttribute($value): int
    {
        return (int) $value;
    }

    public function setStatusSerahTerimaAttribute($value): void
    {
        $this->attributes['status_serah_terima'] = (int) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
