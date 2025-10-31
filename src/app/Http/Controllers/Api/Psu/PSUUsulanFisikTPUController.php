<?php

namespace App\Http\Controllers\Api\PSU;

use App\Http\Controllers\Controller;
use App\Models\PSUUsulanFisikTPU;
use App\Models\PSUUploadTemp;
use App\Models\PSUUpload;
use App\Models\Perencanaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PSUUsulanFisikTPUController extends Controller
{
    private const FILE_ARRAY_FIELDS = [
        'suratPermohonanUsulanFisik',
        'proposalUsulanFisik',
        'sertifikatStatusTanah',   // boleh null
        'dokumentasiEksisting',
    ];

    /** GET /api/psu/usulan/tpu */
    public function index(Request $request)
    {
        $q = PSUUsulanFisikTPU::query()->latest();

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

            // Dokumen (array UUID)
            'suratPermohonanUsulanFisik'   => 'required|array|min:1|max:20',
            'suratPermohonanUsulanFisik.*' => 'uuid',
            'proposalUsulanFisik'           => 'required|array|min:1|max:20',
            'proposalUsulanFisik.*'         => 'uuid',
            'sertifikatStatusTanah'         => 'sometimes|nullable|array|max:20',
            'sertifikatStatusTanah.*'       => 'uuid',
            'dokumentasiEksisting'          => 'required|array|min:1|max:30',
            'dokumentasiEksisting.*'        => 'uuid',

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
            $payload['proposalUsulanFisik'],
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
            'proposalUsulanFisik'           => 'sometimes|nullable|array|min:1|max:20',
            'proposalUsulanFisik.*'         => 'uuid',
            'sertifikatStatusTanah'         => 'sometimes|nullable|array|max:20',
            'sertifikatStatusTanah.*'       => 'uuid',
            'dokumentasiEksisting'          => 'sometimes|nullable|array|min:1|max:30',
            'dokumentasiEksisting.*'        => 'uuid',

            'status_verifikasi_usulan'      => 'sometimes|integer|in:0,1,2,3,4,5,6,7,8,9',
            'pesan_verifikasi'              => 'sometimes|nullable|string|max:512',
        ]);

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
        $row = PSUUsulanFisikTPU::where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Ambil list perencanaan yang menempel ke uuid usulan ini
        $perencanaanRows = Perencanaan::where('uuidUsulan', $uuid)
            ->orderBy('created_at', 'desc')
            ->get();

        $perencanaanList = $perencanaanRows->map(function ($r) {
            return [
                'uuidPerencanaan' => $r->id,          // PK (string) perencanaans
                'uuidUsulan'      => $r->uuidUsulan,
                'nilaiHPS'        => $r->nilaiHPS,
                'catatanSurvey'   => $r->catatanSurvey,
                'created_at'      => $r->created_at,
                'updated_at'      => $r->updated_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'usulan'      => $row,
                'perencanaan' => $perencanaanList,
            ],
        ]);
    }

    /** DELETE /api/psu/usulan/tpu/{uuid} */
    public function destroy(string $uuid)
    {
        $row = PSUUsulanFisikTPU::where('uuid', $uuid)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        $row->delete();

        return response()->json(['success' => true, 'message' => 'Data berhasil dihapus']);
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

            $old = $temp->file_path; // psu_temp/<uuid>.<ext>
            $file = basename($old);
            $new = 'psu_final/' . $file;

            if (Storage::disk('local')->exists($new)) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
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
                ['user_id' => $userId, 'file_path' => $new,
                 'original_name' => $temp->original_name, 'mime' => $temp->mime, 'size' => $temp->size]
            );

            $temp->delete();
        }
    }
}
