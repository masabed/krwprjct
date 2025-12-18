<?php

namespace App\Http\Controllers\Api\Psu;

use App\Http\Controllers\Controller;
use App\Models\PSUUsulanFisikPerumahan;
use App\Models\PerumahanDb;
use App\Models\PSUUploadTemp;
use App\Models\PSUUpload;
use App\Models\Perencanaan;
use App\Models\Pembangunan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PSUUsulanFisikPerumahanController extends Controller
{
    /**
     * Field dokumen (array UUID) yang dipakai di usulan fisik perumahan.
     * Sesuai FormData: hanya 2 dokumen → suratPermohonanUsulanFisik, dokumentasiEksisting
     */
    private const FILE_ARRAY_FIELDS = [
        'suratPermohonanUsulanFisik',
        'dokumentasiEksisting',
    ];

    /** GET /api/psu/usulan/perumahan */
   public function index(Request $request)
{
    $user = $request->user();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    $role   = strtolower((string) ($user->role ?? ''));
    // role privileged: bebas lihat semua
    $isPriv = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);

    $q = PSUUsulanFisikPerumahan::query()->latest();

    if (!$isPriv) {
        $userId        = (string) $user->id;
        $userKecamatan = Str::lower(trim((string) ($user->kecamatan ?? '')));
        $userKelurahan = Str::lower(trim((string) ($user->kelurahan ?? '')));

        $perumahanIds = collect();

        // Kalau user punya kecamatan → ambil semua perumahan di kecamatan (dan kelurahan kalau ada)
        if ($userKecamatan !== '') {
            $perQuery = PerumahanDb::query()
                ->whereRaw('LOWER(COALESCE(kecamatan, "")) = ?', [$userKecamatan]);

            if ($userKelurahan !== '') {
                $perQuery->whereRaw('LOWER(COALESCE(kelurahan, "")) = ?', [$userKelurahan]);
            }

            $perumahanIds = $perQuery->pluck('id')->map(fn($v) => (string) $v);
        }

        // User biasa: UNION → data milik sendiri ATAU data di kecamatan/kelurahan-nya
        $q->where(function ($qq) use ($userId, $perumahanIds) {
            $qq->where('user_id', $userId);

            if ($perumahanIds->isNotEmpty()) {
                $qq->orWhereIn('perumahanId', $perumahanIds);
            }
        });
    }

    // filter opsional
    if ($request->has('perumahanId')) {
        $q->where('perumahanId', $request->query('perumahanId'));
    }
    if ($request->has('status_verifikasi_usulan')) {
        $q->where('status_verifikasi_usulan', (int) $request->query('status_verifikasi_usulan'));
    }

    return response()->json([
        'success' => true,
        'data'    => $q->get(),
    ]);
}


    /** POST /api/psu/usulan/perumahan/create */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Normalisasi semua field dokumen
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $payload = $request->validate([
            'tanggalPermohonan'             => 'required|string|max:30',
            'nomorSuratPermohonan'          => 'required|string|max:255',
            'sumberUsulan'                  => 'required|string|max:100',
            'namaAspirator'                 => 'required|string|max:150',
            'noKontakAspirator'             => 'required|string|max:50',
            'namaPIC'                       => 'required|string|max:150',
            'noKontakPIC'                   => 'required|string|max:50',
            'jenisUsulan'                   => 'required|string|max:100',
            'uraianMasalah'                 => 'sometimes|nullable|string',

            // dimensi
            'dimensiUsulanUtama'           => 'sometimes|nullable|string|max:150',
            'dimensiUsulanTambahan'        => 'sometimes|nullable|string|max:150',

            // lokasi
            'alamatUsulan'                  => 'required|string|max:500',
            'rtUsulan'                      => 'sometimes|nullable|string|max:10',
            'rwUsulan'                      => 'sometimes|nullable|string|max:10',
            'titikLokasiUsulan'             => 'sometimes|nullable|string|max:255',
            'perumahanId'                   => 'required|uuid',

            // dokumen (sesuai FormData: hanya 2)
            'suratPermohonanUsulanFisik'    => 'required|array|min:1|max:20',
            'suratPermohonanUsulanFisik.*'  => 'uuid',

            'dokumentasiEksisting'          => 'required|array|min:1|max:30',
            'dokumentasiEksisting.*'        => 'uuid',

            // meta verifikasi
            'status_verifikasi_usulan'      => 'sometimes|integer|in:0,1,2,3,4',
            'pesan_verifikasi'              => 'sometimes|nullable|string|max:512',
        ]);

        $row = PSUUsulanFisikPerumahan::create([
            ...$payload,
            'user_id' => (string) $user->id,
        ]);

        // kumpulkan semua UUID file yang dipakai (2 field saja sekarang)
        $uuids = array_values(array_unique(array_merge(
            $payload['suratPermohonanUsulanFisik'] ?? [],
            $payload['dokumentasiEksisting'] ?? [],
        )));
        $this->moveTempsToFinal($uuids, (string) $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Usulan fisik PERUMAHAN berhasil disimpan',
            'uuid'    => $row->uuid,
            'data'    => $row,
        ], 201);
    }

    /** POST /api/psu/usulan/perumahan/update/{uuid} */
    public function update(Request $request, string $uuid)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = PSUUsulanFisikPerumahan::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Normalisasi hanya field dokumen yang dikirim
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f)) {
                $this->normalizeUuidArrayField($request, $f);
            }
        }

        $validated = $request->validate([
            'tanggalPermohonan'             => 'sometimes|string|max:30',
            'nomorSuratPermohonan'          => 'sometimes|string|max:255',
            'sumberUsulan'                  => 'sometimes|string|max:100',
            'namaAspirator'                 => 'sometimes|string|max:150',
            'noKontakAspirator'             => 'sometimes|string|max:50',
            'namaPIC'                       => 'sometimes|string|max:150',
            'noKontakPIC'                   => 'sometimes|string|max:50',
            'jenisUsulan'                   => 'sometimes|string|max:100',
            'uraianMasalah'                 => 'sometimes|nullable|string',

            'dimensiUsulanUtama'            => 'sometimes|nullable|string|max:150',
            'dimensiUsulanTambahan'         => 'sometimes|nullable|string|max:150',

            'alamatUsulan'                  => 'sometimes|string|max:500',
            'rtUsulan'                      => 'sometimes|nullable|string|max:10',
            'rwUsulan'                      => 'sometimes|nullable|string|max:10',
            'titikLokasiUsulan'             => 'sometimes|nullable|string|max:255',
            'perumahanId'                   => 'sometimes|uuid',

            // File arrays (nullable → kalau null, abaikan/tidak overwrite)
            'suratPermohonanUsulanFisik'    => 'sometimes|nullable|array|min:1|max:20',
            'suratPermohonanUsulanFisik.*'  => 'uuid',
            'dokumentasiEksisting'          => 'sometimes|nullable|array|min:1|max:30',
            'dokumentasiEksisting.*'        => 'uuid',

            // Verifikasi
            'status_verifikasi_usulan'      => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
            'pesan_verifikasi'              => 'sometimes|nullable|string|max:512',
        ]);

        // UUID file baru (temp → final), tanpa overwrite dengan null
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (!array_key_exists($f, $validated)) {
                continue;
            }

            if (is_null($validated[$f])) {
                // jangan sentuh kolom file di DB
                unset($validated[$f]);
                continue;
            }

            $incoming = $validated[$f] ?? [];
            $existing = $item->getAttribute($f) ?? [];
            $diff     = array_diff($incoming, is_array($existing) ? $existing : []);
            if (!empty($diff)) {
                $uuidsToMove = array_merge($uuidsToMove, $diff);
            }
        }

        if (!empty($uuidsToMove)) {
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $user->id);
        }

        $item->fill($validated);

        // Auto-clear pesan kalau status dikirim dan >= 4
        if ($request->has('status_verifikasi_usulan')
            && (int) $request->input('status_verifikasi_usulan') >= 4) {
            $item->pesan_verifikasi = null;
        }

        $dirty = $item->getDirty();
        if (empty($dirty)) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada perubahan data',
                'data'    => $item,
                'changed' => [],
            ]);
        }

        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Field Berikut Berhasil di Perbaharui: ' . implode(', ', array_keys($dirty)),
            'data'    => $item->fresh(),
        ]);
    }

    /** GET /api/psu/usulan/perumahan/{uuid} */
    public function show(string $uuid)
{
    $auth = auth()->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    $row = PSUUsulanFisikPerumahan::where('uuid', $uuid)->first();
    if (!$row) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    $role    = strtolower((string) ($auth->role ?? ''));
    $isOwner = (string) ($row->user_id ?? '') === (string) $auth->id;
    $isPriv  = in_array($role, ['admin','admin_bidang','operator','pengawas'], true);

    // --- Cek akses berbasis kecamatan/kelurahan dari PerumahanDb ---
    $allowed = false;

    if ($isPriv || $isOwner) {
        // admin/admin_bidang/operator/pengawas atau owner → selalu boleh
        $allowed = true;
    } else {
        // user biasa → cek kecamatan / kelurahan dari profil + PerumahanDb
        $userKecamatan = Str::lower(trim((string) ($auth->kecamatan ?? '')));
        $userKelurahan = Str::lower(trim((string) ($auth->kelurahan ?? '')));

        if ($userKecamatan !== '') {
            $perumahanId = $row->perumahanId ?? null;

            if ($perumahanId) {
                $per = PerumahanDb::find($perumahanId);

                if ($per) {
                    $perKec = Str::lower(trim((string) ($per->kecamatan ?? '')));
                    $perKel = Str::lower(trim((string) ($per->kelurahan ?? '')));

                    if ($perKec !== '') {
                        if ($userKelurahan === '') {
                            // user punya kecamatan tapi tidak punya kelurahan → akses semua kelurahan di kecamatan tsb
                            $allowed = ($perKec === $userKecamatan);
                        } else {
                            // user punya kecamatan + kelurahan → harus match dua-duanya
                            $allowed = ($perKec === $userKecamatan && $perKel === $userKelurahan);
                        }
                    }
                }
            }
        }
    }

    if (!$allowed) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Perencanaan
    $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)
        ->orderBy('created_at', 'desc')
        ->get();

    $perencanaanList = $perencanaanRows->map(function ($r) {
        return [
            'uuidPerencanaan' => (string) $r->id,
            'uuidUsulan'      => (string) $r->uuidUsulan,
            'nilaiHPS'        => $r->nilaiHPS,
            'lembarKontrol'   => $r->lembarKontrol,
            'dokumentasi'     => $r->dokumentasi ?? [],   // ← perbaiki $p jadi $r
            'catatanSurvey'   => $r->catatanSurvey,
            'created_at'      => $r->created_at,
            'updated_at'      => $r->updated_at,
        ];
    })->values();

    // Pembangunan (support string/JSON array)
    $pembangunanRows = Pembangunan::query()
        ->where(function($q) use ($uuid) {
            $q->where('uuidUsulan', $uuid)
              ->orWhereJsonContains('uuidUsulan', $uuid);
        })
        ->orderBy('created_at', 'desc')
        ->get();

    // Resolve nama pengawas lapangan
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
            if (\Schema::hasColumn($userTable, 'uuid')) {
                $usersByUuid = \App\Models\User::query()
                    ->select('uuid','name','username')
                    ->whereIn('uuid', $pengawasKeys)
                    ->get()
                    ->keyBy(fn ($u) => (string) $u->uuid);
            }
        } catch (\Throwable $e) { $usersByUuid = collect(); }
    }

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
                $uuList = [$t];
            }
        }

        $uuidUsulanCount = collect($uuList)
            ->map(fn($v) => trim((string)$v))
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->count();

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

    // Pengawasan terkait
    $canSeeAllPengawasan = in_array($role, ['admin','admin_bidang','pengawas'], true) || $isOwner;

    $pengawasanRows = \App\Models\Pengawasan::query()
        ->where('uuidUsulan', $uuid)
        ->when(!$canSeeAllPengawasan, fn($q) => $q->where('pengawas', (string) $auth->id))
        ->orderByDesc('tanggal_pengawasan')
        ->orderByDesc('created_at')
        ->get();

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
            if (\Schema::hasColumn($userTable, 'uuid')) {
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

    /** DELETE /api/psu/usulan/perumahan/{uuid} */
    public function destroy(string $uuid)
{
    $auth = auth()->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    /** @var \App\Models\PSUUsulanFisikPerumahan|null $row */
    $row = \App\Models\PSUUsulanFisikPerumahan::where('uuid', $uuid)->first();
    if (!$row) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // === ACCESS CONTROL: admin / operator / owner ===
    $role    = strtolower((string) ($auth->role ?? ''));
    $isAdmin = ($role === 'admin');
    $isOper  = ($role === 'operator');
    $isOwner = (string) ($row->user_id ?? '') === (string) ($auth->id ?? '');

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
    $usulanUuid = (string) ($row->uuid ?? '');
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

    \DB::transaction(function () use ($row, $tokens, $tokensLow, $toArray, &$result) {

        // 1) Hapus Perencanaan terkait
        $result['deleted_perencanaan'] = \App\Models\Perencanaan::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) $q->orWhere('uuidUsulan', $t);
            })
            ->delete();

        // 2) Hapus Pengawasan terkait
        try {
            $result['deleted_pengawasan'] = \App\Models\Pengawasan::query()
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $t) $q->orWhere('uuidUsulan', $t);
                })
                ->delete();
        } catch (\Throwable $e) {
            $result['deleted_pengawasan'] = 0;
        }

        // 3) Cabut token dari semua row Pembangunan yang memuat usulan ini
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

        // 4) Hapus usulan utamanya
        $row->delete();
    });

    return response()->json([
        'success' => true,
        'message' => 'Data berhasil dihapus; perencanaan & pengawasan dibersihkan, UUID dicabut dari pembangunan, dan pembangunan kosong ikut terhapus.',
        'result'  => $result,
    ]);
}

    // =============== Helpers ===============

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
            if ($t === '' || strtolower($t) === 'null') { $request->merge([$field => null]); return; }

            if (str_starts_with($t, '[')) {
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

        $temps = PSUUploadTemp::whereIn('uuid', $uuids)
            ->where('user_id', $userId)
            ->get()->keyBy('uuid');

        foreach ($uuids as $u) {
            $temp = $temps->get($u);
            if (!$temp) continue;

            $old  = $temp->file_path; // psu_temp/<uuid>.<ext>
            $file = basename($old);
            $new  = 'psu_final/' . $file;

            if (Storage::disk('local')->exists($new)) {
                $ext  = pathinfo($file, PATHINFO_EXTENSION);
                $file = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                $new  = 'psu_final/' . $file;
            }

            if (Storage::disk('local')->exists($old)) {
                Storage::disk('local')->move($old, $new);
            } elseif (!Storage::disk('local')->exists($new)) {
                continue;
            }

            PSUUpload::updateOrCreate(
                ['uuid' => $temp->uuid],
                [
                    'user_id'       => $userId,
                    'file_path'     => $new,
                    'original_name' => $temp->original_name,
                    'mime'          => $temp->mime,
                    'size'          => $temp->size,
                ]
            );

            $temp->delete();
        }
    }
}
