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
     * PATCH /api/users/{id}
     * Update profil user (admin bisa siapa pun; user biasa hanya dirinya).
     * {id} bisa "me" atau UUID.
     */
    public function update(Request $request, string $id)
    {
        $actor = auth('api')->user();
        if (!$actor) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $target = $this->resolveTargetUser($actor, $id);
        if ($target instanceof \Illuminate\Http\JsonResponse) {
            return $target; // return 404/403 json dari helper
        }

        $isAdmin = $actor->role === 'admin';

        $rules = [
            'name'          => ['sometimes','string','max:255'],
            'username'      => ['sometimes','alpha_dash', Rule::unique('users','username')->ignore($target->id, 'id')],
            'email'         => ['sometimes','email', Rule::unique('users','email')->ignore($target->id, 'id')],
            'avatar'        => ['sometimes','file','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'delete_avatar' => ['sometimes','boolean'],
        ];
        if ($isAdmin) {
            $rules['role'] = ['sometimes','string','in:admin,pengawas,user'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dirty = [];

        if ($request->has('name'))     { $target->name = $request->name;         $dirty[] = 'name'; }
        if ($request->has('username')) { $target->username = $request->username; $dirty[] = 'username'; }
        if ($request->has('email'))    { $target->email = $request->email;       $dirty[] = 'email'; }

        if ($isAdmin && $request->has('role')) {
            $target->role = $request->role;
            $dirty[] = 'role';
        }

        // Hapus avatar
        if ($request->boolean('delete_avatar')) {
            if ($target->avatar_path && Storage::disk('public')->exists($target->avatar_path)) {
                Storage::disk('public')->delete($target->avatar_path);
            }
            $target->avatar_path = null;
            $dirty[] = 'avatar';
        }

        // Ganti avatar
        if ($request->hasFile('avatar')) {
            if ($target->avatar_path && Storage::disk('public')->exists($target->avatar_path)) {
                Storage::disk('public')->delete($target->avatar_path);
            }
            $ext      = strtolower($request->file('avatar')->getClientOriginalExtension() ?: $request->file('avatar')->extension() ?: 'jpg');
            $filename = 'avatar-'.Str::uuid().'.'.$ext;
            $path     = $request->file('avatar')->storeAs('avatars/'.$target->id, $filename, 'public');

            $target->avatar_path = $path;
            $dirty[] = 'avatar';
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
                'role'       => $target->role,
                'avatar_url' => $target->avatar_url ?? null,
                'updated_at' => $target->updated_at,
            ],
        ]);
    }

    /**
     * PATCH /api/users/{id}/password
     * Update password (admin bebas; user biasa butuh old_password).
     * {id} bisa "me" atau UUID.
     */
    public function updatePassword(Request $request, string $id)
{
    $actor = auth('api')->user();
    if (!$actor) {
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }

    $target = $this->resolveTargetUser($actor, $id);
    if ($target instanceof \Illuminate\Http\JsonResponse) {
        return $target; // return 404/403 json dari helper
    }

    $isAdmin = $actor->role === 'admin';
    $isSelf  = $actor->id === $target->id;

    $rules = [
        'password'              => ['required','string','min:8','regex:/[A-Z]/','regex:/[0-9]/','confirmed'],
        'password_confirmation' => ['required','string','min:8'],
    ];
    if (!$isAdmin || $isSelf) {
        $rules['old_password'] = ['required','string'];
    }

    $validator = \Validator::make($request->all(), $rules, [
        'password.regex' => 'Password must contain at least one capital letter and one number.',
    ]);
    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    if ((!$isAdmin || $isSelf) && !\Hash::check($request->old_password, $target->password)) {
        return response()->json(['error' => 'Old password is incorrect.'], 403);
    }

    $target->password = bcrypt($request->password);
    $target->save();

    // Pesan sesuai permintaan: "Username {USERNAME} Berhasil Update Password"
    $msg = 'Berhasil Update Password';

    return response()->json([
        'success'  => true,
        'message'  => $msg,
        'user_id'  => $target->id,
        'username' => $target->username,
    ]);
}


    /**
     * DELETE /api/users/{id}
     * Hapus user (admin only). Mendukung id="me" (tetapi admin dicegah hapus dirinya).
     */
    public function destroy(Request $request, string $id)
    {
        $actor = auth('api')->user();
        if (!$actor || $actor->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $target = $id === 'me' ? $actor : User::find($id);
        if (!$target) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Cegah admin hapus dirinya sendiri
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
     * (opsional, kalau ada FE lama yang masih memanggil deleteUser)
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
     * Return:
     *   - User  : jika authorized
     *   - JsonResponse 404: jika user tidak ditemukan
     *   - JsonResponse 403: jika bukan admin & bukan dirinya sendiri
     */
    private function resolveTargetUser(User $actor, string $id)
    {
        $target = ($id === 'me') ? $actor : User::find($id);

        if (!$target) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if ($actor->role === 'admin' || $actor->id === $target->id) {
            return $target;
        }

        return response()->json(['error' => 'Unauthorized.'], 403);
    }
}
