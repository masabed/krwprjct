<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PSUUsulanFisikPJL extends Model
{
    use HasUuids;

    protected $table = 'psu_usulan_fisik_pjl';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        // pemohon
        'tanggalPermohonan',
        'nomorSuratPermohonan',
        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',
        'namaPIC',
        'noKontakPIC',

        // rincian
        'jenisUsulan',
        'uraianMasalah',

        // eksisting
        'panjangJalanEksisting',
        'jumlahTitikPJLEksisting',

        // lokasi
        'alamatUsulan',
        'rtUsulan',
        'rwUsulan',
        'rayonUsulan',
        'kecamatanUsulan',
        'kelurahanUsulan',
        'titikLokasiUsulan',
        'jenisLokasi',

        // bsl
        'perumahanId',
        'statusJalan',

        // dokumen (arrays)
        'suratPermohonanUsulanFisik',
        'proposalUsulanFisik',
        'dokumentasiEksisting',

        // meta
        'user_id',
        'status_verifikasi_usulan',
        'pesan_verifikasi',
    ];

    protected $casts = [
        'suratPermohonanUsulanFisik' => 'array',
        'proposalUsulanFisik'        => 'array',
        'dokumentasiEksisting'       => 'array',

        'status_verifikasi_usulan' => 'integer',
        'created_at'               => 'datetime',
        'updated_at'               => 'datetime',
    ];
}
