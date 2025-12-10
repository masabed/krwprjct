<?php

namespace App\Http\Controllers\Api\Pengawasan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

use App\Models\Pengawasan;
use App\Models\User;

// Upload models
use App\Models\PengawasanUpload;
use App\Models\PengawasanUploadTemp;

// Usulan kandidat (validasi keberadaan uuidUsulan)
use App\Models\UsulanFisikBSL;
use App\Models\PsuSerahTerima;
use App\Models\PSUUsulanFisikPerumahan;
use App\Models\PSUUsulanFisikTPU;
use App\Models\PSUUsulanFisikPJL;
use App\Models\Permukiman;
use App\Models\PerumahanDb;
use App\Models\Rutilahu;
use App\Models\Pembangunan;
use App\Models\SAPDLahanMasyarakat;
use App\Models\UsulanSAPDSIndividual;
use App\Models\UsulanSAPDSFasilitasUmum;

class PengawasanController extends Controller
{
    /**
     * GET /api/pengawasan
     * Optional: ?uuidUsulan=...&uuidPembangunan=...
     * Role:
     * - admin/admin_bidang: semua
     * - pengawas: hanya miliknya
     */
    public function index(Request $request)
    {
        $auth = $request->user();
        if (!$auth) return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);

        $request->validate([
            'uuidUsulan'      => ['sometimes','uuid'],
            'uuidPembangunan' => ['sometimes','uuid'],
        ]);

        $role   = strtolower((string) ($auth->role ?? ''));
        $isPriv = in_array($role, ['admin','admin_bidang'], true);

        $q = Pengawasan::query()->latest();

        if (!$isPriv) {
            $q->where('pengawas', (string) $auth->id);
        }
        if ($request->filled('uuidUsulan')) {
            $q->where('uuidUsulan', $request->query('uuidUsulan'));
        }
        if ($request->filled('uuidPembangunan')) {
            $q->where('uuidPembangunan', $request->query('uuidPembangunan'));
        }

