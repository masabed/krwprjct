<?php

namespace App\Http\Controllers\Api\getDataPribadi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models (existing)
use App\Models\Rutilahu;
use App\Models\Permukiman;
use App\Models\UsulanSAPDSIndividual;
use App\Models\UsulanSAPDSFasilitasUmum;
use App\Models\SAPDLahanMasyarakat;
use App\Models\SAPDUpload;
use App\Models\PsuSerahTerima;

// === Tambahan PSU Usulan ===
use App\Models\PSUUsulanFisikTPU;
use App\Models\PSUUsulanFisikPJL;
use App\Models\PSUUsulanFisikPerumahan;

class MySubmissionsController extends Controller
{
    private const PERMUKIMAN_FILE_FIELDS = [
        'foto_sertifikat_status_tanah',
        'foto_sta0',
        'foto_sta100',
        'surat_pemohonan',
        'proposal_usulan',
    ];

    private const SAPD_INDIVIDUAL_FILE_FIELDS = [
        'fotoRumah',
        'fotoLahan',
    ];

    private const SAPD_FASUM_FILE_FIELDS = [
        'buktiKepemilikan',
        'proposal',
        'fotoLahan',
    ];

    private const SAPD_LAHAN_FILE_FIELDS = [
        'buktiKepemilikan',
        'dokumenProposal',
        'dokumenDJPM',
        'fotoLahan',
    ];

    private const PSU_FILE_FIELDS = [
        'dokumenIzinBangunan',
        'dokumenIzinPemanfaatan',
        'dokumenKondisi',
        'dokumenTeknis',
        'ktpPengusul',
        'aktaPerusahaan',
        'suratPermohonanPenyerahan',
        'buktiBAST',
        'dokumenSiteplan',
        'salinanSertifikat',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $type = strtolower($request->query('type', 'all'));

        switch ($type) {
            case 'rutilahu':
                return response()->json(['success' => true, 'data' => $this->queryRutilahu($user->id)]);
            case 'permukiman':
                return response()->json(['success' => true, 'data' => $this->queryPermukiman($user->id)]);
            case 'sapd_individual':
                return response()->json(['success' => true, 'data' => $this->querySapdIndividual($user->id)]);
            case 'sapd_fasum':
                return response()->json(['success' => true, 'data' => $this->querySapdFasum($user->id)]);
            case 'sapd_lahan':
                return response()->json(['success' => true, 'data' => $this->querySapdLahan($user->id)]);

            // === PSU Usulan (baru ditambah) ===
            case 'psu_usulan_tpu':
                return response()->json(['success' => true, 'data' => $this->queryPsuUsulanTPU($user->id)]);
            case 'psu_usulan_pjl':
                return response()->json(['success' => true, 'data' => $this->queryPsuUsulanPJL($user->id)]);
            case 'psu_usulan_perumahan': // alias BSL (kalau BSL model-nya beda, ganti di sini)
            case 'psu_usulan_bsl':
                return response()->json(['success' => true, 'data' => $this->queryPsuUsulanPerumahan($user->id)]);

            case 'psu':
            case 'psu_serah_terima':
                return response()->json(['success' => true, 'data' => $this->queryPsuSerahTerima($user->id)]);

            case 'all':
            default:
                return response()->json([
                    'success' => true,
                    'data' => [
                        'rutilahu'              => $this->queryRutilahu($user->id),
                        'permukiman'            => $this->queryPermukiman($user->id),
                        'sapd_individual'       => $this->querySapdIndividual($user->id),
                        'sapd_fasum'            => $this->querySapdFasum($user->id),
                        'sapd_lahan'            => $this->querySapdLahan($user->id),
                        // === PSU Usulan (baru) ===
                        'psu_usulan_tpu'        => $this->queryPsuUsulanTPU($user->id),
                        'psu_usulan_pjl'        => $this->queryPsuUsulanPJL($user->id),
                        'psu_usulan_perumahan'  => $this->queryPsuUsulanPerumahan($user->id),
                        // === Serah Terima PSU (sudah ada)
                        'psu_serah_terima'      => $this->queryPsuSerahTerima($user->id),
                    ],
                ]);
        }
    }

