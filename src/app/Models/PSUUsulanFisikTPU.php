<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PSUUsulanFisikTPU extends Model
{
    use HasUuids;

    protected $table = 'psu_usulan_fisik_tpu';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        // Keterangan permohonan
        'tanggalPermohonan',
        'nomorSuratPermohonan',

        // Sumber usulan & Data pemohon
        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',
        'namaPIC',
        'noKontakPIC',

        // Rincian usulan
        'jenisUsulan',
        'uraianMasalah',

        // Dimensi/eksisting
        'luasTPUEksisting',

        // Lokasi usulan
        'alamatUsulan',
        'rtUsulan',
        'rwUsulan',
        'kecamatanUsulan',
        'kelurahanUsulan',
        'titikLokasiUsulan',
        'jenisLokasi',

        // Keterangan lokasi
        'perumahanId',
        'statusTanah',

        // Dokumen pendukung (arrays of UUID)
        'suratPermohonanUsulanFisik',
        'sertifikatStatusTanah',
        'dokumentasiEksisting',

        // Status
        'status_verifikasi_usulan',
        'pesan_verifikasi',

        // Ownership
        'user_id',
    ];

    protected $casts = [
        'suratPermohonanUsulanFisik' => 'array',
        'sertifikatStatusTanah'      => 'array',
        'dokumentasiEksisting'       => 'array',

        'status_verifikasi_usulan'   => 'integer',
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',
    ];
}
