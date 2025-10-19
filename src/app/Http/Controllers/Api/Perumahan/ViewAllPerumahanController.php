<?php

namespace App\Http\Controllers\Api\Perumahan;

use App\Http\Controllers\Controller;
use App\Models\Perumahan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewAllPerumahanController extends Controller
{
    // Return all perumahan posts
    public function index()
    {
        $perumahans = Perumahan::all();

        return response()->json([
            'success' => true,
            'data' => $perumahans,
        ]);
    }

    // Return a single perumahan post by UUID
    public function show($id)
    {
        $perumahan = Perumahan::find($id);

        if (!$perumahan) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $perumahan,
        ]);
    }

    // Return only selected fields with role check
    public function ViewPerumahanPengawas()
    {
        $user = auth()->user();
    
        if (!$user || !in_array($user->role, ['admin', 'admin_bidang', 'pengawas'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }
    
        // Get all perumahan data with selected fields
        $perumahans = Perumahan::select(
            'id',
            'bidang',
            'kegiatan',
            'nama_cpcl',
            'kecamatan',
            'kelurahan',
            'photos',
            'pdfs'
        )->get();
    
        // Map and add photo/pdf count
        $data = $perumahans->map(function ($item) {
            return [
                'id' => $item->id,
                'bidang' => $item->bidang,
                'kegiatan' => $item->kegiatan,
                'nama_cpcl' => $item->nama_cpcl,
                'kecamatan' => $item->kecamatan,
                'kelurahan' => $item->kelurahan,
                'photo_count' => is_array($item->photos) ? count($item->photos) : 0,
                'pdf_count' => is_array($item->pdfs) ? count($item->pdfs) : 0,
            ];
        });
    
        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'data' => $data,
        ]);
    }
    public function ViewPerumahanBidangPerumahan()
    {
        $user = auth()->user();
    
        if (!$user || !in_array($user->role, ['admin', 'admin_bidang','pengawas'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }
    
        // Get all perumahan data with selected fields
        $perumahans = Perumahan::select(
            'id',
            'bidang',
            'kegiatan',
            'nama_cpcl',
            'kecamatan',
            'kelurahan',
            'tanggal_selesai',
            'kontraktor_pelaksana',
            'updated_at',
            'photos',
            'pdfs'
        )->get();
    
        // Map and add photo/pdf count
        $data = $perumahans->map(function ($item) {
            return [
                'id' => $item->id,
                'bidang' => $item->bidang,
                'kegiatan' => $item->kegiatan,
                'nama_cpcl' => $item->nama_cpcl,
                'kecamatan' => $item->kecamatan,
                'kelurahan' => $item->kelurahan,
                'kontraktor_pelaksana'=> $item->kontraktor_pelaksana, 
                'tanggal_selesai'=>$item->tanggal_selesai,
                'updated_at'=>$item->updated_at,
                'photo_count' => is_array($item->photos) ? count($item->photos) : 0,
                'pdf_count' => is_array($item->pdfs) ? count($item->pdfs) : 0,
            ];
        });
    
        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'data' => $data,
        ]);
    }
    public function resumeByKecamatanKelurahan()
    {
        // Authentication check
        $user = auth()->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }
    
        // Total number of raw rows
        $totalInputed = Perumahan::count();
    
        // Group by kecamatan + kelurahan
        $grouped = DB::table('perumahans')
            ->select('kecamatan', 'kelurahan', DB::raw('COUNT(*) as total'))
            ->groupBy('kecamatan', 'kelurahan')
            ->orderBy('kecamatan')
            ->orderBy('kelurahan')
            ->get();
    
        // Build nested result and compute subtotals
        $result = [];
        foreach ($grouped as $row) {
            $kec = $row->kecamatan ?? 'N/A';
            if (! isset($result[$kec])) {
                $result[$kec] = [
                    'kecamatan'       => $kec,
                    'kelurahan_data'  => [],
                    'kecamatan_total' => 0,
                ];
            }
            $result[$kec]['kelurahan_data'][] = [
                'kelurahan' => $row->kelurahan,
                'total'     => $row->total,
            ];
            $result[$kec]['kecamatan_total'] += $row->total;
        }
    
        // Number of distinct kecamatan
        $kecamatanCount    = count($result);
        // Sum of all kecamatan_total values
        $sumKecamatanTotal = array_sum(array_column($result, 'kecamatan_total'));
    
        return response()->json([
            'success'             => true,
            'total_data_perumahan'       => $totalInputed,
            'kecamatan_count'     => $kecamatanCount,
            'data'                => array_values($result),
        ]);
    }
}       