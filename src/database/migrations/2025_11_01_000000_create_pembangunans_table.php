<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pembangunans', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            // PK
            $table->uuid('id')->primary();

            // Relasi ke usulan: kini berupa ARRAY JSON UUID
            $table->json('uuidUsulan'); // <- list of UUIDs

            // Data utama SPK
            $table->string('nomorSPK', 150);      // tidak unique
            $table->date('tanggalSPK')->nullable();
            $table->string('nilaiKontrak', 100)->nullable();
            $table->string('unit', 100)->nullable(); // tetap ada di schema

            // Data pelaksana
            $table->string('kontraktorPelaksana', 255)->nullable();

            // Waktu pelaksanaan
            $table->date('tanggalMulai')->nullable();
            $table->date('tanggalSelesai')->nullable();
            $table->string('jangkaWaktu', 100)->nullable();

            // Pengawas (id/uuid user sebagai string)
            $table->string('pengawasLapangan', 255)->nullable();

            // Audit (opsional)
            $table->string('user_id')->nullable();

            $table->timestamps();

            // **Sesuai permintaan**: tidak ada index tambahan
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembangunans');
    }
};
