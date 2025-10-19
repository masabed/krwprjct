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
     * ?source=final|temp|auto (default auto: final → temp)
     */
    public function show(Request $request, string $uuid)
    {
        $prefer = strtolower($request->query('source', 'auto'));
        $prefer = in_array($prefer, ['final','temp','auto']) ? $prefer : 'auto';

        // 1) FINAL dulu (kecuali user paksa temp)
        if ($prefer !== 'temp') {
            if ($resp = $this->tryServeFromFinal($uuid)) {
                return $resp;
            }
            if ($prefer === 'final') {
                return response()->json(['success' => false, 'message' => 'File not found in FINAL'], 404);
            }
        }

        // 2) TEMP (fallback)
        if ($resp = $this->tryServeFromTemp($uuid)) {
            return $resp;
        }

        return response()->json(['success' => false, 'message' => 'File not found in FINAL or TEMP'], 404);
    }

    private function tryServeFromFinal(string $uuid)
    {
        $rec = PermukimanUpload::where('uuid', $uuid)->first();
        if (!$rec || empty($rec->file_path)) return null;

        // Cek di disk 'local' dulu
        if (Storage::disk('local')->exists($rec->file_path)) {
            $abs = Storage::disk('local')->path($rec->file_path);
            return $this->streamFile($abs);
        }
        // Cek di 'public' kalau ternyata file disimpan di sana
        if (Storage::disk('public')->exists($rec->file_path)) {
            $abs = Storage::disk('public')->path($rec->file_path);
            return $this->streamFile($abs);
        }
        return null;
    }

    private function tryServeFromTemp(string $uuid)
    {
        $rec = PermukimanUploadTemp::where('uuid', $uuid)->first();
        if (!$rec || empty($rec->file_path)) return null;

        // (opsional) batasi akses hanya owner:
        // if (auth()->check() && $rec->user_id && (string)$rec->user_id !== (string)auth()->id()) return null;

        if (Storage::disk('local')->exists($rec->file_path)) {
            $abs = Storage::disk('local')->path($rec->file_path);
            return $this->streamFile($abs);
        }
        if (Storage::disk('public')->exists($rec->file_path)) {
            $abs = Storage::disk('public')->path($rec->file_path);
            return $this->streamFile($abs);
        }
        return null;
    }

    private function streamFile(string $absPath)
    {
        $mime = function_exists('mime_content_type') ? mime_content_type($absPath) : 'application/octet-stream';
        return response()->file($absPath, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
