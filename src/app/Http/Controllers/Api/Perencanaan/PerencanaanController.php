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
use App\Models\TpuSerahTerima;                     // <== NEW: TPU Serah Terima
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
     * Upload TEMP → balikin UUID untuk dipakai di lembarKontrol/dokumentasi
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10512',
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
                'uuid'    => $temp->uuid,
                'user_id' => $temp->user_id,
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
     * GET /api/perencanaan/file/preview/{uuid}
     *
     * Preview fixed-size:
     *  - image/*  → resize lebar 512px (JPEG)
     *  - PDF      → halaman pertama jadi JPEG lebar 512px
     *
     * Optional: ?source=final|temp (default: final→temp)
     */
    public function preview(string $uuid, Request $request)
    {
        $source = $request->query('source'); // final | temp | null
        $disk   = Storage::disk(config('filesystems.default'));

        if ($source === 'final') {
            $file = PerencanaanUpload::where('uuid', $uuid)->first();
        } elseif ($source === 'temp') {
            $file = PerencanaanUploadTemp::where('uuid', $uuid)->first();
        } else {
            $file = PerencanaanUpload::where('uuid', $uuid)->first()
                 ?: PerencanaanUploadTemp::where('uuid', $uuid)->first();
        }

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in FINAL or TEMP',
            ], 404);
        }

        if (!$disk->exists($file->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Physical file missing on disk',
            ], 404);
        }

        $abs = method_exists($disk, 'path')
            ? $disk->path($file->file_path)
            : storage_path('app/'.$file->file_path);

        $mime = $file->mime
            ?: ($disk->mimeType($file->file_path)
            ?: (function_exists('mime_content_type') ? @mime_content_type($abs) : null));

        $w = 512; // target width

        // ====================== IMAGE PREVIEW ======================
        if ($mime && str_starts_with($mime, 'image/')) {
            if (class_exists(\Intervention\Image\ImageManagerStatic::class)) {
                try {
                    $img = \Intervention\Image\ImageManagerStatic::make($abs)
                        ->orientate()
                        ->resize($w, $w, function ($c) {
                            $c->aspectRatio();
                            $c->upsize();
                        });

                    $binary = $img->encode('jpg', 75);

                    return response($binary, 200, [
                        'Content-Type'            => 'image/jpeg',
                        'X-Content-Type-Options'  => 'nosniff',
                        'Cache-Control'           => 'private, max-age=86400',
                        'X-Preview-Engine'        => 'intervention',
                        'X-Preview-Bytes'         => (string) strlen($binary),
                    ]);
                } catch (\Throwable $e) {
                    // fallback ke GD
                }
            }

            if (function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor')) {
                try {
                    $data = @file_get_contents($abs);
                    if ($data !== false) {
                        $src = @imagecreatefromstring($data);
                        if ($src !== false) {
                            $srcW = imagesx($src) ?: 1;
                            $srcH = imagesy($src) ?: 1;

                            $targetW = $w;
                            $targetH = (int) round($srcH * ($w / $srcW));

                            $dst = imagecreatetruecolor($targetW, $targetH);
                            imagecopyresampled(
                                $dst, $src,
                                0, 0, 0, 0,
                                $targetW, $targetH, $srcW, $srcH
                            );

                            ob_start();
                            imagejpeg($dst, null, 75);
                            $binary = ob_get_clean();

                            imagedestroy($src);
                            imagedestroy($dst);

                            if ($binary !== false) {
                                return response($binary, 200, [
                                    'Content-Type'            => 'image/jpeg',
                                    'X-Content-Type-Options'  => 'nosniff',
                                    'Cache-Control'           => 'private, max-age=86400',
                                    'X-Preview-Engine'        => 'gd',
                                    'X-Preview-Bytes'         => (string) strlen($binary),
                                ]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // fallthrough
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Preview requires GD or Intervention Image for images.',
            ], 415);
        }

        // ====================== PDF PREVIEW ======================
        $isPdf = ($mime === 'application/pdf') || str_ends_with(strtolower($abs), '.pdf');
        if ($isPdf) {
            if (class_exists(\Imagick::class)) {
                try {
                    $targetWidth  = 512;
                    $targetDpi    = 96;
                    $jpegQuality  = 60;

                    $im = new \Imagick();
                    $im->setResolution($targetDpi, $targetDpi);
                    $im->readImage($abs . '[0]');
                    $im->setImageBackgroundColor(new \ImagickPixel('white'));
                    $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                    $im->setImageFormat('jpeg');
                    $im->stripImage();

                    $origW = $im->getImageWidth() ?: 1;
                    $origH = $im->getImageHeight() ?: 1;

                    if ($origW > $targetWidth) {
                        $ratio        = $targetWidth / $origW;
                        $targetHeight = (int) max(1, round($origH * $ratio));
                    } else {
                        $targetWidth  = $origW;
                        $targetHeight = $origH;
                    }

                    $im->thumbnailImage($targetWidth, $targetHeight, true);
                    $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
                    $im->setImageCompressionQuality($jpegQuality);

                    $blob = $im->getImageBlob();
                    $size = strlen($blob);

                    $im->clear();
                    $im->destroy();

                    return response($blob, 200, [
                        'Content-Type'            => 'image/jpeg',
                        'Content-Length'          => (string) $size,
                        'X-Content-Type-Options'  => 'nosniff',
                        'Cache-Control'           => 'private, max-age=86400',
                        'X-Preview-Engine'        => 'imagick',
                        'X-Preview-Bytes'         => (string) $size,
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('Perencanaan PDF thumbnail failed', [
                        'file'  => $abs,
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Unable to generate PDF preview. Please open the original file.',
                    ], 500);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'PDF preview requires Imagick. Please open the original file.',
            ], 415);
        }

        return response()->json([
            'success' => false,
            'message' => 'Preview only supports images and PDFs.',
        ], 415);
    }

    /**
     * DELETE /api/psu/perencanaan/file/{uuid}
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
     */
    public function store(Request $request)
    {
        // === ACCESS CONTROL: hanya admin_bidang & operator ===
        $auth = auth()->user();
        if (!$auth) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $role   = strtolower((string) ($auth->role ?? ''));
        $isPriv = in_array($role, ['admin', 'operator'], true);  // <== FIXED

        if (!$isPriv) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya admin dan operator yang boleh membuat perencanaan.',
            ], 403);
        }

        // Terima string array & normalisasi ke array ['uuid', ...]
        $this->normalizeUuidArrayField($request, 'lembarKontrol');
        $this->normalizeUuidArrayField($request, 'dokumentasi');

        $validated = $request->validate([
            'uuidUsulan'      => ['required','uuid'],
            'nilaiHPS'        => ['sometimes','nullable','string','max:255'],
            'catatanSurvey'   => ['sometimes','nullable','string','max:512'],

            'lembarKontrol'   => ['sometimes','array','min:1','max:50'],
            'lembarKontrol.*' => ['uuid'],

            'dokumentasi'     => ['sometimes','nullable','array','max:5'],
            'dokumentasi.*'   => ['uuid'],
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

        // Cari usulan tujuan (sekarang termasuk TpuSerahTerima)
        $usulan = $this->findUsulanByUuid($validated['uuidUsulan']);
        if (!$usulan) {
            return response()->json([
                'success' => false,
                'message' => 'Usulan tidak ditemukan untuk uuidUsulan yang diberikan.',
            ], 422);
        }

        // Buat Perencanaan + set status usulan = 5
        $row = null;
        DB::transaction(function () use (&$row, $validated, $usulan) {
            $row = Perencanaan::create($validated);

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
        $fileUuids = [];

        if (!empty($validated['lembarKontrol'])) {
            $fileUuids = array_merge($fileUuids, $validated['lembarKontrol']);
        }
        if (!empty($validated['dokumentasi'])) {
            $fileUuids = array_merge($fileUuids, $validated['dokumentasi']);
        }

        if ($auth && $fileUuids) {
            $this->moveTempsToFinal(
                array_values(array_unique($fileUuids)),
                (string) $auth->id
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Data perencanaan berhasil dibuat. Status usulan dinaikkan ke 5.',
            'data'    => $row,
        ], 201);
    }

    /**
     * POST/PUT/PATCH /api/psu/perencanaan/update/{id}
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

        $row = Perencanaan::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $this->normalizeUuidArrayField($request, 'lembarKontrol');
        $this->normalizeUuidArrayField($request, 'dokumentasi');

        $validated = $request->validate([
            'uuidUsulan'      => ['sometimes','uuid'],
            'nilaiHPS'        => ['sometimes','nullable','string','max:255'],
            'catatanSurvey'   => ['sometimes','nullable','string','max:512'],

            'lembarKontrol'   => ['sometimes','nullable','array','max:50'],
            'lembarKontrol.*' => ['uuid'],

            'dokumentasi'     => ['sometimes','nullable','array','max:5'],
            'dokumentasi.*'   => ['uuid'],
        ]);

        $targetUuidUsulan = $validated['uuidUsulan'] ?? $row->uuidUsulan;
        $usulan = $this->findUsulanByUuid($targetUuidUsulan);
        if (!$usulan) {
            return response()->json([
                'success' => false,
                'message' => 'Usulan tidak ditemukan untuk uuidUsulan yang diberikan.',
            ], 422);
        }

        $uuidsToMove  = [];
        $removedUuids = [];

        // lembarKontrol
        $incomingLKProvided = array_key_exists('lembarKontrol', $validated);
        if ($incomingLKProvided) {
            $incoming = $validated['lembarKontrol'];
            $existing = $row->lembarKontrol ?? [];

            if (is_array($incoming)) {
                $toMove  = array_values(array_diff($incoming, is_array($existing) ? $existing : []));
                $removed = array_values(array_diff(is_array($existing) ? $existing : [], $incoming));

                $uuidsToMove  = array_merge($uuidsToMove, $toMove);
                $removedUuids = array_merge($removedUuids, $removed);
            }
        }

        // dokumentasi
        $incomingDokProvided = array_key_exists('dokumentasi', $validated);
        if ($incomingDokProvided) {
            $incoming = $validated['dokumentasi'];
            $existing = $row->dokumentasi ?? [];

            if (is_array($incoming)) {
                $toMove  = array_values(array_diff($incoming, is_array($existing) ? $existing : []));
                $removed = array_values(array_diff(is_array($existing) ? $existing : [], $incoming));

                $uuidsToMove  = array_merge($uuidsToMove, $toMove);
                $removedUuids = array_merge($removedUuids, $removed);
            }
        }

        $payload = $validated;

        if ($incomingLKProvided && array_key_exists('lembarKontrol', $payload) && is_null($payload['lembarKontrol'])) {
            unset($payload['lembarKontrol']);
        }
        if ($incomingDokProvided && array_key_exists('dokumentasi', $payload) && is_null($payload['dokumentasi'])) {
            unset($payload['dokumentasi']);
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

        if ($uuidsToMove) {
            $this->moveTempsToFinal(array_values(array_unique($uuidsToMove)), (string)$user->id);
        }

        if ($removedUuids) {
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

    private function normalizeUuidArrayField(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);
        if ($val === null || is_array($val)) return;

        if (is_string($val)) {
            $t = trim($val);
            $uuids = [];

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

            if (str_contains($t, ',')) {
                foreach (array_map('trim', explode(',', $t)) as $p) {
                    if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/', $p, $m)) {
                        $uuids[] = strtolower($m[0]);
                    }
                }
                $request->merge([$field => array_values(array_unique($uuids))]);
                return;
            }

            if (preg_match('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}/', $t, $m)) {
                $request->merge([$field => [strtolower($m[0])]]);
            }
        }
    }

    /**
     * Cari usulan di beberapa tabel kandidat dengan kunci fleksibel
     * (Sekarang termasuk TpuSerahTerima)
     */
    private function findUsulanByUuid(string $value): ?object
    {
        $candidates = [
            UsulanFisikBSL::class,
            PSUUsulanFisikPerumahan::class,
            PsuSerahTerima::class,       // PSU serah terima perumahan
            TpuSerahTerima::class,       // <== NEW: PSU serah terima TPU
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
            if (!$temp) continue;

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
