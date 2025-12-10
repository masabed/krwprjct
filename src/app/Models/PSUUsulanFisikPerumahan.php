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

        // dimensi
        'dimensiUsulanUtama',
        'dimensiUsulanTambahan',

        // lokasi
        'alamatUsulan',
        'rtUsulan',
        'rwUsulan',
        'titikLokasiUsulan',
        'perumahanId',

        // dokumen
        'suratPermohonanUsulanFisik',
        'dokumentasiEksisting',

        // meta
        'status_verifikasi_usulan',
        'pesan_verifikasi',
        'user_id',
    ];

    protected $casts = [
        'tanggalPermohonan'            => 'date',

        'suratPermohonanUsulanFisik'   => 'array',
        'dokumentasiEksisting'         => 'array',

        'status_verifikasi_usulan'     => 'integer',
        'created_at'                   => 'datetime',
        'updated_at'                   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            // default [] untuk kolom JSON dokumen kalau null
            foreach (['suratPermohonanUsulanFisik', 'dokumentasiEksisting'] as $f) {
                if (!array_key_exists($f, $m->attributes) || is_null($m->{$f})) {
                    $m->{$f} = [];
                }
            }

            // default status verifikasi = 0 kalau belum di-set
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
