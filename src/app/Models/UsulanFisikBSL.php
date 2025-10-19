<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UsulanFisikBsl extends Model
{
    protected $table = 'usulan_fisik_bsl';

    protected $primaryKey   = 'id';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'id',

        // Keterangan permohonan
        'tanggalPermohonan',
        'nomorSuratPermohonan',

        // Sumber usulan & data pemohon
        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',
        'namaPIC',
        'noKontakPIC',

        // Rincian usulan
        'jenisUsulan',
        'uraianMasalah',

        // Dimensi
        'luasTanahTersedia',
        'luasSarana',

        // Lokasi usulan
        'jenisBSL',
        'alamatCPCL',
        'rtCPCL',
        'rwCPCL',
        'titikLokasiUsulan',

        // Keterangan lokasi BSL
        'perumahanId',
        'statusTanah',

        // Dokumen (JSON arrays of UUID)
        'suratPermohonanUsulanFisik',
        'proposalUsulanFisik',
        'sertifikatStatusTanah',
        'dokumentasiEksisting',

        // Audit
        'user_id',
    ];

    protected $casts = [
        // tanggal
        'tanggalPermohonan' => 'date',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',

        // json arrays (nullable)
        'suratPermohonanUsulanFisik' => 'array',
        'proposalUsulanFisik'        => 'array',
        'sertifikatStatusTanah'      => 'array',
        'dokumentasiEksisting'       => 'array',
    ];

    protected static function booted()
    {
        static::creating(function (self $m) {
            if (empty($m->id)) {
                $m->id = (string) Str::uuid();
            }
            // Biar konsisten: kolom JSON default [] saat create jika belum diisi
            foreach ([
                'suratPermohonanUsulanFisik',
                'proposalUsulanFisik',
                'sertifikatStatusTanah',
                'dokumentasiEksisting',
            ] as $f) {
                if (is_null($m->{$f})) {
                    $m->{$f} = [];
                }
            }
        });
    }
}
