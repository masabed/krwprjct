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
        'user_id',                // <— ditambahkan
        'namaFasilitasUmum',
        'alamatFasilitasUmum',
        'rwFasilitasUmum',
        'rtFasilitasUmum',
        'kecamatan',
        'kelurahan',
        'ukuranLahan',
        'statusKepemilikan',
        'titikLokasi',

        // File arrays (JSON, boleh null)
        'buktiKepemilikan',
        'proposal',
        'fotoLahan',

        'status_verifikasi_usulan',
        'pesan_verifikasi',
    ];

    protected $casts = [
        'buktiKepemilikan'         => 'array',
        'proposal'                  => 'array',
        'fotoLahan'                 => 'array',
        'status_verifikasi_usulan'  => 'integer',
        'created_at'                => 'datetime',
        'updated_at'                => 'datetime',
    ];

    /* ================= Relasi ================= */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /* ================ Scope bantu ================ */

    public function scopeOwnedBy($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