        $rows = $q->get()->map(function ($r) {
            return [
                'id'                 => (string) $r->id,
                'uuidUsulan'         => (string) $r->uuidUsulan,
                'uuidPembangunan'    => (string) $r->uuidPembangunan,
                'pengawas'           => (string) $r->pengawas,
                'pengawas_name'      => $this->resolveUserName($r->pengawas),
                'tanggal_pengawasan' => $r->tanggal_pengawasan,
                'foto'               => $r->foto ?? [],
                'pesan_pengawasan'   => $r->pesan_pengawasan,
                'created_at'         => $r->created_at,
                'updated_at'         => $r->updated_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** GET /api/pengawasan/{id} */
    public function show(string $id)
    {
        $auth = auth()->user();
        if (!$auth) return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);

        $row = Pengawasan::find($id);
        if (!$row) return response()->json(['success'=>false,'message'=>'Data tidak ditemukan'], 404);

        $role    = strtolower((string) ($auth->role ?? ''));
        $isPriv  = in_array($role, ['admin','admin_bidang'], true);
        $isOwner = (string)$row->pengawas === (string)$auth->id;

        if (!$isPriv && !$isOwner) {
            return response()->json(['success'=>false,'message'=>'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'                 => (string) $row->id,
                'uuidUsulan'         => (string) $row->uuidUsulan,
                'uuidPembangunan'    => (string) $row->uuidPembangunan,
                'pengawas'           => (string) $row->pengawas,
                'pengawas_name'      => $this->resolveUserName($row->pengawas),
                'tanggal_pengawasan' => $row->tanggal_pengawasan,
                'foto'               => $row->foto ?? [],
                'pesan_pengawasan'   => $row->pesan_pengawasan,
                'created_at'         => $row->created_at,
                'updated_at'         => $row->updated_at,
            ],
        ]);
    }

    /**
     * POST /api/pengawasan/upload
     * Upload ke TEMP → return UUID (dipakai untuk field "foto")
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10512',
        ]);

        $user = $request->user();
        if (!$user) return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);

        $uuid = (string) Str::uuid();
        $file = $request->file('file');

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $timestamp = now()->format('Ymd_His');
        $basename  = "{$timestamp}_{$uuid}.{$ext}";
        // gunakan disk 'local' untuk konsistensi
        $path      = $file->storeAs('pengawasan_temp', $basename, 'local');

        PengawasanUploadTemp::create([
            'uuid'          => $uuid,
            'user_id'       => (string) $user->id,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime'          => $file->getClientMimeType(),
            'size'          => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'uuid'    => $uuid,
                'user_id' => (string) $user->id,
            ],
        ], 201);
    }

    /**
     * GET /api/pengawasan/file/{uuid}?download=1
     * Stream FINAL → fallback TEMP
     */
    public function fileShow(string $uuid, Request $request)
    {
        $file = PengawasanUpload::where('uuid', $uuid)->first()
             ?: PengawasanUploadTemp::where('uuid', $uuid)->first();

        if (!$file) return response()->json(['success'=>false,'message'=>'File not found'], 404);

        $disk = $this->disk(); // konsisten pakai 'local'
        if (!$disk->exists($file->file_path)) {
            return response()->json(['success'=>false,'message'=>'File missing'], 404);
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
     * GET /api/pengawasan/file/preview/{uuid}
     * Preview fixed-size:
     *  - image/*  → resize lebar 512px (JPEG)
     *  - PDF      → halaman pertama jadi JPEG lebar 512px
     * Optional: ?source=final|temp (default: final→temp)
     */
    public function filePreview(string $uuid, Request $request)
    {
        $source = $request->query('source'); // final | temp | null
        $disk   = $this->disk(); // Storage::disk('local')

        // Cari record upload
        if ($source === 'final') {
            $file = PengawasanUpload::where('uuid', $uuid)->first();
        } elseif ($source === 'temp') {
            $file = PengawasanUploadTemp::where('uuid', $uuid)->first();
        } else {
            $file = PengawasanUpload::where('uuid', $uuid)->first()
                 ?: PengawasanUploadTemp::where('uuid', $uuid)->first();
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

        // Absolute path + mime
        $abs = method_exists($disk, 'path')
            ? $disk->path($file->file_path)
            : storage_path('app/'.$file->file_path);

        $mime = $file->mime
            ?: ($disk->mimeType($file->file_path)
            ?: (function_exists('mime_content_type') ? @mime_content_type($abs) : null));

        $w = 512; // target width

        // ====================== IMAGE PREVIEW ======================
        if ($mime && str_starts_with($mime, 'image/')) {
            // Coba pakai Intervention Image jika ada
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
                        'Content-Type'        => 'image/jpeg',
                        'X-Content-Type-Options' => 'nosniff',
                        'Cache-Control'       => 'private, max-age=86400',
                        'X-Preview-Engine'    => 'intervention',
                        'X-Preview-Bytes'     => (string) strlen($binary),
                    ]);
                } catch (\Throwable $e) {
                    // fallback ke GD
                }
            }

            // Fallback: GD murni
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
                                    'Content-Type'        => 'image/jpeg',
                                    'X-Content-Type-Options' => 'nosniff',
                                    'Cache-Control'       => 'private, max-age=86400',
                                    'X-Preview-Engine'    => 'gd',
                                    'X-Preview-Bytes'     => (string) strlen($binary),
                                ]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // fallthrough ke error di bawah
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
                    $im->readImage($abs . '[0]'); // halaman pertama
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
                        'Content-Type'        => 'image/jpeg',
                        'Content-Length'      => (string) $size,
                        'X-Content-Type-Options' => 'nosniff',
                        'Cache-Control'       => 'private, max-age=86400',
                        'X-Preview-Engine'    => 'imagick',
                        'X-Preview-Bytes'     => (string) $size,
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('Pengawasan PDF thumbnail failed', [
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

        // ====================== UNSUPPORTED TYPE ======================
        return response()->json([
            'success' => false,
            'message' => 'Preview only supports images and PDFs.',
        ], 415);
    }

    /**
     * DELETE /api/pengawasan/file/{uuid}
     * ?source=final|temp
     */
    public function fileDestroy(string $uuid, Request $request)
    {
        $auth = $request->user();
        if (!$auth) {
            return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);
        }

        $source  = $request->query('source'); // 'final' | 'temp' | null
        $results = [];
        $disk    = $this->disk(); // konsisten

        $deleteOne = function (string $modelClass, string $label) use ($uuid, $disk, &$results) {
            $rec = $modelClass::where('uuid', $uuid)->first();
            if (!$rec) { $results[$label] = ['found' => false]; return false; }

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
            $deleted = $deleteOne(PengawasanUpload::class, 'final');
        } elseif ($source === 'temp') {
            $deleted = $deleteOne(PengawasanUploadTemp::class, 'temp');
        } else {
            // default: coba final dulu, kalau tidak ada, baru temp
            $deleted = $deleteOne(PengawasanUpload::class, 'final');
            if (!$deleted) {
                $deleted = $deleteOne(PengawasanUploadTemp::class, 'temp');
            }
        }

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in final or temp',
                'result'  => $results
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'File deleted',
            'result'  => $results
        ]);
    }

