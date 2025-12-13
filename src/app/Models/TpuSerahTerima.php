<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TpuSerahTerima extends Model
{
    protected $table = 'tpu_serah_terimas';

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'perumahanId',
        'user_id',

        'tipePengaju',
        'namaPemohon',
        'nikPemohon',

        'jenisDeveloper',
        'namaDeveloper',
        'alamatDeveloper',
        'rtDeveloper',
        'rwDeveloper',

        'noKontak',
        'email',

        'tanggalPengusulan',
        'noSuratPST',

        'lokasiSama',
        'namaTPU',
        'jenisTPU',
        'statusTanah',
        'karakterTPU',

        'aksesJalan',
        'lokasiBerdekatan',

        'alamatTPU',
        'rtTPU',
        'rwTPU',
        'kecamatanTPU',
        'kelurahanTPU',
        'titikLokasi',

        // Dokumen (array UUID)
        'ktpPemohon',
        'aktaPerusahaan',
        'suratPermohonan',
        'suratPernyataan',
        'suratKeteranganDesa',
        'suratIzinLingkungan',
        'suratPelepasan',
        'sertifikatHAT',
        'pertekBPN',
        'suratKeteranganLokasi',

        // Verifikasi
        'status_verifikasi_usulan',
        'pesan_verifikasi',
        'noBASTTPU',
    ];

    protected $casts = [
        'karakterTPU'           => 'array',

        'ktpPemohon'            => 'array',
        'aktaPerusahaan'        => 'array',
        'suratPermohonan'       => 'array',
        'suratPernyataan'       => 'array',
        'suratKeteranganDesa'   => 'array',
        'suratIzinLingkungan'   => 'array',
        'suratPelepasan'        => 'array',
        'sertifikatHAT'         => 'array',
        'pertekBPN'             => 'array',
        'suratKeteranganLokasi' => 'array',

        'tanggalPengusulan'     => 'date',
        'status_verifikasi_usulan' => 'integer',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
