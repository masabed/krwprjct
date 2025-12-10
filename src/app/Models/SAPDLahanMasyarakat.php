<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SAPDLahanMasyarakat extends Model
{
    use HasUuids;

    protected $table = 'usulan_lahan_masyarakat';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',

        // Sumber & pengusul (sesuai FormData)
        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',

        // Data lahan
        'namaPemilikLahan',
        'ukuranLahan',
        'statusLegalitasTanah',

        // Alamat
        'alamatDusun',
        'alamatRT',
        'alamatRW',
        'kecamatan',
        'kelurahan',
        'titikLokasi',

        'user_id',

        // arrays (JSON)
        'buktiLegalitasTanah',
        'fotoLahan',

        'status_verifikasi_usulan',
        'pesan_verifikasi',
    ];

    protected $attributes = [
        'status_verifikasi_usulan' => 0,
    ];

    protected $casts = [
        'created_at'               => 'datetime',
        'updated_at'               => 'datetime',
        'status_verifikasi_usulan' => 'integer',

        // JSON casts
        'buktiLegalitasTanah'      => 'array',
        'fotoLahan'                => 'array',
    ];
}
