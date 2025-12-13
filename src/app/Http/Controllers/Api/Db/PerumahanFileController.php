<?php

namespace App\Http\Controllers\Api\Db;

use App\Http\Controllers\Controller;
use App\Models\PerumahanUpload;
use App\Models\PerumahanUploadTemp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerumahanFileController extends Controller
{
    // View FINAL file by upload UUID (PRIVATE)
    // GET /api/perumahan-db/file/{uuid}?download=1
    public function showByUuid(string $uuid, Request $request)
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $file = PerumahanUpload::where('uuid', $uuid)->first();
        if (!$file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        // Enforce ownership; adjust if you want broader access or roles
        if ((string)$file->user_id !== (string)$userId) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $disk = Storage::disk('local'); // PRIVATE
        $path = $file->file_path;

        if (!Str::startsWith($path, 'perumahan_final/')) {
            return response()->json(['success' => false, 'message' => 'Invalid file location'], 403);
        }
        if (!$disk->exists($path)) {
            return response()->json(['success' => false, 'message' => 'File missing'], 404);
        }

        $mime        = $disk->mimeType($path) ?? 'application/octet-stream';
        $name        = basename($path);
        $download    = $request->boolean('download');
        $disposition = $download ? 'attachment' : 'inline';

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => $disposition . '; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                 => 'no-cache',
        ]);
    }

    // View TEMP file by temp UUID (PRIVATE preview before finalize)
    // GET /api/perumahan-db/file/temp/{uuid}?download=1
    public function showTempByUuid(string $uuid, Request $request)
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $file = PerumahanUploadTemp::where('uuid', $uuid)->first();
        if (!$file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        // Temp files are strictly owner-only
        if ((string)$file->user_id !== (string)$userId) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $disk = Storage::disk('local'); // PRIVATE
        $path = $file->file_path;

        if (!Str::startsWith($path, 'perumahan_temp/')) {
            return response()->json(['success' => false, 'message' => 'Invalid file location'], 403);
        }
        if (!$disk->exists($path)) {
            return response()->json(['success' => false, 'message' => 'File missing'], 404);
        }

        $mime        = $disk->mimeType($path) ?? 'application/octet-stream';
        $name        = basename($path);
        $download    = $request->boolean('download');
        $disposition = $download ? 'attachment' : 'inline';

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => $disposition . '; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                 => 'no-cache',
        ]);
    }

    /**
     * PREVIEW (thumbnail) FINAL/TEMP file by UUID (PRIVATE)
     *
     * GET /api/perumahan-db/file/preview/{uuid}
     * Optional: ?source=final|temp (default: final→temp)
     * Output: JPEG image (512px width) untuk image/* atau halaman pertama PDF
     */
    public function preview(string $uuid, Request $request)
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $source = $request->query('source'); // final | temp | null
        $disk   = Storage::disk('local');    // PRIVATE

        // Cari record upload
        if ($source === 'final') {
            $file = PerumahanUpload::where('uuid', $uuid)->first();
        } elseif ($source === 'temp') {
            $file = PerumahanUploadTemp::where('uuid', $uuid)->first();
        } else {
            $file = PerumahanUpload::where('uuid', $uuid)->first()
                 ?: PerumahanUploadTemp::where('uuid', $uuid)->first();
        }

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in FINAL or TEMP',
            ], 404);
        }

        // Enforce ownership
        if ((string)$file->user_id !== (string)$userId) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $path = $file->file_path;

        // Validasi lokasi path sesuai jenis file
        if ($file instanceof PerumahanUpload && !Str::startsWith($path, 'perumahan_final/')) {
            return response()->json(['success' => false, 'message' => 'Invalid file location'], 403);
        }
        if ($file instanceof PerumahanUploadTemp && !Str::startsWith($path, 'perumahan_temp/')) {
            return response()->json(['success' => false, 'message' => 'Invalid file location'], 403);
        }

        if (!$disk->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Physical file missing on disk',
            ], 404);
        }

        // Absolute path + mime
        $abs = method_exists($disk, 'path')
            ? $disk->path($path)
            : storage_path('app/'.$path);

        $mime = $file->mime
            ?: ($disk->mimeType($path)
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
                        'Content-Type'            => 'image/jpeg',
                        'Content-Length'          => (string) $size,
                        'X-Content-Type-Options'  => 'nosniff',
                        'Cache-Control'           => 'private, max-age=86400',
                        'X-Preview-Engine'        => 'imagick',
                        'X-Preview-Bytes'         => (string) $size,
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('Perumahan PDF thumbnail failed', [
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

    // List FINAL uploads (PRIVATE). mine=true(default) to restrict to caller.
    // GET /api/perumahan-db/uploads?mine=true&per_page=20
    public function index(Request $request)
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $mine = filter_var($request->query('mine', 'true'), FILTER_VALIDATE_BOOLEAN);

        $q = PerumahanUpload::query()
            ->where('file_path', 'like', 'perumahan_final/%');

        if ($mine) {
            $q->where('user_id', $userId);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $files   = $q->latest()->paginate($perPage);

        $items = $files->getCollection()->map(function ($row) {
            return [
                'uuid'         => $row->uuid,
                'filename'     => basename($row->file_path),
                'file_path'    => $row->file_path,
                'mime'         => $row->mime,
                'size'         => $row->size,
                'created_at'   => $row->created_at,
                'view_api'     => url("/api/perumahan-db/file/{$row->uuid}"),
                'download_api' => url("/api/perumahan-db/file/{$row->uuid}?download=1"),
            ];
        })->values();

        return response()->json([
            'success'    => true,
            'message'    => 'List of uploads in perumahan_final (private)',
            'data'       => $items,
            'pagination' => [
                'current_page' => $files->currentPage(),
                'per_page'     => $files->perPage(),
                'total'        => $files->total(),
                'last_page'    => $files->lastPage(),
            ],
        ]);
    }
}
