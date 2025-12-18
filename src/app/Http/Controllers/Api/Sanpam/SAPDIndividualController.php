<?php

namespace App\Http\Controllers\Api\Sanpam;

use App\Http\Controllers\Controller;
use App\Models\UsulanSAPDSIndividual;
use App\Models\SAPDUpload;
use App\Models\SAPDUploadTemp;
use App\Models\Perencanaan;
use App\Models\Pembangunan;
use App\Models\Pengawasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SAPDIndividualController extends Controller
{
    private const FILE_ARRAY_FIELDS = ['fotoRumah', 'fotoLahan'];

    /**
     * POST /api/sanpam/individual/upload
     * Upload file ke TEMP: return UUID file temp
     */
    public function upload(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10512',
        ]);

        $path = $request->file('file')->store('sapd_temp');

        $temp = SAPDUploadTemp::create([
            'uuid'      => (string) Str::uuid(),
            'user_id'   => (string) auth()->id(),
            'file_path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'uuid'    => $temp->uuid,
                'user_id' => $temp->user_id,
            ],
        ], 201);
    }

    /**
     * POST /api/sanpam/individual/submit
     * Create + pindahkan file-file dari TEMP → FINAL
     */
    public function submit(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Aliases & normalisasi array-UUID
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $payload = $request->validate([
            // META USULAN
            'sumberUsulan'       => 'required|string|max:255',
            'namaAspirator'      => 'required|string|max:255',
            'noKontakAspirator'  => 'required|string|max:50',

            // DATA PENERIMA
            'namaCalonPenerima'  => 'required|string',
            'nikCalonPenerima'   => 'required|string',
            'noKKCalonPenerima'  => 'required|string',
            'alamatPenerima'     => 'required|string',
            'rwPenerima'         => 'required|string',
            'rtPenerima'         => 'required|string',
            'kecamatan'          => 'required|string',
            'kelurahan'          => 'required|string',
            'ukuranLahan'        => 'nullable|string',
            'ketersedianSumber'  => 'required|string',
            'titikLokasi'        => 'nullable|string',
            'pesan_verifikasi'   => 'nullable|string|max:512',

            // FILE ARRAYS
            'fotoRumah'          => 'required|array|min:1|max:10',
            'fotoRumah.*'        => 'uuid',
            'fotoLahan'          => 'required|array|min:1|max:10',
            'fotoLahan.*'        => 'uuid',
        ]);

        $uuid = (string) Str::uuid();

        $payload['uuid']                     = $uuid;
        $payload['user_id']                  = (string) $user->id;
        $payload['status_verifikasi_usulan'] = 0;

        // Simpan data dulu
        $data = UsulanSAPDSIndividual::create($payload);

        // Pindahkan file TEMP → FINAL
        $allUuids = array_values(array_unique(array_merge(
            $payload['fotoRumah'],
            $payload['fotoLahan'],
        )));
        $this->moveTempsToFinal($allUuids, (string) $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Usulan SAPD Individual berhasil disimpan',
            'uuid'    => $uuid,
            'data'    => $data,
        ], 201);
    }

    /**
     * POST /api/sanpam/individual/update/{uuid}
     * Partial update; kolom file berupa ARRAY UUID
     * Role: admin/operator/owner
     */
    public function update(Request $request, string $uuid)
    {
        $auth = auth()->user();
        if (!$auth) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = UsulanSAPDSIndividual::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // === ACCESS CONTROL: admin / operator / owner ===
        $role    = strtolower((string) ($auth->role ?? ''));
        $isAdmin = ($role === 'admin');
        $isOper  = ($role === 'operator');
        $isOwner = (string) ($item->user_id ?? '') === (string) ($auth->id ?? '');

        if (!($isAdmin || $isOper || $isOwner)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya admin, operator, atau pemilik data yang boleh mengedit.',
            ], 403);
        }

        // Aliases & normalisasi file arrays
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            // META USULAN
            'sumberUsulan'              => 'sometimes|string|max:255',
            'namaAspirator'             => 'sometimes|string|max:255',
            'noKontakAspirator'         => 'sometimes|string|max:50',

            // DATA PENERIMA
            'namaCalonPenerima'         => 'sometimes|string',
            'nikCalonPenerima'          => 'sometimes|string',
            'noKKCalonPenerima'         => 'sometimes|string',
            'alamatPenerima'            => 'sometimes|string',
            'rwPenerima'                => 'sometimes|string',
            'rtPenerima'                => 'sometimes|string',
            'kecamatan'                 => 'sometimes|string',
            'kelurahan'                 => 'sometimes|string',
            'ukuranLahan'               => 'sometimes|nullable|string',
            'ketersedianSumber'         => 'sometimes|string',
            'titikLokasi'               => 'sometimes|nullable|string',
            'pesan_verifikasi'          => 'sometimes|nullable|string|max:512',

            // File arrays
            'fotoRumah'                 => 'sometimes|nullable|array|min:1|max:10',
            'fotoRumah.*'               => 'uuid',
            'fotoLahan'                 => 'sometimes|nullable|array|min:1|max:10',
            'fotoLahan.*'               => 'uuid',

            // Verifikasi
            'status_verifikasi_usulan'  => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
        ]);

        // AUTO-CLEAR pesan saat status naik ke ≥ 4 (hanya kalau sebelumnya < 4)
        if (array_key_exists('status_verifikasi_usulan', $validated)
            && (int) $validated['status_verifikasi_usulan'] >= 4
            && (int) ($item->status_verifikasi_usulan ?? 0) < 4) {
            $validated['pesan_verifikasi'] = null;
        }

        // UUID baru yang harus dipindah ke final
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f) && is_array($request->input($f))) {
                $incoming = $request->input($f);
                $existing = $item->getAttribute($f) ?? [];
                $diff     = array_diff($incoming, is_array($existing) ? $existing : []);
                if ($diff) $uuidsToMove = array_merge($uuidsToMove, $diff);
            }
        }
        if ($uuidsToMove) {
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $item->user_id);
        }

        // Jangan overwrite kolom file kalau user kirim null
        $updateData = $validated;
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
                unset($updateData[$f]);
            }
        }

        $changed = [];
        foreach ($updateData as $k => $v) {
            if ($item->getAttribute($k) !== $v) {
                $changed[] = $k;
            }
        }

        if ($updateData) {
            $item->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => $changed
                ? ('Field Berikut Berhasil di Perbaharui: ' . implode(', ', $changed))
                : 'Tidak ada perubahan data',
            'uuid'    => $item->uuid,
            'data'    => $item->fresh(),
        ]);
    }

    /**
     * DELETE /api/sanpam/individual/{uuid}
     * Role: admin/operator/owner
     * - hapus perencanaan + pengawasan
     * - cabut uuid dari pembangunan; kalau pembangunan jadi kosong -> delete row pembangunan
     * - hapus metadata upload (final+temp) + hapus file fisik
     */
    /**
 * DELETE /api/sanpam/individual/{uuid}
 */
