<?php

namespace App\Http\Controllers\Api\Db;

use App\Http\Controllers\Controller;
use App\Models\Pokir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PokirController extends Controller
{
    // GET /api/pokir
    public function index()
    {
        $rows = Pokir::query()
            ->select('uuid', 'nama')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'uuid' => (string) $r->uuid,
                'nama' => $r->nama,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $rows, // [{ uuid, nama }, ...]
        ]);
    }

    // GET /api/pokir/{uuid}
    public function show(string $uuid)
    {
        // kalau uuid adalah kolom biasa, pakai where
        $row = Pokir::where('uuid', $uuid)->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $row,
        ]);
    }

    // POST /api/pokir
    public function store(Request $request)
    {
        // --- CEK AUTH & ROLE ---
        $actor = $request->user(); // atau auth('api')->user()
        if (!$actor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $role = strtolower((string) ($actor->role ?? ''));
        if (!in_array($role, ['admin_bidang', 'operator'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: hanya admin_bidang dan operator yang boleh menambah data.',
            ], 403);
        }

        // --- VALIDASI ---
        $val = $request->validate([
            'nama'    => 'required|string|max:200',
            'telepon' => 'sometimes|nullable|string|max:30',
            'photo'   => 'sometimes|file|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // --- SIMPAN FILE FOTO JIKA ADA ---
        $path = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('pokir_photos');
        }

        // --- SIMPAN DATA POKIR ---
        $row = Pokir::create([
            'uuid'    => (string) Str::uuid(),
            'nama'    => $val['nama'],
            'telepon' => $val['telepon'] ?? null,
            'photo'   => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data tersimpan',
            'data'    => $row,
        ], 201);
    }

    // PUT/PATCH /api/pokir/{uuid}
    public function update(Request $request, string $uuid)
    {
        // --- CEK AUTH & ROLE ---
        $actor = $request->user();
        if (!$actor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $role = strtolower((string) ($actor->role ?? ''));
        if (!in_array($role, ['admin_bidang', 'operator'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: hanya admin_bidang dan operator yang boleh mengubah data.',
            ], 403);
        }

        // --- CARI DATA BERDASARKAN UUID ---
        $row = Pokir::where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        // --- VALIDASI ---
        $val = $request->validate([
            'nama'    => 'sometimes|required|string|max:200',
            'telepon' => 'sometimes|nullable|string|max:30',
            'photo'   => 'sometimes|file|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // --- HANDLE FOTO BARU ---
        if ($request->hasFile('photo')) {
            // hapus foto lama jika ada
            if ($row->photo && Storage::exists($row->photo)) {
                Storage::delete($row->photo);
            }
            $val['photo'] = $request->file('photo')->store('pokir_photos');
        }

        $row->update($val);

        return response()->json([
            'success' => true,
            'message' => 'Data diperbarui',
            'data'    => $row->fresh(),
        ]);
    }

    // DELETE /api/pokir/{uuid}
    public function destroy(Request $request, string $uuid)
    {
        // --- CEK AUTH & ROLE ---
        $actor = $request->user();
        if (!$actor) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $role = strtolower((string) ($actor->role ?? ''));
        if (!in_array($role, ['admin_bidang', 'operator'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: hanya admin_bidang dan operator yang boleh menghapus data.',
            ], 403);
        }

        // --- CARI DATA ---
        $row = Pokir::where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        // opsional: hapus file fisik
        if ($row->photo && Storage::exists($row->photo)) {
            Storage::delete($row->photo);
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data dihapus',
        ]);
    }
}
