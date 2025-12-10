<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('psu_serah_terimas', function (Blueprint $table) {
            // Rename kolom agar match dengan FormData
            $table->renameColumn('namaPengusul', 'namaPemohon');
            $table->renameColumn('nikPengusul', 'nikPemohon');
            $table->renameColumn('ktpPengusul', 'ktpPemohon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('psu_serah_terimas', function (Blueprint $table) {
            // Balik lagi ke nama semula kalau rollback
            $table->renameColumn('namaPemohon', 'namaPengusul');
            $table->renameColumn('nikPemohon', 'nikPengusul');
            $table->renameColumn('ktpPemohon', 'ktpPengusul');
        });
    }
};
