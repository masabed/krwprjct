<?php

namespace App\Http\Controllers\Api\Db;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PerumahanDb;
use App\Models\PerumahanUploadTemp;
use App\Models\PerumahanUpload;
use App\Models\PsuSerahTerima;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PerumahanDbController extends Controller
{
    /** Kolom JSON array UUID yang dikelola (harus kirim UUID dari upload) */
    private const FILE_ARRAY_FIELDS = [
        'foto_gerbang',        // max 5
        'fileSerahTerimaTPU',  // max 10
    ];

    // ================== UPLOAD TEMP ==================
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $uuid = (string) Str::uuid();

        $path = $file->storeAs('perumahan_temp', "{$uuid}.{$ext}", 'local'); // PRIVATE

        $temp = PerumahanUploadTemp::create([
            'uuid'          => $uuid,
            'user_id'       => (string) $userId,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime'          => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'uuid'    => $temp->uuid,
                'user_id' => $temp->user_id,
            ],
        ], 201);
    }

    // ================== CREATE (TEMP → FINAL) ==================
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Normalisasi kolom array UUID
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }
        // String biasa opsional (tetap ADA)
        $this->normalizeFlexibleString($request, 'bastTPU');

        $validated = $request->validate([
            'namaPerumahan'       => 'required|string|max:255',
            'developerPerumahan'  => 'required|string|max:255',
            'tahunDibangun'       => 'required|string|max:10',
            'luasPerumahan'       => 'required|string|max:50',
            'jenisPerumahan'      => 'required|string|max:100',
            'kecamatan'           => 'required|string|max:100',
            'kelurahan'           => 'required|string|max:100',
            'alamatPerumahan'     => 'required|string|max:500',
            'rwPerumahan'         => 'sometimes|string|max:100',
            'rtPerumahan'         => 'sometimes|string|max:100',
            'titikLokasi'         => 'required|string|max:255',

            // ARRAY UUID
            'foto_gerbang'        => 'required|array|min:1|max:5',
            'foto_gerbang.*'      => 'uuid',

            // ARRAY UUID opsional
            'fileSerahTerimaTPU'  => 'sometimes|array|max:10',
            'fileSerahTerimaTPU.*'=> 'uuid',

            // String biasa (INI YANG DIBIARKAN)
            'bastTPU'             => 'sometimes|nullable|string|max:255',
            'pesan_verifikasi'    => 'sometimes|nullable|string|max:512',

            // Kontrol
            'duplicate'           => 'sometimes|boolean',
            'status_serah_terima' => 'sometimes|boolean',
        ]);

        if (!array_key_exists('status_serah_terima', $validated)) {
            $validated['status_serah_terima'] = false;
        }

        // FINAL-kan kolom file array UUID
        $finals = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $validated)) {
                $max = $f === 'foto_gerbang' ? 5 : 10;
                $finals[$f] = $this->ensureFinalUploads(
                    array_slice($validated[$f], 0, $max),
                    (string) $user->id,
                    $request->boolean('duplicate')
                );
            }
        }

        // Satukan payload
        $payload = array_merge($validated, $finals);

        // Default [] untuk yang tidak dikirim
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (!array_key_exists($f, $payload)) $payload[$f] = [];
        }

        unset($payload['duplicate']); // bukan kolom tabel

        $row = PerumahanDb::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Data created',
            'data'    => $row,
        ], 201);
    }

    // ================== UPDATE ==================
    public function update(Request $request, string $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $row = PerumahanDb::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Aliases ringan (opsional)
        $aliases = [
            'nama'        => 'namaPerumahan',
            'developer'   => 'developerPerumahan',
            'tahun'       => 'tahunDibangun',
            'luas'        => 'luasPerumahan',
            'jenis'       => 'jenisPerumahan',
            'alamat'      => 'alamatPerumahan',
            'titik'       => 'titikLokasi',
            'fotoGerbang' => 'foto_gerbang',
        ];
        $merge = [];
        foreach ($aliases as $from => $to) {
            if ($request->has($from) && !$request->has($to)) $merge[$to] = $request->input($from);
        }
        if ($merge) $request->merge($merge);

        // Normalisasi yang dikirim saja
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f)) $this->normalizeUuidArrayField($request, $f);
        }
        if ($request->has('bastTPU')) $this->normalizeFlexibleString($request, 'bastTPU');

        // Validasi partial
        $validated = $request->validate([
            'namaPerumahan'       => 'sometimes|string|max:255',
            'developerPerumahan'  => 'sometimes|string|max:255',
            'tahunDibangun'       => 'sometimes|string|max:10',
            'luasPerumahan'       => 'sometimes|string|max:50',
            'jenisPerumahan'      => 'sometimes|string|max:100',
            'kecamatan'           => 'sometimes|string|max:100',
            'kelurahan'           => 'sometimes|string|max:100',
            'alamatPerumahan'     => 'sometimes|string|max:500',
            'rwPerumahan'         => 'sometimes|string|max:100',
            'rtPerumahan'         => 'sometimes|string|max:100',
            'titikLokasi'         => 'sometimes|string|max:255',
            'status_serah_terima' => 'sometimes|boolean',

            // String biasa (TETAP ADA)
            'bastTPU'             => 'sometimes|nullable|string|max:255',
            'pesan_verifikasi'    => 'sometimes|nullable|string|max:512',

            // FILE ARRAY (nullable: null → abaikan)
            'foto_gerbang'        => 'sometimes|nullable|array|min:1|max:5',
            'foto_gerbang.*'      => 'uuid',
            'fileSerahTerimaTPU'  => 'sometimes|nullable|array|max:10',
            'fileSerahTerimaTPU.*'=> 'uuid',

            // kontrol
            'duplicate'           => 'sometimes|boolean',
        ]);

        // Tangani kolom file array UUID kalau dikirim
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (!array_key_exists($f, $validated)) continue;      // tidak dikirim
            if (is_null($validated[$f])) { unset($validated[$f]); continue; } // null → abaikan

            $max = $f === 'foto_gerbang' ? 5 : 10;
            $incoming = $this->ensureFinalUploads(
                array_slice($validated[$f], 0, $max),
                (string) $user->id,
                $request->boolean('duplicate')
            );

            $old = $row->getAttribute($f) ?? [];
            $old = is_array($old) ? $old : [];

            $different = (count(array_diff($incoming, $old)) > 0) || (count(array_diff($old, $incoming)) > 0);
            if ($different) {
                $this->deleteFinalUploads($old);
                $validated[$f] = $incoming;
            } else {
                unset($validated[$f]);
            }
        }

        unset($validated['duplicate']); // bukan kolom

        // Simpan jika ada perubahan
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
            'message' => 'Field Berikut Berhasil di Perbaharui: ' . implode(', ', array_keys($dirty)),
            'data'    => $row->fresh(),
        ]);
    }

    // ================== SHOW (by UUID) ==================



