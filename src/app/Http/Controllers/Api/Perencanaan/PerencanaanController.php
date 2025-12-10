<?php

namespace App\Http\Controllers\Api\Perencanaan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Perencanaan;

// ====== Model usulan kandidat ======
use App\Models\UsulanFisikBSL;
use App\Models\PsuSerahTerima;
use App\Models\PSUUsulanFisikPerumahan;
use App\Models\PSUUsulanFisikTPU;
use App\Models\PSUUsulanFisikPJL;
use App\Models\Permukiman;
use App\Models\Rutilahu;
use App\Models\SAPDLahanMasyarakat;
use App\Models\UsulanSAPDSIndividual;
use App\Models\UsulanSAPDSFasilitasUmum;

// ====== Upload models (Perencanaan) ======
use App\Models\PerencanaanUpload;
use App\Models\PerencanaanUploadTemp;
use Illuminate\Support\Facades\DB;

class PerencanaanController extends Controller
{
    /**
     * GET /api/psu/perencanaan
     * optional: ?uuidUsulan={uuid}
     */
    public function index(Request $request)
    {
        $request->validate([
            'uuidUsulan' => ['sometimes','uuid'],
        ]);

        $q = Perencanaan::query()->latest();

        if ($request->has('uuidUsulan')) {
            $q->where('uuidUsulan', $request->query('uuidUsulan'));
        }

        return response()->json([
            'success' => true,
            'data'    => $q->get(),
        ]);
    }

