<?php

namespace App\Http\Controllers\Api\Perencanaan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Perencanaan;

class PerencanaanController extends Controller
{
    /**
     * GET /api/psu/perencanaan
     * optional query: ?uuidUsulan={uuid}
     *
     * List semua data perencanaan (bisa difilter per uuidUsulan)
     */
    public function index(Request $request)
    {
        $q = Perencanaan::query()->latest();

        if ($request->has('uuidUsulan')) {
            $q->where('uuidUsulan', $request->query('uuidUsulan'));
        }

        return response()->json([
            'success' => true,
            'data'    => $q->get(),
        ]);
    }

    /**
     * POST /api/psu/perencanaan/create
     *
     * Body:
     * {
     *   "uuidUsulan": "uuid",        (required)
     *   "nilaiHPS": "string|null",
     *   "catatanSurvey": "string|null"
     * }
     *
     * Catatan:
     * - Kolom PK di DB adalah "id" (UUID string), bukan auto-increment.
     * - Model Perencanaan akan auto-generate "id" di boot() kalau kosong.
     */
    public function store(Request $request)
    {
        // kalau mau pakai auth:
        // $user = $request->user();
        // if (!$user) {
        //     return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        // }

        $validated = $request->validate([
            'uuidUsulan'     => ['required', 'uuid'],
            'nilaiHPS'       => ['sometimes','nullable','string','max:255'],
            'catatanSurvey'  => ['sometimes','nullable','string','max:512'],
        ]);

        // langsung create, model yg generate "id"
        $row = Perencanaan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data perencanaan berhasil dibuat',
            'data'    => $row,
        ], 201);
    }

    /**
     * PUT/PATCH /api/psu/perencanaan/update/{id}
     * Param {id} = UUID kolom "id" (primary key di tabel perencanaans)
     *
     * Body (semua optional):
     * {
     *   "uuidUsulan": "uuid",
     *   "nilaiHPS": "string|null",
     *   "catatanSurvey": "string|null"
     * }
     */
    public function update(Request $request, string $id)
    {
        // cari exact match by PK
        $row = Perencanaan::find($id);

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        // kalau mau force login:
        // $user = $request->user();
        // if (!$user) {
        //     return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        // }

        $validated = $request->validate([
            'uuidUsulan'     => ['sometimes','uuid'],
            'nilaiHPS'       => ['sometimes','nullable','string','max:255'],
            'catatanSurvey'  => ['sometimes','nullable','string','max:512'],
        ]);

        $row->fill($validated);

        $dirty = $row->getDirty();
        if (empty($dirty)) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada perubahan data',
                'data'    => $row,
                'changed' => [],
            ]);
        }

        $row->save();

        return response()->json([
            'success' => true,
            'message' => 'Field berikut berhasil diperbarui: ' . implode(', ', array_keys($dirty)),
            'data'    => $row->fresh(),
        ]);
    }

    /**
     * GET /api/psu/perencanaan/{id}
     * Param {id} = UUID kolom "id"
     */
    public function show(string $id)
    {
        $row = Perencanaan::find($id);

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
}
