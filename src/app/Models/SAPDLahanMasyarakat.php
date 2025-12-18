<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SAPDLahanMasyarakat extends Model
{
    use HasUuids;

    protected $table = 'usulan_lahan_masyarakat';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',

        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',

        'namaPemilikLahan',
        'ukuranLahan',
        'statusLegalitasTanah',

        'alamatDusun',
        'alamatRT',
        'alamatRW',
        'kecamatan',
        'kelurahan',
        'titikLokasi',

        'user_id',

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

        'buktiLegalitasTanah'      => 'array',
        'fotoLahan'                => 'array',
    ];

    // ✅ relasi ke users untuk ambil users.name
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
