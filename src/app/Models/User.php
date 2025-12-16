<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'noHP',  
         'kecamatan',
    'kelurahan',    // nomor HP user
        'avatar_path',  // path file avatar di disk public
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    protected static function booted(): void
    {
        static::creating(function ($user) {
            if (empty($user->id)) {
                $user->id = (string) Str::uuid();
            }
        });
    }

    // ===== JWT =====
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    // ===== Avatar helpers =====

    // URL siap pakai untuk ditampilkan di frontend
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar_path) {
            // Jika file disimpan di disk "public" → url /storage/...
            return Storage::disk('public')->url($this->avatar_path);
        }

        // Fallback (opsional): gravatar berdasar email
        if ($this->email) {
            $hash = md5(strtolower(trim($this->email)));
            return "https://www.gravatar.com/avatar/{$hash}?s=256&d=mp";
        }

        // Fallback lain: placeholder
        return 'https://i.pravatar.cc/256?img=1';
    }

    // Hapus file fisik avatar saat perlu ganti/hapus
    public function deleteAvatarFile(): void
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            Storage::disk('public')->delete($this->avatar_path);
        }
    }
}
