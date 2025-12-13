<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ViewUserController extends Controller
{
    // View all users (Admin only)
    public function index()
    {
        $user = auth()->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $users = User::select('id', 'name', 'username', 'email', 'role', 'created_at')->get();

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    // View one user by UUID (Admin only)
    public function show($id)
    {
        $user = auth()->user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $userData = User::select('id', 'name', 'username', 'email', 'role', 'created_at')
            ->where('id', $id)
            ->first();

        if (!$userData) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'user' => $userData
        ]);
    }

public function indexPengawas(Request $request)
{
    $auth = auth()->user();
    if (!$auth) {
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }

    // Hanya admin / admin_bidang yang boleh lihat daftar pengawas
    if (!in_array($auth->role ?? null, ['admin'], true)) {
        return response()->json(['error' => 'Unauthorized.'], 403);
    }

    $q = trim((string) $request->query('q', ''));

    // Deteksi apakah tabel users punya kolom 'uuid'
    $userModel = new \App\Models\User;
    $userTable = $userModel->getTable();
    $hasUuid   = \Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid');

    $users = \App\Models\User::query()
        ->where('role', 'pengawas')
        ->when($q !== '', function ($qq) use ($q) {
            $qq->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('username', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        })
        ->orderByDesc('created_at')
        // Ambil kolom minimal untuk efisiensi; kalau tidak ada 'uuid', ambil 'id'
        ->get($hasUuid ? ['name', 'uuid'] : ['name', 'id'])
        // Petakan output agar selalu punya key 'uuid'
        ->map(function ($u) use ($hasUuid) {
            return [
                'uuid' => (string) ($hasUuid ? $u->uuid : $u->id),
                'name' => (string) $u->name,
            ];
        })
        ->values();

    return response()->json([
        'success' => true,
        'count'   => $users->count(),
        'data'    => $users,
    ]);
}


    // View own profile
    public function profile()
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_url' => $user->avatar_url,
                'created_at' => $user->created_at,
            ]
        ]);
    }
}
