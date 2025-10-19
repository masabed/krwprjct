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
        'namaPemilikLahan',
        'ukuranLahan',
        'statusKepemilikan',
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
        'dokumenDJPM',
        'fotoLahan',

        'status_verifikasi_usulan',
        'pesan_verifikasi',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status_verifikasi_usulan' => 'integer',

        // file arrays
        'buktiKepemilikan' => 'array',
        'dokumenProposal'  => 'array',
        'dokumenDJPM'      => 'array',
        'fotoLahan'        => 'array',
    ];
}
