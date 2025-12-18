<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsulanSummary extends Model
{
    protected $table = 'usulan_summaries';

    public $incrementing = false;
    public $timestamps   = false; // kalau tabel summary tidak pakai created_at/updated_at

    // jangan null-kan primaryKey
    protected $primaryKey = 'uuid_usulan';
    protected $keyType = 'string';

    protected $fillable = [
        'uuid_usulan',
        'form',
        'user_id',
        'user_kecamatan',
        'user_kelurahan',
        'status_verifikasi_usulan',
        'kecamatan',
        'kelurahan',
        'titik_lokasi',
    ];

    // ✅ penting: pastikan UPDATE pakai composite key (form + uuid_usulan)
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('form', $this->getAttribute('form'))
            ->where('uuid_usulan', $this->getAttribute('uuid_usulan'));
    }
}