public function show(string $id)
{
    $row = PerumahanDb::find($id);
    if (!$row) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // Ambil semua serah-terima PSU untuk perumahan ini (terbaru dulu)
    $raw = PsuSerahTerima::where('perumahanId', $id)
        ->whereNotNull('noBASTPSU')
        ->where('noBASTPSU', '!=', '')
        ->orderByDesc('created_at')
        ->get(['id', 'noBASTPSU', 'created_at']);

    // Dedup by noBASTPSU (case-insensitive, tanpa spasi)
    $seen = [];
    $uniq = $raw->filter(function ($r) use (&$seen) {
        $key = strtoupper(preg_replace('/\s+/', '', (string) $r->noBASTPSU));
        if (isset($seen[$key])) return false;
        $seen[$key] = true;
        return true;
    })->values();

    // Bentuk list array 0..n
    $list = $uniq->map(function ($r) {
        return [
            'idSerahTerima' => $r->id,
            'noBASTPSU'     => $r->noBASTPSU,
            'created_at'    => optional($r->created_at)->toIso8601String(),
        ];
    })->values();

    // Data perumahan tanpa field “latest”
    $data = $row->toArray();
    unset($data['idSerahTerima'], $data['noBASTPSU']); // jaga-jaga kalau pernah ada

    // Tambahkan list saja
    $data['psuSerahTerimaList'] = $list;

    return response()->json([
        'success' => true,
        'data'    => $data,
    ]);
}


    // ================== SHOW DATA SATUAN + RELASI KE SERAH TERIMA==================



