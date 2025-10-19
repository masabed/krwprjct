<?php

namespace App\Http\Controllers\Api\Perumahan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Perumahan;
use Illuminate\Support\Facades\Storage;

class PerumahanController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['admin', 'admin_bidang'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'bidang' => 'required|string|in:Perumahan,Permukiman',
            'kegiatan' => 'required|string',
            'nik' => 'required|digits:16',
            'nama_cpcl' => 'required|string',
            'dusun' => 'required|string',
            'kelurahan' => 'required|string',
            'kecamatan' => 'required|string',
            'no_surat' => 'required|string',
            'tanggal_sp' => 'required|date',
            'nilai_kontrak' => 'required|string',
            'jumlah_unit' => 'required|integer',
            'type' => 'required|string',
            'kontraktor_pelaksana' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date',
            'waktu_kerja' => 'required|integer',
            'pengawas_lapangan' => 'required|string',

            'pdfs.*' => 'file|mimes:pdf|max:5120',       // max 5MB
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:1024',  // max 1MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $perumahan = Perumahan::create([
            'id' => Str::uuid(),
            'bidang' => strip_tags($request->bidang),
            'kegiatan' => strip_tags($request->kegiatan),
            'nik' => strip_tags($request->nik),
            'nama_cpcl' => strip_tags($request->nama_cpcl),
            'dusun' => strip_tags($request->dusun),
            'kelurahan' => strip_tags($request->kelurahan),
            'kecamatan' => strip_tags($request->kecamatan),
            'no_surat' => strip_tags($request->no_surat),
            'tanggal_sp' => strip_tags($request->tanggal_sp),
            'nilai_kontrak' => strip_tags($request->nilai_kontrak),
            'jumlah_unit' => strip_tags($request->jumlah_unit),
            'type' => strip_tags($request->type),
            'kontraktor_pelaksana' => strip_tags($request->kontraktor_pelaksana),
            'tanggal_mulai' => strip_tags($request->tanggal_mulai),
            'tanggal_selesai' => strip_tags($request->tanggal_selesai),
            'waktu_kerja' => strip_tags($request->waktu_kerja),
            'pengawas_lapangan' => strip_tags($request->pengawas_lapangan),
        ]);

        // Save PDFs
        if ($request->hasFile('pdfs')) {
            $pdfPaths = [];
            foreach ($request->file('pdfs') as $pdf) {
                $originalName = pathinfo($pdf->getClientOriginalName(), PATHINFO_FILENAME); // filename without extension
                $extension = $pdf->getClientOriginalExtension();
                $timestamp = now()->setTimezone('Asia/Jakarta')->format('YmdHis');
                $name = $originalName . '_' . $timestamp . '.' . $extension;
        
                $path = $pdf->storeAs('uploads/perumahan/pdfs', $name, 'public');
                if ($path) {
                    $pdfPaths[] = $path;
                }
            }
        
            if (!empty($pdfPaths)) {
                $perumahan->pdfs = $pdfPaths;
                $perumahan->save();
            }
        }
        
        // Save photos without compression
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $timestamp = now()->setTimezone('Asia/Jakarta')->format('YmdHis');
                $name = $timestamp . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                $path = $photo->storeAs('uploads/perumahan/photos', $name, 'public');
                if ($path) {
                    $photoPaths[] = $path;
                }
            }
        
            if (!empty($photoPaths)) {
                $perumahan->photos = $photoPaths;
                $perumahan->save();
            }
        }        

        $perumahan->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Perumahan post created successfully.',
            'data' => $perumahan,
        ]);
    }
}
