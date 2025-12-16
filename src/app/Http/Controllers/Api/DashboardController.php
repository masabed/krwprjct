<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// MODELS
use App\Models\UsulanFisikBSL;
use App\Models\PSUUsulanFisikPerumahan;
use App\Models\PSUUsulanFisikTPU;
use App\Models\PSUUsulanFisikPJL;
use App\Models\PsuSerahTerima;
use App\Models\TpuSerahTerima;
use App\Models\SAPDLahanMasyarakat;
use App\Models\UsulanSAPDSIndividual;
use App\Models\UsulanSAPDSFasilitasUmum;
use App\Models\Permukiman;
use App\Models\Rutilahu;

class DashboardController extends Controller
{
    /**
     * Label status_verifikasi_usulan 0–7 (versi baru)
     */
    private const STATUS_LABELS = [
        0 => 'Usulan',
        1 => 'Ditolak',
        2 => 'Dikembalikan',
        3 => 'Direvisi',
        4 => 'Verifikasi Fisik',
        5 => 'Perencanaan',
        6 => 'Pembangunan',
        7 => 'Selesai',
    ];

    /**
     * GET /api/dashboard
     * Ringkasan angka untuk dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        // ================== DAFTAR TABEL & MODEL ==================
        $tables = [
            'usulan_fisik_bsl'              => UsulanFisikBSL::class,
            'psu_usulan_fisik_perumahans'   => PSUUsulanFisikPerumahan::class,
            'psu_usulan_fisik_tpus'         => PSUUsulanFisikTPU::class,
            'psu_usulan_fisik_pjls'         => PSUUsulanFisikPJL::class,
            'psu_serah_terimas'             => PsuSerahTerima::class,
            'tpu_serah_terimas'             => TpuSerahTerima::class,
            'sapd_lahan_masyarakats'        => SAPDLahanMasyarakat::class,
            'usulan_sapds_individuals'      => UsulanSAPDSIndividual::class,
            'usulan_sapds_fasilitas_umums'  => UsulanSAPDSFasilitasUmum::class,
            'permukimans'                   => Permukiman::class,
            'rutilahus'                     => Rutilahu::class,
        ];

        // Kelompok logis untuk summary & status grouped
        $groupMap = [
            'psu_usulan' => [
                'usulan_fisik_bsl',
                'psu_usulan_fisik_perumahans',
                'psu_usulan_fisik_tpus',
                'psu_usulan_fisik_pjls',
            ],
            'sapd' => [
                'sapd_lahan_masyarakats',
                'usulan_sapds_individuals',
                'usulan_sapds_fasilitas_umums',
            ],
            'serah_terima' => [
                'psu_serah_terimas',
                'tpu_serah_terimas',
            ],
            'permukiman' => [
                'permukimans',
            ],
            'rutilahu' => [
                'rutilahus',
            ],
        ];

        // ================== TOTAL PER TABEL ==================
        $counts = [];
        foreach ($tables as $key => $modelClass) {
            if (!class_exists($modelClass)) {
                $counts[$key] = 0;
                continue;
            }
            $counts[$key] = (int) $modelClass::query()->count();
        }

        // ================== SUMMARY GLOBAL ==================
        $summary = [
            'total_usulan_psu'   => $this->sumKeys($counts, $groupMap['psu_usulan']),
            'total_sapd'         => $this->sumKeys($counts, $groupMap['sapd']),
            'total_serah_terima' => $this->sumKeys($counts, $groupMap['serah_terima']),
            'total_permukiman'   => $this->sumKeys($counts, $groupMap['permukiman']),
            'total_rutilahu'     => $this->sumKeys($counts, $groupMap['rutilahu']),
            'total_semua'        => array_sum($counts),
        ];

        // ================== STATUS_VERIFIKASI PER TABEL (RAW COUNTS) ==================
        $statusPerTableCounts = [];
        foreach ($tables as $key => $modelClass) {
            $statusPerTableCounts[$key] = $this->countStatusForModel($modelClass); // [0..7 => n]
        }

        // ================== STATUS_VERIFIKASI GROUPED (RAW COUNTS) ==================
        $groupedStatusCounts = [
            'psu_usulan'   => array_fill(0, 8, 0),
            'sapd'         => array_fill(0, 8, 0),
            'serah_terima' => array_fill(0, 8, 0),
            'permukiman'   => array_fill(0, 8, 0),
            'rutilahu'     => array_fill(0, 8, 0),
            'all'          => array_fill(0, 8, 0),
        ];

        foreach ($groupMap as $groupName => $keys) {
            foreach ($keys as $tableKey) {
                $arr = $statusPerTableCounts[$tableKey] ?? array_fill(0, 8, 0);
                for ($i = 0; $i < 8; $i++) {
                    $groupedStatusCounts[$groupName][$i] += $arr[$i] ?? 0;
                    $groupedStatusCounts['all'][$i]      += $arr[$i] ?? 0;
                }
            }
        }

        // ================== KONVERSI RAW COUNTS → ADA LABEL (per_table & grouped) ==================
        $statusPerTable = [];
        foreach ($statusPerTableCounts as $key => $countsArr) {
            $statusPerTable[$key] = $this->wrapStatusCounts($countsArr);
        }

        $groupedStatus = [];
        foreach ($groupedStatusCounts as $groupName => $countsArr) {
            $groupedStatus[$groupName] = $this->wrapStatusCounts($countsArr);
        }

        // ================== HITUNG PER KECAMATAN (TOTAL PER TABEL) ==================
        $kecamatanPerTable = [];
        foreach ($tables as $key => $modelClass) {
            $kecamatanPerTable[$key] = $this->countByKecamatanForModel($modelClass);
        }

        // ================== SUMMARY KECAMATAN + STATUS (RAW COUNTS) ==================
        $kecamatanStatusSummaryCounts = []; // ['Nama Kec' => ['kecamatan'=>.., 'per_status'=>[0..7], 'total'=>N]]

        foreach ($tables as $key => $modelClass) {
            $perModel = $this->countKecamatanStatusForModel($modelClass); // array of ['kecamatan','per_status'=>[0..7]]

            foreach ($perModel as $row) {
                $kec = (string) ($row['kecamatan'] ?? '');
                if ($kec === '') continue;

                if (!isset($kecamatanStatusSummaryCounts[$kec])) {
                    $kecamatanStatusSummaryCounts[$kec] = [
                        'kecamatan'  => $kec,
                        'per_status' => array_fill(0, 8, 0),
                        'total'      => 0,
                    ];
                }

                for ($s = 0; $s < 8; $s++) {
                    $val = (int) ($row['per_status'][$s] ?? 0);
                    $kecamatanStatusSummaryCounts[$kec]['per_status'][$s] += $val;
                    $kecamatanStatusSummaryCounts[$kec]['total']          += $val;
                }
            }
        }

        ksort($kecamatanStatusSummaryCounts);

        // KONVERSI kecamatan summary → embed label di setiap status
        $kecamatanStatusSummary = [];
        foreach ($kecamatanStatusSummaryCounts as $kec => $row) {
            $kecamatanStatusSummary[] = [
                'kecamatan'  => $row['kecamatan'],
                'total'      => (int) $row['total'],
                'per_status' => $this->wrapStatusCounts($row['per_status']),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                // total record per tabel
                'usulan_fisik_bsl'             => $counts['usulan_fisik_bsl'],
                'psu_usulan_fisik_perumahans'  => $counts['psu_usulan_fisik_perumahans'],
                'psu_usulan_fisik_tpus'        => $counts['psu_usulan_fisik_tpus'],
                'psu_usulan_fisik_pjls'        => $counts['psu_usulan_fisik_pjls'],
                'psu_serah_terimas'            => $counts['psu_serah_terimas'],
                'tpu_serah_terimas'            => $counts['tpu_serah_terimas'],
                'sapd_lahan_masyarakats'       => $counts['sapd_lahan_masyarakats'],
                'usulan_sapds_individuals'     => $counts['usulan_sapds_individuals'],
                'usulan_sapds_fasilitas_umums' => $counts['usulan_sapds_fasilitas_umums'],
                'permukimans'                  => $counts['permukimans'],
                'rutilahus'                    => $counts['rutilahus'],

                // summary global
                'summary' => $summary,

                // distribusi status (sudah ada label di tiap elemen)
                'status_verifikasi' => [
                    'per_table' => $statusPerTable,  // per tabel: [ {status,label,total}, ... ]
                    'grouped'   => $groupedStatus,   // per group : [ {status,label,total}, ... ]
                ],

                // distribusi per kecamatan
                'kecamatan' => [
                    // per tabel: [ [kecamatan,total], ... ]
                    'per_table' => $kecamatanPerTable,

                    // summary lintas semua tabel:
                    //   kecamatan A:
                    //      total      = semua record di kec A
                    //      per_status = array of {status,label,total}
                    'summary'   => $kecamatanStatusSummary,
                ],
            ],
        ]);
    }

    // =====================================================================
    // HELPERS
    // =====================================================================

    /**
     * Menjumlahkan beberapa key dari array $counts.
     */
    private function sumKeys(array $counts, array $keys): int
    {
        $sum = 0;
        foreach ($keys as $k) {
            $sum += $counts[$k] ?? 0;
        }
        return $sum;
    }

