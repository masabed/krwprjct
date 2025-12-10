<?php

namespace App\Http\Controllers\Api\PSU;

use App\Http\Controllers\Controller;
use App\Models\PSUUsulanFisikTPU;
use App\Models\PSUUploadTemp;
use App\Models\PSUUpload;
use App\Models\Perencanaan;
use App\Models\Pembangunan;
use App\Models\Pengawasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PSUUsulanFisikTPUController extends Controller
{
    private const FILE_ARRAY_FIELDS = [
        'suratPermohonanUsulanFisik',
        'sertifikatStatusTanah',   // boleh null
        'dokumentasiEksisting',
    ];

    /** GET /api/psu/usulan/tpu */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Admin & admin_bidang bisa lihat semua
        $role    = strtolower((string) ($user->role ?? ''));
        $isAdmin = in_array($role, ['admin', 'admin_bidang'], true);

        $q = PSUUsulanFisikTPU::query()->latest();

        // User biasa hanya melihat data miliknya
        if (!$isAdmin) {
            $q->where('user_id', (string) $user->id);
        }

        // Filter opsional
        if ($request->has('perumahanId')) {
            $q->where('perumahanId', $request->query('perumahanId'));
        }
        if ($request->has('status_verifikasi_usulan')) {
            $q->where('status_verifikasi_usulan', (int) $request->query('status_verifikasi_usulan'));
        }

        return response()->json(['success' => true, 'data' => $q->get()]);
    }

    /** POST /api/psu/usulan/tpu/create */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Normalisasi semua field file array → array berisi UUID saja
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }

        $payload = $request->validate([
            // Keterangan permohonan
            'tanggalPermohonan'        => 'required|string|max:30',
            'nomorSuratPermohonan'     => 'required|string|max:255',

            // Sumber usulan & Data pemohon
            'sumberUsulan'             => 'required|string|max:100',
            'namaAspirator'            => 'required|string|max:150',
            'noKontakAspirator'        => 'required|string|max:50',
            'namaPIC'                  => 'required|string|max:150',
            'noKontakPIC'              => 'required|string|max:50',

            // Rincian usulan
            'jenisUsulan'              => 'required|string|max:100',
            'uraianMasalah'            => 'sometimes|nullable|string',

            // Dimensi/eksisting
            'luasTPUEksisting'         => 'sometimes|nullable|string|max:100',

            // Lokasi
            'alamatUsulan'             => 'required|string|max:500',
            'rtUsulan'                 => 'sometimes|nullable|string|max:10',
            'rwUsulan'                 => 'sometimes|nullable|string|max:10',
            'kecamatanUsulan'          => 'required|string|max:150',
            'kelurahanUsulan'          => 'required|string|max:150',
            'titikLokasiUsulan'        => 'sometimes|nullable|string|max:255',
            'jenisLokasi'              => 'sometimes|nullable|string|max:100',

            // Lokasi tambahan
            'perumahanId'              => 'sometimes|nullable|uuid',
            'statusTanah'              => 'sometimes|nullable|string|max:150',

            // Dokumen (array UUID) — SESUAI FormData: tidak ada proposalUsulanFisik
            'suratPermohonanUsulanFisik'   => 'required|array|min:1|max:20',
            'suratPermohonanUsulanFisik.*' => 'uuid',
            'sertifikatStatusTanah'        => 'sometimes|nullable|array|max:20',
            'sertifikatStatusTanah.*'      => 'uuid',
            'dokumentasiEksisting'         => 'required|array|min:1|max:30',
            'dokumentasiEksisting.*'       => 'uuid',

            // Opsional status
            'status_verifikasi_usulan'      => 'sometimes|integer|in:0,1,2,3,4',
            'pesan_verifikasi'              => 'sometimes|nullable|string|max:512',
        ]);

        // Create row (PK uuid auto dari HasUuids)
        $row = PSUUsulanFisikTPU::create([
            ...$payload,
            'user_id' => (string) $user->id,
        ]);

        // Pindahkan semua UUID dari psu_temp → psu_final (hanya UUID yang ada di payload)
        $uuids = array_values(array_unique(array_merge(
            $payload['suratPermohonanUsulanFisik'],
            $payload['dokumentasiEksisting'],
            $payload['sertifikatStatusTanah'] ?? []
        )));
        $this->moveTempsToFinal($uuids, (string) $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Usulan fisik TPU berhasil disimpan',
            'uuid'    => $row->uuid,
            'data'    => $row,
        ], 201);
    }

    /** POST /api/psu/usulan/tpu/update/{uuid} (PUT/PATCH juga bisa diarahkan ke sini) */
    public function update(Request $request, string $uuid)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $item = PSUUsulanFisikTPU::where('uuid', $uuid)->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f)) $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            // Semua optional di update
            'tanggalPermohonan'        => 'sometimes|string|max:30',
            'nomorSuratPermohonan'     => 'sometimes|string|max:255',
            'sumberUsulan'             => 'sometimes|string|max:100',
            'namaAspirator'            => 'sometimes|string|max:150',
            'noKontakAspirator'        => 'sometimes|string|max:50',
            'namaPIC'                  => 'sometimes|string|max:150',
            'noKontakPIC'              => 'sometimes|string|max:50',
            'jenisUsulan'              => 'sometimes|string|max:100',
            'uraianMasalah'            => 'sometimes|nullable|string',
            'luasTPUEksisting'         => 'sometimes|nullable|string|max:100',
            'alamatUsulan'             => 'sometimes|string|max:500',
            'rtUsulan'                 => 'sometimes|nullable|string|max:10',
            'rwUsulan'                 => 'sometimes|nullable|string|max:10',
            'kecamatanUsulan'          => 'sometimes|string|max:150',
            'kelurahanUsulan'          => 'sometimes|string|max:150',
            'titikLokasiUsulan'        => 'sometimes|nullable|string|max:255',
            'jenisLokasi'              => 'sometimes|nullable|string|max:100',
            'perumahanId'              => 'sometimes|nullable|uuid',
            'statusTanah'              => 'sometimes|nullable|string|max:150',

            // FILE arrays (nullable → null = abaikan)
            'suratPermohonanUsulanFisik'   => 'sometimes|nullable|array|min:1|max:20',
            'suratPermohonanUsulanFisik.*' => 'uuid',
            'sertifikatStatusTanah'        => 'sometimes|nullable|array|max:20',
            'sertifikatStatusTanah.*'      => 'uuid',
            'dokumentasiEksisting'         => 'sometimes|nullable|array|min:1|max:30',
            'dokumentasiEksisting.*'       => 'uuid',

            'status_verifikasi_usulan'     => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
            'pesan_verifikasi'             => 'sometimes|nullable|string|max:512',
        ]);

        // ====== AUTO-CLEAR PESAN SAAT STATUS NAIK KE ≥ 4 ======
        if (array_key_exists('status_verifikasi_usulan', $validated)
            && (int)$validated['status_verifikasi_usulan'] >= 4
            && (int)($item->status_verifikasi_usulan ?? -1) < 4) {
            $validated['pesan_verifikasi'] = null;
        }
        // ======================================================

        // Kumpulkan UUID yang baru muncul (untuk dipindahkan temp->final)
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if (!array_key_exists($f, $validated)) continue;
            if (is_null($validated[$f])) { unset($validated[$f]); continue; } // null → abaikan

            $incoming = $validated[$f] ?? [];
            $existing = $item->getAttribute($f) ?? [];
            $diff = array_diff($incoming, is_array($existing) ? $existing : []);
            if (!empty($diff)) {
                $uuidsToMove = array_merge($uuidsToMove, $diff);
            }
        }
        if (!empty($uuidsToMove)) {
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string) $user->id);
        }

        $item->fill($validated);
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

    /** GET /api/psu/usulan/tpu/{uuid}  (+ tarik daftar perencanaan terkait uuidUsulan ini) */
    public function show(string $uuid)
    {
        // 0) Auth
        $auth = auth()->user();
        if (!$auth) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // 1) Usulan TPU
        $row = PSUUsulanFisikTPU::where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // 1b) Access control
        $role    = strtolower((string) ($auth->role ?? ''));
        $isOwner = (string) ($row->user_id ?? '') === (string) $auth->id;
        $isPriv  = in_array($role, ['admin','admin_bidang','pengawas'], true);
        if (!$isPriv && !$isOwner) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // 2) Perencanaan
        $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)
            ->orderBy('created_at', 'desc')
            ->get();

        $perencanaanList = $perencanaanRows->map(function ($r) {
            return [
                'uuidPerencanaan' => (string) $r->id,
                'uuidUsulan'      => (string) $r->uuidUsulan,
                'nilaiHPS'        => $r->nilaiHPS,
                'lembarKontrol'   => $r->lembarKontrol,
                'catatanSurvey'   => $r->catatanSurvey,
                'created_at'      => $r->created_at,
                'updated_at'      => $r->updated_at,
            ];
        })->values();

        // 3) Pembangunan (support string/JSON array)
        $pembangunanRows = Pembangunan::query()
            ->where(function($q) use ($uuid) {
                $q->where('uuidUsulan', $uuid)
                  ->orWhereJsonContains('uuidUsulan', $uuid);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // 3a) Resolve nama pengawas lapangan (by id/uuid)
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

        // 3b) Bentuk list pembangunan + HITUNG per-row uuidUsulan_count dari list uuidUsulan
        $pembangunanList = $pembangunanRows->map(function ($b) use ($usersById, $usersByUuid) {
            // Normalisasi uuidUsulan -> selalu array
            $uuList = [];
            $uuRaw  = $b->uuidUsulan;

            if (is_array($uuRaw)) {
                $uuList = $uuRaw;
            } elseif (is_string($uuRaw)) {
                $t = trim($uuRaw);
                if ($t !== '' && (function_exists('str_starts_with') ? str_starts_with($t,'[') : substr($t,0,1)==='[')) {
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

            // Nama pengawas
            $key  = (string) ($b->pengawasLapangan ?? '');
            $name = null;
            if ($key !== '') {
                $u = $usersById->get($key) ?? $usersByUuid->get($key);
                $name = $u->name ?? $u->username ?? null;
            }

            return [
                'uuidPembangunan'       => (string) $b->id,
                'uuidUsulan'            => $uuList,            // ← sudah array
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
                'uuidUsulan_count'      => $uuidUsulanCount,   // ← per-row
                'created_at'            => $b->created_at,
                'updated_at'            => $b->updated_at,
            ];
        })->values();

        // 4) PENGAWASAN terkait (uuidUsulan sama) + role filter
        $canSeeAllPengawasan = in_array($role, ['admin','admin_bidang','pengawas'], true) || $isOwner;

        $pengawasanRows = \App\Models\Pengawasan::query()
            ->where('uuidUsulan', $uuid)
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
                    ->select('id','name','username')
                    ->whereIn('id', $pengawasCatatanKeys)
                    ->get()
                    ->keyBy(fn($u) => (string) $u->id);
                $usersById = $usersById->merge($addById);
            } catch (\Throwable $e) {}

            try {
                $userTable = (new \App\Models\User)->getTable();
                if (\Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid')) {
                    $addByUuid = \App\Models\User::query()
                        ->select('uuid','name','username')
                        ->whereIn('uuid', $pengawasCatatanKeys)
                        ->get()
                        ->keyBy(fn($u) => (string) $u->uuid);
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

    /** DELETE /api/psu/usulan/tpu/{uuid} */
    public function destroy(string $uuid)
    {
        /** @var \App\Models\PSUUsulanFisikTPU|null $row */
        $row = \App\Models\PSUUsulanFisikTPU::where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        \DB::transaction(function () use ($uuid, $row) {
            // 1) Hapus semua Perencanaan yang menempel ke usulan ini
            \App\Models\Perencanaan::where('uuidUsulan', $uuid)->delete();

            // 2) Cabut UUID usulan ini dari semua baris Pembangunan terkait
            $buildRows = \App\Models\Pembangunan::query()
                ->where('uuidUsulan', $uuid)                 // legacy string
                ->orWhereJsonContains('uuidUsulan', $uuid)   // JSON array
                ->get();

            foreach ($buildRows as $b) {
                $raw = $b->getAttribute('uuidUsulan');

                // Normalisasi ke array (tahan legacy & JSON-string)
                if (is_array($raw)) {
                    $arr = $raw;
                } elseif (is_string($raw)) {
                    $t = trim($raw);
                    if ($t !== '' && (function_exists('str_starts_with') ? str_starts_with($t, '[') : $t[0] === '[')) {
                        $dec = json_decode($t, true);
                        $arr = is_array($dec) ? $dec : [];
                    } elseif ($t !== '') {
                        $arr = [$t];
                    } else {
                        $arr = [];
                    }
                } else {
                    $arr = [];
                }

                // Buang $uuid dari list
                $after = collect($arr)
                    ->map(fn($v) => trim((string)$v))
                    ->filter(fn($v) => $v !== '' && $v !== $uuid)
                    ->values()
                    ->all();

                // Simpan kembali (kosong → null biar rapi)
                $b->uuidUsulan = $after ?: null;
                $b->save();
            }

            // (Opsional) kalau ingin sekalian bersihkan catatan pengawasan:
            // \App\Models\Pengawasan::where('uuidUsulan', $uuid)->delete();

            // 3) Hapus usulan utama
            $row->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus; perencanaan dihapus dan UUID dicabut dari pembangunan.',
        ]);
    }

    // ================= Helpers =================

    /** Normalisasi field array-UUID dari berbagai bentuk input */
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

    /** Ekstrak UUID dari string/path */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /** Pindahkan daftar UUID dari psu_temp → psu_final untuk user tertentu */
    private function moveTempsToFinal(array $uuids, string $userId): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return;

        $temps = PSUUploadTemp::whereIn('uuid', $uuids)
            ->where('user_id', $userId)
            ->get()->keyBy('uuid');

        foreach ($uuids as $u) {
            $temp = $temps->get($u);
            if (!$temp) continue; // mungkin sudah ada di FINAL

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
