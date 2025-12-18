<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\UsulanSummary;
use App\Observers\UsulanSummaryObserver;

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

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/**
 * summary:rebuild
 */
Artisan::command('summary:rebuild
    {--fresh : Truncate usulan_summaries dulu sebelum rebuild}
    {--with-trashed : Ikutkan data soft-deleted (kalau model pakai SoftDeletes)}
    {--chunk=500 : Ukuran batch upsert}', function () {

    DB::disableQueryLog();

    $table = (new UsulanSummary)->getTable();
    $chunkSize = max(50, (int) $this->option('chunk'));

    // kolom aktual (biar aman kalau ada payload kolom yang gak ada)
    $columns = Schema::getColumnListing($table);
    $colSet  = array_flip($columns);

    foreach (['form', 'uuid_usulan'] as $must) {
        if (!isset($colSet[$must])) {
            $this->error("Kolom wajib '{$must}' tidak ada di tabel {$table}.");
            return 1;
        }
    }

    $hasCreated = isset($colSet['created_at']);
    $hasUpdated = isset($colSet['updated_at']);

    if ($this->option('fresh')) {
        $this->warn("Truncating {$table} ...");
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table($table)->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    $models = [
        UsulanFisikBSL::class,
        PSUUsulanFisikPerumahan::class,
        PSUUsulanFisikTPU::class,
        PSUUsulanFisikPJL::class,

        SAPDLahanMasyarakat::class,
        UsulanSAPDSIndividual::class,
        UsulanSAPDSFasilitasUmum::class,

        Permukiman::class,
        Rutilahu::class,

        PsuSerahTerima::class,
        TpuSerahTerima::class,
    ];

    $uniqueBy = ['form', 'uuid_usulan'];

    $updateColumns = [
        'user_id',
        'user_kecamatan',
        'user_kelurahan',
        'status_verifikasi_usulan',
        'kecamatan',
        'kelurahan',
        'titik_lokasi',
    ];
    $updateColumns = array_values(array_filter($updateColumns, fn($c) => isset($colSet[$c])));
    if ($hasUpdated) $updateColumns[] = 'updated_at';

    $usesSoftDeletes = function (string $modelClass): bool {
        return in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
    };

    $total = 0;
    $buffer = [];

    $flush = function () use (&$buffer, $table, $uniqueBy, $updateColumns, &$total) {
        if (empty($buffer)) return;
        DB::table($table)->upsert($buffer, $uniqueBy, $updateColumns);
        $total += count($buffer);
        $buffer = [];
    };

    foreach ($models as $modelClass) {
        $this->info("Rebuilding from: {$modelClass}");

        $query = $modelClass::query();

        if ($this->option('with-trashed') && $usesSoftDeletes($modelClass)) {
            $query = $query->withTrashed();
        }

        foreach ($query->cursor() as $row) {
            $payload = UsulanSummaryObserver::buildPayload($row);
            if (!$payload) continue;

            $now = now();
            if ($hasCreated && empty($payload['created_at'])) $payload['created_at'] = $now;
            if ($hasUpdated) $payload['updated_at'] = $now;

            // filter payload ke kolom yang ada di tabel
            $payload = array_intersect_key($payload, $colSet);

            if (empty($payload['form']) || empty($payload['uuid_usulan'])) continue;

            $buffer[] = $payload;

            if (count($buffer) >= $chunkSize) {
                $flush();
            }
        }

        $flush();
    }

    $this->info("DONE. Total rows processed: {$total}");
    return 0;

})->purpose('Rebuild tabel usulan_summaries dari semua model sumber');


/**
 * usulan-notifications:prune-read
 * Hapus notifikasi yang sudah dibaca (read_at != null)
 */
Artisan::command('usulan-notifications:prune-read', function () {
    $table = 'usulan_notifications';

    if (!Schema::hasTable($table)) {
        $this->error("Table '{$table}' tidak ada.");
        return 1;
    }
    if (!Schema::hasColumn($table, 'read_at')) {
        $this->error("Kolom 'read_at' tidak ada di table '{$table}'.");
        return 1;
    }

    // Hapus yang sudah read
    $deleted = DB::table($table)
        ->whereNotNull('read_at')
        ->delete();

    $this->info("Deleted {$deleted} read notifications from {$table}.");
    return 0;
})->purpose('Delete read usulan notifications');


// =====================
// Scheduler (Laravel 11/12)
// =====================
// Jalan setiap hari jam 00:00
// 00:00 WIB = 17:00 UTC
Schedule::command('usulan-notifications:prune-read')->dailyAt('17:00');
