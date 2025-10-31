<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Perencanaan extends Model
{
    protected $table = 'perencanaans';

    // Primary key kita pakai kolom "id" bertipe string (UUID), bukan auto increment
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    // Kolom yang boleh di-mass assign
    protected $fillable = [
        'id',            // UUID kita simpan di sini
        'uuidUsulan',    // UUID dari usulan (relasi ke tabel usulan/rutilahu)
        'nilaiHPS',      // string nullable
        'catatanSurvey', // string/text nullable
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // pas create, kalau belum ada id, generate UUID baru
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
