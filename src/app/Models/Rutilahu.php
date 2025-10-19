<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rutilahu extends Model
{
    protected $table = 'rutilahus';

    protected $fillable = [
        'uuid',
        'jenisProgram',
        'kecamatan','kelurahan','nama_CPCL','nomorNIK','nomorKK','jumlahKeluarga',
        'alamatDusun','alamatRT','alamatRW',
        'umur','luasTanah','luasBangunan','pendidikanTerakhir','pekerjaan',
        'besaranPenghasilan','statusKepemilikanRumah','asetRumahLain','asetTanahLain',
        'sumberPenerangan','bantuanPerumahan','jenisKawasan','pesan_verifikasi',
        'jenisKelamin',

        // kondisi bangunan
        'kondisiPondasi','kondisiSloof','kondisiKolom','kondisiRingBalok','kondisiRangkaAtap',
        'kondisiDinding','kondisiLantai','kondisiPenutupAtap','aksesAirMinum','aksesAirSanitasi',

        // file (JSON arrays)
        'fotoKTP','fotoSuratTanah','fotoRumah','fotoKK',
        'dokumentasiSurvey','statusVerifikasi','user_id',
    ];

    // Sabuk pengaman di sisi app
    protected $attributes = [
        'statusVerifikasi' => 0,
    ];

    protected $casts = [
        'statusVerifikasi'   => 'integer',

        // File arrays
        'fotoKTP'            => 'array',
        'fotoSuratTanah'     => 'array',
        'fotoRumah'    => 'array',
        'fotoKK'             => 'array',
        'dokumentasiSurvey'  => 'array',
    ];
}
