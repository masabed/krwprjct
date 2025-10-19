<?php
// app/Models/UsulanSAPDSIndividual.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UsulanSAPDSIndividual extends Model
{
    use HasFactory;

    protected $table = 'usulan_sapds_individual';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'user_id',
        'namaCalonPenerima',
        'nikCalonPenerima',
        'noKKCalonPenerima',
        'alamatPenerima',
        'rwPenerima',
        'rtPenerima',
        'kecamatan',
        'kelurahan',
        'ukuranLahan',
        'ketersedianSumber',
        'titikLokasi',
        'fotoLahan',                 // json array
        'fotoRumah',                 // json array
        'status_verifikasi_usulan',
        'pesan_verifikasi',          // NEW
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status_verifikasi_usulan' => 'integer',
        'fotoLahan' => 'array',
        'fotoRumah' => 'array',
    ];
}
