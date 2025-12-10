<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan_sapds_individual', function (Blueprint $table) {
            // Sumber & pengusul
            if (!Schema::hasColumn('usulan_sapds_individual', 'sumberUsulan')) {
                $table->string('sumberUsulan', 255)
                    ->nullable()
                    ->after('user_id');
            }

            if (!Schema::hasColumn('usulan_sapds_individual', 'namaAspirator')) {
                $table->string('namaAspirator', 255)
                    ->nullable()
                    ->after('sumberUsulan');
            }

            if (!Schema::hasColumn('usulan_sapds_individual', 'noKontakAspirator')) {
                $table->string('noKontakAspirator', 50)
                    ->nullable()
                    ->after('namaAspirator');
            }

            // ❌ bagian PIC dihapus
            // if (!Schema::hasColumn('usulan_sapds_individual', 'namaPIC')) { ... }
            // if (!Schema::hasColumn('usulan_sapds_individual', 'noKontakPIC')) { ... }
        });
    }

    public function down(): void
    {
        Schema::table('usulan_sapds_individual', function (Blueprint $table) {
            if (Schema::hasColumn('usulan_sapds_individual', 'sumberUsulan')) {
                $table->dropColumn('sumberUsulan');
            }
            if (Schema::hasColumn('usulan_sapds_individual', 'namaAspirator')) {
                $table->dropColumn('namaAspirator');
            }
            if (Schema::hasColumn('usulan_sapds_individual', 'noKontakAspirator')) {
                $table->dropColumn('noKontakAspirator');
            }

            // ❌ bagian drop namaPIC / noKontakPIC juga dihapus
        });
    }
};
