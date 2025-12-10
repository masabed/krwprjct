<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan_sapds_fasilitas_umum', function (Blueprint $table) {
            // 🔹 Tambah 3 kolom baru (kalau belum ada)
            if (!Schema::hasColumn('usulan_sapds_fasilitas_umum', 'sumberUsulan')) {
                $table->string('sumberUsulan', 255)->nullable()->after('uuid');
            }

            if (!Schema::hasColumn('usulan_sapds_fasilitas_umum', 'namaAspirator')) {
                $table->string('namaAspirator', 255)->nullable()->after('sumberUsulan');
            }

            if (!Schema::hasColumn('usulan_sapds_fasilitas_umum', 'noKontakAspirator')) {
                $table->string('noKontakAspirator', 50)->nullable()->after('namaAspirator');
            }

            // 🔻 Drop kolom proposal (camelCase & snake_case kalau pernah ada)
            if (Schema::hasColumn('usulan_sapds_fasilitas_umum', 'proposal')) {
                $table->dropColumn('proposal');
            }

            if (Schema::hasColumn('usulan_sapds_fasilitas_umum', 'proposal_file')) {
                $table->dropColumn('proposal_file');
            }

            if (Schema::hasColumn('usulan_sapds_fasilitas_umum', 'proposal_json')) {
                $table->dropColumn('proposal_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usulan_sapds_fasilitas_umum', function (Blueprint $table) {
            // Balikin kolom proposal sebagai JSON nullable kalau di-rollback
            if (!Schema::hasColumn('usulan_sapds_fasilitas_umum', 'proposal')) {
                $table->json('proposal')->nullable();
            }

            // Hapus lagi 3 kolom baru kalau rollback
            if (Schema::hasColumn('usulan_sapds_fasilitas_umum', 'sumberUsulan')) {
                $table->dropColumn('sumberUsulan');
            }
            if (Schema::hasColumn('usulan_sapds_fasilitas_umum', 'namaAspirator')) {
                $table->dropColumn('namaAspirator');
            }
            if (Schema::hasColumn('usulan_sapds_fasilitas_umum', 'noKontakAspirator')) {
                $table->dropColumn('noKontakAspirator');
            }
        });
    }
};
