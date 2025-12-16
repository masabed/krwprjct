<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class UserManagementController extends Controller
{
    /**
     * POST /api/logout
     * Invalidate JWT token.
     */
    public function logout(Request $request)
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * POST /api/users/update/{id}
     * Update profil user.
     * - Admin / admin_bidang bisa update user mana pun (termasuk password, tanpa old_password).
     * - User biasa hanya bisa update dirinya sendiri.
     *   Jika ingin ganti password, wajib kirim old_password yang benar.
     * {id} bisa "me" atau UUID.
     */
    public function update(Request $request, string $id)
    {
        $actor = auth('api')->user();
        if (!$actor) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Normalisasi: kalau klien kirim no_hp → isi ke noHP (DB pakai noHP)
        if ($request->has('no_hp') && !$request->has('noHP')) {
            $request->merge(['noHP' => $request->input('no_hp')]);
        }

        $target = $this->resolveTargetUser($actor, $id);
        if ($target instanceof \Illuminate\Http\JsonResponse) {
            return $target; // bisa 404 / 403 dari helper
        }

        $isAdmin = in_array($actor->role, ['admin', 'admin_bidang'], true);

        // ---------- RULES DASAR ----------
        $rules = [
            'name'          => ['sometimes','string','max:255'],
            'username'      => [
                'sometimes',
                'alpha_dash',
                Rule::unique('users','username')->ignore($target->id, 'id'),
            ],
            'email'         => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('users','email')->ignore($target->id, 'id'),
            ],
            'noHP'          => ['sometimes','nullable','string','max:30'],
            'kecamatan'     => ['sometimes','nullable','string','max:100'],
            'kelurahan'     => ['sometimes','nullable','string','max:100'],
            'avatar'        => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'delete_avatar' => ['sometimes','boolean'],
        ];

        // ---------- ROLE (HANYA ADMIN / ADMIN_BIDANG) ----------
        if ($isAdmin) {
            $rules['role'] = ['sometimes','string','in:admin,admin_bidang,operator,pengawas,user'];
        }

        // ---------- PASSWORD RULES ----------
        // Admin / admin_bidang: boleh ganti password tanpa old_password
        $rules['password'] = [
            'sometimes',
            'string',
            'min:8',
            'regex:/[A-Z]/',
            'regex:/[0-9]/',
            'confirmed',
        ];
        $rules['password_confirmation'] = [
            'required_with:password',
            'string',
            'min:8',
        ];

        if (!$isAdmin) {
            // User biasa: kalau ganti password, wajib old_password
            $rules['old_password'] = [
                'required_with:password',
                'string',
            ];
        }

        $validator = Validator::make($request->all(), $rules, [
            'password.regex' => 'Password must contain at least one capital letter and one number.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dirty = [];

        // ---------- UPDATE FIELD PROFIL ----------
        if ($request->has('name')) {
            $target->name = $request->name;
            $dirty[] = 'name';
        }

        if ($request->has('username')) {
            $target->username = $request->username;
            $dirty[] = 'username';
        }

        if ($request->has('email')) {
            $target->email = $request->email;
            $dirty[] = 'email';
        }

        if ($request->has('noHP')) {
            $target->noHP = $request->noHP;
            $dirty[] = 'noHP';
        }

        if ($request->has('kecamatan')) {
            $target->kecamatan = $request->kecamatan;
            $dirty[] = 'kecamatan';
        }

        if ($request->has('kelurahan')) {
            $target->kelurahan = $request->kelurahan;
            $dirty[] = 'kelurahan';
        }

        // ROLE hanya boleh diubah admin / admin_bidang
        if ($isAdmin && $request->has('role')) {
            $target->role = $request->role;
            $dirty[] = 'role';
        }

        // ---------- AVATAR ----------
        if ($request->boolean('delete_avatar')) {
            if ($target->avatar_path && Storage::disk('public')->exists($target->avatar_path)) {
                Storage::disk('public')->delete($target->avatar_path);
            }
            $target->avatar_path = null;
            $dirty[] = 'avatar';
        }

        if ($request->hasFile('avatar')) {
            if ($target->avatar_path && Storage::disk('public')->exists($target->avatar_path)) {
                Storage::disk('public')->delete($target->avatar_path);
            }

            $ext      = strtolower(
                $request->file('avatar')->getClientOriginalExtension()
                ?: $request->file('avatar')->extension()
                ?: 'jpg'
            );
            $filename = 'avatar-' . Str::uuid() . '.' . $ext;
            $path     = $request->file('avatar')->storeAs('avatars/'.$target->id, $filename, 'public');

            $target->avatar_path = $path;
            $dirty[] = 'avatar';
        }

        // ---------- PASSWORD ----------
        if ($request->filled('password')) {
            if (!$isAdmin) {
                if (
                    !$request->filled('old_password') ||
                    !Hash::check($request->old_password, $target->password)
                ) {
                    return response()->json([
                        'error' => 'Old password is incorrect.',
                    ], 403);
                }
            }

            $target->password = Hash::make($request->password);
            $dirty[] = 'password';
        }

        if (!empty($dirty)) {
            $target->save();
        }

        return response()->json([
            'success' => true,
            'message' => $dirty ? ('Updated: '.implode(', ', $dirty)) : 'No changes',
            'user'    => [
                'id'         => $target->id,
                'name'       => $target->name,
                'username'   => $target->username,
                'email'      => $target->email,
                'noHP'       => $target->noHP,
                'kecamatan'  => $target->kecamatan,
                'kelurahan'  => $target->kelurahan,
                'role'       => $target->role,
                'avatar_url' => $target->avatar_url ?? null,
                'updated_at' => $target->updated_at,
            ],
        ]);
    }

    /**
     * DELETE /api/users/{id}
     * Hapus user (admin / admin_bidang only). Mendukung id="me" (tetapi dicegah hapus dirinya).
     */
    public function destroy(Request $request, string $id)
    {
        $actor = auth('api')->user();
        if (!$actor || !in_array($actor->role, ['admin', 'admin_bidang'], true)) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $target = $id === 'me' ? $actor : User::find($id);
        if (!$target) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Cegah admin/admin_bidang hapus dirinya sendiri
        if ($actor->id === $target->id) {
            return response()->json(['error' => 'You cannot delete your own account.'], 403);
        }

        if ($target->avatar_path && Storage::disk('public')->exists($target->avatar_path)) {
            Storage::disk('public')->delete($target->avatar_path);
        }

        $target->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun Pengguna Berhasil Di Hapus',
        ]);
    }

    /**
     * Alias lama agar tidak error "Call to undefined method ... deleteUser()"
     */
    public function deleteUser(Request $request, string $id)
    {
        return $this->destroy($request, $id);
    }

    // ================= Helpers =================

    /**
     * Resolve target user dari {id} dan cek otorisasi:
     * - "me" → actor
     * - UUID → user tsb
     * Admin/admin_bidang boleh akses user mana saja, user biasa hanya dirinya.
     */
    private function resolveTargetUser(User $actor, string $id)
    {
        $target = ($id === 'me') ? $actor : User::find($id);

        if (!$target) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $isAdmin = in_array($actor->role, ['admin', 'admin_bidang'], true);

        if ($isAdmin || $actor->id === $target->id) {
            return $target;
        }

        return response()->json(['error' => 'Unauthorized.'], 403);
    }
}
