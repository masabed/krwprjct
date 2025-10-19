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
