<?php

namespace App\Http\Controllers\Api\Rutilahu;

use App\Http\Controllers\Controller;
use App\Models\RutilahuUpload;
use App\Models\RutilahuUploadTemp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RutilahuFileController extends Controller
{
    /**
     * GET /rutilahu/file/{uuid}
     * Stream file inline (image/pdf tampil di browser).
     */
    public function show(string $uuid, Request $request)
    {
        $file = RutilahuUpload::where('uuid', $uuid)->first();
        if (!$file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (!$disk->exists($file->file_path)) {
            return response()->json(['success' => false, 'message' => 'File missing'], 404);
        }

        $mime = $disk->mimeType($file->file_path) ?? 'application/octet-stream';
        $name = basename($file->file_path);

        return new StreamedResponse(function () use ($disk, $file) {
            $stream = $disk->readStream($file->file_path);
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
     * DELETE /rutilahu/file/{uuid}
     * atau  POST /rutilahu/file/{uuid}/delete
     *
     * Hapus file berdasarkan UUID.
     * - Default: cek FINAL dulu, kalau tidak ada cek TEMP.
     * - ?source=final | temp → batasi lokasi.
     * - ?delete_all=true → hapus di FINAL dan TEMP jika duplikat ada di keduanya.
     */
    public function destroy(string $uuid, Request $request)
    {
        $source    = $request->query('source');          // 'final' | 'temp' | null
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
            $deleted = $deleteOne(RutilahuUpload::class, 'final');
        } elseif ($source === 'temp') {
            $deleted = $deleteOne(RutilahuUploadTemp::class, 'temp');
        } else {
            // Auto: coba FINAL, lalu TEMP
            $deleted = $deleteOne(RutilahuUpload::class, 'final');
            if ($deleteAll) {
                $deleteOne(RutilahuUploadTemp::class, 'temp');
            } elseif (!$deleted) {
                $deleted = $deleteOne(RutilahuUploadTemp::class, 'temp');
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
}
