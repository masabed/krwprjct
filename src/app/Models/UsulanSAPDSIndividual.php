<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UsulanSAPDSIndividual extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'usulan_sapds_individual';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'user_id',

        // Sumber & pengusul
        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',

        // Data calon penerima
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

        // File arrays
        'fotoLahan',
        'fotoRumah',

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
        'fotoLahan'                => 'array',
        'fotoRumah'                => 'array',
    ];
}
