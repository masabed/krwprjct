<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rutilahu extends Model
{
    protected $table = 'rutilahus';

    protected $fillable = [
        'uuid',

        // Sumber & pengusul
        'sumberUsulan',
        'namaAspirator',
        'noKontakAspirator',

        'jenisProgram',

        // Lokasi/administrasi
        'kecamatan',
        'kelurahan',
        'titikLokasi',

        // Identitas CPCL
        'nama_CPCL',
        'nomorNIK',
        'nomorKK',
        'jumlahKeluarga',

        // Alamat detail
        'alamatDusun',
        'alamatRT',
        'alamatRW',

        // Data personal & aset
        'umur',
        'luasTanah',
        'luasBangunan',
        'pendidikanTerakhir',
        'pekerjaan',
        'besaranPenghasilan',
        'statusKepemilikanRumah',
        'asetRumahLain',
        'asetTanahLain',

        // Utilitas & program
        'sumberPenerangan',
        'bantuanPerumahan',
        'jenisKawasan',
        'pesan_verifikasi',
        'jenisKelamin',

        // Kondisi bangunan
        'kondisiPondasi',
        'kondisiSloof',
        'kondisiKolom',
        'kondisiRingBalok',
        'kondisiRangkaAtap',
        'kondisiDinding',
        'kondisiLantai',
        'kondisiPenutupAtap',
        'aksesAirMinum',
        'aksesAirSanitasi',

        // File (JSON arrays)
        'fotoKTP',
        'fotoSuratTanah',
        'fotoRumah',
        'fotoKK',
        'dokumentasiSurvey',

        // Status/verifikator
        'status_verifikasi_usulan',
        'user_id',
    ];

    protected $attributes = [
        'status_verifikasi_usulan' => 0,
    ];

    protected $casts = [
        'status_verifikasi_usulan' => 'integer',

        'fotoKTP'           => 'array',
        'fotoSuratTanah'    => 'array',
        'fotoRumah'         => 'array',
        'fotoKK'            => 'array',
        'dokumentasiSurvey' => 'array',

        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    // ✅ Tambahan: relasi ke tabel users
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
