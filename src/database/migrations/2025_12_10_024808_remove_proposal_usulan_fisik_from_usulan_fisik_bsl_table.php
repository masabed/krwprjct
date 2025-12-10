<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        Schema::table('usulan_fisik_bsl', function (Blueprint $table) {
            // Hapus kolom proposalUsulanFisik
            if (Schema::hasColumn('usulan_fisik_bsl', 'proposalUsulanFisik')) {
                $table->dropColumn('proposalUsulanFisik');
            }
        });
    }

    /**
     * Rollback migrasi.
     */
    public function down(): void
    {
        Schema::table('usulan_fisik_bsl', function (Blueprint $table) {
            // Kembalikan kolomnya kalau di-rollback
            if (!Schema::hasColumn('usulan_fisik_bsl', 'proposalUsulanFisik')) {
                $table->json('proposalUsulanFisik')->nullable();
            }
        });
    }
};
