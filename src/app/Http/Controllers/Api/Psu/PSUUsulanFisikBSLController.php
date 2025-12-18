<?php

namespace App\Http\Controllers\Api\Psu;

use App\Http\Controllers\Controller;
use App\Models\UsulanFisikBSL;
use App\Models\PSUUploadTemp;
use App\Models\PSUUpload;
use App\Models\Perencanaan;
use App\Models\Pembangunan;
use App\Models\Pengawasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PSUUsulanFisikBSLController extends Controller
{
    /**
     * Kolom dokumen (array of UUID) yang dipakai di Usulan Fisik BSL.
     * Semua pakai pipeline PSU: psu_temp -> psu_final
     */
    private const FILE_FIELDS = [
        'suratPermohonanUsulanFisik',
        'sertifikatStatusTanah',   // nullable array
        'dokumentasiEksisting',
    ];

    /** ================== STORE ==================
     * POST /api/psu/usulan-fisik-bsl
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Normalisasi semua field dokumen (bisa JSON string/CSV/single/array)
        foreach (self::FILE_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            // Keterangan permohonan
            'tanggalPermohonan'      => ['required', 'date'],
            'nomorSuratPermohonan'   => ['required', 'string', 'max:150'],

            // Sumber usulan & Data pemohon
            'sumberUsulan'           => ['required', 'string', 'max:150'],
            'namaAspirator'          => ['required', 'string', 'max:255'],
            'noKontakAspirator'      => ['required', 'string', 'max:100'],
            'namaPIC'                => ['required', 'string', 'max:255'],
            'noKontakPIC'            => ['required', 'string', 'max:100'],

            // Rincian usulan
            'jenisUsulan'            => ['required', 'string', 'max:150'],
            'uraianMasalah'          => ['required', 'string'],

            // Dimensi usulan / eksisting
            'luasTanahTersedia'      => ['required', 'string', 'max:100'],
            'luasSarana'             => ['required', 'string', 'max:100'],

            // Lokasi usulan (WAJIB: 'jenisLokasi')
            'jenisLokasi'            => ['required', 'string', 'max:150'],
            'alamatCPCL'             => ['required', 'string', 'max:500'],
            'rtCPCL'                 => ['required', 'string', 'max:10'],
            'rwCPCL'                 => ['required', 'string', 'max:10'],
            'titikLokasiUsulan'      => ['required', 'string', 'max:255'],
            'kecamatanUsulan'        => ['sometimes', 'nullable', 'string', 'max:150'],
            'kelurahanUsulan'        => ['sometimes', 'nullable', 'string', 'max:150'],

            // Keterangan lokasi BSL
            'perumahanId'            => ['sometimes', 'nullable', 'uuid'],
            'statusTanah'            => ['sometimes', 'nullable', 'string', 'max:150'],

            // Dokumen pendukung (opsional & nullable)
            'suratPermohonanUsulanFisik'   => ['sometimes', 'nullable', 'array', 'max:10'],
            'suratPermohonanUsulanFisik.*' => ['uuid'],

            'sertifikatStatusTanah'        => ['sometimes', 'nullable', 'array', 'max:10'],
            'sertifikatStatusTanah.*'      => ['uuid'],

            'dokumentasiEksisting'         => ['sometimes', 'nullable', 'array', 'max:20'],
            'dokumentasiEksisting.*'       => ['uuid'],
        ]);

        // Admin boleh set status/pesan verifikasi saat create
        if (($user->role ?? null) === 'admin') {
            $adminValidated = $request->validate([
                'status_verifikasi_usulan' => ['sometimes', 'integer', 'in:0,1,2,3,4,5,6,7'],
                'pesan_verifikasi'         => ['sometimes', 'nullable', 'string', 'max:512'],
            ]);
            $validated = array_merge($validated, $adminValidated);
        }

        // Finalisasi dokumen (TEMP -> FINAL / reuse FINAL)
        $finalized = [];
        foreach (self::FILE_FIELDS as $f) {
            if (array_key_exists($f, $validated)) {
                $finalized[$f] = (is_array($validated[$f]) && count($validated[$f]) > 0)
                    ? $this->ensureFinalUploads($validated[$f], (string) $user->id, true)
                    : null; // biarkan null untuk kolom nullable
            }
        }

        // Payload
        $payload = array_merge($validated, $finalized);
        $payload['user_id'] = (string) $user->id;

        $row = UsulanFisikBSL::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Usulan Fisik BSL berhasil dibuat',
            'data'    => $row,
        ], 201);
    }

    /** ================== UPDATE ==================
     * POST/PUT/PATCH /api/psu/usulan-fisik-bsl/{id}
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $row = UsulanFisikBSL::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Hanya pemilik atau admin
        if ((string) $row->user_id !== (string) $user->id && ($user->role ?? null) !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Normalisasi hanya field dokumen yang dikirim
        foreach (self::FILE_FIELDS as $f) {
            if ($request->has($f)) {
                $this->normalizeUuidArrayField($request, $f);
            }
        }

        $validated = $request->validate([
            // Keterangan permohonan
            'tanggalPermohonan'      => ['sometimes', 'date'],
            'nomorSuratPermohonan'   => ['sometimes', 'string', 'max:150'],

            // Sumber usulan & Data pemohon
            'sumberUsulan'           => ['sometimes', 'string', 'max:150'],
            'namaAspirator'          => ['sometimes', 'string', 'max:255'],
            'noKontakAspirator'      => ['sometimes', 'string', 'max:100'],
            'namaPIC'                => ['sometimes', 'string', 'max:255'],
            'noKontakPIC'            => ['sometimes', 'string', 'max:100'],

            // Rincian usulan
            'jenisUsulan'            => ['sometimes', 'string', 'max:150'],
            'uraianMasalah'          => ['sometimes', 'string'],

            // Dimensi usulan / eksisting
            'luasTanahTersedia'      => ['sometimes', 'string', 'max:100'],
            'luasSarana'             => ['sometimes', 'string', 'max:100'],

            // Lokasi usulan
            'jenisLokasi'            => ['sometimes', 'string', 'max:150'],
            'alamatCPCL'             => ['sometimes', 'string', 'max:500'],
            'rtCPCL'                 => ['sometimes', 'string', 'max:10'],
            'rwCPCL'                 => ['sometimes', 'string', 'max:10'],
            'titikLokasiUsulan'      => ['sometimes', 'string', 'max:255'],
            'kecamatanUsulan'        => ['sometimes', 'nullable', 'string', 'max:150'],
            'kelurahanUsulan'        => ['sometimes', 'nullable', 'string', 'max:150'],

            // Keterangan lokasi BSL
            'perumahanId'            => ['sometimes', 'nullable', 'uuid'],
            'statusTanah'            => ['sometimes', 'nullable', 'string', 'max:150'],

            // Dokumen pendukung
            'suratPermohonanUsulanFisik'   => ['sometimes', 'nullable', 'array', 'max:10'],
            'suratPermohonanUsulanFisik.*' => ['uuid'],
            'sertifikatStatusTanah'        => ['sometimes', 'nullable', 'array', 'max:10'],
            'sertifikatStatusTanah.*'      => ['uuid'],
            'dokumentasiEksisting'         => ['sometimes', 'nullable', 'array', 'max:20'],
            'dokumentasiEksisting.*'       => ['uuid'],
        ]);

        // Admin boleh set verifikasi
        if (($user->role ?? null) === 'admin') {
            $adminValidated = $request->validate([
                'status_verifikasi_usulan' => ['sometimes', 'integer', 'in:0,1,2,3,4,5,6,7'],
                'pesan_verifikasi'         => ['sometimes', 'nullable', 'string', 'max:512'],
            ]);
            $validated = array_merge($validated, $adminValidated);

            // AUTO-CLEAR PESAN SAAT STATUS NAIK KE ≥ 4 (pertama kali)
            if (array_key_exists('status_verifikasi_usulan', $adminValidated)
                && (int) $adminValidated['status_verifikasi_usulan'] >= 4
                && (int) ($row->status_verifikasi_usulan ?? -1) < 4) {
                $validated['pesan_verifikasi'] = null;
            }
        } else {
            // user biasa tidak boleh mengubah verifikasi
            unset($validated['status_verifikasi_usulan'], $validated['pesan_verifikasi']);
        }

        // Tangani file-fields yang dikirim
        foreach (self::FILE_FIELDS as $f) {
            if (!array_key_exists($f, $validated)) {
                continue;
            }

            if (is_null($validated[$f])) { // null => abaikan perubahan kolom file
                unset($validated[$f]);
                continue;
            }

            $incomingFinal = (is_array($validated[$f]) && count($validated[$f]) > 0)
                ? $this->ensureFinalUploads($validated[$f], (string) $user->id, true)
                : [];

            $old     = $row->getAttribute($f) ?? [];
            $old     = is_array($old) ? $old : [];
            $removed = array_values(array_diff($old, $incomingFinal)); // yang hilang

            if (!empty($removed)) {
                $this->deleteFinalUploads($removed);
            }

            $validated[$f] = $incomingFinal ?: null;
        }

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
            'message' => 'Field berikut berhasil diperbarui: ' . implode(', ', array_keys($dirty)),
            'data'    => $row->refresh(),
        ]);
    }

    /** ================== SHOW ==================
     * GET /api/psu/usulan-fisik-bsl/{id}
     */
   public function show(Request $request, string $id)
{
    $auth = $request->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    // 1) Usulan BSL
    $row = UsulanFisikBSL::find($id);
    if (!$row) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // 1b) Access control
    $role    = strtolower((string) ($auth->role ?? ''));
    $isOwner = (string) ($row->user_id ?? '') === (string) $auth->id;

    // Role yang full akses: admin, admin_bidang, operator, pengawas
    $isPriv  = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);

    if (!$isPriv && !$isOwner) {
        // User biasa → cek akses berbasis kecamatan & kelurahan
        $userKec = strtolower(trim((string) ($auth->kecamatan ?? '')));
        $userKel = strtolower(trim((string) ($auth->kelurahan ?? '')));

        // Kalau user tidak punya kecamatan di profil → tidak boleh lihat
        if ($userKec === '') {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Cek kecamatan usulan
        $itemKec = strtolower(trim((string) ($row->kecamatanUsulan ?? '')));
        if ($itemKec === '' || $itemKec !== $userKec) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Kalau user punya kelurahan → wajib sama persis (case-insensitive)
        if ($userKel !== '') {
            $itemKel = strtolower(trim((string) ($row->kelurahanUsulan ?? '')));
            if ($itemKel === '' || $itemKel !== $userKel) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }
        }
    }

    // 2) Perencanaan terkait
    $perencanaanRows = Perencanaan::where('uuidUsulan', (string) $row->id)
        ->orderBy('created_at', 'desc')
        ->get();

    $perencanaanList = $perencanaanRows->map(function ($r) {
        return [
            'uuidPerencanaan' => (string) $r->id,
            'uuidUsulan'      => (string) $r->uuidUsulan,
            'lembarKontrol'   => $r->lembarKontrol,
            'nilaiHPS'        => $r->nilaiHPS,
            'catatanSurvey'   => $r->catatanSurvey,
            'dokumentasi'     => $r->dokumentasi ?? [],   // <-- perbaikan di sini
            'created_at'      => $r->created_at,
            'updated_at'      => $r->updated_at,
        ];
    })->values();

    // 3) Pembangunan terkait (support string/JSON array)
    $pembangunanRows = Pembangunan::query()
        ->where(function ($q) use ($row) {
            $q->where('uuidUsulan', (string) $row->id)
              ->orWhereJsonContains('uuidUsulan', (string) $row->id);
        })
        ->orderBy('created_at', 'desc')
        ->get();

    // 3a) Resolve nama pengawas lapangan
    $pengawasKeys = $pembangunanRows->pluck('pengawasLapangan')
        ->filter(fn($v) => !empty($v))
        ->map(fn($v) => (string) $v)
        ->unique()
        ->values();

    $usersById   = collect();
    $usersByUuid = collect();

    if ($pengawasKeys->isNotEmpty() && class_exists(\App\Models\User::class)) {
        try {
            $usersById = \App\Models\User::query()
                ->select('id', 'name', 'username')
                ->whereIn('id', $pengawasKeys)
                ->get()
                ->keyBy(fn($u) => (string) $u->id);
        } catch (\Throwable $e) {
            $usersById = collect();
        }

        try {
            $userTable = (new \App\Models\User)->getTable();
            if (\Schema::hasColumn($userTable, 'uuid')) {
                $usersByUuid = \App\Models\User::query()
                    ->select('uuid', 'name', 'username')
                    ->whereIn('uuid', $pengawasKeys)
                    ->get()
                    ->keyBy(fn($u) => (string) $u->uuid);
            }
        } catch (\Throwable $e) {
            $usersByUuid = collect();
        }
    }

    // 3b) Bentuk list pembangunan + hitung uuidUsulan_count PER ROW
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
            ->map(fn($v) => trim((string) $v))
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
            'uuidUsulan'            => $uuList, // sudah array
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
    $canSeeAllPengawasan = in_array($role, ['admin', 'admin_bidang', 'pengawas'], true) || $isOwner;

    $pengawasanRows = Pengawasan::query()
        ->where('uuidUsulan', (string) $row->id)
        ->when(!$canSeeAllPengawasan, fn($q) => $q->where('pengawas', (string) $auth->id))
        ->orderByDesc('tanggal_pengawasan')
        ->orderByDesc('created_at')
        ->get();

    // extend lookup user untuk pengawas catatan jika belum ter-cover
    $pengawasCatatanKeys = $pengawasanRows->pluck('pengawas')
        ->filter(fn($v) => !empty($v))
        ->map(fn($v) => (string) $v)
        ->diff($pengawasKeys)
        ->values();

    if ($pengawasCatatanKeys->isNotEmpty() && class_exists(\App\Models\User::class)) {
        try {
            $addById = \App\Models\User::query()
                ->select('id', 'name', 'username')
                ->whereIn('id', $pengawasCatatanKeys)
                ->get()
                ->keyBy(fn($u) => (string) $u->id);
            $usersById = $usersById->merge($addById);
        } catch (\Throwable $e) {}

        try {
            $userTable = (new \App\Models\User)->getTable();
            if (\Schema::hasColumn($userTable, 'uuid')) {
                $addByUuid = \App\Models\User::query()
                    ->select('uuid', 'name', 'username')
                    ->whereIn('uuid', $pengawasCatatanKeys)
                    ->get()
                    ->keyBy(fn($u) => (string) $u->uuid);
                $usersByUuid = $usersByUuid->merge($addByUuid);
            }
        } catch (\Throwable $e) {}
    }

    $pengawasanList = $pengawasanRows->map(function ($r) use ($usersById, $usersByUuid) {
        $k  = (string) ($r->pengawas ?? '');
        $nm = null;
        if ($k !== '') {
            $u  = $usersById->get($k) ?? $usersByUuid->get($k);
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
        'data'    => [
            'usulan'      => $row,
            'perencanaan' => $perencanaanList,
            'pembangunan' => $pembangunanList,
            'pengawasan'  => $pengawasanList,
        ],
    ]);
}

    /** ================== INDEX ==================
     * GET /api/psu/usulan-fisik-bsl
     * Optional filter: perumahanId
     */
    /** ================== INDEX ==================
 * GET /api/psu/usulan-fisik-bsl
 * Optional filter: perumahanId
 */
public function index(Request $request)
{
    $auth = $request->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    $role   = strtolower((string) ($auth->role ?? ''));
    // Full akses list: admin, admin_bidang, operator, pengawas
    $isPriv = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);

    $q = UsulanFisikBSL::query()->latest();

    if ($isPriv) {
        // Kalau mau lihat hanya data milik sendiri → ?mine=1
        if ($request->boolean('mine')) {
            $q->where('user_id', (string) $auth->id);
        }
    } else {
        // User biasa: filter berdasar user_id + kecamatanUsulan/kelurahanUsulan
        $userKec = strtolower(trim((string) ($auth->kecamatan ?? '')));
        $userKel = strtolower(trim((string) ($auth->kelurahan ?? '')));

        if ($userKec === '') {
            // Tidak punya kecamatan di profil → hanya data miliknya
            $q->where('user_id', (string) $auth->id);
        } else {
            // Boleh:
            // - data miliknya sendiri
            // - semua usulan di kecamatanUsulan yg sama
            //   - jika kelurahan user kosong → semua kelurahanUsulan di kecamatan tsb
            //   - jika kelurahan user ada → hanya kelurahanUsulan tsb
            $q->where(function ($qq) use ($auth, $userKec, $userKel) {
                $qq->where('user_id', (string) $auth->id)
                   ->orWhere(function ($sub) use ($userKec, $userKel) {
                       $sub->whereRaw('LOWER(kecamatanUsulan) = ?', [$userKec]);

                       if ($userKel !== '') {
                           // ✅ FIX DI SINI: pakai '' bukan \"\"
                           $sub->whereRaw("LOWER(COALESCE(kelurahanUsulan, '')) = ?", [$userKel]);
                       }
                   });
            });
        }
    }

    // Filter opsional perumahanId
    if ($request->has('perumahanId')) {
        $q->where('perumahanId', $request->query('perumahanId'));
    }

    return response()->json([
        'success' => true,
        'data'    => $q->get(),
    ]);
}



    /** ================== DESTROY ==================
     * DELETE /api/psu/usulan-fisik-bsl/{id}
     */
    public function destroy(Request $request, string $id)
{
    $user = $request->user();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    /** @var \App\Models\UsulanFisikBSL|null $row */
    $row = UsulanFisikBSL::find($id);
    if (!$row) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // === ACCESS CONTROL: admin / operator / owner ===
    $role    = strtolower((string) ($user->role ?? ''));
    $isAdmin = ($role === 'admin');
    $isOper  = ($role === 'operator');
    $isOwner = (string) ($row->user_id ?? '') === (string) ($user->id ?? '');

    if (!($isAdmin || $isOper || $isOwner)) {
        return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
    }

    // 0) Hapus file FINAL terkait (mengikuti helper kamu)
    foreach (self::FILE_FIELDS as $f) {
        $uuids = $row->getAttribute($f) ?? [];
        if ($uuids) {
            $this->deleteFinalUploads(is_array($uuids) ? $uuids : []);
        }
    }

    // token relasi untuk uuidUsulan (id + uuid kalau ada)
    $usulanId   = (string) $row->getKey();          // biasanya id
    $usulanUuid = '';
    try {
        $usulanUuid = (string) ($row->uuid ?? '');
    } catch (\Throwable $e) {
        $usulanUuid = '';
    }

    $tokens    = array_values(array_unique(array_filter([$usulanId, $usulanUuid])));
    $tokensLow = array_map('strtolower', $tokens);

    // helper normalisasi: null|string(JSON)|string single|array -> array<string>
    $toArray = function ($val): array {
        if (is_null($val)) return [];
        if (is_array($val)) {
            return collect($val)
                ->map(fn($v) => trim((string)$v))
                ->filter()
                ->values()
                ->all();
        }
        $s = trim((string)$val);
        if ($s === '') return [];
        if (str_starts_with($s, '[')) {
            $arr = json_decode($s, true);
            if (is_array($arr)) {
                return collect($arr)
                    ->map(fn($v) => trim((string)$v))
                    ->filter()
                    ->values()
                    ->all();
            }
            return [$s];
        }
        return [$s];
    };

    $result = [
        'deleted_perencanaan'      => 0,
        'deleted_pengawasan'       => 0,
        'updated_pembangunan_rows' => 0,
        'deleted_pembangunan_rows' => 0,
        'deleted_pembangunan_ids'  => [],
    ];

    DB::transaction(function () use ($row, $tokens, $tokensLow, $toArray, &$result) {

        // 1) Hapus Perencanaan terkait (support token id/uuid)
        $result['deleted_perencanaan'] = \App\Models\Perencanaan::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) $q->orWhere('uuidUsulan', $t);
            })
            ->delete();

        // 2) Hapus Pengawasan terkait (support token id/uuid)
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

    // ================== HELPERS ==================

    /** Normalisasi field array-UUID dari JSON/CSV/single/path → array UUID unik atau null */
    private function normalizeUuidArrayField(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);

        // "" atau "null" → null
        if ($val === '' || $val === null || (is_string($val) && strtolower(trim($val)) === 'null')) {
            $request->merge([$field => null]);
            return;
        }

        // Array
        if (is_array($val)) {
            $uuids = $this->filterUuidArray($val);
            $request->merge([$field => $uuids ?: null]);
            return;
        }

        // String JSON
        if (is_string($val)) {
            $t = trim($val);

            if ($t !== '' && $t[0] === '[') {
                $arr   = json_decode($t, true);
                $uuids = is_array($arr) ? $this->filterUuidArray($arr) : [];
                $request->merge([$field => $uuids ?: null]);
                return;
            }

            // CSV
            if (str_contains($t, ',')) {
                $parts = array_map('trim', explode(',', $t));
                $uuids = $this->filterUuidArray($parts);
                $request->merge([$field => $uuids ?: null]);
                return;
            }

            // Single/path
            $u = $this->extractUuid($t);
            $request->merge([$field => $u ? [$u] : null]);
        }
    }

    /** Ambil UUID valid dari array campur-campur */
    private function filterUuidArray(array $arr): array
    {
        $uuids = [];
        foreach ($arr as $v) {
            $u = $this->extractUuid((string) $v);
            if ($u) {
                $uuids[] = $u;
            }
        }
        return array_values(array_unique($uuids));
    }

    /** Ekstrak UUID v1–v5 dari string/path */
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
     * Finalisasi UUID: TEMP ➜ FINAL atau reuse FINAL.
     * (pakai tabel PSUUploadTemp & PSUUpload)
     */
    private function ensureFinalUploads(array $uuids, string $currentUserId, bool $strict = true): array
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return [];

        $disk  = Storage::disk('local');
        $final = PSUUpload::whereIn('uuid', $uuids)->get()->keyBy('uuid');
        $temps = PSUUploadTemp::whereIn('uuid', $uuids)->get()->keyBy('uuid');

        $result  = [];
        $invalid = [];

        foreach ($uuids as $u) {
            // FINAL?
            if ($final->has($u)) {
                $row = $final->get($u);
                if (!$disk->exists($row->file_path)) {
                    $invalid[] = $u;
                    continue;
                }
                $result[] = $u;
                continue;
            }

            // TEMP → FINAL
            if ($temps->has($u)) {
                $temp = $temps->get($u);
                if (!$disk->exists($temp->file_path)) {
                    $invalid[] = $u;
                    continue;
                }

                $filename = basename($temp->file_path);
                $ext      = pathinfo($filename, PATHINFO_EXTENSION);
                $newName  = $filename;
                $newPath  = 'psu_final/' . $newName;

                if ($disk->exists($newPath)) {
                    $newName = (string) Str::uuid() . ($ext ? ".{$ext}" : '');
                    $newPath = 'psu_final/' . $newName;
                }

                DB::transaction(function () use ($disk, $temp, $newPath, $currentUserId) {
                    $disk->move($temp->file_path, $newPath);

                    PSUUpload::updateOrCreate(
                        ['uuid' => $temp->uuid],
                        [
                            'user_id'       => $currentUserId,
                            'file_path'     => $newPath,
                            'original_name' => $temp->original_name,
                            'mime'          => $temp->mime,
                            'size'          => $temp->size,
                        ]
                    );

                    $temp->delete();
                });

                $result[] = $u;
                continue;
            }

            // tidak ada di final/temp
            $invalid[] = $u;
        }

        if ($strict && !empty($invalid)) {
            return abort(response()->json([
                'success'       => false,
                'message'       => 'Beberapa UUID file tidak valid / file fisik tidak ada.',
                'invalid_uuids' => array_values($invalid),
            ], 422));
        }

        return array_values(array_unique($result));
    }

    /** Hapus record FINAL + file fisiknya berdasarkan daftar UUID */
    private function deleteFinalUploads(array $uuids): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return;

        $disk  = Storage::disk('local');
        $files = PSUUpload::whereIn('uuid', $uuids)->get();

        foreach ($files as $f) {
            if ($f->file_path && $disk->exists($f->file_path)) {
                $disk->delete($f->file_path);
            }
            $f->delete();
        }
    }
}
