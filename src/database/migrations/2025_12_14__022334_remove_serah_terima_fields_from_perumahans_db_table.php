<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi: hapus kolom fileSerahTerimaTPU, bastTPU, status_serah_terima
     * dari tabel perumahans_db.
     */
    public function up(): void
    {
        Schema::table('perumahans_db', function (Blueprint $table) {
            $table->dropColumn([
                'fileSerahTerimaTPU',
                'bastTPU',
                'status_serah_terima',
            ]);
        });
    }

    /**
     * Rollback migrasi: tambahkan lagi kolom yang dihapus.
     */
    public function down(): void
    {
        Schema::table('perumahans_db', function (Blueprint $table) {
            // JSON array UUID, boleh null
            $table->json('fileSerahTerimaTPU')->nullable();

            // String biasa, nullable
            $table->string('bastTPU')->nullable();

            // Status integer 0–4, default 0
            $table->unsignedTinyInteger('status_serah_terima')->default(0);
        });
    }
};
