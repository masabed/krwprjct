<?php

namespace App\Http\Controllers\Api\Psu;

use App\Http\Controllers\Controller;
use App\Models\TpuSerahTerima;
use App\Models\PSUUploadTemp;
use App\Models\PSUUpload;
use App\Models\Perencanaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TpuSerahTerimaController extends Controller
{
    /** Field dokumen (array UUID) yang dikelola */
    private const FILE_FIELDS = [
        'ktpPemohon',
        'aktaPerusahaan',
        'suratPermohonan',
        'suratPernyataan',
        'suratKeteranganDesa',
        'suratIzinLingkungan',
        'suratPelepasan',
        'sertifikatHAT',
        'pertekBPN',
        'suratKeteranganLokasi', // max 2
    ];

    /**
     * POST /api/psu/tpu/upload
     * Upload satu file ke TEMP (PSUUploadTemp) → balikin UUID temp.
     */
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

    /**
     * POST /api/psu/tpu/serah-terima
     * Buat data serah terima TPU baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Normalisasi: file arrays (UUID) & karakterTPU (list string)
        foreach (self::FILE_FIELDS as $f) {
            $this->normalizeUuidArrayField($request, $f);
        }
        $this->normalizeStringArrayField($request, 'karakterTPU');

        $validated = $request->validate([
            'perumahanId'        => ['required', 'uuid'],

            // Pemohon
            'tipePengaju'        => ['sometimes', 'string', 'max:100'],
            'namaPemohon'        => ['sometimes', 'string', 'max:255'],
            'nikPemohon'         => ['sometimes', 'string', 'max:100'],

            // Developer
            'jenisDeveloper'     => ['sometimes', 'string', 'max:100'],
            'namaDeveloper'      => ['sometimes', 'string', 'max:255'],
            'alamatDeveloper'    => ['sometimes', 'string', 'max:500'],
            'rtDeveloper'        => ['sometimes', 'string', 'max:10'],
            'rwDeveloper'        => ['sometimes', 'string', 'max:10'],

            // Kontak
            'noKontak'           => ['sometimes', 'string', 'max:100'],
            'email'              => ['sometimes', 'email', 'max:255'],

            // Administratif
            'tanggalPengusulan'  => ['sometimes', 'date'],
            'noSuratPST'         => ['sometimes', 'string', 'max:150'],

            // Info lokasi & karakter
            'lokasiSama'         => ['sometimes', 'string', 'max:100'],
            'namaTPU'            => ['sometimes', 'string', 'max:255'],
            'jenisTPU'           => ['sometimes', 'string', 'max:150'],
            'statusTanah'        => ['sometimes', 'string', 'max:150'],
            'karakterTPU'        => ['sometimes', 'nullable', 'array', 'max:20'],
            'karakterTPU.*'      => ['string', 'max:255'],

            'aksesJalan'         => ['sometimes', 'string', 'max:255'],
            'lokasiBerdekatan'   => ['sometimes', 'string', 'max:100'],

            'alamatTPU'          => ['sometimes', 'string', 'max:500'],
            'rtTPU'              => ['sometimes', 'string', 'max:10'],
            'rwTPU'              => ['sometimes', 'string', 'max:10'],
            'kecamatanTPU'       => ['sometimes', 'string', 'max:100'],
            'kelurahanTPU'       => ['sometimes', 'string', 'max:100'],
            'titikLokasi'        => ['sometimes', 'string', 'max:255'],

            // Dokumen UUID arrays
            'ktpPemohon'            => ['sometimes', 'nullable', 'array', 'max:5'],
            'ktpPemohon.*'          => ['uuid'],
            'aktaPerusahaan'        => ['sometimes', 'nullable', 'array', 'max:5'],
            'aktaPerusahaan.*'      => ['uuid'],
            'suratPermohonan'       => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratPermohonan.*'     => ['uuid'],
            'suratPernyataan'       => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratPernyataan.*'     => ['uuid'],
            'suratKeteranganDesa'   => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratKeteranganDesa.*' => ['uuid'],
            'suratIzinLingkungan'   => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratIzinLingkungan.*' => ['uuid'],
            'suratPelepasan'        => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratPelepasan.*'      => ['uuid'],
            'sertifikatHAT'         => ['sometimes', 'nullable', 'array', 'max:5'],
            'sertifikatHAT.*'       => ['uuid'],
            'pertekBPN'             => ['sometimes', 'nullable', 'array', 'max:5'],
            'pertekBPN.*'           => ['uuid'],
            'suratKeteranganLokasi' => ['sometimes', 'nullable', 'array', 'max:2'],
            'suratKeteranganLokasi.*'=> ['uuid'],

            // Verifikasi
            'status_verifikasi_usulan' => ['sometimes', 'integer', 'in:0,1,2,3,4,5,6,7,8,9'],
            'pesan_verifikasi'         => ['sometimes', 'nullable', 'string', 'max:512'],

            // noBASTTPU akan diisi belakangan
            'noBASTTPU'               => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        // Finalisasi dokumen → FINAL
        $finalized = [];
        foreach (self::FILE_FIELDS as $f) {
            if (array_key_exists($f, $validated)) {
                $arr = $validated[$f];
                $finalized[$f] = (is_array($arr) && count($arr) > 0)
                    ? $this->ensureFinalUploads($arr, (string) $user->id, true)
                    : null;
            }
        }

        $payload = array_merge($validated, $finalized);
        $payload['status_verifikasi_usulan'] = $validated['status_verifikasi_usulan'] ?? 0;
        $payload['user_id']                  = (string) $user->id;

        $row = TpuSerahTerima::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Data Serah Terima TPU berhasil dibuat',
            'data'    => $row,
        ], 201);
    }

    /**
     * POST/PUT/PATCH /api/psu/tpu/serah-terima/{id}
     * Update partial (mirip PsuSerahTerimaController::update).
     */
    public function update(Request $request, ?string $id = null)
    {
        $id = $id
            ?? $request->route('id')
            ?? $request->input('id');

        if (!is_string($id) ||
            !preg_match('/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/', $id)) {
            return response()->json([
                'success'     => false,
                'message'     => 'Parameter id tidak valid (harus UUID).',
                'received_id' => $id,
            ], 422);
        }

        $row = TpuSerahTerima::query()->where('id', strtolower($id))->first();
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

        // Normalisasi input yang dikirim saja
        foreach (self::FILE_FIELDS as $f) {
            if ($request->has($f)) {
                $this->normalizeUuidArrayField($request, $f);
            }
        }
        if ($request->has('karakterTPU')) {
            $this->normalizeStringArrayField($request, 'karakterTPU');
        }

        $validated = $request->validate([
            'perumahanId'        => ['sometimes', 'uuid'],

            'tipePengaju'        => ['sometimes', 'string', 'max:100'],
            'namaPemohon'        => ['sometimes', 'string', 'max:255'],
            'nikPemohon'         => ['sometimes', 'string', 'max:100'],

            'jenisDeveloper'     => ['sometimes', 'string', 'max:100'],
            'namaDeveloper'      => ['sometimes', 'string', 'max:255'],
            'alamatDeveloper'    => ['sometimes', 'string', 'max:500'],
            'rtDeveloper'        => ['sometimes', 'string', 'max:10'],
            'rwDeveloper'        => ['sometimes', 'string', 'max:10'],

            'noKontak'           => ['sometimes', 'string', 'max:100'],
            'email'              => ['sometimes', 'email', 'max:255'],

            'tanggalPengusulan'  => ['sometimes', 'date'],
            'noSuratPST'         => ['sometimes', 'string', 'max:150'],

            'lokasiSama'         => ['sometimes', 'string', 'max:100'],
            'namaTPU'            => ['sometimes', 'string', 'max:255'],
            'jenisTPU'           => ['sometimes', 'string', 'max:150'],
            'statusTanah'        => ['sometimes', 'string', 'max:150'],
            'karakterTPU'        => ['sometimes', 'nullable', 'array', 'max:20'],
            'karakterTPU.*'      => ['string', 'max:255'],

            'aksesJalan'         => ['sometimes', 'string', 'max:255'],
            'lokasiBerdekatan'   => ['sometimes', 'string', 'max:100'],

            'alamatTPU'          => ['sometimes', 'string', 'max:500'],
            'rtTPU'              => ['sometimes', 'string', 'max:10'],
            'rwTPU'              => ['sometimes', 'string', 'max:10'],
            'kecamatanTPU'       => ['sometimes', 'string', 'max:100'],
            'kelurahanTPU'       => ['sometimes', 'string', 'max:100'],
            'titikLokasi'        => ['sometimes', 'string', 'max:255'],

            // Dokumen
            'ktpPemohon'            => ['sometimes', 'nullable', 'array', 'max:5'],
            'ktpPemohon.*'          => ['uuid'],
            'aktaPerusahaan'        => ['sometimes', 'nullable', 'array', 'max:5'],
            'aktaPerusahaan.*'      => ['uuid'],
            'suratPermohonan'       => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratPermohonan.*'     => ['uuid'],
            'suratPernyataan'       => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratPernyataan.*'     => ['uuid'],
            'suratKeteranganDesa'   => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratKeteranganDesa.*' => ['uuid'],
            'suratIzinLingkungan'   => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratIzinLingkungan.*' => ['uuid'],
            'suratPelepasan'        => ['sometimes', 'nullable', 'array', 'max:5'],
            'suratPelepasan.*'      => ['uuid'],
            'sertifikatHAT'         => ['sometimes', 'nullable', 'array', 'max:5'],
            'sertifikatHAT.*'       => ['uuid'],
            'pertekBPN'             => ['sometimes', 'nullable', 'array', 'max:5'],
            'pertekBPN.*'           => ['uuid'],
            'suratKeteranganLokasi' => ['sometimes', 'nullable', 'array', 'max:2'],
            'suratKeteranganLokasi.*'=> ['uuid'],

            'status_verifikasi_usulan' => ['sometimes', 'integer', 'in:0,1,2,3,4,5,6,7,8,9'],
            'pesan_verifikasi'         => ['sometimes', 'nullable', 'string', 'max:512'],
            'noBASTTPU'                => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        // Proses dokumen
        foreach (self::FILE_FIELDS as $f) {
            if (!array_key_exists($f, $validated)) {
                continue;
            }

            // null → abaikan (tidak overwrite)
            if (is_null($validated[$f])) {
                unset($validated[$f]);
                continue;
            }

            $incomingFinal = (is_array($validated[$f]) && count($validated[$f]) > 0)
                ? $this->ensureFinalUploads($validated[$f], (string) $user->id, true)
                : [];

            $old     = $row->getAttribute($f) ?? [];
            $old     = is_array($old) ? $old : [];
            $removed = array_values(array_diff($old, $incomingFinal));

            if (!empty($removed)) {
                $this->deleteFinalUploads($removed);
            }

            $validated[$f] = $incomingFinal ?: null;
        }

        // Isi field biasa
        $row->fill($validated);

        // Auto-clear pesan saat status >= 4
        if ($request->has('status_verifikasi_usulan')
            && (int) $request->input('status_verifikasi_usulan') >= 4) {
            $row->pesan_verifikasi = null;
        }

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

    /**
     * GET /api/psu/tpu/serah-terima/{id}
     * Return: { usulan: TPU, perencanaan: [ ... ] }
     */
    public function show(string $id)
    {
        $auth = auth()->user();
        if (!$auth) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $data = TpuSerahTerima::find($id);
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $role    = strtolower((string) ($auth->role ?? ''));
        $isOwner = (string) ($data->user_id ?? '') === (string) $auth->id;
        $isPriv  = in_array($role, ['admin', 'admin_bidang', 'operator', 'pengawas'], true);

        if (!$isPriv && !$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Ambil Perencanaan di mana uuidUsulan = id TPU ini
        $perencanaanRows = Perencanaan::where('uuidUsulan', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $perencanaanList = $perencanaanRows->map(function ($row) {
            return [
                'uuidPerencanaan' => (string) $row->id,
                'uuidUsulan'      => (string) $row->uuidUsulan,
                'nilaiHPS'        => $row->nilaiHPS,
                'lembarKontrol'   => $row->lembarKontrol,
                'dokumentasi'     => $row->dokumentasi ?? [],
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

    /**
     * GET /api/psu/tpu/serah-terima
     * Admin/admin_bidang: lihat semua.
     * User biasa: hanya data miliknya.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $role    = strtolower((string) ($user->role ?? ''));
        $isAdmin = in_array($role, ['admin', 'admin_bidang'], true);

        $q = TpuSerahTerima::query()->latest();

        if (!$isAdmin) {
            $q->where('user_id', (string) $user->id);
        }

        if ($request->has('status_verifikasi_usulan')) {
            $q->where('status_verifikasi_usulan', (int) $request->query('status_verifikasi_usulan'));
        }

        if ($request->has('perumahanId')) {
            $q->where('perumahanId', $request->query('perumahanId'));
        }

        return response()->json([
            'success' => true,
            'data'    => $q->get(),
        ]);
    }

    /**
     * DELETE /api/psu/tpu/serah-terima/{id}
     */
    public function destroy(string $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $row = TpuSerahTerima::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $role    = strtolower((string) ($user->role ?? ''));
        $isAdmin = in_array($role, ['admin', 'admin_bidang'], true);

        if (!$isAdmin && (string) $row->user_id !== (string) $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Hapus file FINAL di semua field dokumen
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

            if ($t !== '' && $t[0] === '[') {
                $arr   = json_decode($t, true);
                $uuids = is_array($arr) ? $this->filterUuidArray($arr) : [];
                $request->merge([$field => $uuids ?: null]);
                return;
            }

            if (str_contains($t, ',')) {
                $parts = array_map('trim', explode(',', $t));
                $uuids = $this->filterUuidArray($parts);
                $request->merge([$field => $uuids ?: null]);
                return;
            }

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

            if (str_contains($t, ',')) {
                $parts = array_map('trim', explode(',', $t));
                $arr   = array_values(array_filter($parts, fn($v) => $v !== ''));
                $request->merge([$field => $arr ?: null]);
                return;
            }

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
            if ($final->has($u)) {
                $row = $final->get($u);
                if (!$disk->exists($row->file_path)) {
                    $invalid[] = $u;
                    continue;
                }
                $result[] = $u;
                continue;
            }

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
