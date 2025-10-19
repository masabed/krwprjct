<?php

namespace App\Http\Controllers\Api\Sanpam;

use App\Http\Controllers\Controller;
use App\Models\SAPDUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SAPDFileController extends Controller
{
    public function show(string $uuid, Request $request)
    {
        // Find file record
        $file = SAPDUpload::where('uuid', $uuid)->first();
        if (!$file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        // OPTIONAL: Authorize per-policy (comment this out if all authenticated users may view)
        // Gate::authorize('view-sapd-file', $file);

        // We stored relative path like 'sapd_final/xxx.pdf' on the **local** (private) disk
        $disk = Storage::disk(config('filesystems.default')); // likely 'local'
        if (!$disk->exists($file->file_path)) {
            return response()->json(['success' => false, 'message' => 'File missing'], 404);
        }

        $mime = $disk->mimeType($file->file_path) ?? 'application/octet-stream';
        $name = basename($file->file_path);

        // Stream inline (PDF opens in browser). For download, change to attachment.
        return new StreamedResponse(function () use ($disk, $file) {
            $stream = $disk->readStream($file->file_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'              => $mime,
            'Content-Disposition'       => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                    => 'no-cache',
        ]);
    }
}
