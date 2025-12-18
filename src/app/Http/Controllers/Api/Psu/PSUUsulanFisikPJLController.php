<?php

namespace App\Http\Controllers\Api\Psu;

use App\Http\Controllers\Controller;
use App\Models\PSUUsulanFisikPJL;
use App\Models\PSUUpload;
use App\Models\PSUUploadTemp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Perencanaan;
use App\Models\Pembangunan;
use App\Models\Pengawasan;

class PSUUsulanFisikPJLController extends Controller
{
    /** Daftar kolom file (ARRAY UUID) yang dikelola */
    private const FILE_ARRAY_FIELDS = [
        'suratPermohonanUsulanFisik',
        'dokumentasiEksisting',
    ];

    /** GET /api/psu/pjl */
    public function index(Request $request)
{
    $user = $request->user();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    $role   = strtolower((string) ($user->role ?? ''));
    // Role yang punya akses penuh list:
    // admin, admin_bidang, operator, pengawas
    $isPriv = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);

    $q = PSUUsulanFisikPJL::query()->latest();

    if ($isPriv) {
        // Kalau mau lihat hanya data milik sendiri → ?mine=1
        if ($request->boolean('mine')) {
            $q->where('user_id', (string) $user->id);
        }
    } else {
        // User biasa → filter berdasar user_id + kecamatanUsulan / kelurahanUsulan
        $userKec = strtolower(trim((string) ($user->kecamatan ?? '')));
        $userKel = strtolower(trim((string) ($user->kelurahan ?? '')));

        if ($userKec === '') {
            // Tidak punya kecamatan di profil → hanya boleh lihat data miliknya
            $q->where('user_id', (string) $user->id);
        } else {
            // Boleh:
            // - data miliknya sendiri
            // - semua usulan di kecamatanUsulan yg sama
            //   - kalau userKel kosong → semua kelurahanUsulan di kecamatan tsb
            //   - kalau userKel ada → hanya kelurahanUsulan tersebut
            $q->where(function ($qq) use ($user, $userKec, $userKel) {
                $qq->where('user_id', (string) $user->id)
                   ->orWhere(function ($sub) use ($userKec, $userKel) {
                       $sub->whereRaw('LOWER(kecamatanUsulan) = ?', [$userKec]);

                       if ($userKel !== '') {
                           $sub->whereRaw('LOWER(COALESCE(kelurahanUsulan, "")) = ?', [$userKel]);
                       }
                   });
            });
        }
    }

    // Filter opsional
    if ($request->has('perumahanId')) {
        $q->where('perumahanId', $request->query('perumahanId'));
    }
    if ($request->has('status_verifikasi_usulan')) {
        $q->where(
            'status_verifikasi_usulan',
            (int) $request->query('status_verifikasi_usulan')
        );
    }

    return response()->json([
        'success' => true,
        'data'    => $q->get(),
    ]);
}

    /** GET /api/psu/pjl/{uuid} */
   /** GET /api/psu/pjl/{uuid} */
