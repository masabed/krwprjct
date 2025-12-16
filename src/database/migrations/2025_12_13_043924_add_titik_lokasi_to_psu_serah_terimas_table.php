<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psu_serah_terimas', function (Blueprint $table) {
            $table->string('titikLokasi')->nullable();
            // Kalau mau atur posisi kolom (MySQL), bisa:
            // $table->string('titikLokasi')->nullable()->after('luasRuangTerbuka');
        });
    }

    public function down(): void
    {
        Schema::table('psu_serah_terimas', function (Blueprint $table) {
            $table->dropColumn('titikLokasi');
        });
    }
};
