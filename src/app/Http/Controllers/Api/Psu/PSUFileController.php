<?php

namespace App\Http\Controllers\Api\Psu;

use App\Http\Controllers\Controller;
use App\Models\PSUUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PSUFileController extends Controller
{
    public function show(string $uuid, Request $request)
    {
        // Find file record
        $file = PSUUpload::where('uuid', $uuid)->first();
        if (!$file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        // We stored relative path like 'psu_final/xxx.pdf' inside storage/app/private
        $disk = Storage::disk('local'); // now points to storage/app/private
        if (!$disk->exists($file->file_path)) {
            return response()->json(['success' => false, 'message' => 'File missing'], 404);
        }

        $mime = $disk->mimeType($file->file_path) ?? 'application/octet-stream';
        $name = basename($file->file_path);

        // Stream inline (PDF opens in browser). For download, change to attachment
        return new StreamedResponse(function () use ($disk, $file) {
            $stream = $disk->readStream($file->file_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'              => $mime,
            'Content-Disposition'       => 'inline; filename="' . $name . '"',
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                    => 'no-cache',
        ]);
    }
}
