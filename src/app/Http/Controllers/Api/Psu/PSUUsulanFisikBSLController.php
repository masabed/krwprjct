<?php

namespace App\Http\Controllers\Api\Psu;

use App\Http\Controllers\Controller;
use App\Models\UsulanFisikBSL;
use App\Models\PSUUploadTemp;
use App\Models\PSUUpload;
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
        'proposalUsulanFisik',
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
            'tanggalPermohonan'      => ['required','date'],
            'nomorSuratPermohonan'   => ['required','string','max:150'],

            // Sumber usulan & Data pemohon
            'sumberUsulan'           => ['required','string','max:150'],
            'namaAspirator'          => ['required','string','max:255'],
            'noKontakAspirator'      => ['required','string','max:100'],
            'namaPIC'                => ['required','string','max:255'],
            'noKontakPIC'            => ['required','string','max:100'],

            // Rincian usulan
            'jenisUsulan'            => ['required','string','max:150'],
            'uraianMasalah'          => ['required','string'],

            // Dimensi usulan / eksisting
            'luasTanahTersedia'      => ['required','string','max:100'],
            'luasSarana'             => ['required','string','max:100'],

            // Lokasi usulan
            'jenisBSL'               => ['required','string','max:150'],
            'alamatCPCL'             => ['required','string','max:500'],
            'rtCPCL'                 => ['required','string','max:10'],
            'rwCPCL'                 => ['required','string','max:10'],
            'titikLokasiUsulan'      => ['required','string','max:255'],

            // Keterangan lokasi BSL
            'perumahanId'            => ['sometimes','nullable','uuid'],
            'statusTanah'            => ['sometimes','nullable','string','max:150'],

            // Dokumen pendukung (opsional & nullable)
            'suratPermohonanUsulanFisik' => ['sometimes','nullable','array','max:10'],
            'suratPermohonanUsulanFisik.*' => ['uuid'],

            'proposalUsulanFisik'       => ['sometimes','nullable','array','max:10'],
            'proposalUsulanFisik.*'     => ['uuid'],

            'sertifikatStatusTanah'     => ['sometimes','nullable','array','max:10'],
            'sertifikatStatusTanah.*'   => ['uuid'],

            'dokumentasiEksisting'      => ['sometimes','nullable','array','max:20'],
            'dokumentasiEksisting.*'    => ['uuid'],
        ]);

        // Finalisasi dokumen (TEMP -> FINAL / reuse FINAL)
        $finalized = [];
        foreach (self::FILE_FIELDS as $f) {
            if (array_key_exists($f, $validated)) {
                $finalized[$f] = (is_array($validated[$f]) && count($validated[$f]) > 0)
                    ? $this->ensureFinalUploads($validated[$f], (string) $user->id, true)
                    : null; // biarkan null untuk kolom nullable
            }
        }

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
     * PUT/PATCH /api/psu/usulan-fisik-bsl/{id}
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

        // Normalisasi hanya field dokumen yang dikirim
        foreach (self::FILE_FIELDS as $f) {
            if ($request->has($f)) {
                $this->normalizeUuidArrayField($request, $f);
            }
        }

        $validated = $request->validate([
            // Keterangan permohonan
            'tanggalPermohonan'      => ['sometimes','date'],
            'nomorSuratPermohonan'   => ['sometimes','string','max:150'],

            // Sumber usulan & Data pemohon
            'sumberUsulan'           => ['sometimes','string','max:150'],
            'namaAspirator'          => ['sometimes','string','max:255'],
            'noKontakAspirator'      => ['sometimes','string','max:100'],
            'namaPIC'                => ['sometimes','string','max:255'],
            'noKontakPIC'            => ['sometimes','string','max:100'],

            // Rincian usulan
            'jenisUsulan'            => ['sometimes','string','max:150'],
            'uraianMasalah'          => ['sometimes','string'],

            // Dimensi usulan / eksisting
            'luasTanahTersedia'      => ['sometimes','string','max:100'],
            'luasSarana'             => ['sometimes','string','max:100'],

            // Lokasi usulan
            'jenisBSL'               => ['sometimes','string','max:150'],
            'alamatCPCL'             => ['sometimes','string','max:500'],
            'rtCPCL'                 => ['sometimes','string','max:10'],
            'rwCPCL'                 => ['sometimes','string','max:10'],
            'titikLokasiUsulan'      => ['sometimes','string','max:255'],

            // Keterangan lokasi BSL
            'perumahanId'            => ['sometimes','nullable','uuid'],
            'statusTanah'            => ['sometimes','nullable','string','max:150'],

            // Dokumen pendukung (opsional & nullable)
            'suratPermohonanUsulanFisik'   => ['sometimes','nullable','array','max:10'],
            'suratPermohonanUsulanFisik.*' => ['uuid'],

            'proposalUsulanFisik'          => ['sometimes','nullable','array','max:10'],
            'proposalUsulanFisik.*'        => ['uuid'],

            'sertifikatStatusTanah'        => ['sometimes','nullable','array','max:10'],
            'sertifikatStatusTanah.*'      => ['uuid'],

            'dokumentasiEksisting'         => ['sometimes','nullable','array','max:20'],
            'dokumentasiEksisting.*'       => ['uuid'],
        ]);

        // Tangani file-fields yang dikirim
        foreach (self::FILE_FIELDS as $f) {
            if (!array_key_exists($f, $validated)) continue;

            if (is_null($validated[$f])) { // null => abaikan perubahan
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
    public function show(string $id)
    {
        $row = UsulanFisikBSL::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => $row]);
    }

    /** ================== INDEX ==================
     * GET /api/psu/usulan-fisik-bsl
     * Optional filter: perumahanId
     */
    public function index(Request $request)
    {
        $q = UsulanFisikBSL::query()->latest();

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
    public function destroy(string $id)
    {
        $row = UsulanFisikBSL::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        // Hapus file FINAL yang terkait
        foreach (self::FILE_FIELDS as $f) {
            $uuids = $row->getAttribute($f) ?? [];
            if ($uuids) {
                $this->deleteFinalUploads(is_array($uuids) ? $uuids : []);
            }
        }

        $row->delete();

        return response()->json(['success' => true, 'message' => 'Data berhasil dihapus']);
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
            if ($u) $uuids[] = $u;
        }
        return array_values(array_unique($uuids));
    }

    /** Ekstrak UUID v1–v5 dari string/path */
    private function extractUuid(string $value): ?string
    {
        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/', $value, $m)) {
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
                'success' => false,
                'message' => 'Beberapa UUID file tidak valid / file fisik tidak ada.',
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