    /**
     * Daftar pembangunan (simple) + detail uuidUsulan (sumber & lokasi)
     * GET /api/pengawasan/pembangunan-index-simple
     */
/**
 * GET /api/pengawasan/pembangunan-index-simple
 *
 * Output: satu baris per uuidUsulan (flatten),
 * fields:
 * - uuidUsulan
 * - uuidPembangunan
 * - noSPK
 * - pengawasLapangan
 * - namaPengawasLapangan
 * - kecamatan
 * - kelurahan
 * - keterangan (asal tabel usulan)
 */
public function pembangunanIndexSimple(Request $request)
{
    $auth = $request->user();
    if (!$auth) {
        return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
    }

    $authId = (string) ($auth->id ?? '');
    $role   = strtolower((string) ($auth->role ?? ''));
    $isPriv = in_array($role, ['admin','admin_bidang'], true);

    // --- Query dasar Pembangunan ---
    $table = (new \App\Models\Pembangunan)->getTable();
    $q = \App\Models\Pembangunan::query()->latest('created_at');

    // Non-admin: batasi ke pembangunan milik pengawas ini (cover camelCase & snake_case)
    if (!$isPriv) {
        $q->where(function($w) use ($table, $authId) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'pengawasLapangan')) {
                $w->orWhere('pengawasLapangan', $authId);
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'pengawas_lapangan')) {
                $w->orWhere('pengawas_lapangan', $authId);
            }
        });
    }

    $pembangunanRows = $q->get();

    // --- Prefetch user untuk resolusi nama pengawas (hindari N+1) ---
    $pengawasKeys = collect()
        ->merge($pembangunanRows->pluck('pengawasLapangan'))
        ->merge($pembangunanRows->pluck('pengawas_lapangan'))
        ->filter(fn($v) => !empty($v))
        ->map(fn($v) => (string) $v)
        ->unique()
        ->values();

    $usersById   = collect();
    $usersByUuid = collect();

    if ($pengawasKeys->isNotEmpty() && class_exists(\App\Models\User::class)) {
        try {
            $usersById = \App\Models\User::select('id','name','username')
                ->whereIn('id', $pengawasKeys)
                ->get()
                ->keyBy(fn($u) => (string)$u->id);
        } catch (\Throwable $e) {}

        try {
            $userTable = (new \App\Models\User)->getTable();
            if (\Illuminate\Support\Facades\Schema::hasColumn($userTable, 'uuid')) {
                $usersByUuid = \App\Models\User::select('uuid','name','username')
                    ->whereIn('uuid', $pengawasKeys)
                    ->get()
                    ->keyBy(fn($u) => (string)$u->uuid);
            }
        } catch (\Throwable $e) {}
    }

    // --- Helper: parse uuidUsulan jadi array UUID/string ---
    $parseUuidList = function($val): array {
        if (is_null($val)) return [];
        if (is_array($val)) {
            return array_values(array_unique(array_filter(array_map('strval', $val))));
        }
        $t = trim((string)$val);
        if ($t === '') return [];
        if ($t[0] === '[') {
            $arr = json_decode($t, true);
            return is_array($arr)
                ? array_values(array_unique(array_filter(array_map('strval', $arr))))
                : [];
        }
        if (str_contains($t, ',')) {
            return array_values(array_unique(array_filter(array_map('trim', explode(',', $t)))));
        }
        return [$t];
    };

    // =====================================================================
    // 1) FLATTEN: dari row pembangunan → banyak row per uuidUsulan
    // =====================================================================
    $allIds = collect();
    $flat   = collect(); // satu element = satu baris per uuidUsulan

    foreach ($pembangunanRows as $p) {
        $uuList = $parseUuidList($p->uuidUsulan ?? null);
        if (empty($uuList)) continue;

        // No SPK (fallback beberapa nama kolom)
        $noSpk = $p->nomorSPK ?? $p->nomor_spk ?? $p->no_spk ?? $p->spk ?? null;

        // Pengawas (uuid/id) + nama (fallback: kolom *_name → usersById → usersByUuid)
        $pengawasLapangan = (string) ($p->pengawasLapangan ?? $p->pengawas_lapangan ?? '');
        $denormName = $p->pengawasLapangan_name ?? $p->pengawas_lapangan_name ?? null;
        $u = $usersById->get($pengawasLapangan) ?? $usersByUuid->get($pengawasLapangan);
        $namaPengawas = $denormName ?? ($u->name ?? $u->username ?? null);

        foreach ($uuList as $uu) {
            $uuStr = (string) $uu;
            if ($uuStr === '') continue;

            $allIds->push($uuStr);

            $flat->push([
                'uuidUsulan'           => $uuStr,
                'uuidPembangunan'      => (string) $p->id,
                'noSPK'                => $noSpk,
                'pengawasLapangan'     => $pengawasLapangan,
                'namaPengawasLapangan' => $namaPengawas,
            ]);
        }
    }

    $allIds = $allIds->filter()->unique()->values();

    // Kalau tidak ada data sama sekali:
    if ($flat->isEmpty()) {
        return response()->json(['success' => true, 'data' => []]);
    }

    // =====================================================================
    // 2) BANGUN META PER uuidUsulan (kecamatan, kelurahan, titikLokasi, keterangan)
    // =====================================================================
    $meta = collect(); // key: usulanUUID => array info

    $mergeMeta = function(\Illuminate\Support\Collection &$bag, string $key, array $in) {
        if ($key === '') return;
        $cur = $bag->get($key, []);
        foreach ($in as $k => $v) {
            if (!array_key_exists($k, $cur) || $cur[$k] === null || $cur[$k] === '') {
                $cur[$k] = $v;
            }
        }
        $bag->put($key, $cur);
    };

    $cols = function(string $table, array $wants): array {
        return array_values(array_filter($wants, fn($c) => \Illuminate\Support\Facades\Schema::hasColumn($table, $c)));
    };

    // A) PSU PERUMAHAN  (join ke PerumahanDb)
    if ($allIds->isNotEmpty() && class_exists(\App\Models\PSUUsulanFisikPerumahan::class)) {
        $mdl   = new \App\Models\PSUUsulanFisikPerumahan;
        $t     = $mdl->getTable();
        $hasUuid = \Illuminate\Support\Facades\Schema::hasColumn($t, 'uuid');
        $sel   = $cols($t, ['uuid','id','perumahanId','created_at']);

        $qPerum = \App\Models\PSUUsulanFisikPerumahan::query();
        if ($hasUuid) {
            $qPerum->whereIn('uuid', $allIds);
        } else {
            $qPerum->whereIn('id', $allIds);
        }
        $perumUsulanRows = $qPerum->get($sel);

        $perumahanIds = $perumUsulanRows->pluck('perumahanId')->filter()->unique()->values();

        $perumMeta = collect();
        if ($perumahanIds->isNotEmpty() && class_exists(\App\Models\PerumahanDb::class)) {
            $perumRows = \App\Models\PerumahanDb::query()
                ->whereIn('id', $perumahanIds)
                ->get(['id','kecamatan','kelurahan','titikLokasi'])
                ->keyBy('id');
            $perumMeta = $perumRows->map(fn($r) => [
                'kecamatan'   => $r->kecamatan,
                'kelurahan'   => $r->kelurahan,
                'titikLokasi' => $r->titikLokasi,
            ]);
        }

        foreach ($perumUsulanRows as $r) {
            $key = $hasUuid ? (string) $r->uuid : (string) $r->id;
            $pm  = $perumMeta->get((string)$r->perumahanId, [
                'kecamatan'   => null,
                'kelurahan'   => null,
                'titikLokasi' => null,
            ]);
            $mergeMeta($meta, $key, array_merge([
                'ket'         => 'PSU Perumahan',
                'perumahanId' => $r->perumahanId ?? null,
                'created_at'  => $r->created_at ?? null,
            ], $pm));
        }
    }

    // B) PSU PJL
    if ($allIds->isNotEmpty() && class_exists(\App\Models\PSUUsulanFisikPJL::class)) {
        $mdl = new \App\Models\PSUUsulanFisikPJL;
        $t   = $mdl->getTable();
        $hasUuid = \Illuminate\Support\Facades\Schema::hasColumn($t, 'uuid');
        $sel = $cols($t, ['id','uuid','kecamatanUsulan','kelurahanUsulan','titikLokasiUsulan','created_at']);

        $q = \App\Models\PSUUsulanFisikPJL::query();
        $hasUuid ? $q->whereIn('uuid', $allIds) : $q->whereIn('id', $allIds);
        foreach ($q->get($sel) as $r) {
            $key = $hasUuid ? (string)$r->uuid : (string)$r->id;
            $mergeMeta($meta, $key, [
                'ket'         => 'PSU PJL',
                'kecamatan'   => $r->kecamatanUsulan ?? null,
                'kelurahan'   => $r->kelurahanUsulan ?? null,
                'titikLokasi' => $r->titikLokasiUsulan ?? null,
                'created_at'  => $r->created_at ?? null,
            ]);
        }
    }

    // C) PSU TPU
    if ($allIds->isNotEmpty() && class_exists(\App\Models\PSUUsulanFisikTPU::class)) {
        $mdl = new \App\Models\PSUUsulanFisikTPU;
        $t   = $mdl->getTable();
        $hasUuid = \Illuminate\Support\Facades\Schema::hasColumn($t, 'uuid');
        $sel = $cols($t, ['id','uuid','kecamatanUsulan','kelurahanUsulan','titikLokasiUsulan','created_at']);

        $q = \App\Models\PSUUsulanFisikTPU::query();
        $hasUuid ? $q->whereIn('uuid', $allIds) : $q->whereIn('id', $allIds);
        foreach ($q->get($sel) as $r) {
            $key = $hasUuid ? (string)$r->uuid : (string)$r->id;
            $mergeMeta($meta, $key, [
                'ket'         => 'PSU TPU',
                'kecamatan'   => $r->kecamatanUsulan ?? null,
                'kelurahan'   => $r->kelurahanUsulan ?? null,
                'titikLokasi' => $r->titikLokasiUsulan ?? null,
                'created_at'  => $r->created_at ?? null,
            ]);
        }
    }

    // D) PSU BSL
    if ($allIds->isNotEmpty() && class_exists(\App\Models\UsulanFisikBSL::class)) {
        $mdl = new \App\Models\UsulanFisikBSL;
        $t   = $mdl->getTable();
        $hasUuid = \Illuminate\Support\Facades\Schema::hasColumn($t, 'uuid');
        $sel = $cols($t, ['id','uuid','kecamatanUsulan','kelurahanUsulan','titikLokasiUsulan','created_at']);

        $q = \App\Models\UsulanFisikBSL::query();
        if ($hasUuid) {
            $q->whereIn('uuid', $allIds)->orWhereIn('id', $allIds);
        } else {
            $q->whereIn('id', $allIds);
        }

        foreach ($q->get($sel) as $r) {
            $key = $hasUuid ? (string) $r->uuid : (string) $r->id;
            $mergeMeta($meta, $key, [
                'ket'         => 'PSU BSL',
                'kecamatan'   => $r->kecamatanUsulan ?? null,
                'kelurahan'   => $r->kelurahanUsulan ?? null,
                'titikLokasi' => $r->titikLokasiUsulan ?? null,
                'created_at'  => $r->created_at ?? null,
            ]);
        }
    }

    // E) SANPAM FASUM
    if ($allIds->isNotEmpty() && class_exists(\App\Models\UsulanSAPDSFasilitasUmum::class)) {
        $mdl = new \App\Models\UsulanSAPDSFasilitasUmum;
        $t   = $mdl->getTable();
        $sel = $cols($t, ['uuid','kecamatan','kelurahan','titikLokasi','created_at']);

        foreach (\App\Models\UsulanSAPDSFasilitasUmum::whereIn('uuid', $allIds)->get($sel) as $r) {
            $mergeMeta($meta, (string)$r->uuid, [
                'ket'         => 'Sanpam Fasilitas Umum',
                'kecamatan'   => $r->kecamatan ?? null,
                'kelurahan'   => $r->kelurahan ?? null,
                'titikLokasi' => $r->titikLokasi ?? null,
                'created_at'  => $r->created_at ?? null,
            ]);
        }
    }

    // F) SANPAM INDIVIDUAL
    if ($allIds->isNotEmpty() && class_exists(\App\Models\UsulanSAPDSIndividual::class)) {
        $mdl = new \App\Models\UsulanSAPDSIndividual;
        $t   = $mdl->getTable();
        $sel = $cols($t, ['uuid','kecamatan','kelurahan','titikLokasi','created_at']);

        foreach (\App\Models\UsulanSAPDSIndividual::whereIn('uuid', $allIds)->get($sel) as $r) {
            $mergeMeta($meta, (string)$r->uuid, [
                'ket'         => 'Sanpam Individual',
                'kecamatan'   => $r->kecamatan ?? null,
                'kelurahan'   => $r->kelurahan ?? null,
                'titikLokasi' => $r->titikLokasi ?? null,
                'created_at'  => $r->created_at ?? null,
            ]);
        }
    }

    // G) SANPAM LAHAN MASYARAKAT
    if ($allIds->isNotEmpty() && class_exists(\App\Models\SAPDLahanMasyarakat::class)) {
        $mdl = new \App\Models\SAPDLahanMasyarakat;
        $t   = $mdl->getTable();
        $sel = $cols($t, ['uuid','kecamatan','kelurahan','titikLokasi','created_at']);

        foreach (\App\Models\SAPDLahanMasyarakat::whereIn('uuid', $allIds)->get($sel) as $r) {
            $mergeMeta($meta, (string)$r->uuid, [
                'ket'         => 'Sanpam Sarana Air',
                'kecamatan'   => $r->kecamatan ?? null,
                'kelurahan'   => $r->kelurahan ?? null,
                'titikLokasi' => $r->titikLokasi ?? null,
                'created_at'  => $r->created_at ?? null,
            ]);
        }
    }

    // H) PERMUKIMAN
    if ($allIds->isNotEmpty() && class_exists(\App\Models\Permukiman::class)) {
        $mdl = new \App\Models\Permukiman;
        $t   = $mdl->getTable();
        $sel = $cols($t, ['id','kecamatan','kelurahan','titik_lokasi','created_at']);

        foreach (\App\Models\Permukiman::whereIn('id', $allIds)->get($sel) as $r) {
            $mergeMeta($meta, (string)$r->id, [
                'ket'         => 'Permukiman',
                'kecamatan'   => $r->kecamatan ?? null,
                'kelurahan'   => $r->kelurahan ?? null,
                'titikLokasi' => $r->titik_lokasi ?? null,
                'created_at'  => $r->created_at ?? null,
            ]);
        }
    }

    // I) RUTILAHU
    if ($allIds->isNotEmpty() && class_exists(\App\Models\Rutilahu::class)) {
        $mdl = new \App\Models\Rutilahu;
        $t   = $mdl->getTable();
        $hasUuid = \Illuminate\Support\Facades\Schema::hasColumn($t, 'uuid');
        // tambahkan titikLokasi/titik_lokasi kalau ada
        $sel = $cols($t, ['id','uuid','kecamatan','kelurahan','titikLokasi','titik_lokasi','created_at']);

        $q = \App\Models\Rutilahu::query();
        $hasUuid ? $q->whereIn('uuid', $allIds) : $q->whereIn('id', $allIds);
        foreach ($q->get($sel) as $r) {
            $key = $hasUuid ? (string)$r->uuid : (string)$r->id;
            $mergeMeta($meta, $key, [
                'ket'         => 'Rutilahu',
                'kecamatan'   => $r->kecamatan ?? null,
                'kelurahan'   => $r->kelurahan ?? null,
                'titikLokasi' => $r->titikLokasi ?? $r->titik_lokasi ?? null,
                'created_at'  => $r->created_at ?? null,
            ]);
        }
    }

    // =====================================================================
    // 3) GABUNGKAN flat rows + meta per uuidUsulan
    // =====================================================================
    $rows = $flat->map(function (array $row) use ($meta) {
        $m = $meta->get($row['uuidUsulan'], null);

        return [
            'uuidUsulan'           => $row['uuidUsulan'],
            'uuidPembangunan'      => $row['uuidPembangunan'],
            'noSPK'                => $row['noSPK'],
            'pengawasLapangan'     => $row['pengawasLapangan'],
            'namaPengawasLapangan' => $row['namaPengawasLapangan'],
            'kecamatan'            => $m['kecamatan']   ?? null,
            'kelurahan'            => $m['kelurahan']   ?? null,
            'titikLokasi'          => $m['titikLokasi'] ?? null,
            'keterangan'           => $m['ket']         ?? 'unknown',
        ];
    });

    return response()->json([
        'success' => true,
        'data'    => $rows->values(),
    ]);
}

    /**
     * POST /api/pengawasan/create
     * Body:
     *  - uuidUsulan (uuid, required)
     *  - uuidPembangunan (uuid, required)
     *  - tanggal_pengawasan (date, required)
     *  - pesan_pengawasan (string<=255, optional)
     *  - foto (array<uuid> TEMP uuids | string JSON/CSV/single, optional)
     * Catatan: field 'pengawas' diisi otomatis dari JWT.
     */
    public function store(Request $request)
    {
        $auth = $request->user();
        if (!$auth) return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);

        $role = strtolower((string) ($auth->role ?? ''));
        if (!in_array($role, ['admin','admin_bidang','pengawas'], true)) {
            return response()->json(['success'=>false,'message'=>'Unauthorized'], 403);
        }

        // Normalisasi 'foto' lebih dulu agar validasi 'array' tidak membuang field
        $this->normalizeUuidArrayField($request, 'foto');

        // 'pengawas' TIDAK divalidasi—selalu diisi dari JWT
        $validated = $request->validate([
            'uuidUsulan'         => ['required','uuid'],
            'uuidPembangunan'    => ['required','uuid'],
            'tanggal_pengawasan' => ['required','date'],
            'pesan_pengawasan'   => ['sometimes','nullable','string','max:255'],
            'foto'               => ['sometimes','array','max:50'],
            'foto.*'             => ['uuid'],
        ]);

        // Pastikan uuidUsulan ada
        $usulan = $this->findUsulanByUuid($validated['uuidUsulan']);
        if (!$usulan) {
            return response()->json(['success'=>false,'message'=>'Usulan tidak ditemukan untuk uuidUsulan yang diberikan.'], 422);
        }

        // Sanitize foto (unique)
        $foto = array_values(array_unique(array_filter($validated['foto'] ?? [])));

        $row = null;
        DB::transaction(function () use (&$row, $validated, $foto, $auth) {
            $row = Pengawasan::create([
                'uuidUsulan'         => $validated['uuidUsulan'],
                'uuidPembangunan'    => $validated['uuidPembangunan'],
                'pengawas'           => (string) $auth->id, // ← AUTO dari JWT
                'tanggal_pengawasan' => $validated['tanggal_pengawasan'],
                'pesan_pengawasan'   => $validated['pesan_pengawasan'] ?? null,
                'foto'               => $foto,
            ]);

            if ($foto) {
                $this->moveTempsToFinal($foto, (string)$auth->id);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Pengawasan berhasil dibuat',
            'data'    => $row->fresh(),
        ], 201);
    }

    /**
     * POST/PUT/PATCH /api/pengawasan/update/{id}
     * - Admin/Admin_Bidang: bebas
     * - Pengawas: hanya miliknya; tidak boleh ganti pengawas ke id lain
     */
    public function update(Request $request, string $id)
    {
        $auth = $request->user();
        if (!$auth) return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);

        $row = Pengawasan::find($id);
        if (!$row) return response()->json(['success'=>false,'message'=>'Data tidak ditemukan'], 404);

        $role    = strtolower((string) ($auth->role ?? ''));
        $isAdmin = in_array($role, ['admin','admin_bidang'], true);
        $isOwner = (string)$row->pengawas === (string)$auth->id;

        if (!$isAdmin && !$isOwner) {
            return response()->json(['success'=>false,'message'=>'Unauthorized'], 403);
        }

        // Normalisasi 'foto' sebelum validasi
        $this->normalizeUuidArrayField($request, 'foto');

        $validated = $request->validate([
            'uuidUsulan'         => ['sometimes','uuid'],
            'uuidPembangunan'    => ['sometimes','uuid'],
            'pengawas'           => ['sometimes','string','max:64'],
            'tanggal_pengawasan' => ['sometimes','date'],
            'pesan_pengawasan'   => ['sometimes','nullable','string','max:255'],
            'foto'               => ['sometimes','nullable','array','max:50'],
            'foto.*'             => ['uuid'],
        ]);

        // Pengawas (non-admin) tidak boleh set pengawas != dirinya
        if (!$isAdmin && array_key_exists('pengawas', $validated) &&
            (string)$validated['pengawas'] !== (string)$auth->id) {
            return response()->json(['success'=>false,'message'=>'Forbidden: pengawas mismatch'], 403);
        }

        // Validasi uuidUsulan jika diganti
        if (array_key_exists('uuidUsulan', $validated)) {
            $u = $this->findUsulanByUuid($validated['uuidUsulan']);
            if (!$u) {
                return response()->json(['success'=>false,'message'=>'Usulan tidak ditemukan untuk uuidUsulan yang diberikan.'], 422);
            }
        }

        // Hitung perubahan file (jika foto dikirim)
        $uuidsToMove = $removedUuids = [];
        $incomingProvided = array_key_exists('foto', $validated);

        if ($incomingProvided) {
            if (is_null($validated['foto'])) {
                unset($validated['foto']); // jangan overwrite jadi null
            } else {
                $incoming = array_values(array_unique(array_filter($validated['foto'])));
                $existing = is_array($row->foto) ? $row->foto : [];
                $uuidsToMove  = array_values(array_diff($incoming, $existing));
                $removedUuids = array_values(array_diff($existing, $incoming));
                $validated['foto'] = $incoming;
            }
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

        DB::transaction(function () use (&$row, $dirty, $uuidsToMove, $removedUuids, $auth) {
            $row->save();

            if ($uuidsToMove) {
                $this->moveTempsToFinal($uuidsToMove, (string)$auth->id);
            }
            if ($removedUuids) {
                $this->deleteFinalFiles($removedUuids);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Field berikut berhasil diperbarui: ' . implode(', ', array_keys($dirty)),
            'data'    => $row->fresh(),
        ]);
    }

    /** DELETE /api/pengawasan/{id} (admin/admin_bidang only) */
    public function destroy(string $id)
    {
        $auth = auth()->user();
        if (!$auth) return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);

        $role = strtolower((string) ($auth->role ?? ''));
        if (!in_array($role, ['admin','admin_bidang'], true)) {
            return response()->json(['success'=>false,'message'=>'Unauthorized'], 403);
        }

        $row = Pengawasan::find($id);
        if (!$row) return response()->json(['success'=>false,'message'=>'Data tidak ditemukan'], 404);

        $this->deleteFinalFiles(is_array($row->foto) ? $row->foto : []);
        $row->delete();

        return response()->json(['success'=>true,'message'=>'Data berhasil dihapus']);
    }

    // ============================== Helpers ==============================

    private function resolveUserName(?string $key): ?string
    {
        if (!$key || !class_exists(User::class)) return null;

        try {
            $u = User::select('id','name','username')->where('id', $key)->first();
            if ($u) return $u->name ?? $u->username ?? null;

            $userTable = (new User)->getTable();
            if (Schema::hasColumn($userTable, 'uuid')) {
                $u = User::select('uuid','name','username')->where('uuid', $key)->first();
                if ($u) return $u->name ?? $u->username ?? null;
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /** Konsisten pakai disk 'local' untuk semua operasi file */
    private function disk()
    {
        return Storage::disk('local');
    }

    /** Terima "foto" sebagai JSON array / CSV / single → array UUID */
    private function normalizeUuidArrayField(Request $request, string $field): void
    {
        if (!$request->has($field)) return;

        $val = $request->input($field);
        if ($val === null || is_array($val)) return; // sudah array / null

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

    /** Pindahkan UUID dari TEMP → FINAL (pengawasan) */
    private function moveTempsToFinal(array $fileUuids, string $userId): void
    {
        $fileUuids = array_values(array_unique(array_filter($fileUuids)));
        if (!$fileUuids) return;

        $temps = PengawasanUploadTemp::whereIn('uuid', $fileUuids)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('uuid');

        $disk = $this->disk();

        foreach ($fileUuids as $u) {
            $temp = $temps->get($u);
            if (!$temp) continue; // mungkin sudah final / bukan milik user

            $oldPath = $temp->file_path;
            $ext     = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION) ?: 'bin');
            $newPath = 'pengawasan_final/'.$u.'.'.$ext;

            if ($disk->exists($oldPath)) {
                $disk->move($oldPath, $newPath);
            } elseif (!$disk->exists($newPath)) {
                continue;
            }

            PengawasanUpload::updateOrCreate(
                ['uuid' => $u],
                [
                    'user_id'       => (string) $userId,
                    'file_path'     => $newPath,
                    'original_name' => $temp->original_name,
                    'mime'          => $temp->mime,
                    'size'          => $temp->size,
                ]
            );

            $temp->delete();
        }
    }

    /** Hapus file FINAL & row upload-nya */
    private function deleteFinalFiles(array $uuids): void
    {
        $uuids = array_values(array_unique(array_filter($uuids)));
        if (!$uuids) return;

        $disk = $this->disk();

        foreach ($uuids as $u) {
            $upload = PengawasanUpload::where('uuid', $u)->first();
            if ($upload) {
                if ($upload->file_path && $disk->exists($upload->file_path)) {
                    $disk->delete($upload->file_path);
                }
                $upload->delete();
            }
        }
    }

    /**
     * Cari usulan di beberapa tabel kandidat (fleksibel key: PK/uuid/id)
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
}
