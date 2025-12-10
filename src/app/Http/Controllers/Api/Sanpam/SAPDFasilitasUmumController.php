<?php

namespace App\Http\Controllers\Api\Sanpam;

use App\Http\Controllers\Controller;
use App\Models\UsulanSAPDSFasilitasUmum;
use App\Models\SAPDUpload;
use App\Models\SAPDUploadTemp;
use App\Models\Perencanaan;
use App\Models\Pembangunan;
use App\Models\Pengawasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SAPDFasilitasUmumController extends Controller
{
    // Sekarang hanya 2 file-array: buktiKepemilikan & fotoLahan
    private const FILE_ARRAY_FIELDS = [
        'buktiKepemilikan',
        'fotoLahan',
    ];

    // (Opsional) Upload ke TEMP — path sama (sapd_temp)
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
        ]);
    }

    // Submit usulan + pindahkan file dari TEMP ke FINAL (sapd_final)
    public function submit(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Aliases + normalisasi semua field file (boleh JSON/comma/single/array)
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $payload = $request->validate([
            // 🔹 Sumber & pengusul
            'sumberUsulan'       => 'required|string|max:255',
            'namaAspirator'      => 'required|string|max:255',
            'noKontakAspirator'  => 'required|string|max:50',

            // Data fasum
            'namaFasilitasUmum'   => 'required|string',
            'alamatFasilitasUmum' => 'required|string',
            'rwFasilitasUmum'     => 'required|string',
            'rtFasilitasUmum'     => 'required|string',
            'kecamatan'           => 'required|string',
            'kelurahan'           => 'required|string',
            'ukuranLahan'         => 'nullable|string',
            'statusKepemilikan'   => 'required|string',
            'titikLokasi'         => 'nullable|string',
            'pesan_verifikasi'    => 'nullable|string|max:512',

            // ARRAY UUID (min 1, max 10)
            'buktiKepemilikan'    => 'required|array|min:1|max:10',
            'buktiKepemilikan.*'  => 'uuid',
            'fotoLahan'           => 'required|array|min:1|max:10',
            'fotoLahan.*'         => 'uuid',
        ]);

        $payload['uuid']                     = (string) Str::uuid();
        $payload['user_id']                  = (string) $user->id;
        $payload['status_verifikasi_usulan'] = 0;

        // Pindahkan semua UUID dari TEMP → FINAL
        $allUuids = array_merge(
            $payload['buktiKepemilikan'],
            $payload['fotoLahan'],
        );
        $this->moveTempsToFinal($allUuids, (string) $user->id);

        $data = UsulanSAPDSFasilitasUmum::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Usulan SAPD Fasilitas Umum berhasil disimpan',
            'uuid'    => $data->uuid,
        ], 201);
    }

    /**
     * POST /api/sanpam/fasum/update/{uuid}
     * Partial update
     */
    public function update(Request $request, string $uuid)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = UsulanSAPDSFasilitasUmum::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Aliases + normalisasi semua file-array
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            // Sumber & pengusul
            'sumberUsulan'       => 'sometimes|string|max:255',
            'namaAspirator'      => 'sometimes|string|max:255',
            'noKontakAspirator'  => 'sometimes|string|max:50',

            // Data fasum
            'namaFasilitasUmum'        => 'sometimes|string',
            'alamatFasilitasUmum'      => 'sometimes|string',
            'rwFasilitasUmum'          => 'sometimes|string',
            'rtFasilitasUmum'          => 'sometimes|string',
            'kecamatan'                => 'sometimes|string',
            'kelurahan'                => 'sometimes|string',
            'ukuranLahan'              => 'sometimes|nullable|string',
            'statusKepemilikan'        => 'sometimes|string',
            'titikLokasi'              => 'sometimes|nullable|string',
            'pesan_verifikasi'         => 'sometimes|nullable|string|max:512',

            // File arrays
            'buktiKepemilikan'         => 'sometimes|nullable|array|min:1|max:10',
            'buktiKepemilikan.*'       => 'uuid',
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

        // Pindahkan UUID baru (yang belum ada di existing)
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

        // Siapkan payload update; jika kolom file dikirim null → jangan tulis
        $updateData = $validated;
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
                unset($updateData[$f]);
            }
        }

        // Catat field berubah (untuk pesan)
        $changed = [];
        foreach ($updateData as $key => $val) {
            if ($item->getAttribute($key) !== $val) $changed[] = $key;
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

    // DELETE /api/sanpam/fasum/{uuid}
    public function destroy(string $uuid)
    {
        /** @var \App\Models\UsulanSAPDSFasilitasUmum|null $item */
        $item = UsulanSAPDSFasilitasUmum::where('uuid', $uuid)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        // helper normalisasi: null|string(JSON)|string single|array -> array<string>
        $toArray = function ($val): array {
            if (is_null($val)) return [];
            if (is_array($val)) {
                return collect($val)->map(fn($v)=>(string)$v)->filter()->values()->all();
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

        // hapus token2 ini (uuid & id sebagai fallback)
        $usulanUuid = (string) $item->uuid;
        $usulanId   = (string) $item->getKey();
        $tokens     = array_values(array_unique(array_filter([$usulanUuid, $usulanId])));

        $result = [
            'deleted_perencanaan' => 0,
            'updated_pembangunan' => 0,
            'updated_rows'        => [],
        ];

        \DB::transaction(function () use ($item, $tokens, $toArray, &$result) {
            // 1) Hapus semua Perencanaan yang menunjuk ke usulan ini
            $result['deleted_perencanaan'] = \App\Models\Perencanaan::query()
                ->where(function($q) use ($tokens) {
                    foreach ($tokens as $t) {
                        $q->orWhere('uuidUsulan', $t);
                    }
                })
                ->delete();

            // 2) Temukan semua Pembangunan yang memuat uuidUsulan ini
            $pbQuery = \App\Models\Pembangunan::query()
                ->whereNotNull('uuidUsulan')
                ->where(function($q) use ($tokens) {
                    foreach ($tokens as $t) {
                        $q->orWhereJsonContains('uuidUsulan', $t)
                          ->orWhere('uuidUsulan', $t)
                          ->orWhere('uuidUsulan', 'like', '%"'.$t.'"%')
                          ->orWhere('uuidUsulan', 'like', '%'.$t.'%');
                    }
                })
                ->lockForUpdate();

            $rows = $pbQuery->get();

            foreach ($rows as $b) {
                $after = collect($toArray($b->uuidUsulan))
                    ->reject(fn($v) => in_array((string)$v, $tokens, true))
                    ->values()
                    ->all();

                $b->uuidUsulan = count($after) ? $after : null;
                $b->save();

                $result['updated_pembangunan']++;
                $result['updated_rows'][] = [
                    'uuidPembangunan'  => (string) $b->id,
                    'uuidUsulan_after' => $b->uuidUsulan,
                ];
            }

            // 3) Hapus usulan utamanya
            $item->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Usulan dihapus. Relasi perencanaan dibersihkan dan UUID di kolom uuidUsulan pada pembangunan sudah dihapus.',
            'result'  => $result,
        ]);
    }

    // List (tanpa pagination) — ?mine=1 untuk hanya data milik user login
    public function index(Request $request)
    {
        $q = UsulanSAPDSFasilitasUmum::query()->latest();

        if ($request->boolean('mine') && auth()->check()) {
            $q->where('user_id', (string) auth()->id());
        }

        $list = $q->get()->map(function ($item) {
            return [
                'uuid'                     => $item->uuid,
                'user_id'                  => $item->user_id,

                'sumberUsulan'             => $item->sumberUsulan,
                'namaAspirator'            => $item->namaAspirator,
                'noKontakAspirator'        => $item->noKontakAspirator,

                'namaFasilitasUmum'        => $item->namaFasilitasUmum,
                'alamatFasilitasUmum'      => $item->alamatFasilitasUmum,
                'rwFasilitasUmum'          => $item->rwFasilitasUmum,
                'rtFasilitasUmum'          => $item->rtFasilitasUmum,
                'kecamatan'                => $item->kecamatan,
                'kelurahan'                => $item->kelurahan,
                'ukuranLahan'              => $item->ukuranLahan,
                'statusKepemilikan'        => $item->statusKepemilikan,
                'titikLokasi'              => $item->titikLokasi,
                'pesan_verifikasi'         => $item->pesan_verifikasi,

                // Array UUID file
                'buktiKepemilikan'         => $item->buktiKepemilikan ?? [],
                'fotoLahan'                => $item->fotoLahan ?? [],

                // status verifikasi usulan
                'status_verifikasi_usulan' => $item->status_verifikasi_usulan,
                'created_at'               => $item->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $list]);
    }

    // Detail
    public function show(string $uuid)
    {
        // 0) Auth wajib
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // 1) Ambil usulan utama
        $item = \App\Models\UsulanSAPDSFasilitasUmum::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // 1b) Access control: admin/admin_bidang/pengawas/owner
        $role    = strtolower((string) ($user->role ?? ''));
        $isPriv  = in_array($role, ['admin','admin_bidang','pengawas'], true);
        $isOwner = (string) ($item->user_id ?? '') === (string) $user->id;

        if (!$isPriv && !$isOwner) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // 2) Perencanaan terkait
        $perencanaanRows = \App\Models\Perencanaan::where('uuidUsulan', $uuid)
            ->orderBy('created_at', 'desc')
            ->get();

        $perencanaanList = $perencanaanRows->map(function ($row) {
            return [
                'uuidPerencanaan' => (string) $row->id,
                'uuidUsulan'      => (string) $row->uuidUsulan,
                'nilaiHPS'        => $row->nilaiHPS,
                'catatanSurvey'   => $row->catatanSurvey,
                'lembarKontrol'   => $row->lembarKontrol,
                'created_at'      => $row->created_at,
                'updated_at'      => $row->updated_at,
            ];
        })->values();

        // 3) Pembangunan terkait (support string/JSON)
        $pembangunanRows = \App\Models\Pembangunan::query()
            ->where(function($q) use ($uuid) {
                $q->where('uuidUsulan', $uuid)
                  ->orWhereJsonContains('uuidUsulan', $uuid);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Lookup nama pengawas (dari pembangunan)
        $pengawasKeys = $pembangunanRows->pluck('pengawasLapangan')
            ->filter(fn ($v) => !empty($v))
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values();

        $usersById   = collect();
        $usersByUuid = collect();

        if ($pengawasKeys->isNotEmpty() && class_exists(\App\Models\User::class)) {
            try {
                $usersById = \App\Models\User::query()
                    ->select('id','name','username')
                    ->whereIn('id', $pengawasKeys)
                    ->get()
                    ->keyBy(fn ($u) => (string) $u->id);
            } catch (\Throwable $e) { $usersById = collect(); }

            try {
                $userTable = (new \App\Models\User)->getTable();
                if (\Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid')) {
                    $usersByUuid = \App\Models\User::query()
                        ->select('uuid','name','username')
                        ->whereIn('uuid', $pengawasKeys)
                        ->get()
                        ->keyBy(fn ($u) => (string) $u->uuid);
                }
            } catch (\Throwable $e) { $usersByUuid = collect(); }
        }

        // Bentuk list pembangunan + hitung uuidUsulan_count per row
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

            $uuCount = collect($uuList)
                ->map(fn($v) => trim((string)$v))
                ->filter(fn($v) => $v !== '')
                ->unique()
                ->count();

            // Resolve nama pengawas
            $pengawasKey  = (string) ($b->pengawasLapangan ?? '');
            $pengawasName = null;
            if ($pengawasKey !== '') {
                $u = $usersById->get($pengawasKey) ?? $usersByUuid->get($pengawasKey);
                $pengawasName = $u->name ?? $u->username ?? null;
            }

            return [
                'uuidPembangunan'       => (string) $b->id,
                'uuidUsulan'            => $uuList,    // selalu array
                'nomorSPK'              => $b->nomorSPK,
                'tanggalSPK'            => $b->tanggalSPK,
                'nilaiKontrak'          => $b->nilaiKontrak,
                'kontraktorPelaksana'   => $b->kontraktorPelaksana,
                'tanggalMulai'          => $b->tanggalMulai,
                'tanggalSelesai'        => $b->tanggalSelesai,
                'jangkaWaktu'           => $b->jangkaWaktu,
                'pengawasLapangan'      => $b->pengawasLapangan,
                'pengawasLapangan_name' => $pengawasName,
                'uuidUsulan_count'      => $uuCount,
                'created_at'            => $b->created_at,
                'updated_at'            => $b->updated_at,
            ];
        })->values();

        // 4) PENGAWASAN terkait
        $canSeeAllPengawasan = in_array($role, ['admin','admin_bidang','pengawas'], true) || $isOwner;

        $pengawasanQuery = \App\Models\Pengawasan::query()->where('uuidUsulan', $uuid);
        $pengawasanRows = $canSeeAllPengawasan
            ? $pengawasanQuery->orderByDesc('tanggal_pengawasan')->orderByDesc('created_at')->get()
            : collect();

        // Extend lookup user jika ada pengawas baru di catatan
        $pengawasCatatanKeys = $pengawasanRows->pluck('pengawas')
            ->filter(fn($v) => !empty($v))
            ->map(fn($v) => (string)$v)
            ->diff($pengawasKeys)
            ->values();

        if ($pengawasCatatanKeys->isNotEmpty() && class_exists(\App\Models\User::class)) {
            try {
                $usersById = $usersById->merge(
                    \App\Models\User::select('id','name','username')
                        ->whereIn('id', $pengawasCatatanKeys)->get()
                        ->keyBy(fn($u)=>(string)$u->id)
                );
            } catch (\Throwable $e) {}
            try {
                $userTable = (new \App\Models\User)->getTable();
                if (\Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid')) {
                    $usersByUuid = $usersByUuid->merge(
                        \App\Models\User::select('uuid','name','username')
                            ->whereIn('uuid', $pengawasCatatanKeys)->get()
                            ->keyBy(fn($u)=>(string)$u->uuid)
                    );
                }
            } catch (\Throwable $e) {}
        }

        $pengawasanList = $pengawasanRows->map(function ($r) use ($usersById, $usersByUuid) {
            $k  = (string) ($r->pengawas ?? '');
            $nm = null;
            if ($k !== '') {
                $u = $usersById->get($k) ?? $usersByUuid->get($k);
                $nm = $u->name ?? $u->username ?? null;
            }

            return [
                'id'                 => (string) $r->id,
                'uuidUsulan'         => (string) $r->uuidUsulan,
                'uuidPembangunan'    => (string) $r->uuidPembangunan,
                'pengawas'           => (string) $r->pengawas,
                'pengawas_name'      => $nm,
                'tanggal_pengawasan' => $r->tanggal_pengawasan,
                'foto'               => is_array($r->foto) ? $r->foto : [],
                'pesan_pengawasan'   => $r->pesan_pengawasan,
                'created_at'         => $r->created_at,
                'updated_at'         => $r->updated_at,
            ];
        })->values();

        // 5) Response
        return response()->json([
            'success' => true,
            'data' => [
                'usulan' => [
                    'uuid'                     => $item->uuid,
                    'user_id'                  => $item->user_id,

                    'sumberUsulan'             => $item->sumberUsulan,
                    'namaAspirator'            => $item->namaAspirator,
                    'noKontakAspirator'        => $item->noKontakAspirator,

                    'namaFasilitasUmum'        => $item->namaFasilitasUmum,
                    'alamatFasilitasUmum'      => $item->alamatFasilitasUmum,
                    'rwFasilitasUmum'          => $item->rwFasilitasUmum,
                    'rtFasilitasUmum'          => $item->rtFasilitasUmum,
                    'kecamatan'                => $item->kecamatan,
                    'kelurahan'                => $item->kelurahan,
                    'ukuranLahan'              => $item->ukuranLahan,
                    'statusKepemilikan'        => $item->statusKepemilikan,
                    'titikLokasi'              => $item->titikLokasi,
                    'pesan_verifikasi'         => $item->pesan_verifikasi,

                    'buktiKepemilikan'         => $item->buktiKepemilikan ?? [],
                    'fotoLahan'                => $item->fotoLahan ?? [],

                    'status_verifikasi_usulan' => $item->status_verifikasi_usulan,
                    'created_at'               => $item->created_at,
                    'updated_at'               => $item->updated_at,
                ],
                'perencanaan' => $perencanaanList,
                'pembangunan' => $pembangunanList,
                'pengawasan'  => $pengawasanList,
            ],
        ]);
    }

    // ================ Helpers ================

    /** snake_case → camelCase aliases */
    private function applyAliases(Request $request): void
    {
        $aliases = [
            // sumber & pengusul
            'sumber_usulan'       => 'sumberUsulan',
            'nama_aspirator'      => 'namaAspirator',
            'no_kontak_aspirator' => 'noKontakAspirator',

            // teks
            'nama_fasilitas_umum'   => 'namaFasilitasUmum',
            'alamat_fasilitas_umum' => 'alamatFasilitasUmum',
            'rw_fasilitas_umum'     => 'rwFasilitasUmum',
            'rt_fasilitas_umum'     => 'rtFasilitasUmum',
            'ukuran_lahan'          => 'ukuranLahan',
            'status_kepemilikan'    => 'statusKepemilikan',
            'titik_lokasi'          => 'titikLokasi',
            'pesanVerifikasi'       => 'pesan_verifikasi',

            // file arrays
            'bukti_kepemilikan'     => 'buktiKepemilikan',
            'foto_lahan'            => 'fotoLahan',
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
     * Normalisasi satu field array-UUID
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

            if ($t !== '' && $t[0] === '[') {
                $arr = json_decode($t, true);
                if (is_array($arr)) {
                    $uuids = [];
                    foreach ($arr as $v) {
                        $u = $this->extractUuid((string)$v);
                        if ($u) $uuids[] = $u;
                    }
                    $request->merge([$field => array_values(array_unique($uuids))]);
                    return;
                }
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

    /** Ekstrak UUID dari string */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /** Pindahkan file dari TEMP → FINAL untuk daftar UUID (user yang sama). */
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

            $oldPath  = $temp->file_path; // sapd_temp/<name>.<ext>
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
