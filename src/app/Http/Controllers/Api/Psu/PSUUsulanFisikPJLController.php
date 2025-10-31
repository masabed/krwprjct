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

class PSUUsulanFisikPJLController extends Controller
{
    /** Daftar kolom file (ARRAY UUID) yang dikelola */
    private const FILE_ARRAY_FIELDS = [
        'suratPermohonanUsulanFisik',
        'proposalUsulanFisik',
        'dokumentasiEksisting',
    ];

    /** GET /api/psu/pjl */
    public function index(Request $request)
    {
        $q = PSUUsulanFisikPJL::query()->latest();

        if ($request->has('perumahanId')) {
            $q->where('perumahanId', $request->query('perumahanId'));
        }
        if ($request->has('status_verifikasi_usulan')) {
            $q->where(
                'status_verifikasi_usulan',
                (int) $request->query('status_verifikasi_usulan')
            );
        }

        return response()->json(['success' => true, 'data' => $q->get()]);
    }

    /** GET /api/psu/pjl/{uuid} */
   public function show(string $uuid)
{
    // Ambil usulan PJL-nya dulu
    $row = PSUUsulanFisikPJL::where('uuid', $uuid)->first();
    if (!$row) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    // Ambil semua perencanaan yang menempel ke uuid usulan ini
    $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)
        ->orderBy('created_at', 'desc')
        ->get();

    // Format rapi
    $perencanaanList = $perencanaanRows->map(function ($r) {
        return [
            'uuidPerencanaan' => $r->id,          // PK (string UUID) di tabel perencanaans
            'uuidUsulan'      => $r->uuidUsulan,  // harus sama dengan UUID usulan PJL
            'nilaiHPS'        => $r->nilaiHPS,
            'catatanSurvey'   => $r->catatanSurvey,
            'created_at'      => $r->created_at,
            'updated_at'      => $r->updated_at,
        ];
    })->values();

    return response()->json([
        'success' => true,
        'data' => [
            // usulan PJL lengkap (langsung dari model)
            'usulan'       => $row, 
            // daftar perencanaan terkait
            'perencanaan'  => $perencanaanList,
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
            'tanggalPermohonan'      => 'required|string|max:25',
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
            'proposalUsulanFisik'          => 'required|array|min:1|max:10',
            'proposalUsulanFisik.*'        => 'uuid',
            'dokumentasiEksisting'         => 'required|array|min:1|max:20',
            'dokumentasiEksisting.*'       => 'uuid',

            // meta
            'status_verifikasi_usulan'     => 'sometimes|integer|in:0,1,2,3,4',
            'pesan_verifikasi'             => 'sometimes|nullable|string|max:512',
        ]);

        // Pindahkan file dari psu_temp → psu_final
        $allUuids = array_unique(array_merge(
            $payload['suratPermohonanUsulanFisik'] ?? [],
            $payload['proposalUsulanFisik'] ?? [],
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

        // Normalisasi hanya field yang dikirim
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f)) $this->normalizeUuidArrayField($request, $f);
        }

        $validated = $request->validate([
            // permohonan
            'tanggalPermohonan'      => 'sometimes|string|max:25',
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
            'proposalUsulanFisik'          => 'sometimes|nullable|array|min:1|max:10',
            'proposalUsulanFisik.*'        => 'uuid',
            'dokumentasiEksisting'         => 'sometimes|nullable|array|min:1|max:20',
            'dokumentasiEksisting.*'       => 'uuid',

            // meta
            'status_verifikasi_usulan'     => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
            'pesan_verifikasi'             => 'sometimes|nullable|string|max:512',
        ]);

        // Pindahkan UUID baru (yang belum final) dari temp → final
        $uuidsToMove = [];
        foreach (self::FILE_ARRAY_FIELDS as $f) {
            if ($request->has($f) && is_array($request->input($f))) {
                $incoming = $request->input($f);
                $existing = $row->getAttribute($f) ?? [];
                $diff = array_diff($incoming, is_array($existing) ? $existing : []);
                if (!empty($diff)) $uuidsToMove = array_merge($uuidsToMove, $diff);
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
    public function destroy(string $uuid)
    {
        $row = PSUUsulanFisikPJL::where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Catatan: default TIDAK menghapus file final agar bisa direuse.
        $row->delete();

        return response()->json(['success' => true, 'message' => 'Data berhasil dihapus']);
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
            if ($t === '' || strtolower($t) === 'null') { $request->merge([$field => null]); return; }

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
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $value, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /**
     * Pindahkan file dari psu_temp → psu_final untuk daftar UUID.
     * - Jika UUID sudah ada di PSUUpload milik user yang sama → reuse.
     * - Kalau ada di temp → move ke final + buat/udpate PSUUpload.
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
