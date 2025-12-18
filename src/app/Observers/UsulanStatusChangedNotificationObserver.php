<?php

namespace App\Observers;

use App\Models\UsulanSummary;
use App\Models\UsulanNotification;

class UsulanStatusChangedNotificationObserver
{
    public bool $afterCommit = true;

    public function updated(UsulanSummary $summary): void
    {
        if (!$summary->wasChanged('status_verifikasi_usulan')) {
            return;
        }

        $ownerUserId = trim((string) ($summary->user_id ?? ''));
        if ($ownerUserId === '') {
            return;
        }

        $from = $summary->getOriginal('status_verifikasi_usulan');
        $to   = $summary->status_verifikasi_usulan;

        if ((string)$from === (string)$to) {
            return;
        }

        UsulanNotification::create([
    'owner_user_id' => $ownerUserId,
    'uuid_usulan'   => (string) $summary->uuid_usulan,
    'form'          => (string) ($summary->form ?? null),   // ✅ tambah ini
    'from_status'   => is_null($from) ? null : (int) $from,
    'to_status'     => (int) $to,
    'read_at'       => null,
]);
    }
}
