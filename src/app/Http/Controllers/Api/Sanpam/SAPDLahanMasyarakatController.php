<?php

namespace App\Http\Controllers\Api\Sanpam;

use App\Http\Controllers\Controller;
use App\Models\SAPDLahanMasyarakat;
use App\Models\SAPDUpload;
use App\Models\SAPDUploadTemp;
use App\Models\Perencanaan;
use App\Models\Pembangunan;
use App\Models\Pengawasan;
use App\Models\User; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SAPDLahanMasyarakatController extends Controller
{
    // === File array fields sesuai skema terbaru (tanpa dokumenProposal) ===
    private const FILE_ARRAY_FIELDS = [
        'buktiLegalitasTanah',
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
            // 🔹 Sumber & pengusul (FormData)
            'sumberUsulan'       => 'required|string|max:255',
            'namaAspirator'      => 'required|string|max:255',
            'noKontakAspirator'  => 'required|string|max:50',

            // Data lahan
            'namaPemilikLahan'     => 'required|string|max:255',
            'ukuranLahan'          => 'required|string|max:50',
            'statusLegalitasTanah' => 'required|string|max:100',

            // Alamat
            'alamatDusun'          => 'required|string|max:255',
            'alamatRT'             => 'required|string|max:10',
            'alamatRW'             => 'required|string|max:10',

            'kecamatan'            => 'required|string|max:150',
            'kelurahan'            => 'required|string|max:150',
            'titikLokasi'          => 'required|string|max:255',
            'pesan_verifikasi'     => 'nullable|string|max:512',

            // FILE ARRAYS
            'buktiLegalitasTanah'    => 'required|array|min:1|max:10',
            'buktiLegalitasTanah.*'  => 'uuid',
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
            $payload['buktiLegalitasTanah'] ?? [],
            $payload['fotoLahan'] ?? []
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
            // 🔹 Sumber & pengusul (boleh partial)
            'sumberUsulan'       => 'sometimes|string|max:255',
            'namaAspirator'      => 'sometimes|string|max:255',
            'noKontakAspirator'  => 'sometimes|string|max:50',

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
            'buktiLegalitasTanah'    => 'sometimes|nullable|array|min:1|max:10',
            'buktiLegalitasTanah.*'  => 'uuid',
            'fotoLahan'              => 'sometimes|nullable|array|min:1|max:10',
            'fotoLahan.*'            => 'uuid',

            'status_verifikasi_usulan' => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
        ]);

        // ====== AUTO-CLEAR PESAN SAAT STATUS NAIK KE ≥ 4 ======
        if (array_key_exists('status_verifikasi_usulan', $validated)
            && (int)$validated['status_verifikasi_usulan'] >= 4
            && (int)$item->status_verifikasi_usulan < 4) {
            $validated['pesan_verifikasi'] = null;
        }
        // ======================================================

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
            'message' => $changed
                ? ('Field Berikut Berhasil di Perbaharui: ' . implode(', ', $changed))
                : 'Tidak ada perubahan data',
            'uuid'    => $item->uuid,
            'data'    => $item->fresh(),
        ]);
    }

    /**
     * DELETE /api/sanpam/lahan/{uuid}
     */
    public function destroy(string $uuid)
{
    // 0) Auth
    $auth = auth()->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    // 1) Ambil usulan
    /** @var \App\Models\SAPDLahanMasyarakat|null $item */
    $item = \App\Models\SAPDLahanMasyarakat::where('uuid', $uuid)->first();
    if (!$item) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // 2) Access control: admin/operator/owner
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

    // helper normalisasi: null|string(JSON)|string single|array -> array<string>
    $toArray = function ($val): array {
        if (is_null($val)) return [];
        if (is_array($val)) {
            return collect($val)->map(fn($v)=>(string)$v)->map(fn($v)=>trim($v))->filter()->values()->all();
        }
        $s = trim((string)$val);
        if ($s === '') return [];
        if (str_starts_with($s, '[')) {
            $arr = json_decode($s, true);
            return is_array($arr)
                ? collect($arr)->map(fn($v)=>(string)$v)->map(fn($v)=>trim($v))->filter()->values()->all()
                : [$s];
        }
        return [$s];
    };

    // 3) Kumpulkan UUID file dari row usulan
    $fileUuids = [];
    foreach (self::FILE_ARRAY_FIELDS as $f) {
        $arr = $item->getAttribute($f);
        if (is_array($arr)) {
            $fileUuids = array_merge($fileUuids, $arr);
        }
    }
    $fileUuids = array_values(array_unique(array_filter(array_map('strval', $fileUuids))));

    // Ambil path FINAL/TEMP untuk dihapus SETELAH transaksi DB sukses
    $finalPaths = [];
    $tempPaths  = [];
    if (!empty($fileUuids)) {
        $finalPaths = \App\Models\SAPDUpload::whereIn('uuid', $fileUuids)
            ->pluck('file_path')->filter()->values()->all();

        $tempPaths = \App\Models\SAPDUploadTemp::whereIn('uuid', $fileUuids)
            ->pluck('file_path')->filter()->values()->all();
    }

    // token yang dicabut dari relasi: uuid + PK fallback
    $usulanUuid = (string) $item->uuid;
    $usulanId   = (string) $item->getKey();
    $tokens     = array_values(array_unique(array_filter([$usulanUuid, $usulanId])));

    $result = [
        'deleted_perencanaan' => 0,
        'deleted_pengawasan'  => 0,
        'updated_pembangunan' => 0,
        'deleted_pembangunan' => 0,
        'deleted_files'       => 0,
        'deleted_upload_meta' => 0,
    ];

    \DB::transaction(function () use ($item, $tokens, $toArray, $fileUuids, &$result) {

        // 1) Hapus Perencanaan terkait
        $result['deleted_perencanaan'] = \App\Models\Perencanaan::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) $q->orWhere('uuidUsulan', $t);
            })
            ->delete();

        // 2) Hapus Pengawasan terkait (INI YANG KAMU MINTA)
        $result['deleted_pengawasan'] = \App\Models\Pengawasan::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) $q->orWhere('uuidUsulan', $t);
            })
            ->delete();

        // 3) Cabut UUID dari Pembangunan (kalau kosong -> delete row pembangunan)
        $rows = \App\Models\Pembangunan::query()
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
                ->reject(fn($v) => in_array((string)$v, $tokens, true))
                ->values()
                ->all();

            if (empty($after)) {
                $b->delete();
                $result['deleted_pembangunan']++;
                continue;
            }

            $b->uuidUsulan = $after; // simpan array
            $b->save();
            $result['updated_pembangunan']++;
        }

        // 4) Hapus metadata upload (final/temp)
        if (!empty($fileUuids)) {
            $a = \App\Models\SAPDUpload::whereIn('uuid', $fileUuids)->delete();
            $b = \App\Models\SAPDUploadTemp::whereIn('uuid', $fileUuids)->delete();
            $result['deleted_upload_meta'] = (int) $a + (int) $b;
        }

        // 5) Hapus usulan utamanya
        $item->delete();
    });

    // 6) Hapus file fisik (setelah transaksi DB sukses)
    $allPaths = array_values(array_unique(array_filter(array_merge($finalPaths, $tempPaths))));
    foreach ($allPaths as $p) {
        try {
            if ($p && \Illuminate\Support\Facades\Storage::exists($p)) {
                \Illuminate\Support\Facades\Storage::delete($p);
                $result['deleted_files']++;
            }
        } catch (\Throwable $e) {
            // optional: log kalau perlu
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Data berhasil dihapus; perencanaan & pengawasan dibersihkan, UUID dicabut dari pembangunan (yang kosong ikut terhapus), dan file ikut terhapus.',
        'result'  => $result,
    ]);
}


    /**
     * GET /api/sanpam/lahan
     */
   public function index(Request $request)
{
    $auth = auth()->user();
    if (!$auth) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated',
        ], 401);
    }

    $role   = strtolower((string) ($auth->role ?? ''));
    $isPriv = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);

    // ✅ eager load user utk ambil name (hindari N+1)
    $q = SAPDLahanMasyarakat::query()
        ->with(['user:id,name'])
        ->latest();

    if ($isPriv) {
        if ($request->boolean('mine')) {
            $q->where('user_id', (string) $auth->id);
        }
    } else {
        $userKec = strtolower(trim((string) ($auth->kecamatan ?? '')));
        $userKel = strtolower(trim((string) ($auth->kelurahan ?? '')));

        if ($userKec === '') {
            $q->where('user_id', (string) $auth->id);
        } else {
            $q->where(function ($qq) use ($auth, $userKec, $userKel) {
                $qq->where('user_id', (string) $auth->id)
                   ->orWhere(function ($sub) use ($userKec, $userKel) {
                       $sub->whereRaw('LOWER(kecamatan) = ?', [$userKec]);

                       if ($userKel !== '') {
                           // tetap aman: COALESCE kelurahan kosong jadi ''
                           $sub->whereRaw("LOWER(COALESCE(kelurahan, '')) = ?", [$userKel]);
                       }
                   });
            });
        }
    }

    $items = $q->get()->map(function ($it) {
        return [
            'uuid'                     => $it->uuid,
            'user_id'                  => $it->user_id,
            'user_name'                => $it->user?->name, // ✅ tambahan

            'sumberUsulan'             => $it->sumberUsulan,
            'namaAspirator'            => $it->namaAspirator,
            'noKontakAspirator'        => $it->noKontakAspirator,

            'namaPemilikLahan'         => $it->namaPemilikLahan,
            'ukuranLahan'              => $it->ukuranLahan,
            'statusLegalitasTanah'     => $it->statusLegalitasTanah,

            'alamatDusun'              => $it->alamatDusun,
            'alamatRT'                 => $it->alamatRT,
            'alamatRW'                 => $it->alamatRW,

            'kecamatan'                => $it->kecamatan,
            'kelurahan'                => $it->kelurahan,
            'titikLokasi'              => $it->titikLokasi,

            'buktiLegalitasTanah'      => $it->buktiLegalitasTanah ?? [],
            'fotoLahan'                => $it->fotoLahan ?? [],

            'status_verifikasi_usulan' => $it->status_verifikasi_usulan,
            'pesan_verifikasi'         => $it->pesan_verifikasi,

            'created_at'               => $it->created_at,
            'updated_at'               => $it->updated_at,
        ];
    });

    return response()->json([
        'success' => true,
        'data'    => $items,
    ]);
}


    /**
     * GET /api/sanpam/lahan/{uuid}
     * Detail + join perencanaan by uuidUsulan
     */
