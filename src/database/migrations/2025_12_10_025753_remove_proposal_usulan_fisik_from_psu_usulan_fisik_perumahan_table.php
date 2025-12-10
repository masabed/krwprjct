<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psu_usulan_fisik_perumahan', function (Blueprint $table) {
            if (Schema::hasColumn('psu_usulan_fisik_perumahan', 'proposalUsulanFisik')) {
                $table->dropColumn('proposalUsulanFisik');
            }
        });
    }

    public function down(): void
    {
        Schema::table('psu_usulan_fisik_perumahan', function (Blueprint $table) {
            if (!Schema::hasColumn('psu_usulan_fisik_perumahan', 'proposalUsulanFisik')) {
                $table->json('proposalUsulanFisik')->nullable();
            }
        });
    }
};
