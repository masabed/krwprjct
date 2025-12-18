<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use App\Models\UsulanSummary;
use App\Models\UsulanNotification;
use App\Models\User;
use App\Models\PerumahanDb;

// usulan
use App\Models\UsulanFisikBSL;
use App\Models\PSUUsulanFisikPerumahan;
use App\Models\PSUUsulanFisikTPU;
use App\Models\PSUUsulanFisikPJL;

use App\Models\SAPDLahanMasyarakat;
use App\Models\UsulanSAPDSIndividual;
use App\Models\UsulanSAPDSFasilitasUmum;

use App\Models\Permukiman;
use App\Models\Rutilahu;

// serah terima
use App\Models\PsuSerahTerima;
use App\Models\TpuSerahTerima;

class UsulanSummaryObserver
{
    public bool $afterCommit = true;

    private const FORM_MAP = [
        UsulanFisikBSL::class            => 'psu_bsl',
        PSUUsulanFisikPerumahan::class   => 'psu_perumahan',
        PSUUsulanFisikTPU::class         => 'psu_tpu',
        PSUUsulanFisikPJL::class         => 'psu_pjl',

        SAPDLahanMasyarakat::class       => 'sapd_Sarana Air',
        UsulanSAPDSIndividual::class     => 'sapd_individual',
        UsulanSAPDSFasilitasUmum::class  => 'sapd_fasum',

        Permukiman::class                => 'permukiman',
        Rutilahu::class                  => 'rutilahu',

        PsuSerahTerima::class            => 'psu_serah_terima',
        TpuSerahTerima::class            => 'tpu_serah_terima',
    ];

    private static array $perumahanCache = []; // [perumahanId => ['kec'=>..,'kel'=>..]]
    private static array $userCache      = []; // [userId => ['kec'=>..,'kel'=>..]]

    public function saved(Model $model): void
    {
        $payload = self::buildPayload($model);
        if (!$payload) return;

        $table = (new UsulanSummary)->getTable();

        $where = [
            'form'       => $payload['form'],
            'uuid_usulan' => $payload['uuid_usulan'],
        ];

        // ===== ambil status lama dari summary SEBELUM upsert =====
        $prevStatusRaw = DB::table($table)->where($where)->value('status_verifikasi_usulan');
        $prevStatus    = is_null($prevStatusRaw) ? null : (int) $prevStatusRaw;

        $newStatus = (int) ($payload['status_verifikasi_usulan'] ?? 0);

        // values untuk UPDATE/INSERT (jangan include keys karena sudah di $where)
        $values = $payload;
        unset($values['form'], $values['uuid_usulan']);

        // timestamps opsional (hanya kalau kolomnya ada)
        $now = now();
        $hasUpdated = Schema::hasColumn($table, 'updated_at');
        $hasCreated = Schema::hasColumn($table, 'created_at');

        if ($hasUpdated) {
            $values['updated_at'] = $now;
        }
        if ($hasCreated) {
            // created_at hanya saat insert
            $exists = DB::table($table)->where($where)->exists();
            if (!$exists) {
                $values['created_at'] = $now;
            }
        }

        // ✅ aman: update hanya untuk (form + uuid_usulan)
        DB::table($table)->updateOrInsert($where, $values);

        // ===== buat notifikasi kalau status berubah =====
        // (skip jika row summary baru dibuat: prevStatus null)
        $ownerUserId = trim((string) ($payload['user_id'] ?? ''));

        if ($ownerUserId !== '' && $prevStatus !== null && $prevStatus !== $newStatus) {
            UsulanNotification::create([
                'owner_user_id' => $ownerUserId,
                'uuid_usulan'   => (string) $payload['uuid_usulan'],
                'from_status'   => $prevStatus,
                'to_status'     => $newStatus,
                'read_at'       => null,
            ]);
        }
    }

    public function deleted(Model $model): void
    {
        $payload = self::buildPayload($model);
        if (!$payload) return;

        $table = (new UsulanSummary)->getTable();

        DB::table($table)
            ->where('form', $payload['form'])
            ->where('uuid_usulan', $payload['uuid_usulan'])
            ->delete();
    }