/**
 * GET /api/perumahan/{id}/psu-serah-terima
 * Return daftar PSU Serah Terima milik perumahan {id}.
 * Optional query:
 *   - status_verifikasi=0|1|2|3|4 (filter)
 *   - only_ids=true (hanya list ID)
 */
// public function psuSerahTerimaByPerumahan(Request $request, string $id)
// {
//     // Pastikan perumahan ada
//     $perumahan = PerumahanDb::find($id);
//     if (!$perumahan) {
//         return response()->json(['success' => false, 'message' => 'Data perumahan tidak ditemukan'], 404);
//     }

//     $q = PsuSerahTerima::query()
//         ->where('perumahanId', $id)
//         ->latest();

//     // Filter optional: status_verifikasi
//     if ($request->has('status_verifikasi')) {
//         $q->where('status_verifikasi', (int) $request->query('status_verifikasi'));
//     }

//     // Ambil data ringkas saja (hemat payload)
//     $items = $q->get([
//         'id',
//         'status_verifikasi',
//         'jenisPSU',          // json array of strings (nullable)
//         'noBASTPSU',         // json array of strings (nullable)
//         'created_at',
//         'updated_at',
//     ]);

//     // Hanya ID?
//     if (filter_var($request->query('only_ids'), FILTER_VALIDATE_BOOLEAN)) {
//         return response()->json([
//             'success' => true,
//             'perumahan' => [
//                 'id'             => $perumahan->id,
//                 'namaPerumahan'  => $perumahan->namaPerumahan,
//                 'developer'      => $perumahan->developerPerumahan,
//             ],
//             'psu_serah_terima_ids' => $items->pluck('id'),
//         ]);
//     }