    // ================== Per Modul (langsung by user_id) ==================

    private function queryRutilahu(string $userId)
    {
        return Rutilahu::where('user_id', $userId)->latest()->get()->values();
    }

    private function queryPermukiman(string $userId)
    {
        return Permukiman::where('user_id', $userId)->latest()->get()->values();
    }

    private function querySapdIndividual(string $userId)
    {
        $q = UsulanSAPDSIndividual::query()->where('user_id', $userId);
        // (Opsional) file-based fallback — lihat helper whereAnyJsonContains()
        return $q->latest()->get()->values();
    }

    private function querySapdFasum(string $userId)
    {
        $q = UsulanSAPDSFasilitasUmum::query()->where('user_id', $userId);
        return $q->latest()->get()->values();
    }

    private function querySapdLahan(string $userId)
    {
        $q = SAPDLahanMasyarakat::query()->where('user_id', $userId);
        return $q->latest()->get()->values();
    }

    // =========== PSU: Usulan Fisik (BARU) ===========

    private function queryPsuUsulanTPU(string $userId)
    {
        $q = PSUUsulanFisikTPU::query()->where('user_id', $userId);

        // (Opsional) fallback file-based jika nanti ada tabel PSUUpload
        // $myPsuUuids = \App\Models\PSUUpload::where('user_id', $userId)->pluck('uuid')->map(fn($v)=>strtolower($v))->all();
        // if (!empty($myPsuUuids)) {
        //     $q->orWhere(function ($qb) use ($myPsuUuids) {
        //         $this->whereAnyJsonContains($qb, self::PSU_FILE_FIELDS, $myPsuUuids);
        //     });
        // }

        return $q->latest()->get()->values();
    }

    private function queryPsuUsulanPJL(string $userId)
    {
        $q = PSUUsulanFisikPJL::query()->where('user_id', $userId);
        return $q->latest()->get()->values();
    }

    private function queryPsuUsulanPerumahan(string $userId)
    {
        $q = PSUUsulanFisikPerumahan::query()->where('user_id', $userId);
        return $q->latest()->get()->values();
    }

    private function queryPsuSerahTerima(string $userId)
    {
        $q = PsuSerahTerima::query()->where('user_id', $userId);

        // (Opsional) fallback file-based PSU
        // $myPsuUuids = \App\Models\PSUUpload::where('user_id', $userId)->pluck('uuid')->map(fn($v)=>strtolower($v))->all();
        // if (!empty($myPsuUuids)) {
        //     $q->orWhere(function ($qb) use ($myPsuUuids) {
        //         $this->whereAnyJsonContains($qb, self::PSU_FILE_FIELDS, $myPsuUuids);
        //     });
        // }

        return $q->latest()->get()->values();
    }

    /**
     * Helper untuk fallback file-based (kalau dibutuhkan).
     * (JSON_CONTAINS(f1,'"u1"') OR JSON_CONTAINS(f1,'"u2"') OR JSON_CONTAINS(f2,'"u1"') ...)
     */
    private function whereAnyJsonContains($query, array $jsonFields, array $uuids): void
    {
        $uuids = array_values(array_unique(array_map('strtolower', array_filter($uuids))));
        if (empty($uuids)) return;

        $query->where(function ($qq) use ($jsonFields, $uuids) {
            $first = true;
            foreach ($jsonFields as $field) {
                foreach ($uuids as $u) {
                    if ($first) {
                        $qq->whereJsonContains($field, $u);
                        $first = false;
                    } else {
                        $qq->orWhereJsonContains($field, $u);
                    }
                }
            }
        });
    }
}
