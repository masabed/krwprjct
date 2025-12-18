<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',

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

    // ✅ relasi ke users untuk ambil users.name
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
        // kalau user_id nyimpan uuid user, pakai ini:
        // return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
}
