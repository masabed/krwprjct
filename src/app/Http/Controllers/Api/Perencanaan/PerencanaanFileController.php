<?php

namespace App\Http\Controllers\Api\Perencanaan;

use App\Http\Controllers\Controller;
use App\Models\PerencanaanUpload;
use App\Models\PerencanaanUploadTemp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PerencanaanUploadController extends Controller
{
    /** POST /api/psu/perencanaan/upload */
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
        $ext  = strtolower(
            $request->file('file')->getClientOriginalExtension()
            ?: $request->file('file')->extension()
            ?: 'bin'
        );

        $timestamp = now()->format('Ymd_His');
        $basename  = "{$timestamp}_{$uuid}.{$ext}";
        // Simpan ke storage LOCAL (temp)
        $path = $request->file('file')->storeAs('perencanaan_temp', $basename, 'local');

        $temp = PerencanaanUploadTemp::create([
            'uuid'          => $uuid,
            'user_id'       => $user->id,
            'file_path'     => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'mime'          => $request->file('file')->getClientMimeType(),
            'size'          => $request->file('file')->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'uuid'          => $temp->uuid,
                'user_id'       => $temp->user_id,
                'file_path'     => $temp->file_path,
                'original_name' => $temp->original_name,
                'mime'          => $temp->mime,
                'size'          => $temp->size,
            ],
        ], 201);
    }

    /**
     * GET /api/psu/perencanaan/file/preview/{uuid}
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
        $disk   = $this->disk(); // Storage::disk('local')

        // Cari record upload
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

        // ====================== UNSUPPORTED TYPE ======================
        return response()->json([
            'success' => false,
            'message' => 'Preview only supports images and PDFs.',
        ], 415);
    }

    /** Konsisten pakai disk 'local' untuk semua operasi file */
    private function disk()
    {
        return Storage::disk('local');
    }
}
