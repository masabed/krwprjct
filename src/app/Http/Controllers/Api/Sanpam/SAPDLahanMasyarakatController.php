<?php

namespace App\Http\Controllers\Api\Sanpam;

use App\Http\Controllers\Controller;
use App\Models\SAPDLahanMasyarakat;
use App\Models\SAPDUpload;
use App\Models\SAPDUploadTemp;
use App\Models\Perencanaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SAPDLahanMasyarakatController extends Controller
{
    // === File array fields sesuai skema terbaru ===
    private const FILE_ARRAY_FIELDS = [
        'buktiKepemilikan',     // tetap
        'buktiLegalitasTanah',  // pengganti dokumenDJPM
        'dokumenProposal',
        'fotoLahan',
    ];

    /**
     * POST /api/sanpam/lahan/submit
     */
    public function submit(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // alias snake_case & backward-compat
        $this->applyAliases($request);

        // normalisasi semua field file array jd array UUID bersih
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        // validasi payload (nama kolom terbaru)
        $payload = $request->validate([
            'namaPemilikLahan'     => 'required|string|max:255',
            'ukuranLahan'          => 'required|string|max:50',
            'statusLegalitasTanah' => 'required|string|max:100',

            'alamatDusun'          => 'required|string|max:255',
            'alamatRT'             => 'required|string|max:10',
            'alamatRW'             => 'required|string|max:10',

            'kecamatan'            => 'required|string|max:150',
            'kelurahan'            => 'required|string|max:150',
            'titikLokasi'          => 'nullable|string|max:255',
            'pesan_verifikasi'     => 'nullable|string|max:512',

            // FILE ARRAYS
            'buktiKepemilikan'       => 'required|array|min:1|max:10',
            'buktiKepemilikan.*'     => 'uuid',
            'buktiLegalitasTanah'    => 'nullable|array|min:1|max:10',
            'buktiLegalitasTanah.*'  => 'uuid',
            'dokumenProposal'        => 'required|array|min:1|max:10',
            'dokumenProposal.*'      => 'uuid',
            'fotoLahan'              => 'required|array|min:1|max:10',
            'fotoLahan.*'            => 'uuid',
        ]);

        // create (HasUuids auto set PK 'uuid' di model)
        $row = SAPDLahanMasyarakat::create([
            ...$payload,
            'user_id'                  => (string) $user->id,
            'status_verifikasi_usulan' => 0,
        ]);

        // Pindahkan UUID file dari TEMP → FINAL
        $allUuids = array_unique(array_merge(
            $payload['buktiKepemilikan'] ?? [],
            $payload['buktiLegalitasTanah'] ?? [],
            $payload['dokumenProposal'] ?? [],
            $payload['fotoLahan'] ?? [],
        ));
        $this->moveTempsToFinal($allUuids, (string) $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Usulan lahan masyarakat berhasil disimpan',
            'uuid'    => $row->uuid,
            'data'    => $row,
        ], 201);
    }

    /**
     * POST /api/sanpam/lahan/update/{uuid}
     */
    public function update(Request $request, string $uuid)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = SAPDLahanMasyarakat::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // alias & normalisasi
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            'namaPemilikLahan'       => 'sometimes|string|max:255',
            'ukuranLahan'            => 'sometimes|string|max:50',
            'statusLegalitasTanah'   => 'sometimes|string|max:100',

            'alamatDusun'            => 'sometimes|string|max:255',
            'alamatRT'               => 'sometimes|string|max:10',
            'alamatRW'               => 'sometimes|string|max:10',

            'kecamatan'              => 'sometimes|string|max:150',
            'kelurahan'              => 'sometimes|string|max:150',
            'titikLokasi'            => 'sometimes|nullable|string|max:255',
            'pesan_verifikasi'       => 'sometimes|nullable|string|max:512',

            // FILE ARRAYS (nullable → null = abaikan/ tidak overwrite)
            'buktiKepemilikan'       => 'sometimes|nullable|array|min:1|max:10',
            'buktiKepemilikan.*'     => 'uuid',
            'buktiLegalitasTanah'    => 'sometimes|nullable|array|min:1|max:10',
            'buktiLegalitasTanah.*'  => 'uuid',
            'dokumenProposal'        => 'sometimes|nullable|array|min:1|max:10',
            'dokumenProposal.*'      => 'uuid',
            'fotoLahan'              => 'sometimes|nullable|array|min:1|max:10',
            'fotoLahan.*'            => 'uuid',

            'status_verifikasi_usulan' => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
        ]);

        // Deteksi UUID file baru → pindahkan temp→final
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f) && is_array($request->input($f))) {
                $incoming = $request->input($f);
                $existing = $item->getAttribute($f) ?? [];
                $diff = array_diff($incoming, is_array($existing) ? $existing : []);
                if ($diff) $uuidsToMove = array_merge($uuidsToMove, $diff);
            }
        }
        if ($uuidsToMove) {
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $user->id);
        }

        // Jangan overwrite array file jadi null
        $updateData = $validated;
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
                unset($updateData[$f]);
            }
        }

        // catat changes (optional untuk pesan)
        $changed = [];
        foreach ($updateData as $k => $v) {
            if ($item->getAttribute($k) !== $v) $changed[] = $k;
        }

        if ($updateData) $item->update($updateData);

        return response()->json([
            'success' => true,
            'message' => $changed ? ('Field Berikut Berhasil di Perbaharui: ' . implode(', ', $changed)) : 'Tidak ada perubahan data',
            'uuid'    => $item->uuid,
            'data'    => $item->fresh(),
        ]);
    }

    /**
     * DELETE /api/sanpam/lahan/{uuid}
     */
    public function destroy(string $uuid)
    {
        $item = SAPDLahanMasyarakat::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        $item->delete();

        return response()->json(['success' => true, 'message' => 'Data berhasil dihapus']);
    }

    /**
     * GET /api/sanpam/lahan
     */
    public function index()
    {
        $items = SAPDLahanMasyarakat::latest()->get()->map(function ($it) {
            return [
                'uuid'                      => $it->uuid,
                'user_id'                   => $it->user_id,
                'namaPemilikLahan'          => $it->namaPemilikLahan,
                'ukuranLahan'               => $it->ukuranLahan,
                'statusLegalitasTanah'      => $it->statusLegalitasTanah,

                'alamatDusun'               => $it->alamatDusun,
                'alamatRT'                  => $it->alamatRT,
                'alamatRW'                  => $it->alamatRW,

                'kecamatan'                 => $it->kecamatan,
                'kelurahan'                 => $it->kelurahan,
                'titikLokasi'               => $it->titikLokasi,

                // Arrays UUID (file uploads)
                'buktiKepemilikan'          => $it->buktiKepemilikan,
                'buktiLegalitasTanah'       => $it->buktiLegalitasTanah,
                'dokumenProposal'           => $it->dokumenProposal,
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
     * Detail + join perencanaan by uuidUsulan
     */
    public function show(string $uuid)
    {
        $it = SAPDLahanMasyarakat::where('uuid', $uuid)->first();
        if (!$it) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)->orderBy('created_at', 'desc')->get();

        $perencanaanList = $perencanaanRows->map(function ($row) {
            return [
                'uuidPerencanaan' => $row->id,
                'uuidUsulan'      => $row->uuidUsulan,
                'nilaiHPS'        => $row->nilaiHPS,
                'catatanSurvey'   => $row->catatanSurvey,
                'created_at'      => $row->created_at,
                'updated_at'      => $row->updated_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'usulan' => [
                    'uuid'                      => $it->uuid,
                    'user_id'                   => $it->user_id,
                    'namaPemilikLahan'          => $it->namaPemilikLahan,
                    'ukuranLahan'               => $it->ukuranLahan,
                    'statusLegalitasTanah'      => $it->statusLegalitasTanah,

                    'alamatDusun'               => $it->alamatDusun,
                    'alamatRT'                  => $it->alamatRT,
                    'alamatRW'                  => $it->alamatRW,

                    'kecamatan'                 => $it->kecamatan,
                    'kelurahan'                 => $it->kelurahan,
                    'titikLokasi'               => $it->titikLokasi,

                    // Arrays UUID (file uploads)
                    'buktiKepemilikan'          => $it->buktiKepemilikan,
                    'buktiLegalitasTanah'       => $it->buktiLegalitasTanah,
                    'dokumenProposal'           => $it->dokumenProposal,
                    'fotoLahan'                 => $it->fotoLahan,

                    'status_verifikasi_usulan'  => $it->status_verifikasi_usulan,
                    'pesan_verifikasi'          => $it->pesan_verifikasi,

                    'created_at'                => $it->created_at,
                    'updated_at'                => $it->updated_at,
                ],
                'perencanaan' => $perencanaanList,
            ],
        ]);
    }

    // ================= Helpers =================

    private function applyAliases(Request $request): void
    {
        $aliases = [
            // teks
            'nama_pemilik_lahan'      => 'namaPemilikLahan',
            'ukuran_lahan'            => 'ukuranLahan',
            'status_kepemilikan'      => 'statusLegalitasTanah', // backward compat -> new name
            'status_legalitas_tanah'  => 'statusLegalitasTanah',

            'alamat_dusun'            => 'alamatDusun',
            'alamat_rt'               => 'alamatRT',
            'alamat_rw'               => 'alamatRW',

            'kecamatan'               => 'kecamatan',
            'kelurahan'               => 'kelurahan',

            'titik_lokasi'            => 'titikLokasi',
            'pesan_verifikasi'        => 'pesan_verifikasi',

            // file arrays
            'bukti_kepemilikan'       => 'buktiKepemilikan',
            'dokumen_proposal'        => 'dokumenProposal',
            'dokumen_djpm'            => 'buktiLegalitasTanah',  // backward compat
            'bukti_legalitas_tanah'   => 'buktiLegalitasTanah',
            'foto_lahan'              => 'fotoLahan',
        ];

        $merge = [];
        foreach ($aliases as $from => $to) {
            if ($request->has($from) && !$request->has($to)) {
                $merge[$to] = $request->input($from);
            }
        }
        if ($merge) $request->merge($merge);
    }

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
            return;
        }
    }

    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

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
            if (!$temp) continue;

            $oldPath  = $temp->file_path;
            $filename = basename($oldPath);
            $newPath  = 'sapd_final/' . $filename;

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