public function show(string $uuid)
{
    $auth = auth()->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    // 1) Ambil usulan PJL
    $row = PSUUsulanFisikPJL::where('uuid', $uuid)->first();
    if (!$row) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // 1b) Access control
    $role    = strtolower((string) ($auth->role ?? ''));
    $isOwner = (string) ($row->user_id ?? '') === (string) $auth->id;
    // Role full akses usulan: admin, admin_bidang, operator, pengawas
    $isPriv  = in_array($role, ['admin','admin_bidang','operator','pengawas'], true);

    if (!$isPriv && !$isOwner) {
        // User biasa → cek kecamatan & kelurahan profil vs usulan
        $userKec = strtolower(trim((string) ($auth->kecamatan ?? '')));
        $userKel = strtolower(trim((string) ($auth->kelurahan ?? '')));

        // Kalau user tidak punya kecamatan → tidak boleh lihat
        if ($userKec === '') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $itemKec = strtolower(trim((string) ($row->kecamatanUsulan ?? '')));
        if ($itemKec === '' || $itemKec !== $userKec) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Kalau user punya kelurahan → wajib match dengan kelurahanUsulan
        if ($userKel !== '') {
            $itemKel = strtolower(trim((string) ($row->kelurahanUsulan ?? '')));
            if ($itemKel === '' || $itemKel !== $userKel) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
        }
    }

    // 2) Perencanaan terkait
    $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)
        ->orderBy('created_at', 'desc')
        ->get();

    $perencanaanList = $perencanaanRows->map(function ($r) {
        return [
            'uuidPerencanaan' => (string) $r->id,
            'uuidUsulan'      => (string) $r->uuidUsulan,
            'nilaiHPS'        => $r->nilaiHPS,
            'lembarKontrol'   => $r->lembarKontrol,
            'dokumentasi'     => $r->dokumentasi ?? [],  // <- perbaikan di sini
            'catatanSurvey'   => $r->catatanSurvey,
            'created_at'      => $r->created_at,
            'updated_at'      => $r->updated_at,
        ];
    })->values();

    // 3) Pembangunan terkait (support string/JSON array)
    $pembangunanRows = Pembangunan::query()
        ->where(function($q) use ($uuid) {
            $q->where('uuidUsulan', $uuid)
              ->orWhereJsonContains('uuidUsulan', $uuid);
        })
        ->orderBy('created_at', 'desc')
        ->get();

    // 3a) Lookup nama pengawas lapangan
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

    // 3b) Bentuk list pembangunan + HITUNG per-row uuidUsulan_count
    $pembangunanList = $pembangunanRows->map(function ($b) use ($usersById, $usersByUuid) {
        // Normalisasi uuidUsulan → selalu array
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
                // legacy: single string
                $uuList = [$t];
            }
        }

        // Hitung jumlah UUID unik & non-empty
        $uuidUsulanCount = collect($uuList)
            ->map(fn($v) => trim((string)$v))
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->count();

        // Nama pengawas lapangan
        $key  = (string) ($b->pengawasLapangan ?? '');
        $name = null;
        if ($key !== '') {
            $u = $usersById->get($key) ?? $usersByUuid->get($key);
            $name = $u->name ?? $u->username ?? null;
        }

        return [
            'uuidPembangunan'       => (string) $b->id,
            'uuidUsulan'            => $uuList,
            'nomorSPK'              => $b->nomorSPK,
            'tanggalSPK'            => $b->tanggalSPK,
            'nilaiKontrak'          => $b->nilaiKontrak,
            'unit'                  => $b->unit,
            'kontraktorPelaksana'   => $b->kontraktorPelaksana,
            'tanggalMulai'          => $b->tanggalMulai,
            'tanggalSelesai'        => $b->tanggalSelesai,
            'jangkaWaktu'           => $b->jangkaWaktu,
            'pengawasLapangan'      => $b->pengawasLapangan,
            'pengawasLapangan_name' => $name,
            'uuidUsulan_count'      => $uuidUsulanCount,
            'created_at'            => $b->created_at,
            'updated_at'            => $b->updated_at,
        ];
    })->values();

    // 4) PENGAWASAN terkait (uuidUsulan sama) + role filter
    $canSeeAllPengawasan = in_array($role, ['admin','admin_bidang','operator','pengawas'], true) || $isOwner;

    $pengawasanRows = Pengawasan::query()
        ->where('uuidUsulan', $uuid)
        ->when(!$canSeeAllPengawasan, fn($q) => $q->where('pengawas', (string) $auth->id))
        ->orderByDesc('tanggal_pengawasan')
        ->orderByDesc('created_at')
        ->get();

    // extend lookup jika ada pengawas baru di catatan
    $pengawasCatatanKeys = $pengawasanRows->pluck('pengawas')
        ->filter(fn($v) => !empty($v))
        ->map(fn($v) => (string)$v)
        ->diff($pengawasKeys)
        ->values();

    if ($pengawasCatatanKeys->isNotEmpty() && class_exists(\App\Models\User::class)) {
        try {
            $addById = \App\Models\User::query()
                ->select('id','name','username')
                ->whereIn('id', $pengawasCatatanKeys)
                ->get()
                ->keyBy(fn($u) => (string)$u->id);
            $usersById = $usersById->merge($addById);
        } catch (\Throwable $e) {}

        try {
            $userTable = (new \App\Models\User)->getTable();
            if (\Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid')) {
                $addByUuid = \App\Models\User::query()
                    ->select('uuid','name','username')
                    ->whereIn('uuid', $pengawasCatatanKeys)
                    ->get()
                    ->keyBy(fn($u) => (string)$u->uuid);
                $usersByUuid = $usersByUuid->merge($addByUuid);
            }
        } catch (\Throwable $e) {}
    }

    $pengawasanList = $pengawasanRows->map(function ($r) use ($usersById, $usersByUuid) {
        $k = (string) ($r->pengawas ?? '');
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
            'usulan'       => $row,
            'perencanaan'  => $perencanaanList,
            'pembangunan'  => $pembangunanList,
            'pengawasan'   => $pengawasanList,
        ],
    ]);
}

    /**
     * POST /api/psu/pjl/create
     * Body memuat UUID file hasil upload ke /api/psu/upload (psu_temp)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Normalisasi list UUID (JSON/CSV/single → array UUID)
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $payload = $request->validate([
            // permohonan
            'tanggalPermohonan'      => 'required|date',
            'nomorSuratPermohonan'   => 'required|string|max:150',

            // pemohon
            'sumberUsulan'           => 'required|string|max:150',
            'namaAspirator'          => 'required|string|max:150',
            'noKontakAspirator'      => 'required|string|max:50',
            'namaPIC'                => 'required|string|max:150',
            'noKontakPIC'            => 'required|string|max:50',

            // rincian
            'jenisUsulan'            => 'required|string|max:150',
            'uraianMasalah'          => 'required|string',

            // eksisting
            'panjangJalanEksisting'      => 'sometimes|nullable|string|max:100',
            'jumlahTitikPJLEksisting'    => 'sometimes|nullable|string|max:100',

            // lokasi
            'alamatUsulan'           => 'required|string|max:255',
            'rtUsulan'               => 'sometimes|nullable|string|max:10',
            'rwUsulan'               => 'sometimes|nullable|string|max:10',
            'rayonUsulan'            => 'sometimes|nullable|string|max:100',
            'kecamatanUsulan'        => 'required|string|max:150',
            'kelurahanUsulan'        => 'required|string|max:150',
            'titikLokasiUsulan'      => 'sometimes|nullable|string|max:255',
            'jenisLokasi'            => 'sometimes|nullable|string|max:100',

            // bsl
            'perumahanId'            => 'sometimes|nullable|uuid',
            'statusJalan'            => 'sometimes|nullable|string|max:150',

            // dokumen
            'suratPermohonanUsulanFisik'   => 'required|array|min:1|max:10',
            'suratPermohonanUsulanFisik.*' => 'uuid',
            'dokumentasiEksisting'         => 'required|array|min:1|max:20',
            'dokumentasiEksisting.*'       => 'uuid',
        ]);

        // Admin boleh set status/pesan verifikasi saat create
        if (($user->role ?? null) === 'admin') {
            $adminValidated = $request->validate([
                'status_verifikasi_usulan' => 'sometimes|integer|in:0,1,2,3,4',
                'pesan_verifikasi'         => 'sometimes|nullable|string|max:512',
            ]);
            $payload = array_merge($payload, $adminValidated);
        }

        // Pindahkan file dari psu_temp → psu_final
        $allUuids = array_unique(array_merge(
            $payload['suratPermohonanUsulanFisik'] ?? [],
            $payload['dokumentasiEksisting'] ?? [],
        ));
        $this->moveTempsToFinal($allUuids, (string) $user->id);

        // Simpan row
        $row = PSUUsulanFisikPJL::create(array_merge($payload, [
            'user_id' => (string) $user->id,
            'status_verifikasi_usulan' => (int) ($payload['status_verifikasi_usulan'] ?? 0),
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Usulan PJL berhasil dibuat',
            'uuid'    => $row->uuid,
            'data'    => $row,
        ], 201);
    }

    /**
     * POST /api/psu/pjl/update/{uuid}
     * Partial update. Untuk kolom file: kirim array UUID baru yang mau dipakai.
     */
    public function update(Request $request, string $uuid)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $row = PSUUsulanFisikPJL::where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Hanya pemilik atau admin/admin_bidang
        $role    = strtolower((string) ($user->role ?? ''));
        $isAdmin = in_array($role, ['admin','admin_bidang'], true);
        $isOwner = (string) $row->user_id === (string) $user->id;
        if (!$isAdmin && !$isOwner) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Normalisasi hanya field yang dikirim
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f)) {
                $this->normalizeUuidArrayField($request, $f);
            }
        }

        $validated = $request->validate([
            // permohonan
            'tanggalPermohonan'      => 'sometimes|date',
            'nomorSuratPermohonan'   => 'sometimes|string|max:150',

            // pemohon
            'sumberUsulan'           => 'sometimes|string|max:150',
            'namaAspirator'          => 'sometimes|string|max:150',
            'noKontakAspirator'      => 'sometimes|string|max:50',
            'namaPIC'                => 'sometimes|string|max:150',
            'noKontakPIC'            => 'sometimes|string|max:50',

            // rincian
            'jenisUsulan'            => 'sometimes|string|max:150',
            'uraianMasalah'          => 'sometimes|string',

            // eksisting
            'panjangJalanEksisting'      => 'sometimes|nullable|string|max:100',
            'jumlahTitikPJLEksisting'    => 'sometimes|nullable|string|max:100',

            // lokasi
            'alamatUsulan'           => 'sometimes|string|max:255',
            'rtUsulan'               => 'sometimes|nullable|string|max:10',
            'rwUsulan'               => 'sometimes|nullable|string|max:10',
            'rayonUsulan'            => 'sometimes|nullable|string|max:100',
            'kecamatanUsulan'        => 'sometimes|string|max:150',
            'kelurahanUsulan'        => 'sometimes|string|max:150',
            'titikLokasiUsulan'      => 'sometimes|nullable|string|max:255',
            'jenisLokasi'            => 'sometimes|nullable|string|max:100',

            // bsl
            'perumahanId'            => 'sometimes|nullable|uuid',
            'statusJalan'            => 'sometimes|nullable|string|max:150',

            // dokumen (nullable → kalau null, kita abaikan)
            'suratPermohonanUsulanFisik'   => 'sometimes|nullable|array|min:1|max:10',
            'suratPermohonanUsulanFisik.*' => 'uuid',
            'dokumentasiEksisting'         => 'sometimes|nullable|array|min:1|max:20',
            'dokumentasiEksisting.*'       => 'uuid',
        ]);

        // Admin boleh ubah verifikasi
        if ($isAdmin) {
            $adminValidated = $request->validate([
                'status_verifikasi_usulan' => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
                'pesan_verifikasi'         => 'sometimes|nullable|string|max:512',
            ]);
            $validated = array_merge($validated, $adminValidated);

            // AUTO-CLEAR PESAN SAAT STATUS NAIK KE ≥ 4 PERTAMA KALI
            if (array_key_exists('status_verifikasi_usulan', $adminValidated)
                && (int) $adminValidated['status_verifikasi_usulan'] >= 4
                && (int) ($row->status_verifikasi_usulan ?? -1) < 4) {
                $validated['pesan_verifikasi'] = null;
            }
        } else {
            // user biasa tidak boleh mengubah verifikasi
            unset($validated['status_verifikasi_usulan'], $validated['pesan_verifikasi']);
        }

        // Pindahkan UUID baru (yang belum final) dari temp → final
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f) && is_array($request->input($f))) {
                $incoming = $request->input($f);
                $existing = $row->getAttribute($f) ?? [];
                $diff = array_diff($incoming, is_array($existing) ? $existing : []);
                if (!empty($diff)) {
                    $uuidsToMove = array_merge($uuidsToMove, $diff);
                }
            }
        }
        if ($uuidsToMove) {
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $user->id);
        }

        // Jangan timpa file field dengan null
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $validated) && is_null($validated[$f])) {
                unset($validated[$f]);
            }
        }

        $row->fill($validated);
        $dirty = $row->getDirty();

        if (!empty($dirty)) {
            $row->save();
        }

        return response()->json([
            'success' => true,
            'message' => empty($dirty)
                ? 'Tidak ada perubahan data'
                : 'Field Berikut Berhasil di Perbaharui: ' . implode(', ', array_keys($dirty)),
            'data'    => $row->fresh(),
        ]);
    }

    /** DELETE /api/psu/pjl/{uuid} */
   public function destroy(Request $request, string $uuid)
{
    $user = $request->user();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    /** @var \App\Models\PSUUsulanFisikPJL|null $row */
    $row = PSUUsulanFisikPJL::where('uuid', $uuid)->first();
    if (!$row) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // === ACCESS CONTROL: admin / operator / owner ===
    $role     = strtolower((string) ($user->role ?? ''));
    $isAdmin  = in_array($role, ['admin', 'admin_bidang'], true);
    $isOper   = ($role === 'operator');
    $isOwner  = (string) ($row->user_id ?? '') === (string) ($user->id ?? '');

    if (!($isAdmin || $isOper || $isOwner)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Anda Tidak Berwenang.',
        ], 403);
    }

    // helper normalisasi: null|string(JSON)|string single|array -> array<string>
    $toArray = function ($val): array {
        if (is_null($val)) return [];
        if (is_array($val)) {
            return collect($val)->map(fn($v)=>trim((string)$v))->filter()->values()->all();
        }
        $s = trim((string)$val);
        if ($s === '') return [];
        if (str_starts_with($s, '[')) {
            $arr = json_decode($s, true);
            return is_array($arr)
                ? collect($arr)->map(fn($v)=>trim((string)$v))->filter()->values()->all()
                : [$s];
        }
        return [$s];
    };

    // token relasi (uuid + PK fallback)
    $usulanUuid = (string) ($row->uuid ?? $uuid);
    $usulanId   = (string) $row->getKey();
    $tokens     = array_values(array_unique(array_filter([$usulanUuid, $usulanId])));
    $tokensLow  = array_map('strtolower', $tokens);

    $result = [
        'deleted_perencanaan'      => 0,
        'deleted_pengawasan'       => 0,
        'updated_pembangunan_rows' => 0,
        'deleted_pembangunan_rows' => 0,
        'deleted_pembangunan_ids'  => [],
    ];

    DB::transaction(function () use ($row, $tokens, $tokensLow, $toArray, &$result) {

        // 1) Hapus Perencanaan terkait
        $result['deleted_perencanaan'] = Perencanaan::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) $q->orWhere('uuidUsulan', $t);
            })
            ->delete();

        // 2) Hapus Pengawasan terkait
        try {
            $result['deleted_pengawasan'] = Pengawasan::query()
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) $q->orWhere('uuidUsulan', $t);
                })
                ->delete();
        } catch (\Throwable $e) {
            $result['deleted_pengawasan'] = 0;
        }

        // 3) Cabut token dari Pembangunan yang memuat usulan ini
        $rows = Pembangunan::query()
            ->whereNotNull('uuidUsulan')
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) {
                    $q->orWhereJsonContains('uuidUsulan', $t)
                      ->orWhere('uuidUsulan', $t)                    // legacy single
                      ->orWhere('uuidUsulan', 'like', '%"'.$t.'"%')  // json string
                      ->orWhere('uuidUsulan', 'like', '%'.$t.'%');   // fallback longgar
                }
            })
            ->lockForUpdate()
            ->get();

        foreach ($rows as $b) {
            $after = collect($toArray($b->uuidUsulan))
                ->map(fn($v) => trim((string)$v))
                ->filter(fn($v) => $v !== '')
                ->reject(fn($v) => in_array(strtolower((string)$v), $tokensLow, true))
                ->values()
                ->unique()
                ->all();

            // kosong -> hapus row pembangunan (kalau gagal, fallback set [])
            if (empty($after)) {
                try {
                    $id = (string) $b->id;
                    $b->delete();

                    $result['deleted_pembangunan_rows']++;
                    $result['deleted_pembangunan_ids'][] = $id;
                } catch (\Throwable $e) {
                    $b->uuidUsulan = [];
                    $b->save();
                    $result['updated_pembangunan_rows']++;
                }
                continue;
            }

            $b->uuidUsulan = $after;
            $b->save();
            $result['updated_pembangunan_rows']++;
        }

        // 4) Hapus usulan utamanya (file final tidak dihapus)
        $row->delete();
    });

    return response()->json([
        'success' => true,
        'message' => 'Data berhasil dihapus; perencanaan & pengawasan dibersihkan, UUID dicabut dari pembangunan, dan pembangunan kosong ikut terhapus.',
        'result'  => $result,
    ]);
}

    // ================= Helpers =================

    /** Normalisasi field array UUID dari berbagai bentuk input → array UUID murni */
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

    /** Ekstrak UUID toleran (v1–v7) */
    private function extractUuid(string $value): ?string
    {
        if (preg_match(
            '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/',
            $value,
            $m
        )) {
            return strtolower($m[0]);
        }
        return null;
    }

    /**
     * Pindahkan file dari psu_temp → psu_final untuk daftar UUID.
     * - Jika UUID sudah ada di PSUUpload milik user yang sama → reuse.
     * - Kalau ada di temp → move ke final + buat/update PSUUpload.
     */
    private function moveTempsToFinal(array $uuids, string $userId): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return;

        $disk = Storage::disk('local');

        $final = PSUUpload::whereIn('uuid', $uuids)->get()->keyBy('uuid');
        $temps = PSUUploadTemp::whereIn('uuid', $uuids)
            ->where('user_id', $userId)
            ->get()->keyBy('uuid');

        foreach ($uuids as $u) {
            // Sudah final?
            if ($final->has($u)) {
                $row = $final->get($u);
                if ((string)$row->user_id === (string)$userId && $disk->exists($row->file_path)) {
                    continue; // reuse
                }
            }

            // Dari temp → final
            if ($temps->has($u)) {
                $temp = $temps->get($u);
                $filename = basename($temp->file_path);
                $newPath  = 'psu_final/' . $filename;

                if ($disk->exists($newPath)) {
                    $ext     = pathinfo($filename, PATHINFO_EXTENSION);
                    $newName = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                    $newPath = 'psu_final/' . $newName;
                }

                DB::transaction(function () use ($disk, $temp, $newPath, $userId) {
                    if ($disk->exists($temp->file_path)) {
                        $disk->move($temp->file_path, $newPath);
                    } elseif (!$disk->exists($newPath)) {
                        // file hilang, skip
                        return;
                    }

                    PSUUpload::updateOrCreate(
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
            }
        }
    }
}
