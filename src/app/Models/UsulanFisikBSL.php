<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UsulanFisikBSL extends Model
{
    use HasUuids;

    protected $table = 'usulan_fisik_bsl';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

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

        // Lokasi usulan (kolom DB: jenisLokasi)
        'jenisLokasi',
        'alamatCPCL',
        'rtCPCL',
        'rwCPCL',
        'titikLokasiUsulan',
        'kecamatanUsulan',
        'kelurahanUsulan',

        // Keterangan lokasi BSL
        'perumahanId',
        'statusTanah',

        // Dokumen (JSON arrays of UUID)
        'suratPermohonanUsulanFisik',
        'sertifikatStatusTanah',
        'dokumentasiEksisting',

        // Status verifikasi (baru)
        'status_verifikasi_usulan',
        'pesan_verifikasi',

        // Audit
        'user_id',
    ];

    protected $casts = [
        'tanggalPermohonan'          => 'date',
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',

        // JSON arrays
        'suratPermohonanUsulanFisik' => 'array',
        'sertifikatStatusTanah'      => 'array',
        'dokumentasiEksisting'       => 'array',

        // Verifikasi
        'status_verifikasi_usulan'   => 'integer',
    ];

    // Biar 'jenisBSL' ikut tampil di JSON/array (alias dari jenisLokasi)
    protected $appends = ['jenisBSL'];

    /**
     * Alias field: kompatibel dengan kode lama yang pakai 'jenisBSL'
     * sementara kolom DB adalah 'jenisLokasi'.
     */
    public function getJenisBslAttribute(): ?string
    {
        return $this->attributes['jenisLokasi'] ?? null;
    }

    public function setJenisBslAttribute($value): void
    {
        $this->attributes['jenisLokasi'] = $value;
    }

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            // default array [] untuk kolom JSON jika null saat create
            foreach ([
                'suratPermohonanUsulanFisik',
                'sertifikatStatusTanah',
                'dokumentasiEksisting',
            ] as $f) {
                if (!array_key_exists($f, $m->attributes) || is_null($m->{$f})) {
                    $m->{$f} = [];
                }
            }

            // pastikan default status verifikasi = 0 jika tidak diisi (selaras dengan default DB)
            if (
                !array_key_exists('status_verifikasi_usulan', $m->attributes)
                || is_null($m->status_verifikasi_usulan)
            ) {
                $m->status_verifikasi_usulan = 0;
            }
        });
    }

    /* ================== Relationships (opsional) ================== */

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    public function perumahan()
    {
        return $this->belongsTo(\App\Models\Perumahan::class, 'perumahanId', 'id');
    }
}
