<?php

namespace App\Http\Controllers\Api\Permukiman;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Permukiman;
use App\Models\PermukimanUpload;
use App\Models\PermukimanUploadTemp;
use App\Models\Perencanaan;
use App\Models\Pembangunan;
use App\Models\Pengawasan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PermukimanController extends Controller
{
    /**
     * Semua kolom file bertipe ARRAY UUID.
     */
    private const FILE_ARRAY_FIELDS = [
        'foto_sertifikat_status_tanah',
        'foto_sta0',
        'foto_sta100',
        'surat_pemohonan',
    ];

    /**
     * POST /permukiman/upload
     * Upload ke TEMP; balikan UUID temp untuk dipakai saat store/update.
     */
    public function upload(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10512',
        ]);

        $path   = $request->file('file')->store('permukiman_temp');
        $userId = (string) auth()->id();

        $temp = PermukimanUploadTemp::create([
            'uuid'      => (string) Str::uuid(),
            'user_id'   => $userId,
            'file_path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'data'    => ['uuid' => $temp->uuid, 'user_id' => $temp->user_id],
        ], 201);
    }

    /**
     * GET /permukiman (list + filter)
     */
  public function index(Request $request)
{
    $user = auth()->user();
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);
    }

    $role   = strtolower((string) ($user->role ?? ''));
    $isPriv = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);

    $q = Permukiman::query();

    // === Akses kontrol untuk user biasa ===
    if (!$isPriv) {
        $userId  = (string) $user->id;
        $userKec = trim((string) ($user->kecamatan ?? ''));
        $userKel = trim((string) ($user->kelurahan ?? ''));

        $q->where(function ($w) use ($userId, $userKec, $userKel) {
            // Selalu boleh lihat usulan miliknya sendiri
            $w->where('user_id', $userId);

            // Jika punya kecamatan → boleh lihat data sekecamatan
            if ($userKec !== '') {
                $w->orWhere(function ($ww) use ($userKec, $userKel) {
                    $ww->where('kecamatan', $userKec);

                    // Jika user punya kelurahan → batasi ke kelurahan itu saja
                    if ($userKel !== '') {
                        $ww->where('kelurahan', $userKel);
                    }
                    // Kalau kelurahan user kosong → semua kelurahan di kecamatan tsb boleh diakses
                });
            }
        });
    }

    // === Filter status (opsional) ===
    if ($request->filled('status')) {
        $q->where('status_verifikasi_usulan', (int) $request->input('status'));
    }

    // === Pencarian (opsional) ===
    if ($request->filled('search')) {
        $s = trim((string) $request->input('search'));
        $q->where(function ($qq) use ($s) {
            $qq->where('nama_pengusul', 'like', "%{$s}%")
               ->orWhere('instansi', 'like', "%{$s}%")
               ->orWhere('jenis_usulan', 'like', "%{$s}%");
        });
    }

    $data = $q->latest()->get();

    return response()->json([
        'success' => true,
        'count'   => $data->count(),
        'data'    => $data,
    ]);
}

/**
 * GET /permukiman/{id} (detail by PK string)
 */
