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
        'uuid', // optional; HasUuids akan auto-set jika tidak diisi
        'namaPemilikLahan',
        'ukuranLahan',

        // RENAME: statusKepemilikan -> statusLegalitasTanah
        'statusLegalitasTanah',

        'alamatDusun',
        'alamatRT',
        'alamatRW',
        'kecamatan',
        'kelurahan',
        'titikLokasi',
        'user_id',

        // arrays (JSON)
        'buktiKepemilikan',
        'dokumenProposal',

        // HAPUS: 'dokumenDJPM' (sudah digabungkan ke buktiKepemilikan)

        'fotoLahan',

        'status_verifikasi_usulan',
        'pesan_verifikasi',
    ];

    protected $casts = [
        'created_at'               => 'datetime',
        'updated_at'               => 'datetime',
        'status_verifikasi_usulan' => 'integer',

        // file arrays
        'buktiKepemilikan'         => 'array',
        'dokumenProposal'          => 'array',
        'fotoLahan'                => 'array',
    ];
}
