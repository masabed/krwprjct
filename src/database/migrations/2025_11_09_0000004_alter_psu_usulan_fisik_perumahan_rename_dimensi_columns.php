<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) rename kolom dimensiUsulan -> dimensiUsulaUtama
        Schema::table('psu_usulan_fisik_perumahan', function (Blueprint $table) {
            $table->renameColumn('dimensiUsulan', 'dimensiUsulanUtama');
        });

        // 2) tambah kolom baru dimensiUsulanTambahan
        Schema::table('psu_usulan_fisik_perumahan', function (Blueprint $table) {
            $table->string('dimensiUsulanTambahan', 150)
                  ->nullable()
                  ->after('dimensiUsulanUtama');
        });
    }

    public function down(): void
    {
        // rollback: hapus kolom tambahan
        Schema::table('psu_usulan_fisik_perumahan', function (Blueprint $table) {
            $table->dropColumn('dimensiUsulanTambahan');
        });

        // rollback: kembalikan nama kolom
        Schema::table('psu_usulan_fisik_perumahan', function (Blueprint $table) {
            $table->renameColumn('dimensiUsulanUtama', 'dimensiUsulan');
        });
    }
};