    /**
     * Hitung distribusi status_verifikasi_usulan (0–7) untuk satu model.
     * Return: array index 0..7 (raw counts)
     */
    private function countStatusForModel(string $modelClass): array
    {
        $result = array_fill(0, 8, 0);

        if (!class_exists($modelClass)) {
            return $result;
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();
        $table = $model->getTable();

        if (!Schema::hasColumn($table, 'status_verifikasi_usulan')) {
            return $result;
        }

        $rows = $modelClass::query()
            ->select('status_verifikasi_usulan', DB::raw('COUNT(*) as total'))
            ->groupBy('status_verifikasi_usulan')
            ->get();

        foreach ($rows as $row) {
            $s = (int) $row->status_verifikasi_usulan;
            if ($s >= 0 && $s <= 7) {
                $result[$s] = (int) $row->total;
            }
        }

        return $result;
    }

    /**
     * Bungkus array counts [0..7] → array of:
     * [
     *   ['status'=>0,'label'=>'Usulan','total'=>X],
     *   ...
     * ]
     */
    private function wrapStatusCounts(array $counts): array
    {
        $out = [];
        for ($i = 0; $i < 8; $i++) {
            $out[] = [
                'status' => $i,
                'label'  => self::STATUS_LABELS[$i] ?? (string) $i,
                'total'  => (int) ($counts[$i] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Tentukan nama kolom kecamatan untuk suatu model.
     *
     * - Usulan PSU (BSL, usulan_fisik_perumahans, usulan_fisik_tpus, usulan_fisik_pjls)
     *     → kecamatanUsulan (fallback: kecamatan)
     * - TpuSerahTerima → kecamatanTPU / kecamatan_tpu / kecamatan
     * - Lainnya        → kecamatan (jika ada)
     */
    private function resolveKecamatanColumn(string $modelClass): ?string
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();
        $table = $model->getTable();

        $usulanPsuModels = [
            UsulanFisikBSL::class,
            PSUUsulanFisikPerumahan::class,
            PSUUsulanFisikTPU::class,
            PSUUsulanFisikPJL::class,
        ];

        // Usulan PSU → kecamatanUsulan
        if (in_array($modelClass, $usulanPsuModels, true)) {
            if (Schema::hasColumn($table, 'kecamatanUsulan')) {
                return 'kecamatanUsulan';
            }
            if (Schema::hasColumn($table, 'kecamatan')) {
                return 'kecamatan';
            }
            return null;
        }

        // Serah Terima TPU
        if ($modelClass === TpuSerahTerima::class) {
            if (Schema::hasColumn($table, 'kecamatanTPU')) {
                return 'kecamatanTPU';
            }
            if (Schema::hasColumn($table, 'kecamatan_tpu')) {
                return 'kecamatan_tpu';
            }
            if (Schema::hasColumn($table, 'kecamatan')) {
                return 'kecamatan';
            }
            return null;
        }

        // Default: kecamatan kalau ada
        if (Schema::hasColumn($table, 'kecamatan')) {
            return 'kecamatan';
        }

        return null;
    }

    /**
     * Hitung jumlah per kecamatan untuk satu model.
     * Return: array of ['kecamatan' => string, 'total' => int]
     */
    private function countByKecamatanForModel(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        $kecColumn = $this->resolveKecamatanColumn($modelClass);
        if (!$kecColumn) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();
        $table = $model->getTable();

        $rows = $modelClass::query()
            ->select($kecColumn . ' as kecamatan', DB::raw('COUNT(*) as total'))
            ->whereNotNull($kecColumn)
            ->where($kecColumn, '!=', '')
            ->groupBy($kecColumn)
            ->orderBy($kecColumn)
            ->get();

        return $rows->map(function ($row) {
            return [
                'kecamatan' => (string) $row->kecamatan,
                'total'     => (int) $row->total,
            ];
        })->toArray();
    }

    /**
     * Hitung jumlah per kecamatan + per status (0–7) untuk satu model.
     *
     * Return: array of:
     *   [
     *     'kecamatan'  => 'Nama Kec',
     *     'per_status' => [0 => n0, 1 => n1, ..., 7 => n7]
     *   ]
     */
    private function countKecamatanStatusForModel(string $modelClass): array
    {
        if (!class_exists($modelClass)) {
            return [];
        }

        $kecColumn = $this->resolveKecamatanColumn($modelClass);
        if (!$kecColumn) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();
        $table = $model->getTable();

        if (!Schema::hasColumn($table, 'status_verifikasi_usulan')) {
            return [];
        }

        $rows = $modelClass::query()
            ->select(
                $kecColumn . ' as kecamatan',
                'status_verifikasi_usulan',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull($kecColumn)
            ->where($kecColumn, '!=', '')
            ->groupBy($kecColumn, 'status_verifikasi_usulan')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $kec = (string) $row->kecamatan;
            $s   = (int) $row->status_verifikasi_usulan;
            $cnt = (int) $row->total;

            if ($kec === '' || $s < 0 || $s > 7) {
                continue;
            }

            if (!isset($result[$kec])) {
                $result[$kec] = [
                    'kecamatan'  => $kec,
                    'per_status' => array_fill(0, 8, 0),
                ];
            }

            $result[$kec]['per_status'][$s] += $cnt;
        }

        return array_values($result);
    }
}
