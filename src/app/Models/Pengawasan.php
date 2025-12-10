<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengawasan extends Model
{
    use HasUuids;

    protected $table = 'pengawasans';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'uuidUsulan',          // UUID usulan
        'uuidPembangunan',     // UUID pembangunans.id
        'pengawas',            // UUID/ID user pengawas (disimpan sebagai string)
        'foto',                // array of UUID (final uploads)
        'pesan_pengawasan',    // string(255)
        'tanggal_pengawasan',  // date
    ];

    protected $casts = [
        'foto'               => 'array',
        'tanggal_pengawasan' => 'date',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    /** Relasi opsional ke Pembangunan (uuidPembangunan -> pembangunans.id) */
    public function pembangunan(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Pembangunan::class, 'uuidPembangunan', 'id');
    }
}
