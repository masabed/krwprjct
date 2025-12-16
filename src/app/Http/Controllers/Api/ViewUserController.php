<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;

class ViewUserController extends Controller
{
    // =======================
    // View all users (Admin / Admin Bidang only)
    // =======================
    public function index()
    {
        $auth = auth()->user();

        if (!$auth) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (!in_array($auth->role ?? null, ['admin', 'admin_bidang'], true)) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // tambahkan noHP, kecamatan, kelurahan di select
        $users = User::select(
                'id',
                'name',
                'username',
                'email',
                'noHP',
                'kecamatan',
                'kelurahan',
                'role',
                'created_at'
            )->get();

        return response()->json([
            'success' => true,
            'users'   => $users,
        ]);
    }

    // =======================
    // View one user by UUID (Admin / Admin Bidang only)
    // =======================
    public function show(string $id)
    {
        $auth = auth()->user();

        if (!$auth) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (!in_array($auth->role ?? null, ['admin', 'admin_bidang'], true)) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // tambahkan noHP, kecamatan, kelurahan di select
        $userData = User::select(
                'id',
                'name',
                'username',
                'email',
                'noHP',
                'kecamatan',
                'kelurahan',
                'role',
                'created_at'
            )
            ->where('id', $id)
            ->first();

        if (!$userData) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'user'    => $userData,
        ]);
    }

    // =======================
    // List pengawas (Admin / Admin Bidang only, bisa pakai pencarian)
    // =======================
    public function indexPengawas(Request $request)
    {
        $auth = auth()->user();
        if (!$auth) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Hanya admin / admin_bidang yang boleh lihat daftar pengawas
        if (!in_array($auth->role ?? null, ['admin', 'operator','pengawas'], true)) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $q = trim((string) $request->query('q', ''));

        $userModel = new User();
        $userTable = $userModel->getTable();
        $hasUuid   = Schema::hasColumn($userTable, 'uuid');

        $users = User::query()
            ->where('role', 'pengawas')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('username', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('noHP', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('created_at')
            // Ambil kolom minimal; kalau ada uuid kita pakai, kalau tidak pakai id
            ->get(
                $hasUuid
                    ? ['uuid', 'name', 'noHP', 'kecamatan', 'kelurahan']
                    : ['id', 'name', 'noHP', 'kecamatan', 'kelurahan']
            )
            ->map(function ($u) use ($hasUuid) {
                return [
                    'uuid'      => (string) ($hasUuid ? $u->uuid : $u->id),
                    'name'      => (string) $u->name,
                    'noHP'      => $u->noHP ?? null,
                    'kecamatan' => $u->kecamatan ?? null,
                    'kelurahan' => $u->kelurahan ?? null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'count'   => $users->count(),
            'data'    => $users,
        ]);
    }

    // =======================
    // View own profile
    // =======================
    public function profile()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'success' => true,
            'user'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'username'   => $user->username,
                'email'      => $user->email,
                'noHP'       => $user->noHP,
                'kecamatan'  => $user->kecamatan,
                'kelurahan'  => $user->kelurahan,
                'role'       => $user->role,
                'avatar_url' => $user->avatar_url,
                'created_at' => $user->created_at,
            ],
        ]);
    }
}