    /**
     * GET /api/psu/perencanaan/{id}
     * {id} = UUID kolom "id"
     */
    public function show(string $id)
    {
        $row = Perencanaan::find($id);

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $row,
        ]);
    }

    /**
     * POST /api/psu/perencanaan/upload
     * Upload TEMP → balikin UUID untuk dipakai di lembarKontrol
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $uuid = (string) Str::uuid();
        $file = $request->file('file');

        $ext  = strtolower(
            $file->getClientOriginalExtension()
            ?: $file->extension()
            ?: 'bin'
        );

        $timestamp = now()->format('Ymd_His');
        $basename  = "{$timestamp}_{$uuid}.{$ext}";
        $path      = $file->storeAs('perencanaan_temp', $basename, 'local');

        $temp = PerencanaanUploadTemp::create([
            'uuid'          => $uuid,
            'user_id'       => $user->id,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime'          => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'uuid'          => $temp->uuid,
                'user_id'       => $temp->user_id,
            ],
        ], 201);
    }

    /**
     * GET /api/psu/perencanaan/file/{uuid}
     * Stream FINAL jika ada, selain itu coba TEMP. Tambahkan ?download=1 untuk attachment.
     */
    public function fileShow(string $uuid, Request $request)
    {
        $file = PerencanaanUpload::where('uuid', $uuid)->first()
             ?: PerencanaanUploadTemp::where('uuid', $uuid)->first();

        if (!$file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (!$disk->exists($file->file_path)) {
            return response()->json(['success' => false, 'message' => 'File missing'], 404);
        }

        $mime = $file->mime ?: ($disk->mimeType($file->file_path) ?? 'application/octet-stream');
        $name = basename($file->file_path);
        $size = $file->size ?: $disk->size($file->file_path);

        $disposition = $request->boolean('download')
            ? 'attachment; filename="'.$name.'"'
            : 'inline; filename="'.$name.'"';

        return new StreamedResponse(function () use ($disk, $file) {
            $stream = $disk->readStream($file->file_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'           => $mime,
            'Content-Length'         => (string) $size,
            'Content-Disposition'    => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                 => 'no-cache',
        ]);
    }

    /**
     * DELETE /api/psu/perencanaan/file/{uuid}
     * (alternatif POST /api/psu/perencanaan/file/{uuid}/delete)
     * ?source=final|temp, ?delete_all=true
     */
    public function fileDestroy(string $uuid, Request $request)
    {
        $source    = $request->query('source');
        $deleteAll = $request->boolean('delete_all', false);

        $results = [];
        $disk = Storage::disk(config('filesystems.default'));

        $deleteOne = function ($modelClass, $label) use ($uuid, $disk, &$results) {
            $rec = $modelClass::where('uuid', $uuid)->first();
            if (!$rec) {
                $results[$label] = ['found' => false];
                return false;
            }

            $physical = false;
            if ($rec->file_path && $disk->exists($rec->file_path)) {
                $physical = $disk->delete($rec->file_path);
            }

            $rec->delete();
            $results[$label] = ['found' => true, 'physical_deleted' => (bool) $physical];
            return true;
        };

        $deleted = false;

        if ($source === 'final') {
            $deleted = $deleteOne(PerencanaanUpload::class, 'final');
        } elseif ($source === 'temp') {
            $deleted = $deleteOne(PerencanaanUploadTemp::class, 'temp');
        } else {
            $deleted = $deleteOne(PerencanaanUpload::class, 'final');
            if ($deleteAll) {
                $deleteOne(PerencanaanUploadTemp::class, 'temp');
            } elseif (!$deleted) {
                $deleted = $deleteOne(PerencanaanUploadTemp::class, 'temp');
            }
        }

        if (!$deleted && empty(array_filter($results, fn($r) => ($r['found'] ?? false)))) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in final or temp',
                'result'  => $results,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'File deleted',
            'result'  => $results,
        ]);
    }

    /**
     * POST /api/psu/perencanaan/create
     * Body bisa kirim lembarKontrol sebagai ARRAY atau STRING (JSON/CSV/single)
     */
 public function store(Request $request)
{
    // === ADMIN ONLY ===
    $auth = $request->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }
    if (strtolower((string)($auth->role ?? '')) !== 'admin') {
        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    // Terima string array & normalisasi ke array ['uuid', ...]
    $this->normalizeUuidArrayField($request, 'lembarKontrol');

    $validated = $request->validate([
        'uuidUsulan'      => ['required','uuid'],
        'nilaiHPS'        => ['sometimes','nullable','string','max:255'],
        'catatanSurvey'   => ['sometimes','nullable','string','max:512'],
        'lembarKontrol'   => ['sometimes','array','min:1','max:50'],
        'lembarKontrol.*' => ['uuid'],
    ]);

    // ===== CEK DUPLIKAT uuidUsulan =====
    $already = Perencanaan::where('uuidUsulan', $validated['uuidUsulan'])->exists();
    if ($already) {
        return response()->json([
            'success'    => false,
            'message'    => 'Perencanaan untuk uuidUsulan tersebut sudah ada. Tidak boleh input duplikat.',
            'uuidUsulan' => $validated['uuidUsulan'],
        ], 422);
    }

    // Cari usulan tujuan
    $usulan = $this->findUsulanByUuid($validated['uuidUsulan']);
    if (!$usulan) {
        return response()->json([
            'success' => false,
            'message' => 'Usulan tidak ditemukan untuk uuidUsulan yang diberikan.',
        ], 422);
    }

    // Buat Perencanaan + set status usulan = 5 (clear pesan_verifikasi jika ada)
    $row = null;
    DB::transaction(function () use (&$row, $validated, $usulan) {
        // 1) create perencanaan
        $row = Perencanaan::create($validated);

        // 2) paksa status jadi 5 pada tabel usulan tujuan
        $table = $usulan->getTable();
        if (Schema::hasColumn($table, 'status_verifikasi_usulan')) {
            $usulan->status_verifikasi_usulan = 5;
            if (Schema::hasColumn($table, 'pesan_verifikasi')) {
                $usulan->pesan_verifikasi = null;
            }
            $usulan->save();
        }
    });

    // Pindahkan file dari TEMP → FINAL setelah commit
    if ($request->user() && !empty($validated['lembarKontrol'])) {
        $this->moveTempsToFinal($validated['lembarKontrol'], (string) $request->user()->id);
    }

    return response()->json([
        'success' => true,
        'message' => 'Data perencanaan berhasil dibuat. Status usulan dinaikkan ke 5.',
        'data'    => $row,
    ], 201);
}

    /**
     * POST/PUT/PATCH /api/psu/perencanaan/update/{id}
     * - lembarKontrol: null -> abaikan
     * - lembarKontrol: []   -> kosongkan (hapus semua final)
     * - lembarKontrol: "json string" / "csv" -> otomatis jadi array
     */
   public function update(Request $request, string $id)
{
    // === Auth & Role guard (ADMIN ONLY) ===
    $user = $request->user();
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }
    if (($user->role ?? null) !== 'admin') {
        return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
    }

    // === Ambil row perencanaan ===
    $row = Perencanaan::find($id);
    if (!$row) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    // >>> TERIMA STRING array & normalisasi ke array
    $this->normalizeUuidArrayField($request, 'lembarKontrol');

    // === Validasi input ===
    $validated = $request->validate([
        'uuidUsulan'      => ['sometimes','uuid'],
        'nilaiHPS'        => ['sometimes','nullable','string','max:255'],
        'catatanSurvey'   => ['sometimes','nullable','string','max:512'],

        'lembarKontrol'   => ['sometimes','nullable','array','max:50'],
        'lembarKontrol.*' => ['uuid'],
    ]);

    // === Cek keberadaan usulan target (tanpa cek status) ===
    $targetUuidUsulan = $validated['uuidUsulan'] ?? $row->uuidUsulan;
    $usulan = $this->findUsulanByUuid($targetUuidUsulan);
    if (!$usulan) {
        return response()->json([
            'success' => false,
            'message' => 'Usulan tidak ditemukan untuk uuidUsulan yang diberikan.',
        ], 422);
    }

    // === Hitung perubahan file (jika lembarKontrol dikirim) ===
    $uuidsToMove = $removedUuids = [];
    $incomingProvided = array_key_exists('lembarKontrol', $validated);

    if ($incomingProvided) {
        $incoming = $validated['lembarKontrol']; // bisa null/array
        $existing = $row->lembarKontrol ?? [];

        if (is_array($incoming)) {
            $uuidsToMove  = array_values(array_diff($incoming, is_array($existing) ? $existing : []));
            $removedUuids = array_values(array_diff(is_array($existing) ? $existing : [], $incoming));
        }
    }

    // === Payload update: kalau lembarKontrol === null → abaikan (tidak overwrite ke null) ===
    $payload = $validated;
    if ($incomingProvided && is_null($payload['lembarKontrol'])) {
        unset($payload['lembarKontrol']);
    }

    $row->fill($payload);
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

    // === Move temp→final untuk UUID baru ===
    if ($uuidsToMove) {
        $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string)$user->id);
    }

    // === Hapus final untuk UUID yang dibuang ===
    if ($removedUuids) {
        // Pastikan helper yang kamu punya di project:
        // jika namanya deleteFinalUploads(), ganti baris di bawah ini.
        $this->deleteFinalFiles(array_values(array_unique($removedUuids)));
    }

    return response()->json([
        'success' => true,
        'message' => 'Field berikut berhasil diperbarui: ' . implode(', ', array_keys($dirty)),
        'data'    => $row->fresh(),
    ]);
}


    /**
     * DELETE /api/psu/perencanaan/{id}
     */
    public function destroy(string $id)
    {
        $row = Perencanaan::find($id);

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus',
        ]);
    }

    // =============================================================================
    // Helpers
    // =============================================================================

    /**
     * Terima lembarKontrol dalam berbagai bentuk STRING/ARRAY dan normalkan ke array of UUID.
     * - JSON: '["uuid","uuid2"]'
     * - CSV:  'uuid,uuid2'
     * - Single: 'uuid'
     */
    private function normalizeUuidArrayField(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);
        if ($val === null || is_array($val)) return;

        if (is_string($val)) {
            $t = trim($val);
            $uuids = [];

            // JSON array string
            if ($t !== '' && $t[0] === '[') {
                $arr = json_decode($t, true);
                if (is_array($arr)) {
                    foreach ($arr as $v) {
                        if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/', (string)$v, $m)) {
                            $uuids[] = strtolower($m[0]);
                        }
                    }
                    $request->merge([$field => array_values(array_unique($uuids))]);
                    return;
                }
            }

            // CSV
            if (str_contains($t, ',')) {
                foreach (array_map('trim', explode(',', $t)) as $p) {
                    if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/', $p, $m)) {
                        $uuids[] = strtolower($m[0]);
                    }
                }
                $request->merge([$field => array_values(array_unique($uuids))]);
                return;
            }

            // Single UUID
            if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/', $t, $m)) {
                $request->merge([$field => [strtolower($m[0])]]);
            }
        }
    }

    /**
     * Cari usulan di beberapa tabel kandidat dengan kunci fleksibel
     */
    private function findUsulanByUuid(string $value): ?object
    {
        $candidates = [
            UsulanFisikBSL::class,
            PSUUsulanFisikPerumahan::class,
            PsuSerahTerima::class,
            PSUUsulanFisikTPU::class,
            PSUUsulanFisikPJL::class,
            Permukiman::class,
            Rutilahu::class,
            SAPDLahanMasyarakat::class,
            UsulanSAPDSFasilitasUmum::class,
            UsulanSAPDSIndividual::class,
        ];

        foreach ($candidates as $modelClass) {
            if (!class_exists($modelClass)) continue;

            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $modelClass;
            $table = $instance->getTable();

            $keysToTry = array_values(array_unique([
                $instance->getKeyName(),
                'uuid',
                'id',
            ]));

            foreach ($keysToTry as $col) {
                if (!Schema::hasColumn($table, $col)) continue;

                $found = $modelClass::where($col, $value)->first();
                if ($found) return $found;
            }
        }

        return null;
    }

    /**
     * Pindahkan file TEMP → FINAL
     */
    private function moveTempsToFinal(array $fileUuids, string $userId): void
    {
        $fileUuids = array_values(array_unique(array_filter($fileUuids)));
        if (!$fileUuids) return;

        $temps = PerencanaanUploadTemp::whereIn('uuid', $fileUuids)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('uuid');

        foreach ($fileUuids as $u) {
            $temp = $temps->get($u);
            if (!$temp) continue; // mungkin sudah final/reuse

            $oldPath = $temp->file_path;
            $ext     = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION) ?: 'bin');
            $newPath = 'perencanaan_final/'.$u.'.'.$ext;

            if (Storage::exists($oldPath)) {
                Storage::move($oldPath, $newPath);
            } elseif (!Storage::exists($newPath)) {
                continue;
            }

            PerencanaanUpload::updateOrCreate(
                ['uuid' => $u],
                [
                    'user_id'       => $userId,
                    'file_path'     => $newPath,
                    'original_name' => $temp->original_name,
                    'mime'          => $temp->mime,
                    'size'          => $temp->size,
                ]
            );

            $temp->delete();
        }
    }

    /**
     * Hapus file FINAL & row upload-nya
     */
    private function deleteFinalFiles(array $uuids): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return;

        foreach ($uuids as $u) {
            $upload = PerencanaanUpload::where('uuid', $u)->first();
            if ($upload) {
                if ($upload->file_path && Storage::exists($upload->file_path)) {
                    Storage::delete($upload->file_path);
                }
                $upload->delete();
            }
        }
    }
}