//     // Default: kirim list ringkas + list id
//     return response()->json([
//         'success' => true,
//         'perumahan' => [
//             'id'             => $perumahan->id,
//             'namaPerumahan'  => $perumahan->namaPerumahan,
//             'developer'      => $perumahan->developerPerumahan,
//         ],
//         'psu_serah_terima_ids' => $items->pluck('id'),
//         'psu_serah_terima'     => $items,
//     ]);
// }

    // ================== DELETE ROW ==================
    public function destroy(string $id)
    {
        $row = PerumahanDb::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Hapus juga file FINAL dari kolom file array
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->deleteFinalUploads($row->getAttribute($f) ?? []);
        }

        $row->delete();

        return response()->json(['success' => true, 'message' => 'Data Berhasil Di Hapus']);
    }

    // ================== INDEX ==================
    public function index(Request $request)
    {
        $q = PerumahanDb::query()->latest();

        if ($request->has('status_serah_terima')) {
            $q->where(
                'status_serah_terima',
                filter_var($request->query('status_serah_terima'), FILTER_VALIDATE_BOOLEAN)
            );
        }

        return response()->json(['success' => true, 'data' => $q->get()]);
    }

    // ================== LIST NAMA & ID ==================
    public function listNamaDanId(Request $request)
    {
        $q = PerumahanDb::query()->select('id', 'namaPerumahan', 'developerPerumahan', 'status_serah_terima');

        if ($request->has('status_serah_terima')) {
            $status = filter_var($request->status_serah_terima, FILTER_VALIDATE_BOOLEAN);
            $q->where('status_serah_terima', $status);
        }

        return response()->json([
            'success' => true,
            'data'    => $q->get(),
        ]);
    }

    // ================== HELPERS ==================

    /** Normalisasi field array-UUID dari JSON/CSV/single/path → array UUID murni / null */
    private function normalizeUuidArrayField(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);
        if ($val === null) return;

        if (is_array($val)) {
            $uuids = [];
            foreach ($val as $v) {
                $u = $this->extractUuid((string)$v);
                if ($u) $uuids[] = $u;
            }
            $request->merge([$field => array_values(array_unique($uuids))]);
            return;
        }

        if (is_string($val)) {
            $t = trim($val);
            if ($t === '' || strtolower($t) === 'null') {
                $request->merge([$field => null]); return;
            }

            if ($t[0] === '[') {
                $arr = json_decode($t, true);
                $uuids = [];
                if (is_array($arr)) {
                    foreach ($arr as $v) {
                        $u = $this->extractUuid((string)$v);
                        if ($u) $uuids[] = $u;
                    }
                }
                $request->merge([$field => array_values(array_unique($uuids))]);
                return;
            }

            if (str_contains($t, ',')) {
                $parts = array_map('trim', explode(',', $t));
                $uuids = [];
                foreach ($parts as $p) {
                    $u = $this->extractUuid($p);
                    if ($u) $uuids[] = $u;
                }
                $request->merge([$field => array_values(array_unique($uuids))]);
                return;
            }

            $u = $this->extractUuid($t);
            $request->merge([$field => $u ? [$u] : []]);
        }
    }

    /** Normalisasi string fleksibel: array/JSON → first; ''/"null" → null */
    private function normalizeFlexibleString(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);
        if ($val === null) return;

        if (is_array($val)) {
            $request->merge([$field => (string) ($val[0] ?? null)]); return;
        }

        if (is_string($val)) {
            $t = trim($val);
            if ($t === '' || strtolower($t) === 'null') { $request->merge([$field => null]); return; }
            if ($t[0] === '[') {
                $arr = json_decode($t, true);
                $first = is_array($arr) ? ($arr[0] ?? null) : null;
                $request->merge([$field => $first ? (string)$first : null]);
            }
            return;
        }

        $request->merge([$field => null]);
    }

    /** Ambil UUID dari string/path (v1–v5) */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /**
     * FINAL-kan daftar UUID (TEMP → FINAL atau reuse FINAL milik user).
     */
    private function ensureFinalUploads(array $uuids, string $userId, bool $duplicate = false): array
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return [];

        $disk  = Storage::disk('local');
        $final = PerumahanUpload::whereIn('uuid', $uuids)->get()->keyBy('uuid');
        $temps = PerumahanUploadTemp::whereIn('uuid', $uuids)
            ->where('user_id', $userId)->get()->keyBy('uuid');

        $result = [];

        foreach ($uuids as $u) {
            if ($final->has($u)) {
                $row = $final->get($u);
                if ((string)$row->user_id !== (string)$userId) continue;
                if (!$disk->exists($row->file_path)) continue;

                if ($duplicate) {
                    $ext     = pathinfo($row->file_path, PATHINFO_EXTENSION);
                    $newUuid = (string) Str::uuid();
                    $newName = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                    $newPath = 'perumahan_final/'.$newName;

                    $disk->copy($row->file_path, $newPath);

                    PerumahanUpload::create([
                        'uuid'          => $newUuid,
                        'user_id'       => $userId,
                        'file_path'     => $newPath,
                        'original_name' => $row->original_name,
                        'mime'          => $row->mime,
                        'size'          => $row->size,
                    ]);

                    $result[] = $newUuid;
                } else {
                    $result[] = $u;
                }
                continue;
            }

            if ($temps->has($u)) {
                $temp = $temps->get($u);
                if (!$disk->exists($temp->file_path)) continue;

                $filename = basename($temp->file_path);
                $ext      = pathinfo($filename, PATHINFO_EXTENSION);
                $newName  = $filename;
                $newPath  = 'perumahan_final/'.$newName;

                if ($disk->exists($newPath)) {
                    $newName = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                    $newPath = 'perumahan_final/'.$newName;
                }

                DB::transaction(function () use ($disk, $temp, $newPath, $userId) {
                    $disk->move($temp->file_path, $newPath);

                    PerumahanUpload::updateOrCreate(
                        ['uuid' => $temp->uuid],
                        [
                            'user_id'       => $userId,
                            'file_path'     => $newPath,
                            'original_name' => $temp->original_name,
                            'mime'          => $temp->mime,
                            'size'          => $temp->size,
                        ]
                    );

                    $temp->delete();
                });

                $result[] = $u;
            }
        }

        return array_values(array_unique($result));
    }

    /** Hapus file FINAL + record berdasarkan daftar UUID */
    private function deleteFinalUploads($uuids): void
    {
        $uuids = array_values(array_unique(array_filter(is_array($uuids) ? $uuids : [])));
        if (!$uuids) return;

        $disk  = Storage::disk('local');
        $files = PerumahanUpload::whereIn('uuid', $uuids)->get();

        foreach ($files as $f) {
            if ($f->file_path && $disk->exists($f->file_path)) {
                $disk->delete($f->file_path);
            }
            $f->delete();
        }
    }
}
