<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\UsulanNotification;

class UsulanNotificationController extends Controller
{
    private function authUser(Request $request)
    {
        $u = $request->user() ?: auth()->user();
        if ($u) return $u;

        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // GET /api/usulan-notifications
    public function all(Request $request)
    {
        $user = $this->authUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $userId = trim((string) $user->id);

        // ✅ Ambil read_at untuk hitung is_unread, tapi tidak ditampilkan ke client
        $rows = UsulanNotification::query()
            ->where('owner_user_id', $userId)
            ->latest('created_at')
            ->get(['id','uuid_usulan','form','from_status','to_status','read_at','created_at']);

        $data = $rows->map(function ($n) {
            return [
                'id'          => (string) $n->id,
                'uuid_usulan' => (string) $n->uuid_usulan,
                'form'        => $n->form,
                'from_status' => is_null($n->from_status) ? null : (int) $n->from_status,
                'to_status'   => (int) $n->to_status,

                'is_unread'   => is_null($n->read_at),

                'created_at'  => $n->created_at ? $n->created_at->toISOString() : null,
            ];
        })->values();

        $unreadCount = UsulanNotification::query()
            ->where('owner_user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'data' => $data,
        ]);
    }

    // POST /api/usulan-notifications/read-all
    public function markAllAsRead(Request $request)
    {
        $user = $this->authUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $userId = trim((string) $user->id);

        $affected = UsulanNotification::query()
            ->where('owner_user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(), // biar kelihatan berubah
            ]);

        return response()->json([
            'success' => true,
            'affected' => $affected, // berapa notif yang berubah jadi read
        ]);
    }
}
