<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Observers\UsulanSummaryObserver;
use App\Observers\UsulanStatusChangedNotificationObserver;

use App\Models\UsulanSummary;

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

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 1) Observer SUMMARY: register ke SEMUA model yang masuk summary
        $summaryModels = [
            UsulanFisikBSL::class,
            PSUUsulanFisikPerumahan::class,
            PSUUsulanFisikTPU::class,
            PSUUsulanFisikPJL::class,

            SAPDLahanMasyarakat::class,
            UsulanSAPDSIndividual::class,
            UsulanSAPDSFasilitasUmum::class,

            Permukiman::class,
            Rutilahu::class,

            // ✅ serah terima
            PsuSerahTerima::class,
            TpuSerahTerima::class,
        ];

        foreach ($summaryModels as $modelClass) {
            $modelClass::observe(UsulanSummaryObserver::class);
        }

        // 2) Observer NOTIFIKASI: dipasang ke UsulanSummary
        //    Jadi notif terbuat saat status_verifikasi_usulan pada summary berubah.
        UsulanSummary::observe(UsulanStatusChangedNotificationObserver::class);
    }
}
