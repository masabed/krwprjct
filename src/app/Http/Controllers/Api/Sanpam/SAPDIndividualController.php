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

        // Tambah field teknis
        $payload['uuid']                     = $uuid;
        $payload['user_id']                  = (string) $user->id;
        $payload['status_verifikasi_usulan'] = 0;

        // Simpan data
        $data = UsulanSAPDSIndividual::create($payload);

        // Pindahkan file-file dari TEMP → FINAL
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
     */
    public function update(Request $request, string $uuid)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = UsulanSAPDSIndividual::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Aliases & normalisasi file arrays
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            // META USULAN
            'sumberUsulan'              => 'sometimes|string|max:255',
            'namaAspirator'            => 'sometimes|string|max:255',
            'noKontakAspirator'        => 'sometimes|string|max:50',

            // DATA PENERIMA
            'namaCalonPenerima'        => 'sometimes|string',
            'nikCalonPenerima'         => 'sometimes|string',
            'noKKCalonPenerima'        => 'sometimes|string',
            'alamatPenerima'           => 'sometimes|string',
            'rwPenerima'               => 'sometimes|string',
            'rtPenerima'               => 'sometimes|string',
            'kecamatan'                => 'sometimes|string',
            'kelurahan'                => 'sometimes|string',
            'ukuranLahan'              => 'sometimes|nullable|string',
            'ketersedianSumber'        => 'sometimes|string',
            'titikLokasi'              => 'sometimes|nullable|string',
            'pesan_verifikasi'         => 'sometimes|nullable|string|max:512',

            // File arrays
            'fotoRumah'                => 'sometimes|nullable|array|min:1|max:10',
            'fotoRumah.*'              => 'uuid',
            'fotoLahan'                => 'sometimes|nullable|array|min:1|max:10',
            'fotoLahan.*'              => 'uuid',

            // Verifikasi
            'status_verifikasi_usulan' => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
        ]);

        // AUTO-CLEAR pesan saat status naik ke ≥ 4
        if (array_key_exists('status_verifikasi_usulan', $validated)
            && (int)$validated['status_verifikasi_usulan'] >= 4
            && (int)$item->status_verifikasi_usulan < 4) {
            $validated['pesan_verifikasi'] = null;
        }

        // Siapkan UUID baru yang perlu dipindah
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
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $user->id);
        }

        // Payload update (jika null dikirim untuk file-array, abaikan)
        $updateData = $validated;
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
                unset($updateData[$f]);
            }
        }

        // Catat field berubah
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
     */
    public function destroy(string $uuid)
    {
        /** @var \App\Models\UsulanSAPDSIndividual|null $item */
        $item = \App\Models\UsulanSAPDSIndividual::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        \DB::transaction(function () use ($uuid, $item) {
            // 1) Hapus semua Perencanaan yang menempel ke usulan ini
            \App\Models\Perencanaan::where('uuidUsulan', $uuid)->delete();

            // 2) Cabut UUID usulan ini dari setiap row Pembangunan yang menempel
            $buildRows = \App\Models\Pembangunan::query()
                ->where(function($q) use ($uuid) {
                    $q->where('uuidUsulan', $uuid)                // legacy string
                      ->orWhereJsonContains('uuidUsulan', $uuid); // JSON array
                })
                ->get();

            $needleLower = strtolower($uuid);

            foreach ($buildRows as $b) {
                $raw = $b->getAttribute('uuidUsulan');

                // Normalisasi ke array
                if (is_array($raw)) {
                    $arr = $raw;
                } elseif (is_string($raw)) {
                    $t = trim($raw);
                    if ($t !== '' && str_starts_with($t, '[')) {
                        $dec = json_decode($t, true);
                        $arr = is_array($dec) ? $dec : [];
                    } elseif ($t !== '') {
                        $arr = [$t]; // legacy single
                    } else {
                        $arr = [];
                    }
                } else {
                    $arr = [];
                }

                // Cabut uuid target (case-insensitive)
                $after = collect($arr)
                    ->map(fn($v) => trim((string)$v))
                    ->filter(fn($v) => $v !== '' && strtolower($v) !== $needleLower)
                    ->values()
                    ->all();

                // Simpan balik (kosong → null)
                $b->uuidUsulan = $after ? $after : null;
                $b->save();
            }

            // 3) Hapus usulan utama
            $item->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus; perencanaan dibersihkan dan UUID dicabut dari pembangunan.',
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

        $role    = strtolower((string) ($user->role ?? ''));
        $isAdmin = in_array($role, ['admin', 'operator'], true);

        $q = UsulanSAPDSIndividual::query()->latest();

        if (!$isAdmin) {
            $q->where('user_id', (string) $user->id);
        }

        $list = $q->get()->map(function ($item) {
            return [
                'uuid'                      => $item->uuid,
                'user_id'                   => $item->user_id,

                // META USULAN
                'sumberUsulan'              => $item->sumberUsulan,
                'namaAspirator'            => $item->namaAspirator,
                'noKontakAspirator'        => $item->noKontakAspirator,

                // DATA PENERIMA
                'namaCalonPenerima'        => $item->namaCalonPenerima,
                'nikCalonPenerima'         => $item->nikCalonPenerima,
                'noKKCalonPenerima'        => $item->noKKCalonPenerima,
                'alamatPenerima'           => $item->alamatPenerima,
                'rwPenerima'               => $item->rwPenerima,
                'rtPenerima'               => $item->rtPenerima,
                'kecamatan'                => $item->kecamatan,
                'kelurahan'                => $item->kelurahan,
                'ukuranLahan'              => $item->ukuranLahan,
                'ketersedianSumber'        => $item->ketersedianSumber,
                'titikLokasi'              => $item->titikLokasi,
                'pesan_verifikasi'         => $item->pesan_verifikasi,

                // arrays of UUIDs
                'fotoRumah'                => $item->fotoRumah,
                'fotoLahan'                => $item->fotoLahan,

                'status_verifikasi_usulan' => $item->status_verifikasi_usulan,
                'created_at'               => $item->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * GET /api/sanpam/individual/{uuid}
     */
    public function show(string $uuid)
    {
        // 0) Auth wajib
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // 1) Ambil usulan utama
        $item = \App\Models\UsulanSAPDSIndividual::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        // 1b) Access control: admin/admin_bidang/pengawas/owner
        $role    = strtolower((string) ($user->role ?? ''));
        $isPriv  = in_array($role, ['admin','admin_bidang','pengawas'], true);
        $isOwner = (string) ($item->user_id ?? '') === (string) $user->id;

        if (!$isPriv && !$isOwner) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // 2) Perencanaan terkait usulan ini
        $perencanaanRows = \App\Models\Perencanaan::where('uuidUsulan', $uuid)
            ->orderBy('created_at', 'desc')
            ->get();

        $perencanaanList = $perencanaanRows->map(function ($row) {
            return [
                'uuidPerencanaan' => (string) $row->id,
                'uuidUsulan'      => (string) $row->uuidUsulan,
                  'dokumentasi'     => $p->dokumentasi ?? [],
                'nilaiHPS'        => $row->nilaiHPS,
                'lembarKontrol'   => $row->lembarKontrol,
                'catatanSurvey'   => $row->catatanSurvey,
                'created_at'      => $row->created_at,
                'updated_at'      => $row->updated_at,
            ];
        })->values();

        // 3) Pembangunan terkait (support string/JSON array)
        $pembangunanRows = \App\Models\Pembangunan::query()
            ->where(function($q) use ($uuid) {
                $q->where('uuidUsulan', $uuid)
                  ->orWhereJsonContains('uuidUsulan', $uuid);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // 3a) PENGAWASAN terkait
        $canSeeAllPengawasan = in_array($role, ['admin','admin_bidang','pengawas'], true) || $isOwner;

        $pengawasanRows = \App\Models\Pengawasan::query()
            ->where('uuidUsulan', $uuid)
            ->when(!$canSeeAllPengawasan, fn($q) => $q->whereRaw('1=0'))
            ->orderByDesc('tanggal_pengawasan')
            ->orderByDesc('created_at')
            ->get();

        // 3b) Lookup nama pengawas (gabung: pengawasLapangan + pengawas)
        $pengawasIds = collect()
            ->merge($pembangunanRows->pluck('pengawasLapangan'))
            ->merge($pengawasanRows->pluck('pengawas'))
            ->filter(fn ($v) => !empty($v))
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values();

        $usersById   = collect();
        $usersByUuid = collect();

        if ($pengawasIds->isNotEmpty() && class_exists(\App\Models\User::class)) {
            try {
                $usersById = \App\Models\User::query()
                    ->select('id','name','username')
                    ->whereIn('id', $pengawasIds)
                    ->get()
                    ->keyBy(fn ($u) => (string) $u->id);
            } catch (\Throwable $e) { $usersById = collect(); }

            try {
                $userTable = (new \App\Models\User)->getTable();
                if (\Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid')) {
                    $usersByUuid = \App\Models\User::query()
                        ->select('uuid','name','username')
                        ->whereIn('uuid', $pengawasIds)
                        ->get()
                        ->keyBy(fn ($u) => (string) $u->uuid);
                }
            } catch (\Throwable $e) { $usersByUuid = collect(); }
        }

        // 4) Bentuk list pembangunan + inject nama pengawas & uuidUsulan_count
        $pembangunanList = $pembangunanRows->map(function ($b) use ($usersById, $usersByUuid) {
            // Normalisasi uuidUsulan → array
            $uuList = [];
            $uuRaw  = $b->uuidUsulan;

            if (is_array($uuRaw)) {
                $uuList = $uuRaw;
            } elseif (is_string($uuRaw)) {
                $t = trim($uuRaw);
                if ($t !== '' && str_starts_with($t, '[')) {
                    $arr = json_decode($t, true);
                    $uuList = is_array($arr) ? $arr : [];
                } elseif ($t !== '') {
                    $uuList = [$t]; // legacy single
                }
            }

            $count = collect($uuList)
                ->map(fn($v) => trim((string)$v))
                ->filter(fn($v) => $v !== '')
                ->unique()
                ->count();

            $pengawasKey  = (string) ($b->pengawasLapangan ?? '');
            $pengawasName = null;
            if ($pengawasKey !== '') {
                $u = $usersById->get($pengawasKey) ?? $usersByUuid->get($pengawasKey);
                $pengawasName = $u->name ?? $u->username ?? null;
            }

            return [
                'uuidPembangunan'       => (string) $b->id,
                'uuidUsulan'            => $uuList,
                'nomorSPK'              => $b->nomorSPK,
                'tanggalSPK'            => $b->tanggalSPK,
                'nilaiKontrak'          => $b->nilaiKontrak,
                'kontraktorPelaksana'   => $b->kontraktorPelaksana,
                'tanggalMulai'          => $b->tanggalMulai,
                'tanggalSelesai'        => $b->tanggalSelesai,
                'jangkaWaktu'           => $b->jangkaWaktu,
                'pengawasLapangan'      => $b->pengawasLapangan,
                'pengawasLapangan_name' => $pengawasName,
                'uuidUsulan_count'      => $count,
                'created_at'            => $b->created_at,
                'updated_at'            => $b->updated_at,
            ];
        })->values();

        // 4a) List pengawasan
        $pengawasanList = $pengawasanRows->map(function ($r) use ($usersById, $usersByUuid) {
            $key  = (string) ($r->pengawas ?? '');
            $name = null;
            if ($key !== '') {
                $u = $usersById->get($key) ?? $usersByUuid->get($key);
                $name = $u->name ?? $u->username ?? null;
            }

            return [
                'id'                 => (string) $r->id,
                'uuidUsulan'         => (string) $r->uuidUsulan,
                'uuidPembangunan'    => (string) $r->uuidPembangunan,
                'pengawas'           => (string) $r->pengawas,
                'pengawas_name'      => $name,
                'tanggal_pengawasan' => $r->tanggal_pengawasan,
                'foto'               => is_array($r->foto) ? $r->foto : [],
                'pesan_pengawasan'   => $r->pesan_pengawasan,
                'created_at'         => $r->created_at,
                'updated_at'         => $r->updated_at,
            ];
        })->values();

        // 5) Response gabungan
        return response()->json([
            'success' => true,
            'data' => [
                'usulan' => [
                    'uuid'                      => $item->uuid,
                    'user_id'                   => $item->user_id,

                    'sumberUsulan'              => $item->sumberUsulan,
                    'namaAspirator'            => $item->namaAspirator,
                    'noKontakAspirator'        => $item->noKontakAspirator,

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
                    'fotoRumah'                 => $item->fotoRumah,
                    'fotoLahan'                 => $item->fotoLahan,
                    'status_verifikasi_usulan'  => $item->status_verifikasi_usulan,
                    'created_at'                => $item->created_at,
                    'updated_at'                => $item->updated_at,
                ],
                'perencanaan' => $perencanaanList,
                'pembangunan' => $pembangunanList,
                'pengawasan'  => $pengawasanList,
            ],
        ]);
    }

    // ================= Helpers =================

    /** Terima juga variasi snake_case/camelCase di request */
    private function applyAliases(Request $request): void
    {
        $aliases = [
            // META USULAN
            'sumber_usulan'        => 'sumberUsulan',
            'nama_aspirator'       => 'namaAspirator',
            'no_kontak_aspirator'  => 'noKontakAspirator',

            // DATA PENERIMA
            'nama_calon_penerima'  => 'namaCalonPenerima',
            'nik_calon_penerima'   => 'nikCalonPenerima',
            'no_kk_calon_penerima' => 'noKKCalonPenerima',
            'alamat_penerima'      => 'alamatPenerima',
            'rw_penerima'          => 'rwPenerima',
            'rt_penerima'          => 'rtPenerima',
            'ketersedian_sumber'   => 'ketersedianSumber',
            'titik_lokasi'         => 'titikLokasi',

            // FILE ARRAYS
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
        if ($merge) {
            $request->merge($merge);
        }
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
                $u = $this->extractUuid((string)$v);
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
                        $u = $this->extractUuid((string)$v);
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
            if (!$temp) continue; // mungkin sudah final

            $oldPath  = $temp->file_path;
            $filename = basename($oldPath);
            $newPath  = 'sapd_final/' . $filename;

            // Hindari overwrite bila kebetulan sama
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
