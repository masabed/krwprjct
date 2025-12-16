<?php

namespace App\Http\Controllers\Api\Psu;

use App\Http\Controllers\Controller;
use App\Models\PsuSerahTerima;
use App\Models\PSUUploadTemp;
use App\Models\PerumahanDb;
use App\Models\PSUUpload;
use App\Models\Perencanaan;
use App\Models\Pembangunan;
use App\Models\Pengawasan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PsuSerahTerimaController extends Controller
{
    /** Field file (array UUID) yang dikelola */
    private const FILE_FIELDS = [
        'dokumenIzinBangunan',
        'dokumenIzinPemanfaatan',
        'dokumenKondisi',
        'dokumenTeknis',
        'ktpPemohon',
        'aktaPerusahaan',
        'suratPermohonanPenyerahan',
        'dokumenSiteplan',
        'salinanSertifikat',
        // NOTE: noBASTPSU adalah STRING biasa, bukan file/UUID
    ];

    /** POST /api/psu/upload */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10512',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $uuid = (string) Str::uuid();

        // Simpan ke storage lokal (private) sebagai temp
        $path = $file->storeAs('psu_temp', "{$uuid}.{$ext}", 'local');

        $temp = PSUUploadTemp::create([
            'uuid'          => $uuid,
            'user_id'       => (string) $user->id,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime'          => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'uuid'    => $temp->uuid,
                'user_id' => $temp->user_id,
            ],
        ], 201);
    }

    /** POST /api/psu/serah-terima */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Normalisasi: file arrays (UUID) & jenisPSU (list string)
        foreach (self::FILE_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }
        $this->normalizeStringArrayField($request, 'jenisPSU');
        // noBASTPSU: string biasa → tidak dinormalisasi sebagai array
        // titikLokasi: string biasa → tidak dinormalisasi sebagai array

        $validated = $request->validate([
            'perumahanId'        => ['required', 'uuid'],

            // Lokasi (BARU)
            'titikLokasi'        => ['sometimes', 'nullable', 'string', 'max:255'],

            // Pemohon
            'tipePengaju'        => ['sometimes', 'string', 'max:100'],
            'namaPemohon'        => ['sometimes', 'string', 'max:255'],
            'nikPemohon'         => ['sometimes', 'string', 'max:100'],
            'noKontak'           => ['sometimes', 'string', 'max:100'],
            'email'              => ['sometimes', 'email', 'max:255'],

            // Developer
            'jenisDeveloper'     => ['sometimes', 'string', 'max:100'],
            'namaDeveloper'      => ['sometimes', 'string', 'max:255'],
            'alamatDeveloper'    => ['sometimes', 'string', 'max:500'],
            'rtDeveloper'        => ['sometimes', 'string', 'max:10'],
            'rwDeveloper'        => ['sometimes', 'string', 'max:10'],

            // Administratif
            'tanggalPengusulan'  => ['sometimes', 'date'],
            'tahapanPenyerahan'  => ['sometimes', 'string', 'max:100'],

            // jenisPSU = array of strings (JSON)
            'jenisPSU'           => ['sometimes', 'nullable', 'array', 'max:50'],
            'jenisPSU.*'         => ['string', 'max:150'],

            // noBASTPSU = STRING BIASA
            'noBASTPSU'          => ['sometimes', 'nullable', 'string', 'max:255'],

            'nomorSiteplan'      => ['sometimes', 'string', 'max:150'],
            'tanggalSiteplan'    => ['sometimes', 'date'],
            'noSuratPST'         => ['sometimes', 'string', 'max:150'],

            // Luasan
            'luasKeseluruhan'    => ['sometimes', 'string', 'max:50'],
            'luasRuangTerbangun' => ['sometimes', 'string', 'max:50'],
            'luasRuangTerbuka'   => ['sometimes', 'string', 'max:50'],

            // File UUID arrays
            'dokumenIzinBangunan'         => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenIzinBangunan.*'       => ['uuid'],
            'dokumenIzinPemanfaatan'      => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenIzinPemanfaatan.*'    => ['uuid'],
            'dokumenKondisi'              => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenKondisi.*'            => ['uuid'],
            'dokumenTeknis'               => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenTeknis.*'             => ['uuid'],
            'ktpPemohon'                  => ['sometimes', 'nullable', 'array', 'max:10'],
            'ktpPemohon.*'                => ['uuid'],
            'aktaPerusahaan'              => ['sometimes', 'nullable', 'array', 'max:10'],
            'aktaPerusahaan.*'            => ['uuid'],
            'suratPermohonanPenyerahan'   => ['sometimes', 'nullable', 'array', 'max:10'],
            'suratPermohonanPenyerahan.*' => ['uuid'],
            'dokumenSiteplan'             => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenSiteplan.*'           => ['uuid'],
            'salinanSertifikat'           => ['sometimes', 'nullable', 'array', 'max:10'],
            'salinanSertifikat.*'         => ['uuid'],

            // Verifikasi
            'pesan_verifikasi'            => ['sometimes', 'nullable', 'string', 'max:512'],
            'status_verifikasi_usulan'    => ['sometimes', 'integer', 'in:0,1,2,3,4'],
        ]);

        // Finalisasi UUID hanya untuk field file yang ada di payload
        $finalized = [];
        foreach (self::FILE_FIELDS as $f) {
            if (array_key_exists($f, $validated)) {
                $finalized[$f] = (is_array($validated[$f]) && count($validated[$f]) > 0)
                    ? $this->ensureFinalUploads($validated[$f], (string) $user->id, true)
                    : null; // biar null di DB (nullable)
            }
        }

        $payload = array_merge($validated, $finalized);
        $payload['status_verifikasi_usulan'] = $validated['status_verifikasi_usulan'] ?? 0;
        $payload['user_id']                  = (string) $user->id;

        $row = PsuSerahTerima::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Data PSU Serah Terima berhasil dibuat',
            'data'    => $row,
        ], 201);
    }

    /** POST/PUT/PATCH /api/psu/serah-terima/{id} */
    public function update(Request $request, ?string $id = null)
    {
        // Ambil id dari berbagai kemungkinan route param / body
        $id = $id
            ?? $request->route('id')
            ?? $request->route('psuId')
            ?? $request->route('psu_serah_terima')
            ?? $request->input('id');

        // Validasi id harus UUID
        if (!is_string($id) || !preg_match('/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/', $id)) {
            return response()->json([
                'success'     => false,
                'message'     => 'Parameter id tidak valid (harus UUID).',
                'received_id' => $id,
            ], 422);
        }

        $row = PsuSerahTerima::query()->where('id', strtolower($id))->first();
        if (!$row) {
            return response()->json([
                'success'      => false,
                'message'      => 'Data tidak ditemukan',
                'looked_up_id' => $id,
            ], 404);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Normalisasi input yang dikirim saja (file & jenisPSU)
        foreach (self::FILE_FIELDS as $f) {
            if ($request->has($f)) {
                $this->normalizeUuidArrayField($request, $f);
            }
        }
        if ($request->has('jenisPSU')) {
            $this->normalizeStringArrayField($request, 'jenisPSU');
        }
        // TIDAK menormalisasi noBASTPSU (string apa adanya)
        // TIDAK menormalisasi titikLokasi (string apa adanya)

        $validated = $request->validate([
            'perumahanId'        => ['sometimes', 'uuid'],

            // Lokasi (BARU)
            'titikLokasi'        => ['sometimes', 'nullable', 'string', 'max:255'],

            // Pemohon
            'tipePengaju'        => ['sometimes', 'string', 'max:100'],
            'namaPemohon'        => ['sometimes', 'string', 'max:255'],
            'nikPemohon'         => ['sometimes', 'string', 'max:100'],
            'noKontak'           => ['sometimes', 'string', 'max:100'],
            'email'              => ['sometimes', 'email', 'max:255'],

            // Developer
            'jenisDeveloper'     => ['sometimes', 'string', 'max:100'],
            'namaDeveloper'      => ['sometimes', 'string', 'max:255'],
            'alamatDeveloper'    => ['sometimes', 'string', 'max:500'],
            'rtDeveloper'        => ['sometimes', 'string', 'max:10'],
            'rwDeveloper'        => ['sometimes', 'string', 'max:10'],

            // Administratif
            'tanggalPengusulan'  => ['sometimes', 'date'],
            'tahapanPenyerahan'  => ['sometimes', 'string', 'max:100'],

            // jenisPSU array of strings (JSON)
            'jenisPSU'           => ['sometimes', 'nullable', 'array', 'max:50'],
            'jenisPSU.*'         => ['string', 'max:150'],

            // noBASTPSU = STRING BIASA
            'noBASTPSU'          => ['sometimes', 'nullable', 'string', 'max:255'],

            'nomorSiteplan'      => ['sometimes', 'string', 'max:150'],
            'tanggalSiteplan'    => ['sometimes', 'date'],
            'noSuratPST'         => ['sometimes', 'string', 'max:150'],

            // Luasan
            'luasKeseluruhan'    => ['sometimes', 'string', 'max:50'],
            'luasRuangTerbangun' => ['sometimes', 'string', 'max:50'],
            'luasRuangTerbuka'   => ['sometimes', 'string', 'max:50'],

            // File UUID arrays
            'dokumenIzinBangunan'         => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenIzinBangunan.*'       => ['uuid'],
            'dokumenIzinPemanfaatan'      => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenIzinPemanfaatan.*'    => ['uuid'],
            'dokumenKondisi'              => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenKondisi.*'            => ['uuid'],
            'dokumenTeknis'               => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenTeknis.*'             => ['uuid'],
            'ktpPemohon'                  => ['sometimes', 'nullable', 'array', 'max:10'],
            'ktpPemohon.*'                => ['uuid'],
            'aktaPerusahaan'              => ['sometimes', 'nullable', 'array', 'max:10'],
            'aktaPerusahaan.*'            => ['uuid'],
            'suratPermohonanPenyerahan'   => ['sometimes', 'nullable', 'array', 'max:10'],
            'suratPermohonanPenyerahan.*' => ['uuid'],
            'dokumenSiteplan'             => ['sometimes', 'nullable', 'array', 'max:10'],
            'dokumenSiteplan.*'           => ['uuid'],
            'salinanSertifikat'           => ['sometimes', 'nullable', 'array', 'max:10'],
            'salinanSertifikat.*'         => ['uuid'],

            // Verifikasi
            'status_verifikasi_usulan'    => ['sometimes', 'integer', 'in:0,1,2,3,4,5,6,7,8,9'],
            'pesan_verifikasi'            => ['sometimes', 'nullable', 'string', 'max:512'],
        ]);

        // Proses file arrays yang dikirim
        foreach (self::FILE_FIELDS as $f) {
            if (!array_key_exists($f, $validated)) {
                continue;
            }

            // null → abaikan (tidak overwrite kolom file)
            if (is_null($validated[$f])) {
                unset($validated[$f]);
                continue;
            }

            // Pastikan file FINAL (pindahkan dari temp jika perlu) + dedup
            $incomingFinal = (is_array($validated[$f]) && count($validated[$f]) > 0)
                ? $this->ensureFinalUploads($validated[$f], (string) $user->id, true)
                : [];

            // Hapus file FINAL yang tidak lagi ada
            $old     = $row->getAttribute($f) ?? [];
            $old     = is_array($old) ? $old : [];
            $removed = array_values(array_diff($old, $incomingFinal));
            if (!empty($removed)) {
                $this->deleteFinalUploads($removed);
            }

            $validated[$f] = $incomingFinal ?: null;
        }

        // Isi field biasa dulu
        $row->fill($validated);

        // AUTO-CLEAR: setiap kali status ≥ 4, kosongkan pesan_verifikasi
        if ($request->has('status_verifikasi_usulan')
            && (int) $request->input('status_verifikasi_usulan') >= 4) {
            $row->pesan_verifikasi = null;
        }

        // Hitung perubahan SETELAH auto-clear
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

    /** GET /api/psu/serah-terima/{id} */
   public function show(string $id)
{
    // 0) Auth
    $auth = auth()->user();
    if (!$auth) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated',
        ], 401);
    }

    // 1) Ambil data utama PSU Serah Terima
    $data = PsuSerahTerima::find($id);
    if (!$data) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan',
        ], 404);
    }

    // 1a) Role dasar
    $role    = strtolower((string) ($auth->role ?? ''));
    $isOwner = (string) ($data->user_id ?? '') === (string) $auth->id;
    $isPriv  = in_array($role, ['admin','admin_bidang','operator','pengawas'], true);

    // 1b) Normalisasi kecamatan/kelurahan user (kalau ada di profil)
    $userKecamatan = Str::lower(trim((string) ($auth->kecamatan ?? '')));
    $userKelurahan = Str::lower(trim((string) ($auth->kelurahan ?? '')));

    $allowed = false;

    if ($isPriv || $isOwner) {
        // Admin / admin_bidang / operator / pengawas ATAU owner → selalu boleh
        $allowed = true;
    } else {
        // User biasa → cek kecamatan/kelurahan lewat PerumahanDb
        if ($userKecamatan !== '') {
            $perumahanId = $data->perumahanId ?? null;
            if ($perumahanId) {
                $perumahan = PerumahanDb::find($perumahanId);

                if ($perumahan) {
                    $perKec = Str::lower(trim((string) ($perumahan->kecamatan ?? '')));
                    $perKel = Str::lower(trim((string) ($perumahan->kelurahan ?? '')));

                    if ($perKec !== '') {
                        if ($userKelurahan === '') {
                            // User tidak punya kelurahan → boleh semua kelurahan di kecamatan tsb
                            $allowed = ($perKec === $userKecamatan);
                        } else {
                            // User punya kelurahan → harus match kecamatan & kelurahan
                            $allowed = ($perKec === $userKecamatan && $perKel === $userKelurahan);
                        }
                    }
                }
            }
        }
    }

    if (!$allowed) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized',
        ], 403);
    }

    // 2) Perencanaan untuk usulan ini (uuidUsulan = id serah terima)
    $perencanaanRows = Perencanaan::where('uuidUsulan', $id)
        ->orderBy('created_at', 'desc')
        ->get();

    $perencanaanList = $perencanaanRows->map(function ($row) {
        return [
            'uuidPerencanaan' => (string) $row->id,
            'uuidUsulan'      => (string) $row->uuidUsulan,
            'nilaiHPS'        => $row->nilaiHPS,
            'lembarKontrol'   => $row->lembarKontrol,
            'dokumentasi'     => $row->dokumentasi ?? [],  // aman kalau null
            'catatanSurvey'   => $row->catatanSurvey,
            'created_at'      => $row->created_at,
            'updated_at'      => $row->updated_at,
        ];
    })->values();

    return response()->json([
        'success' => true,
        'data'    => [
            'usulan'      => $data->toArray(),
            'perencanaan' => $perencanaanList,
        ],
    ]);
}


    /** GET /api/psu/serah-terima */
    public function index(Request $request)
{
    $user = $request->user();
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated',
        ], 401);
    }

    $role   = strtolower((string) ($user->role ?? ''));
    // Yang boleh lihat SEMUA data PSU Serah Terima:
    // admin, admin_bidang, operator, pengawas
    $isPriv = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);

    // Normalisasi kecamatan & kelurahan user → lowercase + trim
    $userKecamatan = Str::lower(trim((string) ($user->kecamatan ?? '')));
    $userKelurahan = Str::lower(trim((string) ($user->kelurahan ?? '')));

    $q = PsuSerahTerima::query()->latest();

    if ($isPriv) {
        // Privileged roles boleh lihat semua;
        // kalau mau hanya data miliknya, gunakan ?mine=1
        if ($request->boolean('mine')) {
            $q->where('user_id', (string) $user->id);
        }
    } else {
        // User biasa

        if ($userKecamatan === '') {
            // Tidak ada info kecamatan di profil → fallback: hanya data milik sendiri
            $q->where('user_id', (string) $user->id);
        } else {
            // Cari perumahan di kecamatan (dan kelurahan kalau ada) user
            $perumahanQuery = PerumahanDb::query()
                ->whereRaw('LOWER(kecamatan) = ?', [$userKecamatan]);

            if ($userKelurahan !== '') {
                // Kalau user punya kelurahan, strict ke kelurahan yg sama
                $perumahanQuery->whereRaw('LOWER(kelurahan) = ?', [$userKelurahan]);
            }

            $allowedPerumahanIds = $perumahanQuery
                ->pluck('id')
                ->filter(fn ($v) => !is_null($v))
                ->unique()
                ->values();

            $q->where(function ($sub) use ($user, $allowedPerumahanIds) {
                // 1) selalu boleh data milik sendiri
                $sub->where('user_id', (string) $user->id);

                // 2) plus data yang perumahanId-nya ada di perumahans_db yang sesuai
                if ($allowedPerumahanIds->isNotEmpty()) {
                    $sub->orWhereIn('perumahanId', $allowedPerumahanIds);
                }
            });
        }
    }

    // Filter opsional
    if ($request->has('status_verifikasi_usulan')) {
        $q->where(
            'status_verifikasi_usulan',
            (int) $request->query('status_verifikasi_usulan')
        );
    }

    if ($request->has('perumahanId')) {
        $q->where('perumahanId', $request->query('perumahanId'));
    }

    return response()->json([
        'success' => true,
        'data'    => $q->get(),
    ]);
}


    /** DELETE /api/psu/serah-terima/{id} */
    public function destroy(string $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $row = PsuSerahTerima::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Hanya admin/admin_bidang atau pemilik data yang boleh hapus
        $role    = strtolower((string) ($user->role ?? ''));
        $isAdmin = in_array($role, ['admin', 'admin_bidang'], true);
        if (!$isAdmin && (string) $row->user_id !== (string) $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Hapus file fisik FINAL untuk semua field file array
        foreach (self::FILE_FIELDS as $f) {
            $uuids = $row->getAttribute($f) ?? [];
            if (is_array($uuids) && !empty($uuids)) {
                $this->deleteFinalUploads($uuids);
            }
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus',
        ]);
    }

    // ================== HELPERS ==================

    private function normalizeUuidArrayField(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);

        if ($val === '' || $val === null || (is_string($val) && strtolower(trim($val)) === 'null')) {
            $request->merge([$field => null]);
            return;
        }

        if (is_array($val)) {
            $uuids = $this->filterUuidArray($val);
            $request->merge([$field => $uuids ?: null]);
            return;
        }

        if (is_string($val)) {
            $t = trim($val);

            // JSON string "[...]" 
            if ($t !== '' && $t[0] === '[') {
                $arr   = json_decode($t, true);
                $uuids = is_array($arr) ? $this->filterUuidArray($arr) : [];
                $request->merge([$field => $uuids ?: null]);
                return;
            }

            // "uuid1,uuid2,..."
            if (str_contains($t, ',')) {
                $parts = array_map('trim', explode(',', $t));
                $uuids = $this->filterUuidArray($parts);
                $request->merge([$field => $uuids ?: null]);
                return;
            }

            // Single UUID string
            $u = $this->extractUuid($t);
            $request->merge([$field => $u ? [$u] : null]);
        }
    }

    private function normalizeStringArrayField(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);

        if ($val === null || (is_string($val) && strtolower(trim($val)) === 'null') || $val === '') {
            $request->merge([$field => null]);
            return;
        }

        if (is_array($val)) {
            $arr = array_values(array_filter(
                array_map(fn($v) => trim((string) $v), $val),
                fn($v) => $v !== ''
            ));
            $request->merge([$field => $arr ?: null]);
            return;
        }

        if (is_string($val)) {
            $t = trim($val);

            // JSON string
            if ($t !== '' && $t[0] === '[') {
                $arr = json_decode($t, true);
                $arr = is_array($arr) ? $arr : [];
                $arr = array_values(array_filter(
                    array_map(fn($v) => trim((string) $v), $arr),
                    fn($v) => $v !== ''
                ));
                $request->merge([$field => $arr ?: null]);
                return;
            }

            // Comma separated
            if (str_contains($t, ',')) {
                $parts = array_map('trim', explode(',', $t));
                $arr   = array_values(array_filter($parts, fn($v) => $v !== ''));
                $request->merge([$field => $arr ?: null]);
                return;
            }

            // Single string
            $request->merge([$field => [$t]]);
        }
    }

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
     * Pastikan semua UUID mengarah ke file FINAL (pindah dari temp jika perlu).
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
            // Sudah FINAL
            if ($final->has($u)) {
                $row = $final->get($u);
                if (!$disk->exists($row->file_path)) {
                    $invalid[] = $u;
                    continue;
                }
                $result[] = $u;
                continue;
            }

            // Masih TEMP → pindahkan ke FINAL
            if ($temps->has($u)) {
                $temp = $temps->get($u);
                if (!$disk->exists($temp->file_path)) {
                    $invalid[] = $u;
                    continue;
                }

                $filename = basename($temp->file_path); // {uuid}.ext
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

            // Tidak ketemu di FINAL maupun TEMP
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

    /**
     * Hapus file FINAL (storage + row PSUUpload).
     */
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
