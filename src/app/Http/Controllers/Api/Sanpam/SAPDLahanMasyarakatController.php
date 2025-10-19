<?php

namespace App\Http\Controllers\Api\Sanpam;

use App\Http\Controllers\Controller;
use App\Models\SAPDLahanMasyarakat;
use App\Models\SAPDUpload;
use App\Models\SAPDUploadTemp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SAPDLahanMasyarakatController extends Controller
{
    private const FILE_ARRAY_FIELDS = [
        'buktiKepemilikan',
        'dokumenProposal',
        'dokumenDJPM',
        'fotoLahan',
    ];

    /**
     * POST /api/sanpam/lahan/submit
     * Create usulan + pindahkan file dari sapd_temp → sapd_final.
     */
    public function submit(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Aliases + normalisasi file array
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        // Validasi
        $payload = $request->validate([
            'namaPemilikLahan'   => 'required|string|max:255',
            'ukuranLahan'        => 'required|string|max:50',
            'statusKepemilikan'  => 'required|string|max:100',

            'alamatDusun'        => 'required|string|max:255',
            'alamatRT'           => 'required|string|max:10',
            'alamatRW'           => 'required|string|max:10',

            'kecamatan'          => 'required|string|max:150',
            'kelurahan'          => 'required|string|max:150',
            'titikLokasi'        => 'nullable|string|max:255',
            'pesan_verifikasi'   => 'nullable|string|max:512',

            // FILE ARRAYS
            'buktiKepemilikan'   => 'required|array|min:1|max:10',
            'buktiKepemilikan.*' => 'uuid',
            'dokumenProposal'    => 'required|array|min:1|max:10',
            'dokumenProposal.*'  => 'uuid',
            'dokumenDJPM'        => 'required|array|min:1|max:10',
            'dokumenDJPM.*'      => 'uuid',
            'fotoLahan'          => 'required|array|min:1|max:10',
            'fotoLahan.*'        => 'uuid',
        ]);

        $uuid = (string) Str::uuid();

        // Simpan data + user_id
        $row = SAPDLahanMasyarakat::create(array_merge($payload, [
            'uuid'                     => $uuid,
            'user_id'                  => (string) $user->id,   // <— simpan pemilik
            'status_verifikasi_usulan' => 0,
        ]));

        // Pindahkan semua UUID dari TEMP → FINAL
        $allUuids = array_unique(array_merge(
            $payload['buktiKepemilikan'],
            $payload['dokumenProposal'],
            $payload['dokumenDJPM'],
            $payload['fotoLahan'],
        ));
        $this->moveTempsToFinal($allUuids, (string) $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Usulan lahan masyarakat berhasil disimpan',
            'uuid'    => $uuid,
            'data'    => $row,
        ], 201);
    }

    /**
     * POST /api/sanpam/lahan/update/{uuid}
     * Partial update (file: ARRAY UUID). Null → diabaikan; tidak kirim → tetap.
     */
    public function update(Request $request, string $uuid)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = SAPDLahanMasyarakat::where('uuid', $uuid)
            // ->where('user_id', $user->id) // <— aktifkan jika hanya pemilik boleh update
            ->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            'namaPemilikLahan'          => 'sometimes|string|max:255',
            'ukuranLahan'               => 'sometimes|string|max:50',
            'statusKepemilikan'         => 'sometimes|string|max:100',

            'alamatDusun'               => 'sometimes|string|max:255',
            'alamatRT'                  => 'sometimes|string|max:10',
            'alamatRW'                  => 'sometimes|string|max:10',

            'kecamatan'                 => 'sometimes|string|max:150',
            'kelurahan'                 => 'sometimes|string|max:150',
            'titikLokasi'               => 'sometimes|nullable|string|max:255',
            'pesan_verifikasi'          => 'sometimes|nullable|string|max:512',

            // FILE ARRAYS (nullable → null = abaikan)
            'buktiKepemilikan'          => 'sometimes|nullable|array|min:1|max:10',
            'buktiKepemilikan.*'        => 'uuid',
            'dokumenProposal'           => 'sometimes|nullable|array|min:1|max:10',
            'dokumenProposal.*'         => 'uuid',
            'dokumenDJPM'               => 'sometimes|nullable|array|min:1|max:10',
            'dokumenDJPM.*'             => 'uuid',
            'fotoLahan'                 => 'sometimes|nullable|array|min:1|max:10',
            'fotoLahan.*'               => 'uuid',

            'status_verifikasi_usulan'  => 'sometimes|integer|in:0,1,2,3,4',
        ]);

        // UUID baru yang perlu dipindah
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f) && is_array($request->input($f))) {
                $incoming = $request->input($f);
                $existing = $item->getAttribute($f) ?? [];
                $diff = array_diff($incoming, is_array($existing) ? $existing : []);
                if (!empty($diff)) {
                    $uuidsToMove = array_merge($uuidsToMove, $diff);
                }
            }
        }
        if (!empty($uuidsToMove)) {
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $user->id);
        }

        // Siapkan payload update (jangan timpa file array dengan null)
        $updateData = $validated;
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
                unset($updateData[$f]);
            }
        }

        // Catat field berubah
        $changed = [];
        foreach ($updateData as $key => $val) {
            if ($item->getAttribute($key) !== $val) {
                $changed[] = $key;
            }
        }

        if (!empty($updateData)) {
            $item->update($updateData);
        }

        $message = empty($changed)
            ? 'Tidak ada perubahan data'
            : 'Field Berikut Berhasil di Perbaharui: ' . implode(', ', $changed);

        return response()->json([
            'success' => true,
            'message' => $message,
            'uuid'    => $item->uuid,
            'data'    => $item->fresh(),
        ]);
    }

    /**
     * DELETE /api/sanpam/lahan/{uuid}
     */
    public function destroy(string $uuid)
    {
        $item = SAPDLahanMasyarakat::where('uuid', $uuid)
            // ->where('user_id', auth()->id()) // <— aktifkan jika hanya pemilik boleh hapus
            ->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus',
        ]);
    }

    /**
     * GET /api/sanpam/lahan
     * List usulan: hanya UUID file (array).
     */
    public function index()
    {
        $items = SAPDLahanMasyarakat::latest()->get()->map(function ($it) {
            return [
                'uuid'                      => $it->uuid,
                'user_id'                   => $it->user_id, // tampilkan pemilik
                'namaPemilikLahan'          => $it->namaPemilikLahan,
                'ukuranLahan'               => $it->ukuranLahan,
                'statusKepemilikan'         => $it->statusKepemilikan,

                'alamatDusun'               => $it->alamatDusun,
                'alamatRT'                  => $it->alamatRT,
                'alamatRW'                  => $it->alamatRW,

                'kecamatan'                 => $it->kecamatan,
                'kelurahan'                 => $it->kelurahan,
                'titikLokasi'               => $it->titikLokasi,

                // Arrays UUID
                'buktiKepemilikan'          => $it->buktiKepemilikan,
                'dokumenProposal'           => $it->dokumenProposal,
                'dokumenDJPM'               => $it->dokumenDJPM,
                'fotoLahan'                 => $it->fotoLahan,

                'status_verifikasi_usulan'  => $it->status_verifikasi_usulan,
                'pesan_verifikasi'          => $it->pesan_verifikasi,

                'created_at'                => $it->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * GET /api/sanpam/lahan/{uuid}
     * Detail: hanya UUID file (array).
     */
    public function show(string $uuid)
    {
        $it = SAPDLahanMasyarakat::where('uuid', $uuid)->first();

        if (!$it) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'usulan' => [
                    'uuid'                      => $it->uuid,
                    'user_id'                   => $it->user_id, // tampilkan pemilik
                    'namaPemilikLahan'          => $it->namaPemilikLahan,
                    'ukuranLahan'               => $it->ukuranLahan,
                    'statusKepemilikan'         => $it->statusKepemilikan,

                    'alamatDusun'               => $it->alamatDusun,
                    'alamatRT'                  => $it->alamatRT,
                    'alamatRW'                  => $it->alamatRW,

                    'kecamatan'                 => $it->kecamatan,
                    'kelurahan'                 => $it->kelurahan,
                    'titikLokasi'               => $it->titikLokasi,

                    // Arrays UUID
                    'buktiKepemilikan'          => $it->buktiKepemilikan,
                    'dokumenProposal'           => $it->dokumenProposal,
                    'dokumenDJPM'               => $it->dokumenDJPM,
                    'fotoLahan'                 => $it->fotoLahan,

                    'status_verifikasi_usulan'  => $it->status_verifikasi_usulan,
                    'pesan_verifikasi'          => $it->pesan_verifikasi,

                    'created_at'                => $it->created_at,
                    'updated_at'                => $it->updated_at,
                ],
            ],
        ]);
    }

    // ================= Helpers =================

    /** Aliases snake_case → camelCase */
    private function applyAliases(Request $request): void
    {
        $aliases = [
            'nama_pemilik_lahan'  => 'namaPemilikLahan',
            'ukuran_lahan'        => 'ukuranLahan',
            'status_kepemilikan'  => 'statusKepemilikan',
            'alamat_dusun'        => 'alamatDusun',
            'alamat_rt'           => 'alamatRT',
            'alamat_rw'           => 'alamatRW',
            'titik_lokasi'        => 'titikLokasi',
            'pesan_verifikasi'    => 'pesan_verifikasi',
            'bukti_kepemilikan'   => 'buktiKepemilikan',
            'dokumen_proposal'    => 'dokumenProposal',
            'dokumen_djpm'        => 'dokumenDJPM',
            'foto_lahan'          => 'fotoLahan',
        ];
        $merge = [];
        foreach ($aliases as $from => $to) {
            if ($request->has($from) && !$request->has($to)) {
                $merge[$to] = $request->input($from);
            }
        }
        if ($merge) $request->merge($merge);
    }

    /**
     * Normalisasi satu field array-UUID dari berbagai bentuk input:
     * - JSON string: '["uuid1","uuid2"]'
     * - CSV:         'uuid1,uuid2'
     * - Single:      'uuid1'
     * - Array campur path: ['sapd_temp/xx_uuid1.jpg','uuid2']
     * - "null"/'' → null
     */
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
                if (is_array($arr)) {
                    $uuids = [];
                    foreach ($arr as $v) {
                        $u = $this->extractUuid((string) $v);
                        if ($u) $uuids[] = $u;
                    }
                    $request->merge([$field => array_values(array_unique($uuids))]);
                }
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
            return;
        }
    }

    /** Ekstrak UUID dari string/path (v1–v7 toleran). */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /**
     * Pindahkan file-file dari TEMP → FINAL untuk daftar UUID (user yang sama).
     * Tidak expose path; hanya menjaga rekaman di tabel SAPDUpload.
     */
    private function moveTempsToFinal(array $uuids, string $userId): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return;

        $temps = SAPDUploadTemp::whereIn('uuid', $uuids)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('uuid');

        foreach ($uuids as $u) {
            $temp = $temps->get($u);
            if (!$temp) {
                // mungkin sudah FINAL (reuse) → lewati
                continue;
            }

            $oldPath  = $temp->file_path;               // sapd_temp/<something>.<ext>
            $filename = basename($oldPath);
            $newPath  = 'sapd_final/' . $filename;

            // Hindari overwrite
            if (Storage::exists($newPath)) {
                $ext     = pathinfo($filename, PATHINFO_EXTENSION);
                $newName = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                $newPath = 'sapd_final/' . $newName;
            }

            if (Storage::exists($oldPath)) {
                Storage::move($oldPath, $newPath);
            } elseif (!Storage::exists($newPath)) {
                continue;
            }

            SAPDUpload::updateOrCreate(
                ['uuid' => $temp->uuid],
                ['user_id' => $userId, 'file_path' => $newPath]
            );

            $temp->delete();
        }
    }
}
