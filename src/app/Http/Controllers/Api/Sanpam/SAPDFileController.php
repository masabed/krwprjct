<?php

namespace App\Http\Controllers\Api\Sanpam;

use App\Http\Controllers\Controller;
use App\Models\SAPDUpload;       // FINAL
use App\Models\SAPDUploadTemp;   // TEMP
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SAPDFileController extends Controller
{
    /**
     * GET /sapd/file/{uuid}
     * Stream file inline (PDF/image tampil di browser).
     * ?source=final|temp|auto (default: auto)
     */
    public function show(string $uuid, Request $request)
    {
        $prefer = strtolower($request->query('source', 'auto'));
        $prefer = in_array($prefer, ['final','temp','auto'], true) ? $prefer : 'auto';

        $found = $this->locateFile($uuid, $prefer);
        if (!$found) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in FINAL or TEMP',
            ], 404);
        }

        $disk = Storage::disk($found['disk']);
        $path = $found['path'];
        $mime = $found['mime'] ?? 'application/octet-stream';
        $name = basename($path);

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                 => 'no-cache',
        ]);
    }

    /**
     * GET /sapd/file/preview/{uuid}
     *
     * Fixed-size preview:
     *  - image/*         → resized ke width=512 (JPEG, aspect ratio dijaga)
     *  - application/pdf → halaman pertama jadi JPEG 512px (Imagick)
     *    - kalau gagal → JSON error singkat, tanpa trace
     *
     * ?source=final|temp|auto (default: auto)
     */
   public function preview(string $uuid, Request $request)
{
    $prefer = strtolower($request->query('source', 'auto'));
    $prefer = in_array($prefer, ['final','temp','auto'], true) ? $prefer : 'auto';

    $found = $this->locateFile($uuid, $prefer);
    if (!$found) {
        return response()->json([
            'success' => false,
            'message' => 'File not found in FINAL or TEMP',
        ], 404);
    }

    $abs  = $found['abs'];      // absolute path
    $mime = $found['mime'] ?: (function_exists('mime_content_type') ? @mime_content_type($abs) : null);
    $w    = 512;

    // ===== IMAGES =====
    if ($mime && str_starts_with($mime, 'image/')) {
        // Intervention dulu
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
                    'Content-Type'           => 'image/jpeg',
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control'          => 'private, max-age=86400',
                    'X-Preview-Engine'       => 'intervention',
                    'X-Preview-Bytes'        => (string) strlen($binary),
                ]);
            } catch (\Throwable $e) {
                // fallback ke GD
            }
        }

        // GD fallback
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
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

                        ob_start();
                        imagejpeg($dst, null, 75);
                        $binary = ob_get_clean();
                        imagedestroy($src);
                        imagedestroy($dst);

                        if ($binary !== false) {
                            return response($binary, 200, [
                                'Content-Type'           => 'image/jpeg',
                                'X-Content-Type-Options' => 'nosniff',
                                'Cache-Control'          => 'private, max-age=86400',
                                'X-Preview-Engine'       => 'gd',
                                'X-Preview-Bytes'        => (string) strlen($binary),
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

    // ===== PDF =====
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
                    'Content-Type'           => 'image/jpeg',
                    'Content-Length'         => (string) $size,
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control'          => 'private, max-age=86400',
                    'X-Preview-Engine'       => 'imagick',
                    'X-Preview-Bytes'        => (string) $size,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('SAPD PDF thumbnail failed', [
                    'file'  => $abs,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to generate PDF preview. Please open the original file.',
                ], 404);
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
     * Helper: cari file di FINAL atau TEMP.
     * Return:
     *  [
     *    'abs'    => absolute path,
     *    'path'   => relative path,
     *    'mime'   => mime type,
     *    'disk'   => nama disk,
     *    'source' => 'final' | 'temp',
     *  ]
     */
    private function locateFile(string $uuid, string $prefer = 'auto'): ?array
    {
        $prefer  = in_array($prefer, ['final','temp','auto'], true) ? $prefer : 'auto';
        $diskKey = config('filesystems.default');
        $disk    = Storage::disk($diskKey);

        // FINAL dulu (kecuali prefer=temp)
        if ($prefer !== 'temp') {
            $rec = SAPDUpload::where('uuid', $uuid)->first();
            if ($rec && !empty($rec->file_path) && $disk->exists($rec->file_path)) {
                $abs = method_exists($disk, 'path')
                    ? $disk->path($rec->file_path)
                    : storage_path('app/'.$rec->file_path);

                return [
                    'abs'    => $abs,
                    'path'   => $rec->file_path,
                    'mime'   => $disk->mimeType($rec->file_path) ?? $this->guessMime($abs),
                    'disk'   => $diskKey,
                    'source' => 'final',
                ];
            }

            if ($prefer === 'final') {
                return null;
            }
        }

        // TEMP fallback (atau prefer=temp)
        $tmp = SAPDUploadTemp::where('uuid', $uuid)->first();
        if ($tmp && !empty($tmp->file_path) && $disk->exists($tmp->file_path)) {
            $abs = method_exists($disk, 'path')
                ? $disk->path($tmp->file_path)
                : storage_path('app/'.$tmp->file_path);

            return [
                'abs'    => $abs,
                'path'   => $tmp->file_path,
                'mime'   => $disk->mimeType($tmp->file_path) ?? $this->guessMime($abs),
                'disk'   => $diskKey,
                'source' => 'temp',
            ];
        }

        return null;
    }

    private function guessMime(string $abs): ?string
    {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($abs);
            if ($m) return $m;
        }

        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg','jpeg' => 'image/jpeg',
            'png'        => 'image/png',
            'webp'       => 'image/webp',
            'gif'        => 'image/gif',
            'pdf'        => 'application/pdf',
            default      => 'application/octet-stream',
        };
    }

    private function guessMimeFromPath(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg','jpeg' => 'image/jpeg',
            'png'        => 'image/png',
            'webp'       => 'image/webp',
            'gif'        => 'image/gif',
            'pdf'        => 'application/pdf',
            default      => 'application/octet-stream',
        };
    }
}
