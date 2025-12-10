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
        'dokumentasiEksisting',

        // meta
        'user_id',
        'status_verifikasi_usulan',
        'pesan_verifikasi',
    ];

    protected $casts = [
        'tanggalPermohonan'          => 'date',

        'suratPermohonanUsulanFisik' => 'array',
        'dokumentasiEksisting'       => 'array',

        'status_verifikasi_usulan'   => 'integer',
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            // default [] untuk JSON dokumen
            foreach (['suratPermohonanUsulanFisik', 'dokumentasiEksisting'] as $f) {
                if (!array_key_exists($f, $m->attributes) || is_null($m->{$f})) {
                    $m->{$f} = [];
                }
            }

            // default status verifikasi = 0
            if (!array_key_exists('status_verifikasi_usulan', $m->attributes)
                || is_null($m->status_verifikasi_usulan)) {
                $m->status_verifikasi_usulan = 0;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    public function perumahan()
    {
        return $this->belongsTo(\App\Models\Perumahan::class, 'perumahanId', 'id');
    }
}
