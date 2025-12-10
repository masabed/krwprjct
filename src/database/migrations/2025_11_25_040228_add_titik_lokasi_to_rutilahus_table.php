<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rutilahus', function (Blueprint $table) {
            // sesuaikan posisi AFTER dengan kolom yang ada; contoh setelah 'kelurahan'
            $table->string('titikLokasi')->nullable()->after('kelurahan');
        });
    }

    public function down(): void
    {
        Schema::table('rutilahus', function (Blueprint $table) {
            $table->dropColumn('titikLokasi');
        });
    }
};