    public static function buildPayload(Model $model): ?array
    {
        $class = get_class($model);
        if (!isset(self::FORM_MAP[$class])) return null;

        $form = self::FORM_MAP[$class];

        $uuidUsulan = trim((string) (
            $model->getAttribute('uuid')
            ?: $model->getAttribute('id')
            ?: $model->getKey()
        ));
        if ($uuidUsulan === '') return null;

        $getFirst = function (array $keys) use ($model): ?string {
            foreach ($keys as $k) {
                $v = $model->getAttribute($k);
                if ($v === null) continue;
                $t = trim((string) $v);
                if ($t !== '') return $t;
            }
            return null;
        };

        // user_id
        $userId = $getFirst(['user_id', 'userId', 'idUser', 'created_by', 'createdBy', 'creator_id']);
        if (!$userId) {
            try {
                if (function_exists('auth') && auth()->check()) {
                    $userId = (string) auth()->id();
                }
            } catch (\Throwable $e) {}
        }

        // status support banyak nama kolom
        $statusRaw = $getFirst([
            'status_verifikasi_usulan',
            'status_usulan_verifikasi',
            'statusVerifikasiUsulan',
            'statusUsulanVerifikasi',
            'status_verifikasi',
            'statusVerifikasi',
        ]);
        $status = is_null($statusRaw) ? 0 : (int) $statusRaw;

        $titik = $getFirst(['titikLokasi', 'titik_lokasi']);

        // kecamatan/kelurahan usulan
        $kec = null; $kel = null;

        if ($class === PsuSerahTerima::class) {
            $perumahanId = $getFirst(['perumahanId', 'perumahan_id']);
            if ($perumahanId) {
                [$kec, $kel] = self::resolvePerumahanRegion($perumahanId);
            }
            if (!$kec) $kec = $getFirst(['kecamatanUsulan', 'kecamatan']);
            if (!$kel) $kel = $getFirst(['kelurahanUsulan', 'kelurahan']);
        } elseif ($class === TpuSerahTerima::class) {
            $kec = $getFirst(['kecamatanTPU', 'kecamatan_tpu', 'kecamatan']);
            $kel = $getFirst(['kelurahanTPU', 'kelurahan_tpu', 'kelurahan']);
        } elseif ($class === PSUUsulanFisikPerumahan::class) {
            $perumahanId = $getFirst(['perumahanId', 'perumahan_id']);
            if ($perumahanId) {
                [$kec, $kel] = self::resolvePerumahanRegion($perumahanId);
            }
            if (!$kec) $kec = $getFirst(['kecamatanUsulan', 'kecamatan']);
            if (!$kel) $kel = $getFirst(['kelurahanUsulan', 'kelurahan']);
        } elseif (str_starts_with($form, 'psu_')) {
            $kec = $getFirst(['kecamatanUsulan', 'kecamatan']);
            $kel = $getFirst(['kelurahanUsulan', 'kelurahan']);
        } else {
            $kec = $getFirst(['kecamatan']);
            $kel = $getFirst(['kelurahan']);
        }

        // kecamatan/kelurahan user (owner)
        $userKec = null; $userKel = null;

        if ($userId) {
            if (!isset(self::$userCache[$userId])) {
                $u = null;

                try {
                    $u = User::query()
                        ->select('id', 'kecamatan', 'kelurahan')
                        ->where('id', $userId)
                        ->first();

                    if (!$u) {
                        $userTable = (new User)->getTable();
                        if (Schema::hasColumn($userTable, 'uuid')) {
                            $u = User::query()
                                ->select('uuid', 'kecamatan', 'kelurahan')
                                ->where('uuid', $userId)
                                ->first();
                        }
                    }
                } catch (\Throwable $e) {
                    $u = null;
                }

                self::$userCache[$userId] = [
                    'kec' => $u?->kecamatan ? trim((string) $u->kecamatan) : null,
                    'kel' => $u?->kelurahan ? trim((string) $u->kelurahan) : null,
                ];
            }

            $userKec = self::$userCache[$userId]['kec'] ?? null;
            $userKel = self::$userCache[$userId]['kel'] ?? null;
        }

        return [
            'form'                    => $form,
            'uuid_usulan'             => $uuidUsulan,

            'user_id'                 => $userId,
            'user_kecamatan'          => $userKec,
            'user_kelurahan'          => $userKel,

            'status_verifikasi_usulan'=> $status,
            'kecamatan'               => $kec,
            'kelurahan'               => $kel,
            'titik_lokasi'            => $titik,
        ];
    }

    private static function resolvePerumahanRegion(string $perumahanId): array
    {
        $perumahanId = trim($perumahanId);
        if ($perumahanId === '') return [null, null];

        if (!isset(self::$perumahanCache[$perumahanId])) {
            $p = null;
            try {
                $p = PerumahanDb::query()
                    ->whereKey($perumahanId)
                    ->first(['kecamatan', 'kelurahan']);
            } catch (\Throwable $e) {
                $p = null;
            }

            self::$perumahanCache[$perumahanId] = [
                'kec' => $p?->kecamatan ? trim((string) $p->kecamatan) : null,
                'kel' => $p?->kelurahan ? trim((string) $p->kelurahan) : null,
            ];
        }

        return [
            self::$perumahanCache[$perumahanId]['kec'] ?? null,
            self::$perumahanCache[$perumahanId]['kel'] ?? null,
        ];
    }
}
