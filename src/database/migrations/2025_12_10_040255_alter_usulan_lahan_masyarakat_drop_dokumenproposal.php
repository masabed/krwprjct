<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan_lahan_masyarakat', function (Blueprint $table) {
            // 🔹 Tambah kolom sumberUsulan, namaAspirator, noKontakAspirator
            if (!Schema::hasColumn('usulan_lahan_masyarakat', 'sumberUsulan')) {
                $table->string('sumberUsulan', 255)->nullable()->after('uuid');
            }

            if (!Schema::hasColumn('usulan_lahan_masyarakat', 'namaAspirator')) {
                $table->string('namaAspirator', 255)->nullable()->after('sumberUsulan');
            }

            if (!Schema::hasColumn('usulan_lahan_masyarakat', 'noKontakAspirator')) {
                $table->string('noKontakAspirator', 50)->nullable()->after('namaAspirator');
            }

            // 🔹 Drop dokumenProposal (camelCase & snake_case) kalau masih ada
            if (Schema::hasColumn('usulan_lahan_masyarakat', 'dokumenProposal')) {
                $table->dropColumn('dokumenProposal');
            }

            if (Schema::hasColumn('usulan_lahan_masyarakat', 'dokumen_proposal')) {
                $table->dropColumn('dokumen_proposal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usulan_lahan_masyarakat', function (Blueprint $table) {
            // Balikin dokumenProposal kalau di-rollback
            if (!Schema::hasColumn('usulan_lahan_masyarakat', 'dokumenProposal')) {
                $table->json('dokumenProposal')->nullable();
            }

            // Hapus kembali kolom sumberUsulan, namaAspirator, noKontakAspirator
            if (Schema::hasColumn('usulan_lahan_masyarakat', 'sumberUsulan')) {
                $table->dropColumn('sumberUsulan');
            }

            if (Schema::hasColumn('usulan_lahan_masyarakat', 'namaAspirator')) {
                $table->dropColumn('namaAspirator');
            }

            if (Schema::hasColumn('usulan_lahan_masyarakat', 'noKontakAspirator')) {
                $table->dropColumn('noKontakAspirator');
            }
        });
    }
};
