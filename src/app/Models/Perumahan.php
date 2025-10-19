<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Perumahan extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'bidang',
        'kegiatan',
        'nik',
        'nama_cpcl',
        'dusun',
        'kelurahan',
        'kecamatan',
        'no_surat',
        'tanggal_sp',
        'nilai_kontrak',
        'jumlah_unit',
        'type',
        'kontraktor_pelaksana',
        'tanggal_mulai',
        'tanggal_selesai',
        'waktu_kerja',
        'pengawas_lapangan',
        'photos',
        'pdfs',
    ];

    protected $casts = [
        'tanggal_sp' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'nilai_kontrak' => 'float',
        'jumlah_unit' => 'integer',
        'waktu_kerja' => 'integer',
        'pdfs' => 'array',
        'photos' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }
}
