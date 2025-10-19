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
    // GET /api/perumahans/file/{uuid}?download=1
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
    // GET /api/perumahans/file/temp/{uuid}?download=1
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

    // List FINAL uploads (PRIVATE). mine=true(default) to restrict to caller.
    // GET /api/perumahans/uploads?mine=true&per_page=20
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
                'uuid'       => $row->uuid,
                'filename'   => basename($row->file_path),
                'file_path'  => $row->file_path,
                'mime'       => $row->mime,
                'size'       => $row->size,
                'created_at' => $row->created_at,
                // expose API endpoint (no public URL)
                'view_api'   => url("/api/perumahans/file/{$row->uuid}"),
                'download_api' => url("/api/perumahans/file/{$row->uuid}?download=1"),
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
