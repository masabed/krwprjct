<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsulanSummary extends Model
{
    protected $table = 'usulan_summaries';

    // karena tabel tidak punya PK id
    protected $primaryKey = null;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid_usulan',
        'form',
        'status_verifikasi_usulan',
        'kecamatan',
        'kelurahan',
        'titik_lokasi',
    ];
}
