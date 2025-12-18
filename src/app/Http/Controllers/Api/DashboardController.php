<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

use App\Models\UsulanSummary;

class DashboardController extends Controller
{
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

    public function index(Request $request): JsonResponse
    {
        $auth = $request->user();
        if (!$auth) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $role   = strtolower((string) ($auth->role ?? ''));
        $isPriv = in_array($role, ['admin', 'operator', 'pengawas'], true);

        /**
         * Mapping key dashboard lama -> form di usulan_summaries
         * Pastikan string form ini SAMA dengan FORM_MAP di observer.
         */
        $tableToForm = [
            'usulan_fisik_bsl'             => 'psu_bsl',
            'psu_usulan_fisik_perumahans'  => 'psu_perumahan',
            'psu_usulan_fisik_tpus'        => 'psu_tpu',
            'psu_usulan_fisik_pjls'        => 'psu_pjl',

            // ✅ serah-terima (pastikan observer isi ini)
            'psu_serah_terimas'            => 'psu_serah_terima',
            'tpu_serah_terimas'            => 'tpu_serah_terima',

            'sapd_lahan_masyarakats'       => 'sapd_lahan',
            'usulan_sapds_individuals'     => 'sapd_individual',
            'usulan_sapds_fasilitas_umums' => 'sapd_fasum',

            'permukimans'                  => 'permukiman',
            'rutilahus'                    => 'rutilahu',
        ];

        // Lookup cepat: form -> tableKey
        $formToTableKey = [];
        foreach ($tableToForm as $tableKey => $form) {
            if (!empty($form)) $formToTableKey[$form] = $tableKey;
        }

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

        $forms = collect($tableToForm)
            ->filter(fn ($v) => !empty($v))
            ->values()
            ->unique()
            ->all();

        // ================== TOTAL PER FORM ==================
        $countsByForm = $this->scopedSummaryQuery($auth, $isPriv)
            ->select('form', DB::raw('COUNT(*) as total'))
            ->whereIn('form', $forms)
            ->groupBy('form')
            ->pluck('total', 'form');

        $counts = [];
        foreach ($tableToForm as $tableKey => $form) {
            $counts[$tableKey] = $form ? (int) ($countsByForm[$form] ?? 0) : 0;
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

        // ================== STATUS PER FORM ==================
        $statusRows = $this->scopedSummaryQuery($auth, $isPriv)
            ->select(
                'form',
                DB::raw('COALESCE(status_verifikasi_usulan,0) as status'),
                DB::raw('COUNT(*) as total')
            )
            ->whereIn('form', $forms)
            ->whereRaw('COALESCE(status_verifikasi_usulan,0) BETWEEN 0 AND 7')
            ->groupBy('form')
            ->groupByRaw('COALESCE(status_verifikasi_usulan,0)')
            ->get();

        $statusPerTableCounts = [];
        foreach ($tableToForm as $tableKey => $_form) {
            $statusPerTableCounts[$tableKey] = array_fill(0, 8, 0);
        }

        foreach ($statusRows as $r) {
            $form   = (string) $r->form;
            $status = (int) $r->status;
            $total  = (int) $r->total;

            if ($status < 0 || $status > 7) continue;

            $tableKey = $formToTableKey[$form] ?? null;
            if (!$tableKey) continue;

            $statusPerTableCounts[$tableKey][$status] = $total;
        }

        // ================== STATUS GROUPED ==================
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

        $statusPerTable = [];
        foreach ($statusPerTableCounts as $tableKey => $countsArr) {
            $statusPerTable[$tableKey] = $this->wrapStatusCounts($countsArr);
        }

        $groupedStatus = [];
        foreach ($groupedStatusCounts as $groupName => $countsArr) {
            $groupedStatus[$groupName] = $this->wrapStatusCounts($countsArr);
        }

        // ================== PER KECAMATAN PER FORM ==================
        $kecRows = $this->scopedSummaryQuery($auth, $isPriv)
            ->select('form', 'kecamatan', DB::raw('COUNT(*) as total'))
            ->whereIn('form', $forms)
            ->whereNotNull('kecamatan')
            ->whereRaw("TRIM(kecamatan) <> ''")
            ->groupBy('form', 'kecamatan')
            ->orderBy('kecamatan')
            ->get();

        $kecamatanPerTable = [];
        foreach ($tableToForm as $tableKey => $_form) {
            $kecamatanPerTable[$tableKey] = [];
        }

        foreach ($kecRows as $r) {
            $form = (string) $r->form;
            $kec  = (string) $r->kecamatan;
            $tot  = (int) $r->total;

            $tableKey = $formToTableKey[$form] ?? null;
            if (!$tableKey) continue;

            $kecamatanPerTable[$tableKey][] = [
                'kecamatan' => $kec,
                'total'     => $tot,
            ];
        }

        // ================== SUMMARY KECAMATAN + STATUS (LINTAS FORM) ==================
        $kecStatusRows = $this->scopedSummaryQuery($auth, $isPriv)
            ->select(
                'kecamatan',
                DB::raw('COALESCE(status_verifikasi_usulan,0) as status'),
                DB::raw('COUNT(*) as total')
            )
            ->whereIn('form', $forms)
            ->whereNotNull('kecamatan')
            ->whereRaw("TRIM(kecamatan) <> ''")
            ->whereRaw('COALESCE(status_verifikasi_usulan,0) BETWEEN 0 AND 7')
            ->groupBy('kecamatan')
            ->groupByRaw('COALESCE(status_verifikasi_usulan,0)')
            ->get();

        $kecamatanStatusSummaryCounts = [];

        foreach ($kecStatusRows as $r) {
            $kec    = (string) $r->kecamatan;
            $status = (int) $r->status;
            $tot    = (int) $r->total;

            if ($kec === '' || $status < 0 || $status > 7) continue;

            $key = strtolower($kec);
            if (!isset($kecamatanStatusSummaryCounts[$key])) {
                $kecamatanStatusSummaryCounts[$key] = [
                    'kecamatan'  => $kec,
                    'per_status' => array_fill(0, 8, 0),
                    'total'      => 0,
                ];
            }

            $kecamatanStatusSummaryCounts[$key]['per_status'][$status] += $tot;
            $kecamatanStatusSummaryCounts[$key]['total']               += $tot;
        }

        ksort($kecamatanStatusSummaryCounts);

        $kecamatanStatusSummary = [];
        foreach ($kecamatanStatusSummaryCounts as $row) {
            $kecamatanStatusSummary[] = [
                'kecamatan'  => $row['kecamatan'],
                'total'      => (int) $row['total'],
                'per_status' => $this->wrapStatusCounts($row['per_status']),
            ];
        }

        // ================== PER KECAMATAN+KELURAHAN PER FORM ==================
        $kelLabelExpr = "COALESCE(NULLIF(TRIM(kelurahan), ''), '-')";

        $kelRows = $this->scopedSummaryQuery($auth, $isPriv)
            ->select(
                'form',
                'kecamatan',
                DB::raw("$kelLabelExpr as kelurahan"),
                DB::raw('COUNT(*) as total')
            )
            ->whereIn('form', $forms)
            ->whereNotNull('kecamatan')
            ->whereRaw("TRIM(kecamatan) <> ''")
            ->groupBy('form', 'kecamatan')
            ->groupByRaw($kelLabelExpr)
            ->orderBy('kecamatan')
            ->orderByRaw($kelLabelExpr)
            ->get();

        $kelurahanPerTable = [];
        foreach ($tableToForm as $tableKey => $_form) {
            $kelurahanPerTable[$tableKey] = [];
        }

        foreach ($kelRows as $r) {
            $form = (string) $r->form;
            $kec  = (string) $r->kecamatan;
            $kel  = (string) $r->kelurahan; // '-' jika kosong
            $tot  = (int) $r->total;

            $tableKey = $formToTableKey[$form] ?? null;
            if (!$tableKey) continue;

            $kelurahanPerTable[$tableKey][] = [
                'kecamatan' => $kec,
                'kelurahan' => $kel,
                'total'     => $tot,
            ];
        }

        // ================== SUMMARY KECAMATAN+KELURAHAN + STATUS (LINTAS FORM) ==================
        $kelStatusRows = $this->scopedSummaryQuery($auth, $isPriv)
            ->select(
                'kecamatan',
                DB::raw("$kelLabelExpr as kelurahan"),
                DB::raw('COALESCE(status_verifikasi_usulan,0) as status'),
                DB::raw('COUNT(*) as total')
            )
            ->whereIn('form', $forms)
            ->whereNotNull('kecamatan')
            ->whereRaw("TRIM(kecamatan) <> ''")
            ->whereRaw('COALESCE(status_verifikasi_usulan,0) BETWEEN 0 AND 7')
            ->groupBy('kecamatan')
            ->groupByRaw($kelLabelExpr)
            ->groupByRaw('COALESCE(status_verifikasi_usulan,0)')
            ->get();

        $kelurahanStatusSummaryCounts = []; // key = "kec||kel"

        foreach ($kelStatusRows as $r) {
            $kec    = (string) $r->kecamatan;
            $kel    = (string) $r->kelurahan;
            $status = (int) $r->status;
            $tot    = (int) $r->total;

            if ($kec === '' || $status < 0 || $status > 7) continue;

            $key = strtolower($kec . '||' . $kel);
            if (!isset($kelurahanStatusSummaryCounts[$key])) {
                $kelurahanStatusSummaryCounts[$key] = [
                    'kecamatan'  => $kec,
                    'kelurahan'  => $kel,
                    'per_status' => array_fill(0, 8, 0),
                    'total'      => 0,
                ];
            }

            $kelurahanStatusSummaryCounts[$key]['per_status'][$status] += $tot;
            $kelurahanStatusSummaryCounts[$key]['total']               += $tot;
        }

        $kelurahanStatusSummary = array_values($kelurahanStatusSummaryCounts);
        usort($kelurahanStatusSummary, function ($a, $b) {
            $c = strcasecmp($a['kecamatan'], $b['kecamatan']);
            if ($c !== 0) return $c;
            return strcasecmp($a['kelurahan'], $b['kelurahan']);
        });

        $kelurahanStatusSummary = array_map(function ($row) {
            return [
                'kecamatan'  => $row['kecamatan'],
                'kelurahan'  => $row['kelurahan'],
                'total'      => (int) $row['total'],
                'per_status' => $this->wrapStatusCounts($row['per_status']),
            ];
        }, $kelurahanStatusSummary);

        // ================== RESPONSE ==================
        return response()->json([
            'success' => true,
            'data'    => [
                // total record per tabel (key lama tetap)
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

                'summary' => $summary,

                'status_verifikasi' => [
                    'per_table' => $statusPerTable,
                    'grouped'   => $groupedStatus,
                ],

                'kecamatan' => [
                    'per_table' => $kecamatanPerTable,
                    'summary'   => $kecamatanStatusSummary,
                ],

                // ✅ tambahan sampai kelurahan
                'kelurahan' => [
                    'per_table' => $kelurahanPerTable,     // list [kec, kel, total] per tableKey
                    'summary'   => $kelurahanStatusSummary, // lintas semua form
                ],
            ],
        ]);
    }

    /**
     * Query UsulanSummary yang sudah diberi scope akses kontrol.
     *
     * Rules:
     * 1-3) admin/operator/pengawas => semua
     * 4) user: punya kec, kel null => seluruh kecamatan (semua desa) + data milik sendiri
     * 5) user: punya kec & kel => hanya desa itu + data milik sendiri
     * 6) user: kec & kel kosong => hanya data milik sendiri
     */
    private function scopedSummaryQuery($auth, bool $isPriv): Builder
    {
        $q = UsulanSummary::query();

        if ($isPriv) return $q;

        $userId  = (string) $auth->id;
        $userKec = strtolower(trim((string) ($auth->kecamatan ?? '')));
        $userKel = strtolower(trim((string) ($auth->kelurahan ?? '')));

        // Defensive: kalau kec kosong (walaupun kel ada), treat sebagai hanya miliknya
        if ($userKec === '') {
            return $q->where('user_id', $userId);
        }

        // 4) punya kec, kel kosong => seluruh kecamatan + miliknya
        if ($userKel === '') {
            return $q->where(function ($w) use ($userId, $userKec) {
                $w->whereRaw('LOWER(kecamatan) = ?', [$userKec])
                  ->orWhere('user_id', $userId);
            });
        }

        // 5) punya kec & kel => hanya desa tsb + miliknya
        return $q->where(function ($w) use ($userId, $userKec, $userKel) {
            $w->where(function ($ww) use ($userKec, $userKel) {
                $ww->whereRaw('LOWER(kecamatan) = ?', [$userKec])
                   ->whereRaw("LOWER(COALESCE(kelurahan, '')) = ?", [$userKel]);
            })->orWhere('user_id', $userId);
        });
    }

    private function sumKeys(array $counts, array $keys): int
    {
        $sum = 0;
        foreach ($keys as $k) $sum += $counts[$k] ?? 0;
        return $sum;
    }

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
}
