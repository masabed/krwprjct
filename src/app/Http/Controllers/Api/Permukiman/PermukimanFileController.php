<?php

namespace App\Http\Controllers\Api\Permukiman;

use App\Http\Controllers\Controller;
use App\Models\PermukimanUpload;      // FINAL
use App\Models\PermukimanUploadTemp;  // TEMP
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PermukimanFileController extends Controller
{
    /**
     * GET /permukiman/file/{uuid}
     * Params:
     *  - source=final|temp|auto   (default: auto)
     *  - download=1               (optional: force download)
     */
    public function show(Request $request, string $uuid)
    {
        $prefer  = strtolower($request->query('source', 'auto'));
        $prefer  = in_array($prefer, ['final','temp','auto'], true) ? $prefer : 'auto';
        $found   = $this->locateFile($uuid, $prefer);

        if (!$found) {
            return response()->json(['success' => false, 'message' => 'File not found in FINAL or TEMP'], 404);
        }

        $abs  = $found['abs'];
        $mime = $found['mime'] ?: (function_exists('mime_content_type') ? @mime_content_type($abs) : 'application/octet-stream');
        $name = basename($abs);

        if ($request->boolean('download')) {
            return response()->download($abs, $name, [
                'Content-Type'           => $mime,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->file($abs, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * GET /permukiman/file/{uuid}/preview
     * Fixed-size preview:
     *  - image/*         → resized to width=512 (keeps aspect)
     *  - application/pdf → first page thumbnail (JPEG) if Imagick ok; else inline PDF
     */
    public function preview(Request $request, string $uuid)
    {
        $prefer = strtolower($request->query('source', 'auto'));
        $prefer = in_array($prefer, ['final','temp','auto'], true) ? $prefer : 'auto';

        $found = $this->locateFile($uuid, $prefer);
        if (!$found) {
            return response()->json(['success' => false, 'message' => 'File not found in FINAL or TEMP'], 404);
        }

        $abs    = $found['abs'];
        $mime   = $found['mime'] ?: (function_exists('mime_content_type') ? @mime_content_type($abs) : null);
        $w      = 512; // fixed width preview
        $engine = 'unavailable';

        // ===== IMAGES =====
        if ($mime && str_starts_with($mime, 'image/')) {
            // Try Intervention first
            if (class_exists(\Intervention\Image\ImageManagerStatic::class)) {
                try {
                    $img = \Intervention\Image\ImageManagerStatic::make($abs)
                        ->orientate()
                        ->resize($w, $w, function ($c) { $c->aspectRatio(); $c->upsize(); });
                    // Force JPEG to keep size small
                    $binary = $img->encode('jpg', 75);
                    $engine = 'intervention';
                    return response($binary, 200, [
                        'Content-Type'            => 'image/jpeg',
                        'X-Content-Type-Options'  => 'nosniff',
                        'Cache-Control'           => 'private, max-age=86400',
                        'X-Preview-Engine'        => $engine,
                        'X-Preview-Bytes'         => (string) strlen($binary),
                    ]);
                } catch (\Throwable $e) { /* fallthrough */ }
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
                            imagejpeg($dst, null, 75); // force JPEG
                            $binary = ob_get_clean();
                            imagedestroy($src);
                            imagedestroy($dst);

                            if ($binary !== false) {
                                $engine = 'gd';
                                return response($binary, 200, [
                                    'Content-Type'            => 'image/jpeg',
                                    'X-Content-Type-Options'  => 'nosniff',
                                    'Cache-Control'           => 'private, max-age=86400',
                                    'X-Preview-Engine'        => $engine,
                                    'X-Preview-Bytes'         => (string) strlen($binary),
                                ]);
                            }
                        }
                    }
                } catch (\Throwable $e) { /* fallthrough */ }
            }

            // Kalau sampai sini: tidak ada engine yang bisa resize
            return response()->json([
                'success' => false,
                'message' => 'Preview requires GD or Intervention Image for images.',
            ], 415);
        }

        // ===== PDF =====
        $isPdf = ($mime === 'application/pdf') || str_ends_with(strtolower($abs), '.pdf');
        if ($isPdf) {
            // Coba buat thumbnail JPEG halaman pertama pakai Imagick
            if (class_exists(\Imagick::class)) {
                try {
                    $targetWidth  = 512;
                    $targetDpi    = 96;  // lebih rendah = file lebih kecil
                    $jpegQuality  = 60;  // turunin quality untuk hemat ukuran

                    $im = new \Imagick();
                    $im->setResolution($targetDpi, $targetDpi);
                    $im->readImage($abs . '[0]'); // first page
                    $im->setImageBackgroundColor(new \ImagickPixel('white'));
                    $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                    $im->setImageFormat('jpeg');

                    // buang metadata (EXIF, ICC, dsb) supaya ukuran makin kecil
                    $im->stripImage();

                    // hitung aspect ratio & tinggi target
                    $origW = $im->getImageWidth() ?: 1;
                    $origH = $im->getImageHeight() ?: 1;

                    if ($origW > $targetWidth) {
                        $ratio       = $targetWidth / $origW;
                        $targetHeight = (int) max(1, round($origH * $ratio));
                    } else {
                        // kalau already kecil, pakai ukuran asli
                        $targetWidth  = $origW;
                        $targetHeight = $origH;
                    }

                    // resize dengan tinggi eksplisit, tanpa parameter fill
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
                    \Log::warning('PDF thumbnail failed, fallback to inline PDF', [
                        'file'  => $abs,
                        'error' => $e->getMessage(),
                    ]);
                    // lanjut fallback di bawah
                }
            }

            // Fallback: kirim PDF asli inline (tanpa thumbnail)
            return response()->file($abs, [
                'Content-Type'           => 'application/pdf',
                'Content-Disposition'    => 'inline; filename="'.basename($abs).'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // ===== Unsupported =====
        return response()->json([
            'success' => false,
            'message' => 'Preview only supports images and PDFs.',
        ], 415);
    }

    /**
     * Helper to find a file in FINAL or TEMP storages.
     * Returns: ['abs' => string, 'mime' => string|null, 'disk' => 'local'|'public', 'source' => 'final'|'temp']
     */
    private function locateFile(string $uuid, string $prefer = 'auto'): ?array
    {
        $prefer = in_array($prefer, ['final','temp','auto'], true) ? $prefer : 'auto';

        // FINAL first (unless prefer=temp)
        if ($prefer !== 'temp') {
            $final = PermukimanUpload::where('uuid', $uuid)->first();
            if ($final && !empty($final->file_path)) {
                if (Storage::disk('local')->exists($final->file_path)) {
                    $abs = Storage::disk('local')->path($final->file_path);
                    return [
                        'abs'    => $abs,
                        'mime'   => $this->guessMime($abs),
                        'disk'   => 'local',
                        'source' => 'final',
                    ];
                }
                if (Storage::disk('public')->exists($final->file_path)) {
                    $abs = Storage::disk('public')->path($final->file_path);
                    return [
                        'abs'    => $abs,
                        'mime'   => $this->guessMime($abs),
                        'disk'   => 'public',
                        'source' => 'final',
                    ];
                }
            }
            if ($prefer === 'final') {
                return null;
            }
        }

        // TEMP fallback (or prefer=temp)
        $temp = PermukimanUploadTemp::where('uuid', $uuid)->first();
        if ($temp && !empty($temp->file_path)) {
            if (Storage::disk('local')->exists($temp->file_path)) {
                $abs = Storage::disk('local')->path($temp->file_path);
                return [
                    'abs'    => $abs,
                    'mime'   => $this->guessMime($abs),
                    'disk'   => 'local',
                    'source' => 'temp',
                ];
            }
            if (Storage::disk('public')->exists($temp->file_path)) {
                $abs = Storage::disk('public')->path($temp->file_path);
                return [
                    'abs'    => $abs,
                    'mime'   => $this->guessMime($abs),
                    'disk'   => 'public',
                    'source' => 'temp',
                ];
            }
        }

        return null;
    }

    private function guessMime(string $abs): ?string
    {
        // Use PHP mime if available; otherwise infer from extension.
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
}
