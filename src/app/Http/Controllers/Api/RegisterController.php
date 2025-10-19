<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * POST /api/register
     * Body (multipart/form-data):
     *  - name, username, email, password, password_confirmation, role
     *  - avatar (opsional): image jpg/jpeg/png/webp, max 2MB
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'username'              => 'required|alpha_dash|unique:users,username',
            'email'                 => 'required|email|unique:users,email',
            'password'              => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[A-Z])(?=.*\d).+$/'
            ],
            'password_confirmation' => 'required|string|min:8',
            'role'                  => 'required|string|in:admin,pengawas,user',

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

        // Buat user
        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
            'role'     => $request->role ?? 'user',
        ]);

        // Jika ada avatar diupload → simpan & update avatar_path
        if ($request->hasFile('avatar')) {
            $ext = strtolower($request->file('avatar')->getClientOriginalExtension() ?: $request->file('avatar')->extension() ?: 'jpg');
            $filename = 'avatar-' . Str::uuid() . '.' . $ext;

            // simpan di disk public → storage/app/public/avatars/{user_id}/...
            $path = $request->file('avatar')->storeAs('avatars/' . $user->id, $filename, 'public');

            $user->avatar_path = $path;
            $user->save();
        }

        // Kembalikan juga avatar_url agar frontend langsung bisa pakai
        return response()->json([
            'success' => true,
            'user'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'username'   => $user->username,
                'email'      => $user->email,
                'role'       => $user->role,
                'avatar_url' => $user->avatar_url, // accessor di model
                'created_at' => $user->created_at,
            ],
        ], 201);
    }
}
