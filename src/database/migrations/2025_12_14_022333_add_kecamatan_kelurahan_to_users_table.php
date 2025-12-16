<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tambah kolom setelah noHP (kalau kolomnya ada)
            if (Schema::hasColumn('users', 'noHP')) {
                $table->string('kecamatan', 100)->nullable()->after('noHP');
                $table->string('kelurahan', 100)->nullable()->after('kecamatan');
            } else {
                // fallback kalau noHP belum ada / beda struktur
                $table->string('kecamatan', 100)->nullable();
                $table->string('kelurahan', 100)->nullable()->after('kecamatan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'kecamatan')) {
                $table->dropColumn('kecamatan');
            }
            if (Schema::hasColumn('users', 'kelurahan')) {
                $table->dropColumn('kelurahan');
            }
        });
    }
};