public function show(string $id)
{
    // 0) Auth
    $auth = auth()->user();
    if (!$auth) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);
    }

    // 1) Ambil data utama usulan Permukiman
    $data = Permukiman::find($id);
    if (!$data) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan',
        ], 404);
    }

    // 1b) Access control
    $role    = strtolower((string) ($auth->role ?? ''));
    $isPriv  = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);
    $isOwner = (string) ($data->user_id ?? '') === (string) $auth->id;

    $rowKec  = trim((string) ($data->kecamatan ?? ''));
    $rowKel  = trim((string) ($data->kelurahan ?? ''));
    $userKec = trim((string) ($auth->kecamatan ?? ''));
    $userKel = trim((string) ($auth->kelurahan ?? ''));

    $canAccess = false;

    if ($isPriv || $isOwner) {
        // Admin/admin_bidang/operator/pengawas bebas, atau pemilik usulan
        $canAccess = true;
    } else {
        // User biasa: cek berdasarkan kecamatan/kelurahan
        if ($userKec !== '' && strcasecmp($rowKec, $userKec) === 0) {
            // Jika user tidak punya kelurahan → boleh semua kelurahan di kecamatan itu
            if ($userKel === '' || strcasecmp($rowKel, $userKel) === 0) {
                $canAccess = true;
            }
        }
    }

    if (!$canAccess) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    // 2) Perencanaan
    $perencanaanRows = Perencanaan::where('uuidUsulan', $id)
        ->orderBy('created_at', 'desc')
        ->get();

    $perencanaanList = $perencanaanRows->map(function ($row) {
        return [
            'uuidPerencanaan' => (string) $row->id,
            'uuidUsulan'      => (string) $row->uuidUsulan,
            'nilaiHPS'        => $row->nilaiHPS,
            'dokumentasi'     => $row->dokumentasi ?? [],   // <- perbaiki $p -> $row
            'lembarKontrol'   => $row->lembarKontrol,
            'catatanSurvey'   => $row->catatanSurvey,
            'created_at'      => $row->created_at,
            'updated_at'      => $row->updated_at,
        ];
    })->values();

    // 3) Pembangunan (support string/JSON array)
    $pembangunanRows = Pembangunan::query()
        ->where(function ($q) use ($id) {
            $q->where('uuidUsulan', $id)
              ->orWhereJsonContains('uuidUsulan', $id);
        })
        ->orderBy('created_at', 'desc')
        ->get();

    // 3b) Resolve nama pengawas
    $pengawasKeys = $pembangunanRows->pluck('pengawasLapangan')
        ->filter(fn ($v) => !empty($v))
        ->map(fn ($v) => (string) $v)
        ->unique()
        ->values();

    $usersById   = collect();
    $usersByUuid = collect();

    if ($pengawasKeys->isNotEmpty() && class_exists(\App\Models\User::class)) {
        try {
            $usersById = \App\Models\User::select('id','name','username')
                ->whereIn('id', $pengawasKeys)->get()
                ->keyBy(fn($u) => (string) $u->id);
        } catch (\Throwable $e) {
            $usersById = collect();
        }

        try {
            $userTable = (new \App\Models\User)->getTable();
            if (\Illuminate\Support\Facades\Schema::hasColumn($userTable,'uuid')) {
                $usersByUuid = \App\Models\User::select('uuid','name','username')
                    ->whereIn('uuid',$pengawasKeys)->get()
                    ->keyBy(fn($u) => (string) $u->uuid);
            }
        } catch (\Throwable $e) {
            $usersByUuid = collect();
        }
    }

    // 3c) Bentuk list pembangunan + uuidUsulan_count PER ROW
    $pembangunanList = $pembangunanRows->map(function ($row) use ($usersById, $usersByUuid) {
        // Normalisasi uuidUsulan → array
        $uuList = [];
        $uuRaw  = $row->uuidUsulan;

        if (is_array($uuRaw)) {
            $uuList = $uuRaw;
        } elseif (is_string($uuRaw)) {
            $t = trim($uuRaw);
            if ($t !== '' && str_starts_with($t, '[')) {
                $arr   = json_decode($t, true);
                $uuList = is_array($arr) ? $arr : [];
            } elseif ($t !== '') {
                // legacy single string
                $uuList = [$t];
            }
        }

        // Hitung jumlah UUID unik yang tidak kosong
        $uuidUsulanCount = collect($uuList)
            ->map(fn($v) => trim((string)$v))
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->count();

        // Nama pengawas
        $pengawasKey = (string) ($row->pengawasLapangan ?? '');
        $pengawasName =
            ($pengawasKey !== '')
                ? (optional($usersById->get($pengawasKey))->name
                   ?? optional($usersById->get($pengawasKey))->username
                   ?? optional($usersByUuid->get($pengawasKey))->name
                   ?? optional($usersByUuid->get($pengawasKey))->username
                   ?? null)
                : null;

        return [
            'uuidPembangunan'       => (string) $row->id,
            'uuidUsulan'            => $uuList, // selalu array di response
            'nomorSPK'              => $row->nomorSPK,
            'tanggalSPK'            => $row->tanggalSPK,
            'nilaiKontrak'          => $row->nilaiKontrak,
            'kontraktorPelaksana'   => $row->kontraktorPelaksana,
            'tanggalMulai'          => $row->tanggalMulai,
            'tanggalSelesai'        => $row->tanggalSelesai,
            'jangkaWaktu'           => $row->jangkaWaktu,
            'pengawasLapangan'      => $row->pengawasLapangan,
            'pengawasLapangan_name' => $pengawasName,
            'uuidUsulan_count'      => $uuidUsulanCount,
            'created_at'            => $row->created_at,
            'updated_at'            => $row->updated_at,
        ];
    })->values();

    // 4) Pengawasan (filter role)
    $canSeeAllPengawasan = in_array($role, ['admin','admin_bidang','pengawas'], true) || $isOwner;

    $pengawasanRows = \App\Models\Pengawasan::query()
        ->where('uuidUsulan', $id)
        ->when(!$canSeeAllPengawasan, fn($q) => $q->where('pengawas', (string) $auth->id))
        ->orderByDesc('tanggal_pengawasan')
        ->orderByDesc('created_at')
        ->get();

    // extend lookup jika ada pengawas baru
    $pengawasCatatanKeys = $pengawasanRows->pluck('pengawas')
        ->filter(fn($v)=>!empty($v))
        ->map(fn($v)=>(string)$v)
        ->diff($pengawasKeys)
        ->values();

    if ($pengawasCatatanKeys->isNotEmpty() && class_exists(\App\Models\User::class)) {
        try {
            $usersById = $usersById->merge(
                \App\Models\User::select('id','name','username')
                    ->whereIn('id',$pengawasCatatanKeys)->get()
                    ->keyBy(fn($u)=>(string)$u->id)
            );
        } catch (\Throwable $e) {}

        try {
            $userTable = (new \App\Models\User)->getTable();
            if (\Illuminate\Support\Facades\Schema::hasColumn($userTable,'uuid')) {
                $usersByUuid = $usersByUuid->merge(
                    \App\Models\User::select('uuid','name','username')
                        ->whereIn('uuid',$pengawasCatatanKeys)->get()
                        ->keyBy(fn($u)=>(string)$u->uuid)
                );
            }
        } catch (\Throwable $e) {}
    }

    $pengawasanList = $pengawasanRows->map(function ($r) use ($usersById, $usersByUuid) {
        $k  = (string) ($r->pengawas ?? '');
        $nm = null;
        if ($k !== '') {
            $nm = optional($usersById->get($k))->name
               ?? optional($usersById->get($k))->username
               ?? optional($usersByUuid->get($k))->name
               ?? optional($usersByUuid->get($k))->username
               ?? null;
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

    // 5) RESPONSE
    return response()->json([
        'success' => true,
        'data'    => [
            'usulan'      => $data->toArray(),
            'perencanaan' => $perencanaanList,
            'pembangunan' => $pembangunanList,
            'pengawasan'  => $pengawasanList,
        ],
    ]);
}


    /**
     * POST /permukiman/create
     * Create + pindahkan file dari TEMP → FINAL
     * Catatan: status_verifikasi_usulan auto = 0
     * Semua kolom file = ARRAY UUID
     */
    public function store(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $userId = (string) auth()->id();

        // Alias & normalisasi input file-array
        $this->applyAliases($request);
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $payload = $request->validate([
            'sumber_usulan'                  => ['required','string','max:255'],
            'jenis_usulan'                   => ['required','string','max:255'],
            'nama_pengusul'                  => ['required','string','max:255'],
            'no_kontak_pengusul'             => ['required','string','max:50'],
            'email'                          => ['required','email'],
            'instansi'                       => ['required','string','max:255'],
            'alamat_dusun_instansi'          => ['required','string','max:255'],
            'alamat_rt_instansi'             => ['required','string','max:10'],
            'alamat_rw_instansi'             => ['required','string','max:10'],
            'tanggal_usulan'                 => ['required','date'],
            'nama_pic'                       => ['required','string','max:255'],
            'no_kontak_pic'                  => ['required','string','max:50'],
            'status_tanah'                   => ['required','string','max:100'],
            'pesan_verifikasi'               => ['nullable','string','max:512'],

            // FILES (ARRAY UUID) – max 1 sesuai FormData
            'foto_sertifikat_status_tanah'   => ['required','array','min:1','max:1'],
            'foto_sertifikat_status_tanah.*' => ['uuid'],

            'panjang_usulan'                 => ['required','string','max:100'],
            'alamat_dusun_usulan'            => ['required','string','max:255'],
            'alamat_rt_usulan'               => ['required','string','max:10'],
            'alamat_rw_usulan'               => ['required','string','max:10'],
            'kecamatan'                      => ['required','string','max:100'],
            'kelurahan'                      => ['required','string','max:100'],
            'titik_lokasi'                   => ['required','string','max:255'],

            'foto_sta0'                      => ['required','array','min:1','max:1'],
            'foto_sta0.*'                    => ['uuid'],
            'foto_sta100'                    => ['required','array','min:1','max:1'],
            'foto_sta100.*'                  => ['uuid'],
            'surat_pemohonan'                => ['required','array','min:1','max:1'],
            'surat_pemohonan.*'              => ['uuid'],
        ]);

        // Set nilai otomatis
        $payload['status_verifikasi_usulan'] = 0;
        $payload['user_id']                  = $userId;

        // Pindahkan file dari TEMP → FINAL
        $allUuids = array_values(array_unique(array_merge(
            $payload['foto_sertifikat_status_tanah'],
            $payload['foto_sta0'],
            $payload['foto_sta100'],
            $payload['surat_pemohonan'],
        )));
        $this->moveTempToFinalUuids($allUuids, $userId);

        // Simpan record
        $data = Permukiman::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Permukiman berhasil dibuat dan file dipindahkan',
            'data'    => $data,
        ], 201);
    }

    /**
     * POST /permukiman/update/{id}
     * Partial update; kolom file: ARRAY UUID
     */
    public function update(Request $request, string $id)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = Permukiman::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $this->applyAliases($request);

        // Normalisasi semua file-array
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            'sumber_usulan'                 => 'sometimes|string|max:255',
            'jenis_usulan'                  => 'sometimes|string|max:255',
            'nama_pengusul'                 => 'sometimes|string|max:255',
            'no_kontak_pengusul'            => 'sometimes|string|max:50',
            'email'                         => 'sometimes|email',
            'instansi'                      => 'sometimes|string|max:255',
            'alamat_dusun_instansi'         => 'sometimes|string|max:255',
            'alamat_rt_instansi'            => 'sometimes|string|max:10',
            'alamat_rw_instansi'            => 'sometimes|string|max:10',
            'tanggal_usulan'                => 'sometimes|date',
            'nama_pic'                      => 'sometimes|string|max:255',
            'no_kontak_pic'                 => 'sometimes|string|max:50',
            'status_tanah'                  => 'sometimes|string|max:100',
            'panjang_usulan'                => 'sometimes|string|max:100',
            'alamat_dusun_usulan'           => 'sometimes|string|max:255',
            'alamat_rt_usulan'              => 'sometimes|string|max:10',
            'alamat_rw_usulan'              => 'sometimes|string|max:10',
            'kecamatan'                     => 'sometimes|string|max:100',
            'kelurahan'                     => 'sometimes|string|max:100',
            'titik_lokasi'                  => 'sometimes|string|max:255',
            'pesan_verifikasi'              => 'sometimes|nullable|string|max:512',

            // FILE ARRAYS (nullable → kalau null dikirim, abaikan di payload)
            'foto_sertifikat_status_tanah'   => 'sometimes|nullable|array|min:1|max:1',
            'foto_sertifikat_status_tanah.*' => 'uuid',
            'foto_sta0'                      => 'sometimes|nullable|array|min:1|max:1',
            'foto_sta0.*'                    => 'uuid',
            'foto_sta100'                    => 'sometimes|nullable|array|min:1|max:1',
            'foto_sta100.*'                  => 'uuid',
            'surat_pemohonan'                => 'sometimes|nullable|array|min:1|max:1',
            'surat_pemohonan.*'              => 'uuid',

            'status_verifikasi_usulan'       => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
        ]);

        // AUTO-CLEAR PESAN SAAT STATUS ≥ 4
        if (array_key_exists('status_verifikasi_usulan', $validated)
            && (int)$validated['status_verifikasi_usulan'] >= 4) {
            $validated['pesan_verifikasi'] = null;
        }

        // UUID baru yang harus dipindah
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f) && is_array($request->input($f))) {
                $incoming = $request->input($f);
                $existing = $item->getAttribute($f) ?? [];
                $diff     = array_diff($incoming, is_array($existing) ? $existing : []);
                if (!empty($diff)) {
                    $uuidsToMove = array_merge($uuidsToMove, $diff);
                }
            }
        }
        if (!empty($uuidsToMove)) {
            $this->moveTempToFinalUuids($uuidsToMove, (string) auth()->id());
        }

        // Payload update (jangan tulis kolom file kalau null)
        $updateData = $validated;
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $updateData) && is_null($updateData[$f])) {
                unset($updateData[$f]);
            }
        }

        // Catat field yang berubah
        $changedFields = [];
        foreach ($updateData as $key => $val) {
            if ($item->getAttribute($key) !== $val) {
                $changedFields[] = $key;
            }
        }

        if (!empty($updateData)) {
            $item->update($updateData);
        }

        $message = empty($changedFields)
            ? 'Tidak ada perubahan data'
            : 'Field Berikut Berhasil di Perbaharui: ' . implode(', ', $changedFields);

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * DELETE /permukiman/{id}
     */
    public function destroy(string $id)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        /** @var \App\Models\Permukiman|null $data */
        $data = \App\Models\Permukiman::where('id', $id)->first();
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        \DB::transaction(function () use ($id, $data) {
            // 1) Hapus seluruh Perencanaan yang menempel ke usulan ini
            \App\Models\Perencanaan::where('uuidUsulan', $id)->delete();

            // 2) Cabut UUID usulan ini dari semua row Pembangunan terkait
            $buildRows = \App\Models\Pembangunan::query()
                ->where(function($q) use ($id) {
                    $q->where('uuidUsulan', $id)
                      ->orWhereJsonContains('uuidUsulan', $id);
                })
                ->get();

            $needleLower = strtolower($id);

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

                // Filter keluar id target (case-insensitive)
                $after = collect($arr)
                    ->map(fn($v) => trim((string)$v))
                    ->filter(fn($v) => $v !== '' && strtolower($v) !== $needleLower)
                    ->values()
                    ->all();

                // Simpan balik (kosong → null)
                $b->uuidUsulan = $after ? $after : null;
                $b->save();
            }

            // 3) Hapus usulan Permukiman utamanya
            $data->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus; perencanaan dihapus dan UUID dicabut dari pembangunan.',
        ]);
    }

    // ====================== Helpers ======================

    /** Aliases camelCase → snake_case untuk field file-array & pesan_verifikasi */
    private function applyAliases(Request $request): void
    {
        $aliases = [
            'fotoSertifikatStatusTanah' => 'foto_sertifikat_status_tanah',
            'fotoSta0'                  => 'foto_sta0',
            'fotoSta100'                => 'foto_sta100',
            'suratPemohonan'            => 'surat_pemohonan',
            // proposalUsulan DIHAPUS karena field sudah tidak ada
            'pesanVerifikasi'           => 'pesan_verifikasi',
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
     * Normalisasi satu field array-UUID dari berbagai bentuk input.
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

    /** Ekstrak UUID dari string (v1–v7, toleran) */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /**
     * Pindahkan file dari TEMP → FINAL untuk daftar UUID (milik user yang sama).
     * Final path: permukiman_final/<basename_temp>
     */
    private function moveTempToFinalUuids(array $uuids, string $userId): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return;

        $temps = PermukimanUploadTemp::whereIn('uuid', $uuids)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('uuid');

        foreach ($uuids as $u) {
            $temp = $temps->get($u);
            if (!$temp) {
                // mungkin sudah final (reuse) → lewati
                continue;
            }

            $oldPath  = $temp->file_path;               // permukiman_temp/<hash>.<ext>
            $filename = basename($oldPath);
            $newPath  = 'permukiman_final/' . $filename;

            if (Storage::exists($oldPath)) {
                Storage::move($oldPath, $newPath);
            } elseif (!Storage::exists($newPath)) {
                continue;
            }

            PermukimanUpload::updateOrCreate(
                ['uuid' => $temp->uuid],
                ['user_id' => $userId, 'file_path' => $newPath]
            );

            $temp->delete();
        }
    }
}