public function destroy(string $uuid)
{
    $auth = auth()->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    /** @var \App\Models\UsulanSAPDSIndividual|null $item */
    $item = UsulanSAPDSIndividual::where('uuid', $uuid)->first();
    if (!$item) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // === ACCESS CONTROL: admin / operator / owner ===
    $role    = strtolower((string) ($auth->role ?? ''));
    $isAdmin = ($role === 'admin');
    $isOper  = ($role === 'operator');
    $isOwner = (string) ($item->user_id ?? '') === (string) ($auth->id ?? '');

    if (!($isAdmin || $isOper || $isOwner)) {
        return response()->json([
    'success' => false,
    'message' => 'Unauthorized. Anda Tidak Berwenang.',
], 403);

    }

    // 0) Kumpulkan UUID file dari row usulan (fotoRumah, fotoLahan)
    $fileUuids = [];
    foreach (self::FILE_ARRAY_FIELDS as $f) {
        $arr = $item->getAttribute($f);
        if (is_array($arr)) {
            $fileUuids = array_merge($fileUuids, $arr);
        }
    }
    $fileUuids = array_values(array_unique(array_filter($fileUuids)));

    // Ambil path FINAL/TEMP untuk dihapus setelah transaksi sukses
    $finalPaths = [];
    $tempPaths  = [];
    if ($fileUuids) {
        $finalPaths = SAPDUpload::whereIn('uuid', $fileUuids)
            ->pluck('file_path')->filter()->values()->all();

        $tempPaths = SAPDUploadTemp::whereIn('uuid', $fileUuids)
            ->pluck('file_path')->filter()->values()->all();
    }

    // token relasi uuidUsulan (uuid & key sebagai fallback)
    $usulanUuid = (string) ($item->uuid ?? '');
    $usulanKey  = (string) ($item->getKey() ?? '');
    $tokens     = array_values(array_unique(array_filter([$usulanUuid, $usulanKey])));
    $tokensLow  = array_map('strtolower', $tokens);

    // helper normalisasi: null|string(JSON)|string single|array -> array<string>
    $toArray = function ($val): array {
        if (is_null($val)) return [];
        if (is_array($val)) {
            return collect($val)->map(fn($v) => (string)$v)->filter()->values()->all();
        }
        $s = trim((string)$val);
        if ($s === '') return [];
        if (str_starts_with($s, '[')) {
            $arr = json_decode($s, true);
            return is_array($arr)
                ? collect($arr)->map(fn($v)=>(string)$v)->filter()->values()->all()
                : [$s];
        }
        return [$s];
    };

    $result = [
        'deleted_perencanaan'      => 0,
        'deleted_pengawasan'       => 0,
        'updated_pembangunan_rows' => 0,
        'deleted_pembangunan_rows' => 0,
        'deleted_pembangunan_ids'  => [],
        'deleted_upload_rows'      => 0,
        'deleted_upload_temp_rows' => 0,
    ];

    \DB::transaction(function () use (
        $item, $tokens, $tokensLow, $toArray, $fileUuids, &$result
    ) {
        // 1) Hapus Perencanaan terkait
        $result['deleted_perencanaan'] = Perencanaan::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) $q->orWhere('uuidUsulan', $t);
            })
            ->delete();

        // 2) (Opsional) Hapus Pengawasan terkait (kalau model & kolom ada)
        try {
            $result['deleted_pengawasan'] = Pengawasan::query()
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) $q->orWhere('uuidUsulan', $t);
                })
                ->delete();
        } catch (\Throwable $e) {
            $result['deleted_pengawasan'] = 0;
        }

        // 3) Cabut token dari semua row Pembangunan yang memuat uuidUsulan tsb
        $rows = Pembangunan::query()
            ->whereNotNull('uuidUsulan')
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) {
                    $q->orWhereJsonContains('uuidUsulan', $t)
                      ->orWhere('uuidUsulan', $t)
                      ->orWhere('uuidUsulan', 'like', '%"'.$t.'"%')
                      ->orWhere('uuidUsulan', 'like', '%'.$t.'%');
                }
            })
            ->lockForUpdate()
            ->get();

        foreach ($rows as $b) {
            $after = collect($toArray($b->uuidUsulan))
                ->map(fn($v) => trim((string)$v))
                ->filter(fn($v) => $v !== '')
                ->reject(fn($v) => in_array(strtolower($v), $tokensLow, true))
                ->values()
                ->unique()
                ->all();

            // Kalau kosong → hapus row pembangunan (ga kepakai)
            if (empty($after)) {
                $result['deleted_pembangunan_rows']++;
                $result['deleted_pembangunan_ids'][] = (string) $b->id;
                $b->delete();
                continue;
            }

            // Kalau masih ada → simpan balik array-nya
            $b->uuidUsulan = $after;
            $b->save();
            $result['updated_pembangunan_rows']++;
        }

        // 4) Hapus metadata upload (FINAL & TEMP)
        if ($fileUuids) {
            $result['deleted_upload_rows'] = SAPDUpload::whereIn('uuid', $fileUuids)->delete();
            $result['deleted_upload_temp_rows'] = SAPDUploadTemp::whereIn('uuid', $fileUuids)->delete();
        }

        // 5) Hapus usulan utamanya
        $item->delete();
    });

    // 6) Hapus file fisik setelah transaksi sukses
    foreach (array_unique(array_merge($finalPaths, $tempPaths)) as $p) {
        try {
            if ($p && Storage::exists($p)) {
                Storage::delete($p);
            }
        } catch (\Throwable $e) {
            // optional: log
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Data berhasil dihapus; relasi dibersihkan, pembangunan kosong ikut terhapus, dan file ikut terhapus.',
        'result'  => $result,
    ]);
}

    /**
     * GET /api/sanpam/individual
     */
   public function index()
{
    $user = auth()->user();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    $role   = strtolower((string) ($user->role ?? ''));
    $isPriv = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);

    // ✅ eager load user untuk ambil name
    $q = UsulanSAPDSIndividual::query()
        ->with(['user:id,name'])
        ->latest();

    if ($isPriv) {
        if (request()->boolean('mine')) {
            $q->where('user_id', (string) $user->id);
        }
    } else {
        $userKec = strtolower(trim((string) ($user->kecamatan ?? '')));
        $userKel = strtolower(trim((string) ($user->kelurahan ?? '')));

        if ($userKec === '') {
            $q->where('user_id', (string) $user->id);
        } else {
            $q->where(function ($qq) use ($user, $userKec, $userKel) {
                $qq->where('user_id', (string) $user->id)
                   ->orWhere(function ($sub) use ($userKec, $userKel) {
                       $sub->whereRaw('LOWER(kecamatan) = ?', [$userKec]);
                       if ($userKel !== '') {
                           $sub->whereRaw("LOWER(COALESCE(kelurahan, '')) = ?", [$userKel]);
                       }
                   });
            });
        }
    }

    $list = $q->get()->map(function ($item) {
        return [
            'uuid'                      => $item->uuid,
            'user_id'                   => $item->user_id,
            'user_name'                 => $item->user?->name, // ✅ tambahan

            'sumberUsulan'              => $item->sumberUsulan,
            'namaAspirator'             => $item->namaAspirator,
            'noKontakAspirator'         => $item->noKontakAspirator,
            'namaCalonPenerima'         => $item->namaCalonPenerima,
            'nikCalonPenerima'          => $item->nikCalonPenerima,
            'noKKCalonPenerima'         => $item->noKKCalonPenerima,
            'alamatPenerima'            => $item->alamatPenerima,
            'rwPenerima'                => $item->rwPenerima,
            'rtPenerima'                => $item->rtPenerima,
            'kecamatan'                 => $item->kecamatan,
            'kelurahan'                 => $item->kelurahan,
            'ukuranLahan'               => $item->ukuranLahan,
            'ketersedianSumber'         => $item->ketersedianSumber,
            'titikLokasi'               => $item->titikLokasi,
            'pesan_verifikasi'          => $item->pesan_verifikasi,

            'fotoRumah'                 => $item->fotoRumah ?? [],
            'fotoLahan'                 => $item->fotoLahan ?? [],

            'status_verifikasi_usulan'  => $item->status_verifikasi_usulan,
            'created_at'                => $item->created_at,
            'updated_at'                => $item->updated_at,
        ];
    });

    return response()->json([
        'success' => true,
        'data'    => $list,
    ]);
}

    /**
     * GET /api/sanpam/individual/{uuid}
     */
    public function show(string $uuid)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = UsulanSAPDSIndividual::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Access control sederhana: priv atau owner; (punya filter kecamatan/kelurahan di versi kamu sebelumnya, kalau mau aku bisa gabungkan lagi)
        $role    = strtolower((string) ($user->role ?? ''));
        $isPriv  = in_array($role, ['admin','admin_bidang','operator','pengawas'], true);
        $isOwner = (string) ($item->user_id ?? '') === (string) $user->id;

        if (!$isPriv && !$isOwner) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Perencanaan
        $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)
            ->orderBy('created_at', 'desc')
            ->get();

        $perencanaanList = $perencanaanRows->map(function ($row) {
            return [
                'uuidPerencanaan' => (string) $row->id,
                'uuidUsulan'      => (string) $row->uuidUsulan,
                'dokumentasi'     => $row->dokumentasi ?? [],
                'nilaiHPS'        => $row->nilaiHPS,
                'lembarKontrol'   => $row->lembarKontrol,
                'catatanSurvey'   => $row->catatanSurvey,
                'created_at'      => $row->created_at,
                'updated_at'      => $row->updated_at,
            ];
        })->values();

        // Pembangunan (yang menempel ke uuid usulan ini)
        $pembangunanRows = Pembangunan::query()
            ->where(function($q) use ($uuid) {
                $q->where('uuidUsulan', $uuid)
                  ->orWhereJsonContains('uuidUsulan', $uuid);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Pengawasan (jika role boleh)
        $pengawasanRows = Pengawasan::query()
            ->where('uuidUsulan', $uuid)
            ->orderByDesc('tanggal_pengawasan')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'usulan' => $item->toArray(),
                'perencanaan' => $perencanaanList,
                'pembangunan' => $pembangunanRows,
                'pengawasan'  => $pengawasanRows,
            ],
        ]);
    }

    // ================= Helpers =================

    /** Terima juga variasi snake_case/camelCase di request */
    private function applyAliases(Request $request): void
    {
        $aliases = [
            'sumber_usulan'        => 'sumberUsulan',
            'nama_aspirator'       => 'namaAspirator',
            'no_kontak_aspirator'  => 'noKontakAspirator',

            'nama_calon_penerima'  => 'namaCalonPenerima',
            'nik_calon_penerima'   => 'nikCalonPenerima',
            'no_kk_calon_penerima' => 'noKKCalonPenerima',
            'alamat_penerima'      => 'alamatPenerima',
            'rw_penerima'          => 'rwPenerima',
            'rt_penerima'          => 'rtPenerima',
            'ketersedian_sumber'   => 'ketersedianSumber',
            'titik_lokasi'         => 'titikLokasi',

            'foto_rumah'           => 'fotoRumah',
            'foto_lahan'           => 'fotoLahan',

            'pesanVerifikasi'      => 'pesan_verifikasi',
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
     * Normalisasi satu field array-UUID:
     * - JSON array string: '["uuid1","uuid2"]'
     * - CSV string:        'uuid1,uuid2'
     * - Single UUID/path:  'sapd_temp/xxx_uuid1.jpg' → ['uuid1']
     * - Array campur path: ['sapd_temp/...uuid1.jpg','uuid2']
     * - "null"/''          → null
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

            if (str_starts_with($t, '[')) {
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

    /** Ekstrak UUID (v1–v7) dari string/path */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /** Pindahkan file dari TEMP → FINAL untuk daftar UUID (milik user yang sama) */
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

    /** Resolve array UUID → array of ['uuid' => ..., 'path' => '/storage/...'] */
    private function resolveFilePaths(?array $uuids): array
    {
        if (!is_array($uuids) || empty($uuids)) return [];

        $files = SAPDUpload::whereIn('uuid', $uuids)->get()->keyBy('uuid');

        $out = [];
        foreach ($uuids as $u) {
            $path = optional($files->get($u))->file_path;
            $out[] = [
                'uuid' => $u,
                'path' => $path ? '/storage/' . ltrim($path, '/') : null,
            ];
        }
        return $out;
    }
}