public function show(string $uuid)
{
    // 0) Auth
    $auth = auth()->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    // 1) Usulan utama
    $it = SAPDLahanMasyarakat::where('uuid', $uuid)->first();
    if (!$it) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // 1b) Access control
    $role    = strtolower((string) ($auth->role ?? ''));
    $isPriv  = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);
    $isOwner = (string) ($it->user_id ?? '') === (string) $auth->id;

    if (!$isPriv && !$isOwner) {
        // User biasa → cek kecamatan/kelurahan di profil
        $userKec = strtolower(trim((string) ($auth->kecamatan ?? '')));
        $userKel = strtolower(trim((string) ($auth->kelurahan ?? '')));

        // Tidak punya kecamatan → tidak boleh lihat
        if ($userKec === '') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $itemKec = strtolower(trim((string) ($it->kecamatan ?? '')));
        if ($itemKec === '' || $itemKec !== $userKec) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Kalau user punya kelurahan → wajib sama persis (case-insensitive)
        if ($userKel !== '') {
            $itemKel = strtolower(trim((string) ($it->kelurahan ?? '')));
            if ($itemKel === '' || $itemKel !== $userKel) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }
    }

    // 2) Perencanaan terkait
    $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)
        ->orderBy('created_at', 'desc')
        ->get();

    $perencanaanList = $perencanaanRows->map(function ($row) {
        return [
            'uuidPerencanaan' => (string) $row->id,
            'uuidUsulan'      => (string) $row->uuidUsulan,
            'nilaiHPS'        => $row->nilaiHPS,
            'dokumentasi'     => $row->dokumentasi ?? [],  // <-- perbaikan: pakai $row
            'lembarKontrol'   => $row->lembarKontrol,
            'catatanSurvey'   => $row->catatanSurvey,
            'created_at'      => $row->created_at,
            'updated_at'      => $row->updated_at,
        ];
    })->values();

    // 3) Pembangunan terkait (support JSON array & legacy string)
    $pembangunanRows = Pembangunan::query()
        ->where(function ($q) use ($uuid) {
            $q->whereJsonContains('uuidUsulan', $uuid)   // JSON array
              ->orWhere('uuidUsulan', $uuid);            // legacy string
        })
        ->orderBy('created_at', 'desc')
        ->get();

    // 3a) Pengawasan terkait
    $canSeeAllPengawasan = in_array($role, ['admin','admin_bidang','pengawas'], true) || $isOwner;

    $pengawasanRows = Pengawasan::query()
        ->where('uuidUsulan', $uuid)
        ->when(!$canSeeAllPengawasan, fn($q) => $q->where('pengawas', (string) $auth->id))
        ->orderByDesc('tanggal_pengawasan')
        ->orderByDesc('created_at')
        ->get();

    // 3b) Kumpulkan key user untuk resolve nama
    $pengawasKeys = collect()
        ->merge($pembangunanRows->pluck('pengawasLapangan'))
        ->merge($pengawasanRows->pluck('pengawas'))
        ->filter(fn($v) => !empty($v))
        ->map(fn($v) => (string) $v)
        ->unique()
        ->values();

    $usersById   = collect();
    $usersByUuid = collect();

    if ($pengawasKeys->isNotEmpty() && class_exists(User::class)) {
        try {
            $usersById = User::query()
                ->select('id','name','username')
                ->whereIn('id', $pengawasKeys)
                ->get()
                ->keyBy(fn ($u) => (string) $u->id);
        } catch (\Throwable $e) { $usersById = collect(); }

        try {
            $userTable = (new User)->getTable();
            if (\Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid')) {
                $usersByUuid = User::query()
                    ->select('uuid','name','username')
                    ->whereIn('uuid', $pengawasKeys)
                    ->get()
                    ->keyBy(fn ($u) => (string) $u->uuid);
            }
        } catch (\Throwable $e) { $usersByUuid = collect(); }
    }

    // 4) Bangun list pembangunan
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
                $uuList = [$t]; // legacy single string
            }
        }

        // Hitung jumlah UUID unik yang tidak kosong
        $uuCount = collect($uuList)
            ->map(fn($v) => trim((string)$v))
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->count();

        // Nama pengawas lapangan
        $pengawasKey  = (string) ($b->pengawasLapangan ?? '');
        $pengawasName = null;
        if ($pengawasKey !== '') {
            $u = $usersById->get($pengawasKey) ?? $usersByUuid->get($pengawasKey);
            $pengawasName = $u->name ?? $u->username ?? null;
        }

        return [
            'uuidPembangunan'       => (string) $b->id,
            'uuidUsulan'            => $uuList,                // selalu array
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

    // 4a) Bangun list pengawasan
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

    // 5) Response
    return response()->json([
        'success' => true,
        'data' => [
            'usulan'      => [
                'uuid'                     => $it->uuid,
                'user_id'                  => $it->user_id,

                'sumberUsulan'             => $it->sumberUsulan,
                'namaAspirator'            => $it->namaAspirator,
                'noKontakAspirator'        => $it->noKontakAspirator,

                'namaPemilikLahan'         => $it->namaPemilikLahan,
                'ukuranLahan'              => $it->ukuranLahan,
                'statusLegalitasTanah'     => $it->statusLegalitasTanah,
                'alamatDusun'              => $it->alamatDusun,
                'alamatRT'                 => $it->alamatRT,
                'alamatRW'                 => $it->alamatRW,
                'kecamatan'                => $it->kecamatan,
                'kelurahan'                => $it->kelurahan,
                'titikLokasi'              => $it->titikLokasi,
                'buktiLegalitasTanah'      => $it->buktiLegalitasTanah ?? [],
                'fotoLahan'                => $it->fotoLahan ?? [],
                'status_verifikasi_usulan' => $it->status_verifikasi_usulan,
                'pesan_verifikasi'         => $it->pesan_verifikasi,
                'created_at'               => $it->created_at,
                'updated_at'               => $it->updated_at,
            ],
            'perencanaan' => $perencanaanList,
            'pembangunan' => $pembangunanList,
            'pengawasan'  => $pengawasanList,
        ],
    ]);
}

    // ================= Helpers =================

    private function applyAliases(Request $request): void
    {
        $aliases = [
            // teks — backward compat & snake_case
            'sumber_usulan'         => 'sumberUsulan',
            'nama_aspirator'        => 'namaAspirator',
            'no_kontak_aspirator'   => 'noKontakAspirator',

            'nama_pemilik_lahan'    => 'namaPemilikLahan',
            'ukuran_lahan'          => 'ukuranLahan',
            'status_kepemilikan'    => 'statusLegalitasTanah', // backward compat
            'status_legalitas_tanah'=> 'statusLegalitasTanah',

            'alamat_dusun'          => 'alamatDusun',
            'alamat_rt'             => 'alamatRT',
            'alamat_rw'             => 'alamatRW',

            'kecamatan'             => 'kecamatan',
            'kelurahan'             => 'kelurahan',

            'titik_lokasi'          => 'titikLokasi',
            'pesan_verifikasi'      => 'pesan_verifikasi',

            // file arrays
            'dokumen_djpm'          => 'buktiLegalitasTanah',  // backward compat
            'bukti_legalitas_tanah' => 'buktiLegalitasTanah',
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
