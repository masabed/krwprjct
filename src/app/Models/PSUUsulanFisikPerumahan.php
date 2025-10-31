<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PSUUsulanFisikPerumahan extends Model
{
    use HasUuids;

    protected $table = 'psu_usulan_fisik_perumahan';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tanggalPermohonan',
        'nomorSuratPermohonan',
        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',
        'namaPIC',
        'noKontakPIC',
        'jenisUsulan',
        'uraianMasalah',
        'dimensiUsulan',
        'alamatUsulan',
        'rtUsulan',
        'rwUsulan',
        'titikLokasiUsulan',
        'perumahanId',
        'suratPermohonanUsulanFisik',
        'proposalUsulanFisik',
        'dokumentasiEksisting',
        'status_verifikasi_usulan',
        'pesan_verifikasi',
        'user_id',
    ];

    protected $casts = [
        'suratPermohonanUsulanFisik' => 'array',
        'proposalUsulanFisik'        => 'array',
        'dokumentasiEksisting'       => 'array',
        'status_verifikasi_usulan'   => 'integer',
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',
    ];
}
