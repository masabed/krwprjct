<?php

namespace App\Http\Controllers\Api\Perumahan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Perumahan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class EditDataPerumahanController extends Controller
{
    /**
     * Upload photos by pengawas using UUID.
     */
    public function uploadPhotosByPengawas(Request $request)
    {
        // Log entire request for debugging
        Log::info('UploadPhotos Request:', $request->all());

        $validator = Validator::make($request->all(), [
            'uuid' => 'required|exists:perumahans,id',
            'photos.*' => 'required|image|mimes:jpeg,png,jpg|max:20480', // max 20MB per file
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $id = $request->input('uuid');
        $perumahan = Perumahan::find($id);

        if (!$perumahan) {
            return response()->json([
                'error' => 'Data Tidak Ditemukan.',
                'uuid_sent' => $id,
            ], 404);
        }

        $photoPaths = is_array($perumahan->photos) ? $perumahan->photos : [];

        $manager = new ImageManager(new ImagickDriver());

        foreach ($request->file('photos') as $photo) {
            $timestamp = now()->setTimezone('Asia/Jakarta')->format('YmdHis');
            $name = $timestamp . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $compressedPath = storage_path("app/public/uploads/perumahan/photos/{$name}");

            $image = $manager->read($photo->getRealPath());
            $image->toJpeg(75)->save($compressedPath);

            // Compress until under 1MB
            while (filesize($compressedPath) > 1048576) {
                $image->toJpeg(50)->save($compressedPath);
                if (filesize($compressedPath) <= 1048576) break;
            }

            $photoPaths[] = "uploads/perumahan/photos/{$name}";
        }

        $perumahan->photos = $photoPaths;
        $perumahan->save();

        return response()->json([
            'success' => true,
            'message' => 'Photos berhasil diupload.',
            'data' => $photoPaths,
        ]);
    }

    /**
     * Update all data fields and optionally photos/pdf files of a Perumahan post by UUID.
     */
    public function update(Request $request, $uuid)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['admin', 'admin_bidang'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $perumahan = Perumahan::where('id', $uuid)->first();
        if (!$perumahan) {
            return response()->json(['error' => 'Data Tidak Ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'bidang' => 'sometimes|required|string',
            'kegiatan' => 'sometimes|required|string',
            'nik' => 'sometimes|required|digits:16',
            'nama_cpcl' => 'sometimes|required|string',
            'dusun' => 'sometimes|required|string',
            'kelurahan' => 'sometimes|required|string',
            'kecamatan' => 'sometimes|required|string',
            'no_surat' => 'sometimes|required|string',
            'tanggal_sp' => 'sometimes|required|date',
            'nilai_kontrak' => 'sometimes|required|string',
            'jumlah_unit' => 'sometimes|required|integer',
            'type' => 'sometimes|required|string',
            'kontraktor_pelaksana' => 'sometimes|required|string',
            'tanggal_mulai' => 'sometimes|required|date',
            'tanggal_selesai' => 'sometimes|required|date',
            'waktu_kerja' => 'sometimes|required|integer',
            'pengawas_lapangan' => 'sometimes|required|string',
            'pdfs.*' => 'file|mimes:pdf|max:5120',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fields = [
            'bidang', 'kegiatan', 'nik', 'nama_cpcl', 'dusun', 'kelurahan', 'kecamatan',
            'no_surat', 'tanggal_sp', 'nilai_kontrak', 'jumlah_unit', 'type',
            'kontraktor_pelaksana', 'tanggal_mulai', 'tanggal_selesai', 'waktu_kerja',
            'pengawas_lapangan'
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $perumahan->$field = $request->input($field);
            }
        }

        $pdfPaths = is_array($perumahan->pdfs) ? $perumahan->pdfs : [];
        $photoPaths = is_array($perumahan->photos) ? $perumahan->photos : [];

        if ($request->hasFile('pdfs')) {
            foreach ($request->file('pdfs') as $pdf) {
                $originalName = pathinfo($pdf->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $pdf->getClientOriginalExtension();
                $timestamp = now()->setTimezone('Asia/Jakarta')->format('YmdHis');
                $name = $originalName . '_' . $timestamp . '.' . $extension;

                $path = $pdf->storeAs('uploads/perumahan/pdfs', $name, 'public');
                if ($path) {
                    $pdfPaths[] = $path;
                }
            }
            $perumahan->pdfs = $pdfPaths;
        }

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $timestamp = now()->setTimezone('Asia/Jakarta')->format('YmdHis');
                $name = $timestamp . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                $path = $photo->storeAs('uploads/perumahan/photos', $name, 'public');
                if ($path) {
                    $photoPaths[] = $path;
                }
            }
            $perumahan->photos = $photoPaths;
        }

        $perumahan->save();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diperbarui.',
            'data' => $perumahan,
        ]);
    }

    /**
 * Delete a Perumahan record by UUID.
 */
public function destroy($uuid)
{
    $user = auth()->user();

    // Only admin or admin_bidang can delete
    if (!in_array($user->role, ['admin', 'admin_bidang'])) {
        return response()->json(['error' => 'Unauthorized.'], 403);
    }

    $perumahan = Perumahan::where('id', $uuid)->first();

    if (!$perumahan) {
        return response()->json([
            'error' => 'Data Tidak Ditemukan.',
            'uuid_sent' => $uuid,
        ], 404);
    }

    // Optional: delete associated files (photos, pdfs)
    if (is_array($perumahan->photos)) {
        foreach ($perumahan->photos as $photoPath) {
            Storage::disk('public')->delete($photoPath);
        }
    }

    if (is_array($perumahan->pdfs)) {
        foreach ($perumahan->pdfs as $pdfPath) {
            Storage::disk('public')->delete($pdfPath);
        }
    }

    // Delete the record
    $perumahan->delete();

    return response()->json([
        'success' => true,
        'message' => 'Data berhasil dihapus.',
        'uuid_deleted' => $uuid,
    ]);
}
}
