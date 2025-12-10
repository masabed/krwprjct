<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rutilahus', function (Blueprint $table) {
            // Kolom Sumber & Pengusul
            if (!Schema::hasColumn('rutilahus', 'sumberUsulan')) {
                $table->string('sumberUsulan', 255)->nullable()->after('uuid');
            }

            if (!Schema::hasColumn('rutilahus', 'namaAspirator')) {
                $table->string('namaAspirator', 255)->nullable()->after('sumberUsulan');
            }

            if (!Schema::hasColumn('rutilahus', 'noKontakAspirator')) {
                $table->string('noKontakAspirator', 50)->nullable()->after('namaAspirator');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rutilahus', function (Blueprint $table) {
            if (Schema::hasColumn('rutilahus', 'noKontakAspirator')) {
                $table->dropColumn('noKontakAspirator');
            }
            if (Schema::hasColumn('rutilahus', 'namaAspirator')) {
                $table->dropColumn('namaAspirator');
            }
            if (Schema::hasColumn('rutilahus', 'sumberUsulan')) {
                $table->dropColumn('sumberUsulan');
            }
        });
    }
};
