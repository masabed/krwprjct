<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * POST /api/register
     * Body (multipart/form-data):
     *  - name               (required)
     *  - username           (required)
     *  - password           (required)
     *  - password_confirmation (required)
     *  - role               (required: admin, admin_bidang, operator, pengawas, user)
     *  - email              (optional)
     *  - noHP / no_hp       (optional)
     *  - kecamatan          (optional)
     *  - kelurahan          (optional)
     *  - avatar             (optional image: jpg/jpeg/png/webp, max 2MB)
     *
     * HANYA BISA DIAKSES OLEH ROLE admin atau admin_bidang
     */
    public function __invoke(Request $request): JsonResponse
    {
        // --- NORMALISASI: no_hp -> noHP (DB pakai kolom noHP) ---
        if ($request->has('no_hp') && !$request->has('noHP')) {
            $request->merge(['noHP' => $request->input('no_hp')]);
        }

        // --- CEK AUTH (pakai guard api/JWT) ---
        $authUser = auth('api')->user();
        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // --- CEK ROLE HARUS admin / admin_bidang ---
        $role = strtolower((string) ($authUser->role ?? ''));
        if (!in_array($role, ['admin', 'admin_bidang'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: only admin or admin_bidang can register new users.',
            ], 403);
        }

        // --- VALIDASI INPUT USER BARU ---
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'username'              => 'required|alpha_dash|unique:users,username',

            // email opsional
            'email'                 => 'sometimes|nullable|email|unique:users,email',

            // noHP opsional
            'noHP'                  => 'sometimes|nullable|string|max:30',

            // kecamatan & kelurahan opsional
            'kecamatan'             => 'sometimes|nullable|string|max:100',
            'kelurahan'             => 'sometimes|nullable|string|max:100',

            'password'              => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[A-Z])(?=.*\d).+$/',
            ],
            'password_confirmation' => 'required|string|min:8',

            // role yang boleh dibuat
            'role'                  => 'required|string|in:admin,admin_bidang,operator,pengawas,user',

            // Avatar opsional saat registrasi
            'avatar'                => 'sometimes|file|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'password.confirmed'    => 'Password confirmation does not match.',
            'password.regex'        => 'Password must contain at least one uppercase letter and one number.',
            'password_confirmation.required' => 'Password confirmation is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        // --- BUAT USER BARU ---
        $user = User::create([
            'name'      => $request->name,
            'username'  => $request->username,
            'email'     => $request->email ?? null,
            'noHP'      => $request->noHP ?? null,
            'kecamatan' => $request->kecamatan ?? null,
            'kelurahan' => $request->kelurahan ?? null,
            'password'  => $request->password, // pakai cast 'hashed' di model
            'role'      => $request->role ?? 'user',
        ]);

        // --- SIMPAN AVATAR JIKA ADA ---
        if ($request->hasFile('avatar')) {
            $ext = strtolower(
                $request->file('avatar')->getClientOriginalExtension()
                ?: $request->file('avatar')->extension()
                ?: 'jpg'
            );

            $filename = 'avatar-' . Str::uuid() . '.' . $ext;

            // simpan di disk public → storage/app/public/avatars/{user_id}/...
            $path = $request->file('avatar')->storeAs('avatars/' . $user->id, $filename, 'public');

            $user->avatar_path = $path;
            $user->save();
        }

        // --- RESPONSE ---
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
                'avatar_url' => $user->avatar_url, // accessor di model
                'created_at' => $user->created_at,
            ],
        ], 201);
    }
}
