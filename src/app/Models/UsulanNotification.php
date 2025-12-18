<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UsulanNotification extends Model
{
    protected $table = 'usulan_notifications';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'owner_user_id',
        'uuid_usulan',
        'from_status',
        'to_status',
        'read_at',
    ];

    protected $casts = [
        'from_status' => 'integer',
        'to_status'   => 'integer',
        'read_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($m) {
            if (empty($m->id)) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    // Scope: belum dibaca
    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    // Mark as read
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
