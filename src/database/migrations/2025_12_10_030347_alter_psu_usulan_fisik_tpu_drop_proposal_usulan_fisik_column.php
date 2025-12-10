<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psu_usulan_fisik_tpu', function (Blueprint $table) {
            // Hapus kolom proposalUsulanFisik jika masih ada
            if (Schema::hasColumn('psu_usulan_fisik_tpu', 'proposalUsulanFisik')) {
                $table->dropColumn('proposalUsulanFisik');
            }
        });
    }

    public function down(): void
    {
        Schema::table('psu_usulan_fisik_tpu', function (Blueprint $table) {
            // Kembalikan kolom proposalUsulanFisik (JSON nullable) kalau belum ada
            if (!Schema::hasColumn('psu_usulan_fisik_tpu', 'proposalUsulanFisik')) {
                $table->json('proposalUsulanFisik')
                      ->nullable()
                      ->after('suratPermohonanUsulanFisik');
            }
        });
    }
};
