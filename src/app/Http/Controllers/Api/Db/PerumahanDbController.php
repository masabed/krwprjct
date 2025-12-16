<?php

namespace App\Http\Controllers\Api\Db;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PerumahanDb;
use App\Models\PerumahanUploadTemp;
use App\Models\PerumahanUpload;
use App\Models\PsuSerahTerima;
use App\Models\TpuSerahTerima;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PerumahanDbController extends Controller
{
    /** Kolom JSON array UUID yang dikelola (harus kirim UUID dari upload) */
    private const FILE_ARRAY_FIELDS = [
        'foto_gerbang', // max 5
    ];

    // =========================================================
    // UPLOAD TEMP
    // =========================================================
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
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

    // =========================================================
    // CREATE (TEMP → FINAL)
    // =========================================================
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

        $validated = $request->validate([
            'namaPerumahan'      => 'required|string|max:255',
            'developerPerumahan' => 'required|string|max:255',
            'tahunDibangun'      => 'required|string|max:10',
            'luasPerumahan'      => 'required|string|max:50',
            'jenisPerumahan'     => 'required|string|max:100',
            'kecamatan'          => 'required|string|max:100',
            'kelurahan'          => 'required|string|max:100',
            'alamatPerumahan'    => 'required|string|max:500',
            'rwPerumahan'        => 'sometimes|string|max:100',
            'rtPerumahan'        => 'sometimes|string|max:100',
            'titikLokasi'        => 'required|string|max:255',

            // ARRAY UUID
            'foto_gerbang'       => 'required|array|min:1|max:5',
            'foto_gerbang.*'     => 'uuid',

            'pesan_verifikasi'   => 'sometimes|nullable|string|max:512',

            // Kontrol
            'duplicate'          => 'sometimes|boolean',
        ]);

        // FINAL-kan kolom file array UUID
        $finals = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $validated)) {
                $max = 5; // foto_gerbang
                $finals[$f] = $this->ensureFinalUploads(
                    array_slice($validated[$f], 0, $max),
                    (string) $user->id,
                    $request->boolean('duplicate')
                );
            }
        }

        // Satukan payload
        $payload = array_merge($validated, $finals);

        // Default [] untuk yang tidak dikirim (jaga-jaga)
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (!array_key_exists($f, $payload)) {
                $payload[$f] = [];
            }
        }

        unset($payload['duplicate']); // bukan kolom tabel

        $row = PerumahanDb::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Data created',
            'data'    => $row,
        ], 201);
    }

    // =========================================================
    // UPDATE
    // =========================================================
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
            if ($request->has($from) && !$request->has($to)) {
                $merge[$to] = $request->input($from);
            }
        }
        if ($merge) {
            $request->merge($merge);
        }

        // Normalisasi yang dikirim saja
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f)) {
                $this->normalizeUuidArrayField($request, $f);
            }
        }

        // Validasi partial
        $validated = $request->validate([
            'namaPerumahan'      => 'sometimes|string|max:255',
            'developerPerumahan' => 'sometimes|string|max:255',
            'tahunDibangun'      => 'sometimes|string|max:10',
            'luasPerumahan'      => 'sometimes|string|max:50',
            'jenisPerumahan'     => 'sometimes|string|max:100',
            'kecamatan'          => 'sometimes|string|max:100',
            'kelurahan'          => 'sometimes|string|max:100',
            'alamatPerumahan'    => 'sometimes|string|max:500',
            'rwPerumahan'        => 'sometimes|string|max:100',
            'rtPerumahan'        => 'sometimes|string|max:100',
            'titikLokasi'        => 'sometimes|string|max:255',

            'pesan_verifikasi'   => 'sometimes|nullable|string|max:512',

            'foto_gerbang'       => 'sometimes|nullable|array|min:1|max:5',
            'foto_gerbang.*'     => 'uuid',

            'duplicate'          => 'sometimes|boolean',
        ]);

        // Tangani kolom file array UUID kalau dikirim
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (!array_key_exists($f, $validated)) {
                continue; // tidak dikirim
            }
            if (is_null($validated[$f])) {
                unset($validated[$f]); // null → abaikan
                continue;
            }

            $max = 5;
            $incoming = $this->ensureFinalUploads(
                array_slice($validated[$f], 0, $max),
                (string) $user->id,
                $request->boolean('duplicate')
            );

            $old = $row->getAttribute($f) ?? [];
            $old = is_array($old) ? $old : [];

            $different = (count(array_diff($incoming, $old)) > 0)
                || (count(array_diff($old, $incoming)) > 0);

            if ($different) {
                $this->deleteFinalUploads($old);
                $validated[$f] = $incoming;
            } else {
                unset($validated[$f]);
            }
        }

        unset($validated['duplicate']); // bukan kolom

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

    // =========================================================
    // SHOW (by ID) + PSU / TPU serah-terima list
    // =========================================================
    public function show(string $id)
    {
        $row = PerumahanDb::find($id);
        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $data = $row->toArray();
        // jaga-jaga kalau dulu pernah ada field ini
        unset($data['idSerahTerima'], $data['noBASTPSU'], $data['noBASTTPU']);

        // ================= PSU Serah Terima =================
        $psuRows = PsuSerahTerima::where('perumahanId', $row->id)
            ->orderByDesc('created_at')
            ->get();

        if ($psuRows->isNotEmpty()) {
            $psuSerahTerimaList = $psuRows->map(function ($r) {
                return [
                    'idSerahTerima'            => (string) $r->id,
                    'noBASTPSU'                => $r->noBASTPSU,
                    'status_verifikasi_usulan' => $r->status_verifikasi_usulan ?? null,
                    'created_at'               => optional($r->created_at)->toIso8601String(),
                    'updated_at'               => optional($r->updated_at)->toIso8601String(),
                ];
            })->values();

            if ($psuSerahTerimaList->isNotEmpty()) {
                $data['psuSerahTerimaList'] = $psuSerahTerimaList;
            }
        }

        // ================= TPU Serah Terima =================
        $tpuRows = TpuSerahTerima::where('perumahanId', $row->id)
            ->orderByDesc('created_at')
            ->get();

        if ($tpuRows->isNotEmpty()) {
            $tpuSerahTerimaList = $tpuRows->map(function ($r) {
                return [
                    'idSerahTerima'            => (string) $r->id,
                    'noBASTTPU'                => $r->noBASTTPU,
                    'status_verifikasi_usulan' => $r->status_verifikasi_usulan ?? null,
                    'created_at'               => optional($r->created_at)->toIso8601String(),
                    'updated_at'               => optional($r->updated_at)->toIso8601String(),
                ];
            })->values();

            if ($tpuSerahTerimaList->isNotEmpty()) {
                $data['tpuSerahTerimaList'] = $tpuSerahTerimaList;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // =========================================================
    // INDEX (SEMUA DATA)
    // =========================================================
    public function index(Request $request)
    {
        $rows = PerumahanDb::query()
            ->latest()
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'success' => true,
                'data'    => [],
            ]);
        }

        $perumahanIds = $rows->pluck('id')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        $tpuPerumahanIds = TpuSerahTerima::query()
            ->whereIn('perumahanId', $perumahanIds)
            ->pluck('perumahanId')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->all();

        $psuPerumahanIds = PsuSerahTerima::query()
            ->whereIn('perumahanId', $perumahanIds)
            ->pluck('perumahanId')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->all();

        $tpuSet = array_flip($tpuPerumahanIds);
        $psuSet = array_flip($psuPerumahanIds);

        $data = $rows->map(function (PerumahanDb $row) use ($tpuSet, $psuSet) {
            $id = (string) $row->id;

            $payload = $row->toArray();
            $payload['hasSerahTerimaTPU'] = array_key_exists($id, $tpuSet);
            $payload['hasSerahTerimaPSU'] = array_key_exists($id, $psuSet);

            return $payload;
        })->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // =========================================================
    // INDEX BY STATUS (0 / 1 / 2) - TANPA status_serah_terima
    // =========================================================

    // GET /api/perumahan-db/status/0
    // Semua perumahan yang BELUM punya TPU dengan noBASTTPU terisi
    public function indexStatus0(Request $request)
    {
        $perumahanTable = (new PerumahanDb)->getTable();
        $tpuTable       = (new TpuSerahTerima)->getTable();

        $rows = PerumahanDb::query()
            ->whereNotExists(function ($q) use ($tpuTable, $perumahanTable) {
                $q->select(DB::raw(1))
                    ->from($tpuTable)
                    ->whereColumn($tpuTable . '.perumahanId', $perumahanTable . '.id')
                    ->whereNotNull($tpuTable . '.noBASTTPU')
                    ->whereRaw("TRIM({$tpuTable}.noBASTTPU) <> ''");
            })
            ->latest()
            ->get();

        $data = $this->attachSerahTerimaLists($rows);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // GET /api/perumahan-db/status/1
    // Semua perumahan yang sudah punya minimal 1 TPU Serah Terima dengan noBASTTPU terisi
    public function indexStatus1(Request $request)
    {
        $perumahanTable = (new PerumahanDb)->getTable();
        $tpuTable       = (new TpuSerahTerima)->getTable();

        $rows = PerumahanDb::query()
            ->whereExists(function ($q) use ($tpuTable, $perumahanTable) {
                $q->select(DB::raw(1))
                    ->from($tpuTable)
                    ->whereColumn($tpuTable . '.perumahanId', $perumahanTable . '.id')
                    ->whereNotNull($tpuTable . '.noBASTTPU')
                    ->whereRaw("TRIM({$tpuTable}.noBASTTPU) <> ''");
            })
            ->latest()
            ->get();

        $data = $this->attachSerahTerimaLists($rows);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // GET /api/perumahan-db/status/2
    // Semua perumahan yang sudah punya TPU dan PSU Serah Terima (BAST terisi)
    public function indexStatus2(Request $request)
    {
        $perumahanTable = (new PerumahanDb)->getTable();
        $tpuTable       = (new TpuSerahTerima)->getTable();
        $psuTable       = (new PsuSerahTerima)->getTable();

        $rows = PerumahanDb::query()
            ->whereExists(function ($q) use ($tpuTable, $perumahanTable) {
                $q->select(DB::raw(1))
                    ->from($tpuTable)
                    ->whereColumn($tpuTable . '.perumahanId', $perumahanTable . '.id')
                    ->whereNotNull($tpuTable . '.noBASTTPU')
                    ->whereRaw("TRIM({$tpuTable}.noBASTTPU) <> ''");
            })
            ->whereExists(function ($q) use ($psuTable, $perumahanTable) {
                $q->select(DB::raw(1))
                    ->from($psuTable)
                    ->whereColumn($psuTable . '.perumahanId', $perumahanTable . '.id')
                    ->whereNotNull($psuTable . '.noBASTPSU')
                    ->whereRaw("TRIM({$psuTable}.noBASTPSU) <> ''");
            })
            ->latest()
            ->get();

        $data = $this->attachSerahTerimaLists($rows);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // =========================================================
    // DELETE
    // =========================================================
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

        return response()->json([
            'success' => true,
            'message' => 'Data Berhasil Di Hapus',
        ]);
    }

    // =========================================================
    // HELPERS
    // =========================================================

    /**
     * Attach psuSerahTerimaList & tpuSerahTerimaList ke setiap row PerumahanDb.
     */
    private function attachSerahTerimaLists($rows)
    {
        $rows = collect($rows);
        $ids  = $rows->pluck('id')->map(fn ($v) => (string) $v)->all();

        if (empty($ids)) {
            return collect();
        }

        $psuByPerumahan = PsuSerahTerima::whereIn('perumahanId', $ids)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('perumahanId');

        $tpuByPerumahan = TpuSerahTerima::whereIn('perumahanId', $ids)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('perumahanId');

        return $rows->map(function (PerumahanDb $row) use ($psuByPerumahan, $tpuByPerumahan) {
            $psuList = ($psuByPerumahan[(string) $row->id] ?? collect())->map(function ($r) {
                return [
                    'idSerahTerima'            => (string) $r->id,
                    'noBASTPSU'                => $r->noBASTPSU,
                    'status_verifikasi_usulan' => $r->status_verifikasi_usulan ?? null,
                    'created_at'               => $r->created_at,
                    'updated_at'               => $r->updated_at,
                ];
            })->values();

            $tpuList = ($tpuByPerumahan[(string) $row->id] ?? collect())->map(function ($r) {
                return [
                    'idSerahTerima'            => (string) $r->id,
                    'noBASTTPU'                => $r->noBASTTPU,
                    'status_verifikasi_usulan' => $r->status_verifikasi_usulan ?? null,
                    'created_at'               => $r->created_at,
                    'updated_at'               => $r->updated_at,
                ];
            })->values();

            $data = $row->toArray();
            $data['psuSerahTerimaList'] = $psuList;
            $data['tpuSerahTerimaList'] = $tpuList;

            return $data;
        })->values();
    }

    /** Normalisasi field array-UUID dari JSON/CSV/single/path → array UUID murni / null */
    private function normalizeUuidArrayField(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);
        if ($val === null) return;

        if (is_array($val)) {
            $uuids = [];
            foreach ($val as $v) {
                $u = $this->extractUuid((string) $v);
                if ($u) $uuids[] = $u;
            }
            $request->merge([$field => array_values(array_unique($uuids))]);
            return;
        }

        if (is_string($val)) {
            $t = trim($val);
            if ($t === '' || strtolower($t) === 'null') {
                $request->merge([$field => null]);
                return;
            }

            if ($t[0] === '[') {
                $arr = json_decode($t, true);
                $uuids = [];
                if (is_array($arr)) {
                    foreach ($arr as $v) {
                        $u = $this->extractUuid((string) $v);
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

    /** Ambil UUID dari string/path (v1–v5) */
    private function extractUuid(string $value): ?string
    {
        if (preg_match(
            '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/',
            $value,
            $m
        )) {
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
            // Sudah FINAL
            if ($final->has($u)) {
                $row = $final->get($u);
                if ((string) $row->user_id !== (string) $userId) continue;
                if (!$disk->exists($row->file_path)) continue;

                if ($duplicate) {
                    $ext     = pathinfo($row->file_path, PATHINFO_EXTENSION);
                    $newUuid = (string) Str::uuid();
                    $newName = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                    $newPath = 'perumahan_final/' . $newName;

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

            // Masih TEMP
            if ($temps->has($u)) {
                $temp = $temps->get($u);
                if (!$disk->exists($temp->file_path)) continue;

                $filename = basename($temp->file_path);
                $ext      = pathinfo($filename, PATHINFO_EXTENSION);
                $newName  = $filename;
                $newPath  = 'perumahan_final/' . $newName;

                if ($disk->exists($newPath)) {
                    $newName = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                    $newPath = 'perumahan_final/' . $newName;
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
