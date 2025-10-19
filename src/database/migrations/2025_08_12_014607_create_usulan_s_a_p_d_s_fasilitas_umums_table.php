<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usulan_sapds_fasilitas_umum', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->string('namaFasilitasUmum');
            $table->text('alamatFasilitasUmum');
            $table->text('rwFasilitasUmum');
            $table->text('rtFasilitasUmum');
            $table->string('kecamatan');
            $table->string('kelurahan');
            $table->string('ukuranLahan')->nullable();
            $table->string('statusKepemilikan'); // contoh: "Milik Bersama"
            $table->string('titikLokasi')->nullable();
            $table->string('user_id')->index();

            // Pesan verifikasi (opsional)
            $table->string('pesan_verifikasi', 512)->nullable();

            $table->unsignedTinyInteger('status_verifikasi_usulan')->default(0);

            // FILES: array UUID (JSON)
            $table->json('buktiKepemilikan'); // [uuid, uuid, ...]
            $table->json('proposal');         // [uuid, uuid, ...]
            $table->json('fotoLahan');        // [uuid, uuid, ...]

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_sapds_fasilitas_umum');
    }
};
