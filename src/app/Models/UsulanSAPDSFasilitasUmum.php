<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UsulanSAPDSFasilitasUmum extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'usulan_sapds_fasilitas_umum';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'user_id',

        // 🔹 Sumber & pengusul (sesuai FormData)
        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',

        // Data fasilitas umum
        'namaFasilitasUmum',
        'alamatFasilitasUmum',
        'rtFasilitasUmum',
        'rwFasilitasUmum',
        'statusKepemilikan',

        // Lokasi
        'kecamatan',
        'kelurahan',
        'ukuranLahan',
        'titikLokasi',

        // File arrays (JSON)
        'buktiKepemilikan',
        'fotoLahan',

        // Verifikasi
        'status_verifikasi_usulan',
        'pesan_verifikasi',
    ];

    protected $attributes = [
        'status_verifikasi_usulan' => 0,
    ];

    protected $casts = [
        'buktiKepemilikan'        => 'array',
        'fotoLahan'               => 'array',
        'status_verifikasi_usulan'=> 'integer',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeOwnedBy($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
