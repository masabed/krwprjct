<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Pembangunan extends Model
{
    use HasUuids;

    protected $table = 'pembangunans';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'uuidUsulan',
        'nomorSPK',
        'tanggalSPK',
        'nilaiKontrak',
        'unit',
        'kontraktorPelaksana',
        'tanggalMulai',
        'tanggalSelesai',
        'jangkaWaktu',
        'pengawasLapangan',
        'user_id',
    ];

    protected $casts = [
        'uuidUsulan'      => 'array', 
        'tanggalSPK'     => 'date',
        'tanggalMulai'   => 'date',
        'tanggalSelesai' => 'date',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];
}

