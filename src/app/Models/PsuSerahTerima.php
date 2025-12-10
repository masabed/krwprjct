<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PsuSerahTerima extends Model
{
    protected $table = 'psu_serah_terimas';

    protected $primaryKey   = 'id';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'id',
        'perumahanId',

        // Pemohon
        'tipePengaju',
        'namaPemohon',
        'nikPemohon',
        'noKontak',
        'email',

        // Developer
        'jenisDeveloper',
        'namaDeveloper',
        'alamatDeveloper',
        'rtDeveloper',
        'rwDeveloper',

        // Administratif
        'tanggalPengusulan',
        'tahapanPenyerahan',
        'jenisPSU',          // JSON (array of strings)
        'nomorSiteplan',
        'tanggalSiteplan',
        'noSuratPST',

        // Luasan
        'luasKeseluruhan',
        'luasRuangTerbangun',
        'luasRuangTerbuka',

        // File arrays (JSON of UUIDs)
        'dokumenIzinBangunan',
        'dokumenIzinPemanfaatan',
        'dokumenKondisi',
        'dokumenTeknis',
        'ktpPemohon',
        'aktaPerusahaan',
        'suratPermohonanPenyerahan',
        'dokumenSiteplan',
        'salinanSertifikat',

        // Non-file tambahan
        'noBASTPSU',

        // Verifikasi & audit
        'status_verifikasi_usulan',
        'pesan_verifikasi',
        'user_id',
    ];

    protected $attributes = [
        'status_verifikasi_usulan' => 0,
    ];

    protected $casts = [
        // dates
        'tanggalPengusulan' => 'date',
        'tanggalSiteplan'   => 'date',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',

        // scalar
        'status_verifikasi_usulan' => 'integer',

        // jenisPSU kini JSON (array of strings)
        'jenisPSU'          => 'array',

        // json arrays (nullable)
        'dokumenIzinBangunan'       => 'array',
        'dokumenIzinPemanfaatan'    => 'array',
        'dokumenKondisi'            => 'array',
        'dokumenTeknis'             => 'array',
        'ktpPemohon'                => 'array',
        'aktaPerusahaan'            => 'array',
        'suratPermohonanPenyerahan' => 'array',
        'dokumenSiteplan'           => 'array',
        'salinanSertifikat'         => 'array',
    ];

    protected static function booted()
    {
        static::creating(function (self $m) {
            if (empty($m->id)) {
                $m->id = (string) Str::uuid();
            }
            // Biarkan kolom JSON nullable (jangan paksa jadi [])
        });
    }
}
