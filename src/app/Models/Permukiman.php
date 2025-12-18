<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permukiman extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'permukimans';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'sumber_usulan',
        'jenis_usulan',
        'nama_pengusul',
        'no_kontak_pengusul',
        'email',
        'instansi',
        'alamat_dusun_instansi',
        'alamat_rt_instansi',
        'alamat_rw_instansi',
        'tanggal_usulan',
        'nama_pic',
        'no_kontak_pic',
        'status_tanah',

        'foto_sertifikat_status_tanah',

        'panjang_usulan',
        'alamat_dusun_usulan',
        'alamat_rt_usulan',
        'alamat_rw_usulan',
        'kecamatan',
        'kelurahan',
        'titik_lokasi',
        'status_verifikasi_usulan',
        'pesan_verifikasi',
        'user_id',

        'foto_sta0',
        'foto_sta100',
        'surat_pemohonan',

        'status_verifikasi',
    ];

    protected $casts = [
        'tanggal_usulan'                => 'date',
        'status_verifikasi'             => 'integer',

        'foto_sertifikat_status_tanah'  => 'array',
        'foto_sta0'                     => 'array',
        'foto_sta100'                   => 'array',
        'surat_pemohonan'               => 'array',
    ];

    // ✅ Relasi ke tabel users untuk ambil users.name
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
