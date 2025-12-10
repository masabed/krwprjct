<?php

namespace App\Http\Controllers\Api\Perencanaan;

use App\Http\Controllers\Controller;
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
}
